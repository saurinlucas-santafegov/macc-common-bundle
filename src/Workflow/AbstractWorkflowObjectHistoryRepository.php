<?php

namespace Macc\CommonBundle\Workflow;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AbstractWorkflowObjectHistory>
 *
 * @method AbstractWorkflowObjectHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbstractWorkflowObjectHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbstractWorkflowObjectHistory[]    findAll()
 * @method AbstractWorkflowObjectHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
abstract class AbstractWorkflowObjectHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbstractWorkflowObjectHistory::class);
    }

    /**
     * Busca los historiales por ID del objeto de workflow.
     */
    public function findByWorkflowObjectId(int $objectId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('IDENTITY(h.workflowObject) = :objectId')
            ->setParameter('objectId', $objectId)
            ->orderBy('h.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Devuelve el último estado registrado para un objeto.
     */
    public function findLastState(int $objectId): ?AbstractWorkflowObjectHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('IDENTITY(h.workflowObject) = :objectId')
            ->setParameter('objectId', $objectId)
            ->orderBy('h.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Guarda o actualiza una entrada de historial.
     */
    public function save(AbstractWorkflowObjectHistory $history, bool $flush = false): void
    {
        $this->_em->persist($history);

        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * Elimina una entrada de historial.
     */
    public function remove(AbstractWorkflowObjectHistory $history, bool $flush = false): void
    {
        $this->_em->remove($history);

        if ($flush) {
            $this->_em->flush();
        }
    }
}
