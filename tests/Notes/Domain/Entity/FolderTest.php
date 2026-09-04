<?php

declare(strict_types=1);

namespace App\Tests\Notes\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use PHPUnit\Framework\TestCase;

final class FolderTest extends TestCase
{
    public function testItStoresFolderDetails(): void
    {
        $owner = new User();
        $parent = new Folder();
        $folder = (new Folder())
            ->setName('  References  ')
            ->setSortPosition(20)
            ->setOwner($owner)
            ->setParent($parent);

        self::assertSame('References', $folder->getName());
        self::assertSame(20, $folder->getSortPosition());
        self::assertSame($owner, $folder->getOwner());
        self::assertSame($parent, $folder->getParent());
    }

    public function testItStartsWithEmptyCollections(): void
    {
        $folder = new Folder();

        self::assertCount(0, $folder->getChildren());
        self::assertCount(0, $folder->getNotes());
    }

    public function testItTouchesUpdatedAt(): void
    {
        $folder = new Folder();
        $before = $folder->getUpdatedAt();

        usleep(1000);
        $folder->touchUpdatedAt();

        self::assertGreaterThan($before, $folder->getUpdatedAt());
    }

    public function testItBuildsItsFullPath(): void
    {
        $root = (new Folder())->setName('Projects');
        $child = (new Folder())->setName('Gecko')->setParent($root);
        $folder = (new Folder())->setName('API')->setParent($child);

        self::assertSame('Projects / Gecko / API', $folder->getPath());
    }

    public function testItRecognizesAncestors(): void
    {
        $root = (new Folder())->setName('Projects');
        $child = (new Folder())->setName('Gecko')->setParent($root);
        $folder = (new Folder())->setName('API')->setParent($child);
        $unrelated = (new Folder())->setName('Personal');

        self::assertTrue($folder->isDescendantOf($root));
        self::assertTrue($folder->isDescendantOf($child));
        self::assertFalse($folder->isDescendantOf($unrelated));
        self::assertFalse($root->isDescendantOf($folder));
    }
}
