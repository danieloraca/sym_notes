<?php

declare(strict_types=1);

namespace App\Notes\Presentation\Mcp;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Domain\Entity\Note;
use App\Notes\Infrastructure\Doctrine\Repository\FolderRepository;
use App\Notes\Infrastructure\Doctrine\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Symfony\Bundle\SecurityBundle\Security;

final class NoteTools
{
    private const int MAX_CONTENT_LENGTH = 200_000;

    public function __construct(
        private readonly Security $security,
        private readonly NoteRepository $notes,
        private readonly FolderRepository $folders,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * List active notes, optionally restricted to one folder.
     *
     * @return array{notes: list<array<string, mixed>>, count: int}
     */
    #[McpTool(
        name: 'notes_list',
        title: 'List notes',
        description: 'List active notes belonging to the authenticated user. Returns summaries rather than full note content.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function listNotes(
        #[Schema(description: 'Optional folder ID. Omit to list notes from every folder.', minimum: 1)]
        ?int $folderId = null,
        #[Schema(description: 'Maximum number of notes to return.', minimum: 1, maximum: 100)]
        int $limit = 50,
    ): array {
        $owner = $this->currentUser();
        $notes = null === $folderId
            ? $this->notes->findActiveForOwner($owner)
            : $this->notes->findActiveForOwnerInFolder($owner, $this->ownedFolder($folderId, $owner));

        $items = array_map($this->noteSummary(...), array_slice($notes, 0, $this->boundedLimit($limit)));

        return ['notes' => $items, 'count' => count($items)];
    }

    /**
     * Search titles and content of active notes.
     *
     * @return array{notes: list<array<string, mixed>>, count: int}
     */
    #[McpTool(
        name: 'notes_search',
        title: 'Search notes',
        description: 'Search the authenticated user\'s active notes by case-insensitive text contained in the title or content.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function searchNotes(
        #[Schema(description: 'Text to find in note titles or content.', minLength: 1, maxLength: 500)]
        string $query,
        #[Schema(description: 'Maximum number of matches to return.', minimum: 1, maximum: 100)]
        int $limit = 20,
    ): array {
        $query = mb_strtolower(trim($query));
        if ('' === $query) {
            throw new ToolCallException('Search query cannot be empty.');
        }

        $matches = array_filter(
            $this->notes->findActiveForOwner($this->currentUser()),
            static fn (Note $note): bool => str_contains(mb_strtolower($note->getTitle()."\n".$note->getContent()), $query),
        );
        $items = array_map($this->noteSummary(...), array_slice(array_values($matches), 0, $this->boundedLimit($limit)));

        return ['notes' => $items, 'count' => count($items)];
    }

    /**
     * Read one note including its full content.
     *
     * @return array{note: array<string, mixed>}
     */
    #[McpTool(
        name: 'notes_get',
        title: 'Read note',
        description: 'Read one note belonging to the authenticated user, including its full Markdown content.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function getNote(#[Schema(description: 'Note ID.', minimum: 1)] int $id): array
    {
        return ['note' => $this->noteData($this->ownedNote($id, $this->currentUser()))];
    }

    /**
     * Create a note.
     *
     * @return array{note: array<string, mixed>}
     */
    #[McpTool(
        name: 'notes_create',
        title: 'Create note',
        description: 'Save content as a new note in the user\'s Sym Notes (sym_notes) application. Use this for requests such as "save this conversation to sym_notes" or "add a note to Sym Notes". When the user names a destination folder, first call folders_list and pass the matching folder ID as folderId.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false),
    )]
    public function createNote(
        #[Schema(description: 'Note title.', minLength: 1, maxLength: 255)]
        string $title,
        #[Schema(description: 'Note content in Markdown.', maxLength: self::MAX_CONTENT_LENGTH)]
        string $content = '',
        #[Schema(description: 'Optional folder ID owned by the authenticated user.', minimum: 1)]
        ?int $folderId = null,
        #[Schema(description: 'Whether to pin the note.')]
        bool $pinned = false,
    ): array {
        $owner = $this->currentUser();
        $note = (new Note())
            ->setOwner($owner)
            ->setTitle($this->validTitle($title))
            ->setContent($this->validContent($content))
            ->setIsPinned($pinned);

        if (null !== $folderId) {
            $note->setFolder($this->ownedFolder($folderId, $owner));
        }

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return ['note' => $this->noteData($note)];
    }

    /**
     * Update note fields.
     *
     * @return array{note: array<string, mixed>}
     */
    #[McpTool(
        name: 'notes_update',
        title: 'Update note',
        description: 'Update the title, Markdown content, or pinned state of a note belonging to the authenticated user.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false),
    )]
    public function updateNote(
        #[Schema(description: 'Note ID.', minimum: 1)]
        int $id,
        #[Schema(description: 'Replacement title. Omit to keep the current title.', minLength: 1, maxLength: 255)]
        ?string $title = null,
        #[Schema(description: 'Replacement Markdown content. Omit to keep the current content.', maxLength: self::MAX_CONTENT_LENGTH)]
        ?string $content = null,
        #[Schema(description: 'Replacement pinned state. Omit to keep the current state.')]
        ?bool $pinned = null,
    ): array {
        if (null === $title && null === $content && null === $pinned) {
            throw new ToolCallException('Provide at least one field to update.');
        }

        $note = $this->ownedNote($id, $this->currentUser());
        if (null !== $title) {
            $note->setTitle($this->validTitle($title));
        }
        if (null !== $content) {
            $note->setContent($this->validContent($content));
        }
        if (null !== $pinned) {
            $note->setIsPinned($pinned);
        }

        $this->entityManager->flush();

        return ['note' => $this->noteData($note)];
    }

    /**
     * Move a note to a folder, or remove it from its current folder.
     *
     * @return array{note: array<string, mixed>}
     */
    #[McpTool(
        name: 'notes_move',
        title: 'Move note',
        description: 'Move a note to a folder owned by the authenticated user. Pass null as folderId to make it uncategorized.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function moveNote(
        #[Schema(description: 'Note ID.', minimum: 1)]
        int $id,
        #[Schema(description: 'Destination folder ID, or null to remove the note from its folder.', minimum: 1)]
        ?int $folderId,
    ): array {
        $owner = $this->currentUser();
        $note = $this->ownedNote($id, $owner);
        $note->setFolder(null === $folderId ? null : $this->ownedFolder($folderId, $owner));
        $this->entityManager->flush();

        return ['note' => $this->noteData($note)];
    }

    /**
     * Archive a note without permanently deleting it.
     *
     * @return array{note: array<string, mixed>}
     */
    #[McpTool(
        name: 'notes_archive',
        title: 'Archive note',
        description: 'Archive a note belonging to the authenticated user. This is reversible with notes_restore.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function archiveNote(#[Schema(description: 'Note ID.', minimum: 1)] int $id): array
    {
        $note = $this->ownedNote($id, $this->currentUser());
        if (null === $note->getArchivedAt()) {
            $note->archive();
            $this->entityManager->flush();
        }

        return ['note' => $this->noteData($note)];
    }

    /**
     * Restore an archived note.
     *
     * @return array{note: array<string, mixed>}
     */
    #[McpTool(
        name: 'notes_restore',
        title: 'Restore note',
        description: 'Restore an archived note belonging to the authenticated user.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function restoreNote(#[Schema(description: 'Note ID.', minimum: 1)] int $id): array
    {
        $note = $this->ownedNote($id, $this->currentUser());
        if (null !== $note->getArchivedAt()) {
            $note->restore();
            $this->entityManager->flush();
        }

        return ['note' => $this->noteData($note)];
    }

    /**
     * List folders owned by the authenticated user.
     *
     * @return array{folders: list<array<string, mixed>>, count: int}
     */
    #[McpTool(
        name: 'folders_list',
        title: 'List folders',
        description: 'List folders in the user\'s Sym Notes (sym_notes) application. Call this first to resolve a user-provided folder name before creating or moving a note.',
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    )]
    public function listFolders(): array
    {
        $folders = array_map($this->folderData(...), $this->folders->findForOwner($this->currentUser()));

        return ['folders' => $folders, 'count' => count($folders)];
    }

    /**
     * Create a folder.
     *
     * @return array{folder: array<string, mixed>}
     */
    #[McpTool(
        name: 'folders_create',
        title: 'Create folder',
        description: 'Create a folder in the user\'s Sym Notes (sym_notes) application. Optionally pass the ID of an existing owned folder to create a nested folder. Folder names must be unique within the same parent.',
        annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false),
    )]
    public function createFolder(
        #[Schema(description: 'Folder name.', minLength: 1, maxLength: 120)]
        string $name,
        #[Schema(description: 'Optional parent folder ID owned by the authenticated user.', minimum: 1)]
        ?int $parentId = null,
        #[Schema(description: 'Sort position used when displaying the folder.')]
        int $sortPosition = 0,
    ): array {
        $owner = $this->currentUser();
        $name = $this->validFolderName($name);
        $parent = null === $parentId ? null : $this->ownedFolder($parentId, $owner);

        if ($this->folders->nameExistsForOwner($owner, $name, $parent)) {
            throw new ToolCallException('A folder with that name already exists in the selected parent.');
        }

        $folder = (new Folder())
            ->setOwner($owner)
            ->setName($name)
            ->setParent($parent)
            ->setSortPosition($sortPosition);

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        return ['folder' => $this->folderData($folder)];
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new ToolCallException('MCP authentication is required.');
        }

        return $user;
    }

    private function ownedNote(int $id, User $owner): Note
    {
        $note = $this->notes->findOneForOwner($id, $owner);
        if (null === $note) {
            throw new ToolCallException('Note not found.');
        }

        return $note;
    }

    private function ownedFolder(int $id, User $owner): Folder
    {
        $folder = $this->folders->findOneForOwner($id, $owner);
        if (null === $folder) {
            throw new ToolCallException('Folder not found.');
        }

        return $folder;
    }

    private function validTitle(string $title): string
    {
        $title = trim($title);
        if ('' === $title || mb_strlen($title) > 255) {
            throw new ToolCallException('Title must contain between 1 and 255 characters.');
        }

        return $title;
    }

    private function validContent(string $content): string
    {
        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new ToolCallException('Note content is too long.');
        }

        return $content;
    }

    private function validFolderName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 120) {
            throw new ToolCallException('Folder name must contain between 1 and 120 characters.');
        }

        return $name;
    }

    private function boundedLimit(int $limit): int
    {
        return max(1, min(100, $limit));
    }

    /**
     * @return array<string, mixed>
     */
    private function noteSummary(Note $note): array
    {
        $content = preg_replace('/\s+/u', ' ', trim($note->getContent())) ?? trim($note->getContent());
        $excerpt = mb_strlen($content) > 180 ? mb_substr($content, 0, 177).'...' : $content;

        return [
            'id' => $note->getId(),
            'title' => $note->getTitle(),
            'excerpt' => $excerpt,
            'pinned' => $note->isPinned(),
            'folder' => null === $note->getFolder() ? null : $this->folderData($note->getFolder()),
            'updatedAt' => $note->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteData(Note $note): array
    {
        return [
            'id' => $note->getId(),
            'title' => $note->getTitle(),
            'content' => $note->getContent(),
            'pinned' => $note->isPinned(),
            'folder' => null === $note->getFolder() ? null : $this->folderData($note->getFolder()),
            'archivedAt' => $note->getArchivedAt()?->format(DATE_ATOM),
            'createdAt' => $note->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $note->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function folderData(Folder $folder): array
    {
        return [
            'id' => $folder->getId(),
            'name' => $folder->getName(),
            'parentId' => $folder->getParent()?->getId(),
        ];
    }
}
