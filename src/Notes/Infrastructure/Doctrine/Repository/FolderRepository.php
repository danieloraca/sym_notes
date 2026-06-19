<?php

namespace App\Notes\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\Folder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Folder>
 */
class FolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folder::class);
    }

    /**
     * @return list<Folder>
     */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('folder')
            ->andWhere('folder.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('folder.sortPosition', 'ASC')
            ->addOrderBy('folder.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForOwner(int $id, User $owner): ?Folder
    {
        return $this->createQueryBuilder('folder')
            ->andWhere('folder.id = :id')
            ->andWhere('folder.owner = :owner')
            ->setParameter('id', $id)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function nameExistsForOwner(User $owner, string $name, ?Folder $parent = null, ?Folder $exclude = null): bool
    {
        $queryBuilder = $this->createQueryBuilder('folder')
            ->select('COUNT(folder.id)')
            ->andWhere('folder.owner = :owner')
            ->andWhere('folder.name = :name')
            ->setParameter('owner', $owner)
            ->setParameter('name', trim($name));

        if ($parent) {
            $queryBuilder
                ->andWhere('folder.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $queryBuilder->andWhere('folder.parent IS NULL');
        }

        if ($exclude && null !== $exclude->getId()) {
            $queryBuilder
                ->andWhere('folder.id != :excludeId')
                ->setParameter('excludeId', $exclude->getId());
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult() > 0;
    }
}
