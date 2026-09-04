<?php

declare(strict_types=1);

namespace App\Tests\Notes\Presentation\Form;

use App\Notes\Domain\Entity\Folder;
use App\Notes\Presentation\Form\FolderChoiceTree;
use PHPUnit\Framework\TestCase;

final class FolderChoiceTreeTest extends TestCase
{
    public function testItPlacesDescendantsDirectlyBelowTheirParent(): void
    {
        $linux = (new Folder())->setName('Linux');
        $cli = (new Folder())->setName('cli')->setParent($linux);
        $scripts = (new Folder())->setName('scripts')->setParent($cli);
        $mcp = (new Folder())->setName('MCP');

        $arranged = FolderChoiceTree::arrange([$cli, $linux, $mcp, $scripts]);

        self::assertSame([$linux, $cli, $scripts, $mcp], $arranged);
    }

    public function testItIndentsNestedFolderLabels(): void
    {
        $linux = (new Folder())->setName('Linux');
        $cli = (new Folder())->setName('cli')->setParent($linux);
        $scripts = (new Folder())->setName('scripts')->setParent($cli);

        self::assertSame('Linux', FolderChoiceTree::label($linux));
        self::assertSame("\u{00A0}\u{00A0}\u{00A0}↳ cli", FolderChoiceTree::label($cli));
        self::assertSame("\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}↳ scripts", FolderChoiceTree::label($scripts));
    }
}
