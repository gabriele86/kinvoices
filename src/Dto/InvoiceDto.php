<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\UniqueInvoiceNumber;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input/output model for an invoice and its lines.
 */
#[UniqueInvoiceNumber]
class InvoiceDto
{
    /**
     * Null until the invoice has been created.
     */
    public ?int $id = null;

    #[Assert\NotNull(message: 'Please provide the invoice date.')]
    public ?\DateTimeInterface $invoiceDate = null;

    #[Assert\NotNull(message: 'Please provide the invoice number.')]
    #[Assert\Positive(message: 'The invoice number must be greater than zero.')]
    public ?int $invoiceNumber = null;

    #[Assert\NotNull(message: 'Please provide the customer id.')]
    #[Assert\Positive(message: 'The customer id must be greater than zero.')]
    public ?int $customerId = null;

    /**
     * @var InvoiceLineDto[]
     */
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'An invoice must contain at least one line.')]
    public array $lines = [];

    public function __construct()
    {
        $this->invoiceDate = new \DateTimeImmutable();
    }

    public function addLine(InvoiceLineDto $line): self
    {
        $this->lines[] = $line;

        return $this;
    }

    /**
     * Net total of the invoice.
     */
    public function getTotalAmount(): string
    {
        return $this->sum(static fn (InvoiceLineDto $line): string => $line->amount ?? '0.00');
    }

    public function getTotalVatAmount(): string
    {
        return $this->sum(static fn (InvoiceLineDto $line): string => $line->vatAmount ?? '0.00');
    }

    public function getTotalWithVat(): string
    {
        return $this->sum(static fn (InvoiceLineDto $line): string => $line->getTotalWithVat());
    }

    /**
     * @param callable(InvoiceLineDto): string $extractor
     */
    private function sum(callable $extractor): string
    {
        $total = '0.00';
        foreach ($this->lines as $line) {
            $total = bcadd($total, $extractor($line), InvoiceLineDto::SCALE);
        }

        return $total;
    }
}
