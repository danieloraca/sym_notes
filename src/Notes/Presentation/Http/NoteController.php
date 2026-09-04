<?php

declare(strict_types=1);

namespace App\Notes\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Domain\Entity\Note;
use App\Notes\Infrastructure\Doctrine\Repository\FolderRepository;
use App\Notes\Infrastructure\Doctrine\Repository\NoteRepository;
use App\Notes\Presentation\Form\NoteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'notes_')]
class NoteController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(NoteRepository $notes, FolderRepository $folders): Response
    {
        $owner = $this->currentUser();
        $activeNotes = $notes->findActiveForOwner($owner);
        $ownedFolders = $folders->findForOwner($owner);

        return $this->render('notes/index.html.twig', [
            'notes' => $activeNotes,
            'folders' => $ownedFolders,
            'folder_counts' => $this->folderCounts($ownedFolders, $notes, $owner),
            'current_folder' => null,
            'uncategorized_count' => $notes->countActiveForOwnerInFolder($owner, null),
            'stats' => [
                'notes' => count($activeNotes),
                'pinned' => $notes->countPinnedForOwner($owner),
                'drafts' => 0,
            ],
        ]);
    }

    #[Route('/uncategorized', name: 'uncategorized', methods: ['GET'])]
    public function uncategorized(NoteRepository $notes, FolderRepository $folders): Response
    {
        $owner = $this->currentUser();
        $activeNotes = $notes->findActiveForOwnerInFolder($owner, null);
        $ownedFolders = $folders->findForOwner($owner);

        return $this->render('notes/index.html.twig', [
            'notes' => $activeNotes,
            'folders' => $ownedFolders,
            'folder_counts' => $this->folderCounts($ownedFolders, $notes, $owner),
            'current_folder' => null,
            'uncategorized_count' => count($activeNotes),
            'stats' => [
                'notes' => $notes->countActiveForOwner($owner),
                'pinned' => $notes->countPinnedForOwner($owner),
                'drafts' => 0,
            ],
        ]);
    }

    #[Route('/folders/{id}', name: 'folder', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function folder(int $id, NoteRepository $notes, FolderRepository $folders): Response
    {
        $owner = $this->currentUser();
        $folder = $this->findOwnedFolder($id, $folders);
        $activeNotes = $notes->findActiveForOwnerInFolder($owner, $folder);

        return $this->render('notes/index.html.twig', [
            'notes' => $activeNotes,
            'folders' => $ownedFolders = $folders->findForOwner($owner),
            'folder_counts' => $this->folderCounts($ownedFolders, $notes, $owner),
            'current_folder' => $folder,
            'uncategorized_count' => $notes->countActiveForOwnerInFolder($owner, null),
            'stats' => [
                'notes' => $notes->countActiveForOwner($owner),
                'pinned' => $notes->countPinnedForOwner($owner),
                'drafts' => 0,
            ],
        ]);
    }

    #[Route('/notes/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, FolderRepository $folders, EntityManagerInterface $entityManager): Response
    {
        $owner = $this->currentUser();
        $note = (new Note())->setOwner($owner);
        $form = $this->createForm(NoteType::class, $note, [
            'folders' => $folders->findForOwner($owner),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($note);
            $entityManager->flush();

            $this->addFlash('success', 'Note created.');

            return $this->redirectToRoute('notes_show', ['id' => $note->getId()]);
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
    public function edit(int $id, Request $request, NoteRepository $notes, FolderRepository $folders, EntityManagerInterface $entityManager): Response
    {
        $owner = $this->currentUser();
        $note = $this->findOwnedNote($id, $notes);
        $form = $this->createForm(NoteType::class, $note, [
            'folders' => $folders->findForOwner($owner),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Note saved.');

            return $this->redirectToRoute('notes_show', ['id' => $note->getId()]);
        }

        return $this->render('notes/edit.html.twig', [
            'note' => $note,
            'form' => $form,
        ]);
    }

    #[Route('/notes/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, NoteRepository $notes, EntityManagerInterface $entityManager): Response
    {
        $note = $this->findOwnedNote($id, $notes);

        if ($this->isCsrfTokenValid('delete-note-'.$note->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($note);
            $entityManager->flush();

            $this->addFlash('success', 'Note deleted.');
        }

        return $this->redirectToRoute('notes_index');
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

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
