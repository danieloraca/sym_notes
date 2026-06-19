<?php

namespace App\Notes\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Note;
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
    public function index(NoteRepository $notes): Response
    {
        $owner = $this->currentUser();
        $activeNotes = $notes->findActiveForOwner($owner);

        return $this->render('notes/index.html.twig', [
            'notes' => $activeNotes,
            'stats' => [
                'notes' => count($activeNotes),
                'pinned' => $notes->countPinnedForOwner($owner),
                'drafts' => 0,
            ],
        ]);
    }

    #[Route('/notes/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $note = (new Note())->setOwner($this->currentUser());
        $form = $this->createForm(NoteType::class, $note);
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
    public function edit(int $id, Request $request, NoteRepository $notes, EntityManagerInterface $entityManager): Response
    {
        $note = $this->findOwnedNote($id, $notes);
        $form = $this->createForm(NoteType::class, $note);
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

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
