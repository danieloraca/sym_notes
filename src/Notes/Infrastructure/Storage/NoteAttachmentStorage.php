<?php

declare(strict_types=1);

namespace App\Notes\Infrastructure\Storage;

use App\Notes\Domain\Entity\Note;
use App\Notes\Domain\Entity\NoteAttachment;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class NoteAttachmentStorage
{
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;
    public const MAX_FILES = 10;

    public function __construct(private readonly string $directory)
    {
    }

    public function store(Note $note, UploadedFile $file): NoteAttachment
    {
        $this->ensureDirectoryExists();

        $size = $file->getSize();

        if (false === $size || $size > self::MAX_FILE_SIZE) {
            throw new FileException(sprintf('Each attachment must be no larger than %d MB.', self::MAX_FILE_SIZE / 1024 / 1024));
        }

        if (!$file->isValid()) {
            throw new FileException($file->getErrorMessage());
        }

        [$originalName, $storedName, $mimeType] = $this->metadata($file->getClientOriginalName(), $file->getClientMimeType());

        $file->move($this->directory, $storedName);

        return new NoteAttachment($note, $originalName, $storedName, $mimeType, $size);
    }

    public function storeContent(Note $note, string $originalName, string $mimeType, string $content): NoteAttachment
    {
        $this->ensureDirectoryExists();

        $size = strlen($content);
        if ($size > self::MAX_FILE_SIZE) {
            throw new FileException(sprintf('Each attachment must be no larger than %d MB.', self::MAX_FILE_SIZE / 1024 / 1024));
        }

        [$originalName, $storedName, $mimeType] = $this->metadata($originalName, $mimeType);
        $path = $this->directory.DIRECTORY_SEPARATOR.$storedName;
        $written = file_put_contents($path, $content, LOCK_EX);

        if (false === $written || $written !== $size) {
            if (is_file($path)) {
                unlink($path);
            }

            throw new FileException('The attachment could not be stored.');
        }

        return new NoteAttachment($note, $originalName, $storedName, $mimeType, $size);
    }

    public function path(NoteAttachment $attachment): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$attachment->getStoredName();
    }

    public function remove(NoteAttachment $attachment): void
    {
        $path = $this->path($attachment);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new FileException('The attachment storage directory could not be created.');
        }
    }

    /** @return array{string, string, string} */
    private function metadata(string $originalName, string $mimeType): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';
        $storedName = bin2hex(random_bytes(20)).('' === $extension ? '' : '.'.substr($extension, 0, 16));
        $originalName = basename(str_replace('\\', '/', $originalName));
        $originalName = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '');
        $originalName = '' === $originalName ? 'attachment' : mb_substr($originalName, 0, 255);

        if (strlen($mimeType) > 255 || 1 !== preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#i', $mimeType)) {
            $mimeType = 'application/octet-stream';
        }

        return [$originalName, $storedName, $mimeType];
    }
}
