<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\InvoiceDto;
use App\Exception\InvoiceNotFoundException;

/**
 * The invoice use cases, expressed entirely in DTOs.
 *
 * Controllers depend on this interface only.
 */
interface InvoiceManagerInterface
{
    /**
     * @return InvoiceDto[]
     */
    public function getInvoices(): array;

    /**
     * @throws InvoiceNotFoundException if no invoice has that id
     */
    public function getInvoice(int $id): InvoiceDto;

    /**
     * A new invoice, pre-filled the way the create form expects it.
     */
    public function createDraft(): InvoiceDto;

    public function nextInvoiceNumber(): int;

    public function create(InvoiceDto $dto): InvoiceDto;

    /**
     * @throws InvoiceNotFoundException if the DTO does not point at a stored invoice
     */
    public function update(InvoiceDto $dto): InvoiceDto;

    /**
     * @throws InvoiceNotFoundException if no invoice has that id
     */
    public function delete(int $id): void;
}
