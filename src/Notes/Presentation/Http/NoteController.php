<?php

declare(strict_types=1);

namespace App\Notes\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Domain\Entity\Note;
use App\Notes\Infrastructure\Doctrine\Repository\NoteAttachmentRepository;
use App\Notes\Infrastructure\Doctrine\Repository\FolderRepository;
use App\Notes\Infrastructure\Doctrine\Repository\NoteRepository;
use App\Notes\Infrastructure\Storage\NoteAttachmentStorage;
use App\Notes\Presentation\Form\NoteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'notes_')]
class NoteController extends AbstractController
{
    private const NOTES_PER_PAGE = 20;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, NoteRepository $notes, FolderRepository $folders): Response
    {
        $owner = $this->currentUser();
        $search = $this->searchQuery($request);
        $totalNotes = $notes->countActiveForOwner($owner, $search);
        $pagination = $this->pagination($request, $totalNotes);
        $activeNotes = $notes->findActiveForOwnerPage($owner, $pagination['page'], self::NOTES_PER_PAGE, $search);
        $ownedFolders = $folders->findForOwner($owner);

        return $this->render('notes/index.html.twig', [
            'notes' => $activeNotes,
            'folders' => $ownedFolders,
            'folder_counts' => $this->folderCounts($ownedFolders, $notes, $owner),
            'current_folder' => null,
            'uncategorized_count' => $notes->countActiveForOwnerInFolder($owner, null),
            'stats' => [
                'notes' => $totalNotes,
                'pinned' => $notes->countPinnedForOwner($owner),
                'drafts' => 0,
            ],
            'pagination' => $pagination,
            'search' => $search ?? '',
            'search_route_params' => [],
            'pagination_route_params' => $this->paginationRouteParams([], $search),
        ]);
    }

    #[Route('/uncategorized', name: 'uncategorized', methods: ['GET'])]
    public function uncategorized(Request $request, NoteRepository $notes, FolderRepository $folders): Response
    {
        $owner = $this->currentUser();
        $search = $this->searchQuery($request);
        $totalNotes = $notes->countActiveForOwnerInFolder($owner, null, $search);
        $pagination = $this->pagination($request, $totalNotes);
        $activeNotes = $notes->findActiveForOwnerInFolderPage($owner, null, $pagination['page'], self::NOTES_PER_PAGE, $search);
        $ownedFolders = $folders->findForOwner($owner);

        return $this->render('notes/index.html.twig', [
            'notes' => $activeNotes,
            'folders' => $ownedFolders,
            'folder_counts' => $this->folderCounts($ownedFolders, $notes, $owner),
            'current_folder' => null,
            'uncategorized_count' => $totalNotes,
            'stats' => [
                'notes' => $notes->countActiveForOwner($owner),
                'pinned' => $notes->countPinnedForOwner($owner),
                'drafts' => 0,
            ],
            'pagination' => $pagination,
            'search' => $search ?? '',
            'search_route_params' => [],
            'pagination_route_params' => $this->paginationRouteParams([], $search),
        ]);
    }

    #[Route('/folders/{id}', name: 'folder', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function folder(int $id, Request $request, NoteRepository $notes, FolderRepository $folders): Response
    {
        $owner = $this->currentUser();
        $folder = $this->findOwnedFolder($id, $folders);
        $search = $this->searchQuery($request);
        $totalNotes = $notes->countActiveForOwnerInFolder($owner, $folder, $search);
        $pagination = $this->pagination($request, $totalNotes);
        $activeNotes = $notes->findActiveForOwnerInFolderPage($owner, $folder, $pagination['page'], self::NOTES_PER_PAGE, $search);
        $ownedFolders = $folders->findForOwner($owner);

        return $this->render('notes/index.html.twig', [
            'notes' => $activeNotes,
            'folders' => $ownedFolders,
            'folder_counts' => $this->folderCounts($ownedFolders, $notes, $owner),
            'current_folder' => $folder,
            'uncategorized_count' => $notes->countActiveForOwnerInFolder($owner, null),
            'stats' => [
                'notes' => $notes->countActiveForOwner($owner),
                'pinned' => $notes->countPinnedForOwner($owner),
                'drafts' => 0,
            ],
            'pagination' => $pagination,
            'search' => $search ?? '',
            'search_route_params' => ['id' => (int) $folder->getId()],
            'pagination_route_params' => $this->paginationRouteParams(['id' => (int) $folder->getId()], $search),
        ]);
    }

    #[Route('/notes/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, FolderRepository $folders, EntityManagerInterface $entityManager, NoteAttachmentStorage $attachmentStorage): Response
    {
        $owner = $this->currentUser();
        $note = (new Note())->setOwner($owner);
        $form = $this->createForm(NoteType::class, $note, [
            'folders' => $folders->findForOwner($owner),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $storedAttachments = [];

            try {
                $storedAttachments = $this->storeAttachments($note, $form, $attachmentStorage);
                $entityManager->persist($note);
                $entityManager->flush();

                $this->addFlash('success', 'Note created.');

                return $this->redirectToRoute('notes_show', ['id' => $note->getId()]);
            } catch (FileException $exception) {
                $form->get('attachments')->addError(new FormError($exception->getMessage()));
            } catch (\Throwable $exception) {
                $this->removeAttachments($storedAttachments, $attachmentStorage);

                throw $exception;
            }
        }

        return $this->render('notes/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/notes/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, NoteRepository $notes): Response
    {
        return $this->render('notes/show.html.twig', [
            'note' => $this->findOwnedNote($id, $notes),
        ]);
    }

    #[Route('/notes/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, NoteRepository $notes, FolderRepository $folders, EntityManagerInterface $entityManager, NoteAttachmentStorage $attachmentStorage): Response
    {
        $owner = $this->currentUser();
        $note = $this->findOwnedNote($id, $notes);
        $form = $this->createForm(NoteType::class, $note, [
            'folders' => $folders->findForOwner($owner),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $storedAttachments = [];

            try {
                $storedAttachments = $this->storeAttachments($note, $form, $attachmentStorage);
                $entityManager->flush();

                $this->addFlash('success', 'Note saved.');

                return $this->redirectToRoute('notes_show', ['id' => $note->getId()]);
            } catch (FileException $exception) {
                $form->get('attachments')->addError(new FormError($exception->getMessage()));
            } catch (\Throwable $exception) {
                $this->removeAttachments($storedAttachments, $attachmentStorage);

                throw $exception;
            }
        }

        return $this->render('notes/edit.html.twig', [
            'note' => $note,
            'form' => $form,
        ]);
    }

    #[Route('/notes/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, NoteRepository $notes, EntityManagerInterface $entityManager, NoteAttachmentStorage $attachmentStorage): Response
    {
        $note = $this->findOwnedNote($id, $notes);

        if ($this->isCsrfTokenValid('delete-note-'.$note->getId(), (string) $request->request->get('_token'))) {
            $attachments = $note->getAttachments()->toArray();

            $entityManager->remove($note);
            $entityManager->flush();
            $this->removeAttachments($attachments, $attachmentStorage);

            $this->addFlash('success', 'Note deleted.');
        }

        return $this->redirectToRoute('notes_index');
    }

    #[Route('/notes/attachments/{id}/download', name: 'attachment_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadAttachment(int $id, NoteAttachmentRepository $attachments, NoteAttachmentStorage $attachmentStorage): Response
    {
        $attachment = $attachments->findOneForOwner($id, $this->currentUser());

        if (!$attachment) {
            throw $this->createNotFoundException('Attachment not found.');
        }

        $path = $attachmentStorage->path($attachment);

        if (!is_file($path)) {
            throw $this->createNotFoundException('Attachment file not found.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName());

        return $response;
    }

    /** @return list<\App\Notes\Domain\Entity\NoteAttachment> */
    private function storeAttachments(Note $note, FormInterface $form, NoteAttachmentStorage $storage): array
    {
        $files = $form->get('attachments')->getData();

        if (!is_array($files)) {
            return [];
        }

        if (count($files) > NoteAttachmentStorage::MAX_FILES) {
            throw new FileException(sprintf('A note can have up to %d files per upload.', NoteAttachmentStorage::MAX_FILES));
        }

        $stored = [];

        try {
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $attachment = $storage->store($note, $file);
                $note->addAttachment($attachment);
                $stored[] = $attachment;
            }
        } catch (\Throwable $exception) {
            foreach ($stored as $attachment) {
                $storage->remove($attachment);
            }

            throw $exception;
        }

        return $stored;
    }

    /** @param iterable<\App\Notes\Domain\Entity\NoteAttachment> $attachments */
    private function removeAttachments(iterable $attachments, NoteAttachmentStorage $storage): void
    {
        foreach ($attachments as $attachment) {
            $storage->remove($attachment);
        }
    }

    private function findOwnedNote(int $id, NoteRepository $notes): Note
    {
        $note = $notes->findOneForOwner($id, $this->currentUser());

        if (!$note) {
            throw $this->createNotFoundException('Note not found.');
        }

        return $note;
    }

    private function findOwnedFolder(int $id, FolderRepository $folders): Folder
    {
        $folder = $folders->findOneForOwner($id, $this->currentUser());

        if (!$folder) {
            throw $this->createNotFoundException('Folder not found.');
        }

        return $folder;
    }

    /**
     * @param list<Folder> $folders
     *
     * @return array<int, int>
     */
    private function folderCounts(array $folders, NoteRepository $notes, User $owner): array
    {
        $counts = [];

        foreach ($folders as $folder) {
            $counts[(int) $folder->getId()] = $notes->countActiveForOwnerInFolder($owner, $folder);
        }

        return $counts;
    }

    /**
     * @return array{page: int, per_page: int, total: int, total_pages: int, first_item: int, last_item: int}
     */
    private function pagination(Request $request, int $total): array
    {
        $totalPages = max(1, (int) ceil($total / self::NOTES_PER_PAGE));
        $page = min($totalPages, max(1, $request->query->getInt('page', 1)));

        return [
            'page' => $page,
            'per_page' => self::NOTES_PER_PAGE,
            'total' => $total,
            'total_pages' => $totalPages,
            'first_item' => $total === 0 ? 0 : (($page - 1) * self::NOTES_PER_PAGE) + 1,
            'last_item' => min($page * self::NOTES_PER_PAGE, $total),
        ];
    }

    private function searchQuery(Request $request): ?string
    {
        $search = trim($request->query->getString('q'));

        return '' === $search ? null : $search;
    }

    /**
     * @param array<string, int> $routeParams
     *
     * @return array<string, int|string>
     */
    private function paginationRouteParams(array $routeParams, ?string $search): array
    {
        if (null !== $search) {
            $routeParams['q'] = $search;
        }

        return $routeParams;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
