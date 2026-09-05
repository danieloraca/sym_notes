<?php

declare(strict_types=1);

namespace App\Notes\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\User;
use App\Notes\Domain\Entity\NoteAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NoteAttachment> */
class NoteAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NoteAttachment::class);
    }

    public function findOneForOwner(int $id, User $owner): ?NoteAttachment
    {
        return $this->createQueryBuilder('attachment')
            ->innerJoin('attachment.note', 'note')
            ->andWhere('attachment.id = :id')
            ->andWhere('note.owner = :owner')
            ->setParameter('id', $id)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
