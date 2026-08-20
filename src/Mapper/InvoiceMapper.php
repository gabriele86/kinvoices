<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;

/**
 * Translates between the Doctrine entities and the DTOs used by the rest of the
 * application. It is the only place that knows both sides.
 */
class InvoiceMapper implements InvoiceMapperInterface
{
    public function toDto(Invoice $invoice): InvoiceDto
    {
        $dto = new InvoiceDto();
        $dto->id = $invoice->getId();
        $dto->invoiceDate = $invoice->getInvoiceDate();
        $dto->invoiceNumber = $invoice->getInvoiceNumber();
        $dto->customerId = $invoice->getCustomerId();

        foreach ($invoice->getLines() as $line) {
            $dto->addLine($this->lineToDto($line));
        }

        return $dto;
    }

    /**
     * @param iterable<Invoice> $invoices
     *
     * @return InvoiceDto[]
     */
    public function toDtoList(iterable $invoices): array
    {
        $dtos = [];
        foreach ($invoices as $invoice) {
            $dtos[] = $this->toDto($invoice);
        }

        return $dtos;
    }

    public function lineToDto(InvoiceLine $line): InvoiceLineDto
    {
        $dto = new InvoiceLineDto();
        $dto->id = $line->getId();
        $dto->description = $line->getDescription();
        $dto->quantity = $line->getQuantity();
        $dto->amount = $line->getAmount();
        $dto->vatAmount = $line->getVatAmount();

        return $dto;
    }

    /**
     * Builds a brand new entity from a DTO.
     */
    public function toEntity(InvoiceDto $dto): Invoice
    {
        return $this->applyToEntity($dto, new Invoice());
    }

    /**
     * Copies the DTO onto an existing entity.
     *
     * Lines are matched on their id: known ids are updated in place, lines
     * without an id are inserted, and the lines that the DTO no longer carries
     * are detached so that orphanRemoval deletes them.
     */
    public function applyToEntity(InvoiceDto $dto, Invoice $invoice): Invoice
    {
        $invoice->setInvoiceDate($dto->invoiceDate);
        $invoice->setInvoiceNumber($dto->invoiceNumber);
        $invoice->setCustomerId($dto->customerId);

        $existing = [];
        foreach ($invoice->getLines() as $line) {
            if (null !== $line->getId()) {
                $existing[$line->getId()] = $line;
            }
        }

        $kept = [];
        foreach ($dto->lines as $lineDto) {
            $line = (null !== $lineDto->id && isset($existing[$lineDto->id]))
                ? $existing[$lineDto->id]
                : new InvoiceLine();

            $this->applyToLine($lineDto, $line);

            if (null === $line->getId()) {
                $invoice->addLine($line);
            } else {
                $kept[$line->getId()] = true;
            }
        }

        foreach ($existing as $id => $line) {
            if (!isset($kept[$id])) {
                $invoice->removeLine($line);
            }
        }

        return $invoice;
    }

    private function applyToLine(InvoiceLineDto $dto, InvoiceLine $line): void
    {
        $line->setDescription($dto->description);
        $line->setQuantity($dto->quantity);
        $line->setAmount($dto->amount);
        // Also recomputes total_with_vat; the submitted total is never trusted.
        $line->setVatAmount($dto->vatAmount);
    }
}
