<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;

/**
 * Read side of the invoice storage.
 *
 * The service layer depends on this interface, not on the Doctrine
 * implementation, so the storage can be swapped or faked in a test without
 * touching the use cases.
 */
interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice;

    public function findOneByInvoiceNumber(int $invoiceNumber): ?Invoice;

    /**
     * Invoices with their lines already fetched, most recent first.
     *
     * @return Invoice[]
     */
    public function findAllWithLines(): array;

    /**
     * The first invoice number not used yet.
     */
    public function findNextInvoiceNumber(): int;
}
