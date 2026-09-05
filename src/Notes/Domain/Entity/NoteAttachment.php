<?php

declare(strict_types=1);

namespace App\Notes\Domain\Entity;

use App\Notes\Infrastructure\Doctrine\Repository\NoteAttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoteAttachmentRepository::class)]
#[ORM\Table(name: 'note_attachments')]
class NoteAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Note $note;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 255, unique: true)]
    private string $storedName;

    #[ORM\Column(length: 255)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Note $note, string $originalName, string $storedName, string $mimeType, int $size)
    {
        $this->note = $note;
        $this->originalName = $originalName;
        $this->storedName = $storedName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): Note
    {
        return $this->note;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
