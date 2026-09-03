<?php

declare(strict_types=1);

namespace App\Tests\Notes\Presentation\Mcp;

use App\Identity\Domain\Entity\User;
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

    private function tools(User $owner, NoteRepository $notes, ?EntityManagerInterface $entityManager = null): NoteTools
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($owner);

        return new NoteTools(
            $security,
            $notes,
            $this->createStub(FolderRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
        );
    }
}
