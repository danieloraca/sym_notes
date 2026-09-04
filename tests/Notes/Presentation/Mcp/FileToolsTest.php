<?php

declare(strict_types=1);

namespace App\Tests\Notes\Presentation\Mcp;

use App\Identity\Domain\Entity\User;
use App\Notes\Presentation\Mcp\FileTools;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class FileToolsTest extends TestCase
{
    private string $shareDirectory;

    protected function setUp(): void
    {
        $this->shareDirectory = sys_get_temp_dir().'/sym-notes-files-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->shareDirectory);
    }

    public function testItSavesAFileInTheAuthenticatedUsersPrivateDirectory(): void
    {
        $result = $this->tools()->saveFile('exports/summary.md', '# Summary');

        self::assertSame('exports/summary.md', $result['file']['path']);
        self::assertSame(9, $result['file']['bytes']);
        self::assertFalse($result['file']['overwritten']);

        $files = glob($this->shareDirectory.'/files/*/exports/summary.md');
        self::assertIsArray($files);
        self::assertCount(1, $files);
        self::assertSame('# Summary', file_get_contents($files[0]));
    }

    public function testItRequiresExplicitOverwrite(): void
    {
        $tools = $this->tools();
        $tools->saveFile('draft.txt', 'first');

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Set overwrite to true');
        $tools->saveFile('draft.txt', 'second');
    }

    public function testItCanOverwriteAnExistingFile(): void
    {
        $tools = $this->tools();
        $tools->saveFile('draft.txt', 'first');

        $result = $tools->saveFile('draft.txt', 'second', true);

        self::assertTrue($result['file']['overwritten']);
        $files = glob($this->shareDirectory.'/files/*/draft.txt');
        self::assertIsArray($files);
        self::assertSame('second', file_get_contents($files[0]));
    }

    public function testItRejectsPathTraversal(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('invalid path segment');
        $this->tools()->saveFile('../outside.txt', 'nope');
    }

    private function tools(): FileTools
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn((new User())->setEmail('daniel@example.com'));

        return new FileTools($security, $this->shareDirectory);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if (false === $entries) {
            return;
        }
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $directory.'/'.$entry;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
