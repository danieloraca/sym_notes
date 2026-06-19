<?php

declare(strict_types=1);

namespace App\Tests\Notes\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Domain\Entity\Note;
use PHPUnit\Framework\TestCase;

final class NoteTest extends TestCase
{
    public function testItStoresNoteDetails(): void
    {
        $owner = new User();
        $folder = new Folder();
        $note = (new Note())
            ->setTitle('Docker notes')
            ->setContent("Run `docker compose up`\n\n```bash\nmake migrate\n```")
            ->setOwner($owner)
            ->setFolder($folder)
            ->setIsPinned(true);

        self::assertSame('Docker notes', $note->getTitle());
        self::assertStringContainsString('```bash', $note->getContent());
        self::assertSame($owner, $note->getOwner());
        self::assertSame($folder, $note->getFolder());
        self::assertTrue($note->isPinned());
    }

    public function testItArchivesAndRestores(): void
    {
        $note = new Note();

        self::assertNull($note->getArchivedAt());

        $note->archive();

        self::assertInstanceOf(\DateTimeImmutable::class, $note->getArchivedAt());

        $note->restore();

        self::assertNull($note->getArchivedAt());
    }

    public function testItTouchesUpdatedAt(): void
    {
        $note = new Note();
        $before = $note->getUpdatedAt();

        usleep(1000);
        $note->touchUpdatedAt();

        self::assertGreaterThan($before, $note->getUpdatedAt());
    }
}
