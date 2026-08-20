<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 *
 * Data access only: persisting and flushing belong to App\Service\InvoiceManager.
 */
class InvoiceRepository extends ServiceEntityRepository implements InvoiceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findById(int $id): ?Invoice
    {
        return $this->find($id);
    }

    public function findOneByInvoiceNumber(int $invoiceNumber): ?Invoice
    {
        return $this->findOneBy(['invoiceNumber' => $invoiceNumber]);
    }

    /**
     * The lines are fetch-joined to avoid the N+1 queries that computing the
     * totals in the template would otherwise trigger.
     *
     * @return Invoice[]
     */
    public function findAllWithLines(): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('l')
            ->leftJoin('i.lines', 'l')
            ->orderBy('i.invoiceDate', 'DESC')
            ->addOrderBy('i.invoiceNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findNextInvoiceNumber(): int
    {
        $max = $this->createQueryBuilder('i')
            ->select('MAX(i.invoiceNumber)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
