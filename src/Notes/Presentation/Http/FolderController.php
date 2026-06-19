<?php

declare(strict_types=1);

namespace App\Notes\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Infrastructure\Doctrine\Repository\FolderRepository;
use App\Notes\Infrastructure\Doctrine\Repository\NoteRepository;
use App\Notes\Presentation\Form\FolderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/folders', name: 'folders_')]
class FolderController extends AbstractController
{
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, FolderRepository $folders, EntityManagerInterface $entityManager): Response
    {
        $folder = (new Folder())->setOwner($this->currentUser());
        $form = $this->createForm(FolderType::class, $folder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->folderNameExists($folder, $folders)) {
                $this->addFlash('error', 'A folder with that name already exists.');

                return $this->render('notes/folders/new.html.twig', [
                    'form' => $form,
                ]);
            }

            $entityManager->persist($folder);
            $entityManager->flush();

            $this->addFlash('success', 'Folder created.');

            return $this->redirectToRoute('notes_folder', ['id' => $folder->getId()]);
        }

        return $this->render('notes/folders/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, FolderRepository $folders, EntityManagerInterface $entityManager): Response
    {
        $folder = $this->findOwnedFolder($id, $folders);
        $form = $this->createForm(FolderType::class, $folder);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->folderNameExists($folder, $folders, $folder)) {
                $this->addFlash('error', 'A folder with that name already exists.');

                return $this->render('notes/folders/edit.html.twig', [
                    'folder' => $folder,
                    'form' => $form,
                ]);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Folder saved.');

            return $this->redirectToRoute('notes_folder', ['id' => $folder->getId()]);
        }

        return $this->render('notes/folders/edit.html.twig', [
            'folder' => $folder,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        FolderRepository $folders,
        NoteRepository $notes,
        EntityManagerInterface $entityManager,
    ): Response {
        $folder = $this->findOwnedFolder($id, $folders);

        if ($this->isCsrfTokenValid('delete-folder-'.$folder->getId(), (string) $request->request->get('_token'))) {
            foreach ($notes->findActiveForOwnerInFolder($this->currentUser(), $folder) as $note) {
                $note->setFolder(null);
            }

            $entityManager->remove($folder);
            $entityManager->flush();

            $this->addFlash('success', 'Folder deleted. Its notes were moved to Uncategorized.');
        }

        return $this->redirectToRoute('notes_index');
    }

    private function findOwnedFolder(int $id, FolderRepository $folders): Folder
    {
        $folder = $folders->findOneForOwner($id, $this->currentUser());

        if (!$folder) {
            throw $this->createNotFoundException('Folder not found.');
        }

        return $folder;
    }

    private function folderNameExists(Folder $folder, FolderRepository $folders, ?Folder $exclude = null): bool
    {
        return $folders->nameExistsForOwner(
            $this->currentUser(),
            $folder->getName(),
            $folder->getParent(),
            $exclude,
        );
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
