<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoiceLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoiceLine>
 *
 * Lines are always reached through their invoice; this repository exists so the
 * entity keeps a repositoryClass of its own.
 */
class InvoiceLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceLine::class);
    }
}
