<?php

declare(strict_types=1);

namespace App\Notes\Presentation\Mcp;

use App\Identity\Domain\Entity\User;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FileTools
{
    private const int MAX_PATH_LENGTH = 500;
    private const int MAX_CONTENT_LENGTH = 10_000_000;

    public function __construct(
        private readonly Security $security,
        #[Autowire('%kernel.share_dir%')]
        private readonly string $shareDirectory,
    ) {
    }

    /**
     * Save a text file in the authenticated user's private shared files area.
     *
     * @return array{file: array{path: string, bytes: int, overwritten: bool}}
     */
    #[McpTool(
        name: 'files_save',
        title: 'Save file',
        description: 'Save a UTF-8 text file in the authenticated user\'s private Sym Notes files area. The path must be relative to that area; parent directories are created automatically. Existing files are not replaced unless overwrite is true.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false),
    )]
    public function saveFile(
        #[Schema(description: 'Relative file path, such as exports/summary.md. Absolute paths and .. segments are not allowed.', minLength: 1, maxLength: self::MAX_PATH_LENGTH)]
        string $path,
        #[Schema(description: 'UTF-8 text content to write.', maxLength: self::MAX_CONTENT_LENGTH)]
        string $content,
        #[Schema(description: 'Whether to replace an existing file at this path.')]
        bool $overwrite = false,
    ): array {
        $owner = $this->currentUser();
        $path = $this->validRelativePath($path);
        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new ToolCallException('File content is too long.');
        }

        $ownerDirectory = $this->ownerDirectory($owner);
        $target = $ownerDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $parentDirectory = dirname($target);
        $this->createDirectory($parentDirectory);
        $this->assertInsideDirectory($ownerDirectory, $parentDirectory);

        if (is_dir($target)) {
            throw new ToolCallException('The target path is a directory.');
        }
        if (is_link($target)) {
            throw new ToolCallException('Refusing to write through a symbolic link.');
        }

        $alreadyExists = file_exists($target);
        if ($alreadyExists && !$overwrite) {
            throw new ToolCallException('A file already exists at that path. Set overwrite to true to replace it.');
        }

        $bytes = file_put_contents($target, $content, LOCK_EX);
        if (false === $bytes) {
            throw new ToolCallException('Unable to save the file.');
        }

        return [
            'file' => [
                'path' => $path,
                'bytes' => $bytes,
                'overwritten' => $alreadyExists,
            ],
        ];
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new ToolCallException('MCP authentication is required.');
        }

        return $user;
    }

    private function ownerDirectory(User $owner): string
    {
        $shareDirectory = rtrim($this->shareDirectory, DIRECTORY_SEPARATOR);
        $this->createDirectory($shareDirectory);

        $filesDirectory = $shareDirectory.DIRECTORY_SEPARATOR.'files';
        $this->createDirectory($filesDirectory);
        $this->assertInsideDirectory($shareDirectory, $filesDirectory);

        $ownerKey = null !== $owner->getId()
            ? 'id-'.$owner->getId()
            : 'identifier-'.hash('sha256', $owner->getUserIdentifier());
        $directory = $filesDirectory.DIRECTORY_SEPARATOR.$ownerKey;
        if (is_link($directory)) {
            throw new ToolCallException('Refusing to use a symbolic link as the user file area.');
        }
        $this->createDirectory($directory);
        $this->assertInsideDirectory($filesDirectory, $directory);

        return $directory;
    }

    private function validRelativePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ('' === $path || mb_strlen($path) > self::MAX_PATH_LENGTH || str_starts_with($path, '/') || str_contains($path, "\0")) {
            throw new ToolCallException('File path must be a non-empty relative path of 500 characters or fewer.');
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                throw new ToolCallException('File path contains an invalid path segment.');
            }
        }

        return implode('/', $segments);
    }

    private function createDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new ToolCallException('Unable to create the file storage directory.');
        }
    }

    private function assertInsideDirectory(string $baseDirectory, string $path): void
    {
        $baseRealPath = realpath($baseDirectory);
        $pathRealPath = realpath($path);
        if (false === $baseRealPath || false === $pathRealPath
            || ($pathRealPath !== $baseRealPath && !str_starts_with($pathRealPath, $baseRealPath.DIRECTORY_SEPARATOR))) {
            throw new ToolCallException('File path resolves outside the user file area.');
        }
    }
}
