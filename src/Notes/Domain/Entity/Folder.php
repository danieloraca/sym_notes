<?php

declare(strict_types=1);

namespace App\Notes\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Notes\Infrastructure\Doctrine\Repository\FolderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FolderRepository::class)]
#[ORM\Table(name: 'folders')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_FOLDERS_OWNER_PARENT_NAME', columns: ['owner_id', 'parent_id', 'name'])]
class Folder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(options: ['default' => 0])]
    private int $sortPosition = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    /**
     * @var Collection<int, Note>
     */
    #[ORM\OneToMany(targetEntity: Note::class, mappedBy: 'folder')]
    private Collection $notes;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->children = new ArrayCollection();
        $this->notes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getSortPosition(): int
    {
        return $this->sortPosition;
    }

    public function setSortPosition(int $sortPosition): self
    {
        $this->sortPosition = $sortPosition;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getPath(): string
    {
        $segments = [];
        $folder = $this;
        $visited = [];

        while (null !== $folder) {
            $objectId = spl_object_id($folder);
            if (isset($visited[$objectId])) {
                break;
            }

            $visited[$objectId] = true;
            $segments[] = $folder->getName();
            $folder = $folder->getParent();
        }

        return implode(' / ', array_reverse($segments));
    }

    public function isDescendantOf(self $ancestor): bool
    {
        $folder = $this->parent;
        $visited = [];

        while (null !== $folder) {
            if ($folder === $ancestor) {
                return true;
            }

            $objectId = spl_object_id($folder);
            if (isset($visited[$objectId])) {
                break;
            }

            $visited[$objectId] = true;
            $folder = $folder->getParent();
        }

        return false;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Collection<int, Note>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }
}
