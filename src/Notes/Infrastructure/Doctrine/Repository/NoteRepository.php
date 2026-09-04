<?php

declare(strict_types=1);

namespace App\Notes\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use App\Notes\Domain\Entity\Note;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * @return list<Note>
     */
    public function findActiveForOwner(User $owner): array
    {
        return $this->createQueryBuilder('note')
            ->andWhere('note.owner = :owner')
            ->andWhere('note.archivedAt IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('note.isPinned', 'DESC')
            ->addOrderBy('note.updatedAt', 'DESC')
            ->addOrderBy('note.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Note>
     */
    public function findActiveForOwnerPage(User $owner, int $page, int $perPage): array
    {
        return $this->createQueryBuilder('note')
            ->andWhere('note.owner = :owner')
            ->andWhere('note.archivedAt IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('note.isPinned', 'DESC')
            ->addOrderBy('note.updatedAt', 'DESC')
            ->addOrderBy('note.id', 'DESC')
            ->setFirstResult($this->pageOffset($page, $perPage))
            ->setMaxResults(max(1, $perPage))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Note>
     */
    public function findActiveForOwnerInFolder(User $owner, ?Folder $folder): array
    {
        $queryBuilder = $this->createQueryBuilder('note')
            ->andWhere('note.owner = :owner')
            ->andWhere('note.archivedAt IS NULL')
            ->setParameter('owner', $owner);

        if ($folder) {
            $queryBuilder
                ->andWhere('note.folder = :folder')
                ->setParameter('folder', $folder);
        } else {
            $queryBuilder->andWhere('note.folder IS NULL');
        }

        return $queryBuilder
            ->orderBy('note.isPinned', 'DESC')
            ->addOrderBy('note.updatedAt', 'DESC')
            ->addOrderBy('note.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Note>
     */
    public function findActiveForOwnerInFolderPage(User $owner, ?Folder $folder, int $page, int $perPage): array
    {
        $queryBuilder = $this->createQueryBuilder('note')
            ->andWhere('note.owner = :owner')
            ->andWhere('note.archivedAt IS NULL')
            ->setParameter('owner', $owner);

        if ($folder) {
            $queryBuilder
                ->andWhere('note.folder = :folder')
                ->setParameter('folder', $folder);
        } else {
            $queryBuilder->andWhere('note.folder IS NULL');
        }

        return $queryBuilder
            ->orderBy('note.isPinned', 'DESC')
            ->addOrderBy('note.updatedAt', 'DESC')
            ->addOrderBy('note.id', 'DESC')
            ->setFirstResult($this->pageOffset($page, $perPage))
            ->setMaxResults(max(1, $perPage))
            ->getQuery()
            ->getResult();
    }

    public function findOneForOwner(int $id, User $owner): ?Note
    {
        return $this->createQueryBuilder('note')
            ->andWhere('note.id = :id')
            ->andWhere('note.owner = :owner')
            ->setParameter('id', $id)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countActiveForOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('note')
            ->select('COUNT(note.id)')
            ->andWhere('note.owner = :owner')
            ->andWhere('note.archivedAt IS NULL')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPinnedForOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('note')
            ->select('COUNT(note.id)')
            ->andWhere('note.owner = :owner')
            ->andWhere('note.isPinned = true')
            ->andWhere('note.archivedAt IS NULL')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveForOwnerInFolder(User $owner, ?Folder $folder): int
    {
        $queryBuilder = $this->createQueryBuilder('note')
            ->select('COUNT(note.id)')
            ->andWhere('note.owner = :owner')
            ->andWhere('note.archivedAt IS NULL')
            ->setParameter('owner', $owner);

        if ($folder) {
            $queryBuilder
                ->andWhere('note.folder = :folder')
                ->setParameter('folder', $folder);
        } else {
            $queryBuilder->andWhere('note.folder IS NULL');
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function pageOffset(int $page, int $perPage): int
    {
        return (max(1, $page) - 1) * max(1, $perPage);
    }
}
