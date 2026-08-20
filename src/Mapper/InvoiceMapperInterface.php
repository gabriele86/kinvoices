<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;

/**
 * Translation between the persistence model and the DTOs.
 */
interface InvoiceMapperInterface
{
    public function toDto(Invoice $invoice): InvoiceDto;

    /**
     * @param iterable<Invoice> $invoices
     *
     * @return InvoiceDto[]
     */
    public function toDtoList(iterable $invoices): array;

    public function lineToDto(InvoiceLine $line): InvoiceLineDto;

    /**
     * A brand new entity built from the DTO.
     */
    public function toEntity(InvoiceDto $dto): Invoice;

    /**
     * Copies the DTO onto an existing entity, inserting, updating and detaching
     * lines as needed.
     */
    public function applyToEntity(InvoiceDto $dto, Invoice $invoice): Invoice;
}
