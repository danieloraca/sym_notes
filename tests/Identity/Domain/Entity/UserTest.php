<?php

declare(strict_types=1);

namespace App\Tests\Identity\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Note;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testItNormalizesEmailAndUsesItAsIdentifier(): void
    {
        $user = (new User())->setEmail('  Daniel@Example.COM  ');

        self::assertSame('daniel@example.com', $user->getEmail());
        self::assertSame('daniel@example.com', $user->getUserIdentifier());
    }

    public function testItAlwaysHasUserRole(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testItOwnsAddedNotesAndReleasesRemovedNotes(): void
    {
        $user = new User();
        $note = new Note();

        $user->addNote($note);

        self::assertTrue($user->getNotes()->contains($note));
        self::assertSame($user, $note->getOwner());

        $user->removeNote($note);

        self::assertFalse($user->getNotes()->contains($note));
        self::assertNull($note->getOwner());
    }
}
