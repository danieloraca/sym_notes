<?php

declare(strict_types=1);

namespace App\Tests\Notes\Presentation\Mcp;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Domain\Entity\Note;
use App\Notes\Infrastructure\Doctrine\Repository\FolderRepository;
use App\Notes\Infrastructure\Doctrine\Repository\NoteRepository;
use App\Notes\Presentation\Mcp\NoteTools;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class NoteToolsTest extends TestCase
{
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
        );
    }
}
