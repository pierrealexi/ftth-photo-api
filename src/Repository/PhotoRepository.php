<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    public function findByFilters(
        ?string $prestationReference,
        ?string $internalOrder,
        ?int $interventionId
    ): array {
        $qb = $this->createQueryBuilder('p');

        if ($prestationReference) {
            $qb->andWhere('p.prestationReference = :prestation')
            ->setParameter('prestation', $prestationReference);
        }

        if ($internalOrder) {
            $qb->andWhere('p.internalOrder = :order')
            ->setParameter('order', $internalOrder);
        }

        if ($interventionId) {
            $qb->andWhere('p.interventionId = :intervention')
            ->setParameter('intervention', $interventionId);
        }

        $qb->orderBy('p.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function save(Photo $photo, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($photo);
        if ($flush) {
            $em->flush();
        }
    }

    public function getEm(): EntityManagerInterface
    {
        return $this->getEntityManager();
    }
}