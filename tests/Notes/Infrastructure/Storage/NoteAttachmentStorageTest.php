<?php

declare(strict_types=1);

namespace App\Tests\Notes\Infrastructure\Storage;

use App\Notes\Domain\Entity\Note;
use App\Notes\Infrastructure\Storage\NoteAttachmentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class NoteAttachmentStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/sym-notes-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testItStoresAndRemovesAnUploadedFile(): void
    {
        $source = $this->directory.'/source.txt';
        file_put_contents($source, 'Attachment contents');
        $upload = new UploadedFile($source, 'project notes.txt', 'text/plain', null, true);
        $storage = new NoteAttachmentStorage($this->directory);

        $attachment = $storage->store(new Note(), $upload);

        self::assertSame('project notes.txt', $attachment->getOriginalName());
        self::assertSame('text/plain', $attachment->getMimeType());
        self::assertSame(19, $attachment->getSize());
        self::assertFileExists($storage->path($attachment));
        self::assertStringEndsWith('.txt', $attachment->getStoredName());

        $storage->remove($attachment);

        self::assertFileDoesNotExist($storage->path($attachment));
    }

    public function testItStoresRawContentForNonHttpClients(): void
    {
        $storage = new NoteAttachmentStorage($this->directory);

        $attachment = $storage->storeContent(new Note(), 'report.pdf', 'application/pdf', '%PDF contents');

        self::assertSame('report.pdf', $attachment->getOriginalName());
        self::assertSame('application/pdf', $attachment->getMimeType());
        self::assertSame(13, $attachment->getSize());
        self::assertSame('%PDF contents', file_get_contents($storage->path($attachment)));
        self::assertStringEndsWith('.pdf', $attachment->getStoredName());
    }

    public function testItRejectsFilesLargerThanTenMegabytes(): void
    {
        $source = $this->directory.'/large.bin';
        $handle = fopen($source, 'wb');
        self::assertIsResource($handle);
        ftruncate($handle, NoteAttachmentStorage::MAX_FILE_SIZE + 1);
        fclose($handle);
        $upload = new UploadedFile($source, 'large.bin', 'application/octet-stream', null, true);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('no larger than 10 MB');

        (new NoteAttachmentStorage($this->directory))->store(new Note(), $upload);
    }

    public function testItRejectsRawContentLargerThanTenMegabytes(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('no larger than 10 MB');

        (new NoteAttachmentStorage($this->directory))->storeContent(
            new Note(),
            'large.bin',
            'application/octet-stream',
            str_repeat('a', NoteAttachmentStorage::MAX_FILE_SIZE + 1),
        );
    }

    public function testItFallsBackForAnInvalidMimeType(): void
    {
        $source = $this->directory.'/source.bin';
        file_put_contents($source, 'contents');
        $upload = new UploadedFile($source, 'source.bin', "text/plain\r\nX-Unsafe: yes", null, true);

        $attachment = (new NoteAttachmentStorage($this->directory))->store(new Note(), $upload);

        self::assertSame('application/octet-stream', $attachment->getMimeType());
    }

    public function testItSanitizesTheOriginalFilename(): void
    {
        $source = $this->directory.'/source.txt';
        file_put_contents($source, 'contents');
        $upload = new UploadedFile($source, "unsafe\r\nname.txt", 'text/plain', null, true);

        $attachment = (new NoteAttachmentStorage($this->directory))->store(new Note(), $upload);

        self::assertSame('unsafename.txt', $attachment->getOriginalName());
    }
}
