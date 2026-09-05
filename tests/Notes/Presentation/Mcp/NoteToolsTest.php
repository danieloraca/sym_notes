<?php

declare(strict_types=1);

namespace App\Tests\Notes\Presentation\Mcp;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Domain\Entity\Note;
use App\Notes\Domain\Entity\NoteAttachment;
use App\Notes\Infrastructure\Doctrine\Repository\FolderRepository;
use App\Notes\Infrastructure\Doctrine\Repository\NoteRepository;
use App\Notes\Infrastructure\Storage\NoteAttachmentStorage;
use App\Notes\Presentation\Mcp\NoteTools;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class NoteToolsTest extends TestCase
{
    private string $attachmentDirectory;

    protected function setUp(): void
    {
        $this->attachmentDirectory = sys_get_temp_dir().'/sym-notes-mcp-attachments-'.bin2hex(random_bytes(8));
        mkdir($this->attachmentDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->attachmentDirectory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->attachmentDirectory);
    }

    public function testItListsOnlyNotesReturnedForTheAuthenticatedOwner(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $note = (new Note())->setOwner($owner)->setTitle('Pi setup')->setContent('Run Docker Compose');
        $notes = $this->createMock(NoteRepository::class);
        $notes->expects(self::once())->method('findActiveForOwner')->with($owner)->willReturn([$note]);

        $result = $this->tools($owner, $notes)->listNotes();

        self::assertSame(1, $result['count']);
        self::assertSame('Pi setup', $result['notes'][0]['title']);
        self::assertSame('Run Docker Compose', $result['notes'][0]['excerpt']);
    }

    public function testItCreatesANoteForTheAuthenticatedOwner(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $notes = $this->createStub(NoteRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (Note $note): bool => $note->getOwner() === $owner && 'MCP demo' === $note->getTitle(),
        ));
        $entityManager->expects(self::once())->method('flush');

        $result = $this->tools($owner, $notes, $entityManager)->createNote(' MCP demo ', 'Hello from the Pi');

        self::assertSame('MCP demo', $result['note']['title']);
        self::assertSame('Hello from the Pi', $result['note']['content']);
    }

    public function testItDoesNotExposeANoteOutsideTheOwnerScopedRepository(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $notes = $this->createMock(NoteRepository::class);
        $notes->expects(self::once())->method('findOneForOwner')->with(42, $owner)->willReturn(null);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Note not found.');
        $this->tools($owner, $notes)->getNote(42);
    }

    public function testItAttachesABase64EncodedFileToAnOwnedNote(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $note = (new Note())->setOwner($owner)->setTitle('MCP uploads');
        $notes = $this->createMock(NoteRepository::class);
        $notes->expects(self::once())->method('findOneForOwner')->with(42, $owner)->willReturn($note);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (NoteAttachment $attachment): bool => $attachment->getNote() === $note
                && 'diagram.png' === $attachment->getOriginalName()
                && 'image/png' === $attachment->getMimeType(),
        ));
        $entityManager->expects(self::once())->method('flush');

        $result = $this->tools($owner, $notes, $entityManager)->attachFile(
            42,
            'diagram.png',
            'image/png',
            base64_encode('PNG contents'),
        );

        self::assertSame('diagram.png', $result['attachment']['filename']);
        self::assertSame(12, $result['attachment']['size']);
        self::assertCount(1, $result['note']['attachments']);
        $attachment = $note->getAttachments()->first();
        self::assertInstanceOf(NoteAttachment::class, $attachment);
        self::assertSame('PNG contents', file_get_contents($this->attachmentDirectory.'/'.$attachment->getStoredName()));
    }

    public function testItRejectsInvalidBase64AttachmentContent(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $note = (new Note())->setOwner($owner);
        $notes = $this->createMock(NoteRepository::class);
        $notes->expects(self::once())->method('findOneForOwner')->with(42, $owner)->willReturn($note);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Attachment content must be valid base64.');

        $this->tools($owner, $notes)->attachFile(42, 'invalid.bin', 'application/octet-stream', 'not base64!');
    }

    public function testItAuthorizesTheNoteBeforeDecodingAttachmentContent(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $notes = $this->createMock(NoteRepository::class);
        $notes->expects(self::once())->method('findOneForOwner')->with(42, $owner)->willReturn(null);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Note not found.');

        $this->tools($owner, $notes)->attachFile(42, 'invalid.bin', 'application/octet-stream', 'not base64!');
    }

    public function testItRejectsOversizedEncodedContentBeforeDecoding(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $note = (new Note())->setOwner($owner);
        $notes = $this->createMock(NoteRepository::class);
        $notes->expects(self::once())->method('findOneForOwner')->with(42, $owner)->willReturn($note);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Attachment content exceeds the 10 MB limit.');

        $this->tools($owner, $notes)->attachFile(
            42,
            'large.bin',
            'application/octet-stream',
            str_repeat('A', 13_981_017),
        );
    }

    public function testItRemovesTheStoredFileAndAssociationWhenPersistenceFails(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $note = (new Note())->setOwner($owner);
        $notes = $this->createMock(NoteRepository::class);
        $notes->expects(self::once())->method('findOneForOwner')->with(42, $owner)->willReturn($note);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush')->willThrowException(new \RuntimeException('Database unavailable.'));

        try {
            $this->tools($owner, $notes, $entityManager)->attachFile(
                42,
                'diagram.png',
                'image/png',
                base64_encode('PNG contents'),
            );
            self::fail('Expected persistence to fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Database unavailable.', $exception->getMessage());
        }

        self::assertCount(0, $note->getAttachments());
        self::assertSame([], glob($this->attachmentDirectory.'/*') ?: []);
    }

    public function testItCreatesAFolderForTheAuthenticatedOwner(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $notes = $this->createStub(NoteRepository::class);
        $folders = $this->createMock(FolderRepository::class);
        $folders->expects(self::once())
            ->method('nameExistsForOwner')
            ->with($owner, 'MCP demos', null)
            ->willReturn(false);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (Folder $folder): bool => $folder->getOwner() === $owner
                && 'MCP demos' === $folder->getName()
                && 10 === $folder->getSortPosition()
                && null === $folder->getParent(),
        ));
        $entityManager->expects(self::once())->method('flush');

        $result = $this->tools($owner, $notes, $entityManager, $folders)->createFolder(' MCP demos ', sortPosition: 10);

        self::assertSame('MCP demos', $result['folder']['name']);
        self::assertNull($result['folder']['parentId']);
    }

    public function testItRejectsADuplicateFolderNameWithinTheSameParent(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $notes = $this->createStub(NoteRepository::class);
        $folders = $this->createMock(FolderRepository::class);
        $folders->expects(self::once())
            ->method('nameExistsForOwner')
            ->with($owner, 'MCP', null)
            ->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('A folder with that name already exists in the selected parent.');

        $this->tools($owner, $notes, $entityManager, $folders)->createFolder('MCP');
    }

    public function testItCreatesANestedFolderOnlyUnderAnOwnedParent(): void
    {
        $owner = (new User())->setEmail('daniel@example.com');
        $parent = (new Folder())->setOwner($owner)->setName('Projects');
        $notes = $this->createStub(NoteRepository::class);
        $folders = $this->createMock(FolderRepository::class);
        $folders->expects(self::once())
            ->method('findOneForOwner')
            ->with(7, $owner)
            ->willReturn($parent);
        $folders->expects(self::once())
            ->method('nameExistsForOwner')
            ->with($owner, 'Archive', $parent)
            ->willReturn(false);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::callback(
            static fn (Folder $folder): bool => $folder->getOwner() === $owner
                && 'Archive' === $folder->getName()
                && $parent === $folder->getParent(),
        ));
        $entityManager->expects(self::once())->method('flush');

        $this->tools($owner, $notes, $entityManager, $folders)->createFolder('Archive', parentId: 7);
    }

    private function tools(
        User $owner,
        NoteRepository $notes,
        ?EntityManagerInterface $entityManager = null,
        ?FolderRepository $folders = null,
    ): NoteTools
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($owner);

        return new NoteTools(
            $security,
            $notes,
            $folders ?? $this->createStub(FolderRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            new NoteAttachmentStorage($this->attachmentDirectory),
        );
    }
}
