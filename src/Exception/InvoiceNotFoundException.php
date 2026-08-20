<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown by the service layer when an invoice does not exist. It is turned into
 * a 404 response by App\EventSubscriber\InvoiceNotFoundSubscriber, so the
 * service layer stays free of any HTTP concern.
 */
class InvoiceNotFoundException extends \RuntimeException
{
    public function __construct(private readonly int $invoiceId)
    {
        parent::__construct(sprintf('Invoice "%d" does not exist.', $invoiceId));
    }

    public function getInvoiceId(): int
    {
        return $this->invoiceId;
    }
}
