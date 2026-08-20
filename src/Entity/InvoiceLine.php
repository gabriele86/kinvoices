<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceLineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single line of an invoice.
 *
 * "total_with_vat" is a stored column (as required by the specification) but it is
 * never written by the user: it is always derived from amount + VAT amount, both in
 * the browser (live preview) and server side, so the stored value cannot drift.
 */
#[ORM\Entity(repositoryClass: InvoiceLineRepository::class)]
#[ORM\Table(name: 'invoice_line')]
#[ORM\Index(name: 'idx_invoice_line_invoice', columns: ['invoice_id'])]
#[ORM\HasLifecycleCallbacks]
class InvoiceLine
{
    public const SCALE = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    /**
     * Doctrine type "text" -> TEXT column (unlimited length, no explicit size).
     */
    #[ORM\Column(name: 'description', type: 'text')]
    private ?string $description = null;

    #[ORM\Column(name: 'quantity', type: 'integer')]
    private ?int $quantity = null;

    /**
     * Net amount of the line. Decimal columns are handled as strings to avoid
     * floating point rounding errors.
     */
    #[ORM\Column(name: 'amount', type: 'decimal', precision: 12, scale: self::SCALE)]
    private ?string $amount = null;

    #[ORM\Column(name: 'vat_amount', type: 'decimal', precision: 12, scale: self::SCALE)]
    private ?string $vatAmount = null;

    #[ORM\Column(name: 'total_with_vat', type: 'decimal', precision: 12, scale: self::SCALE)]
    private ?string $totalWithVat = null;

    public function __construct()
    {
        $this->quantity = 1;
        $this->amount = '0.00';
        $this->vatAmount = '0.00';
        $this->totalWithVat = '0.00';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;
        $this->recalculateTotalWithVat();

        return $this;
    }

    public function getVatAmount(): ?string
    {
        return $this->vatAmount;
    }

    public function setVatAmount(?string $vatAmount): self
    {
        $this->vatAmount = $vatAmount;
        $this->recalculateTotalWithVat();

        return $this;
    }

    public function getTotalWithVat(): ?string
    {
        return $this->totalWithVat;
    }

    /**
     * Recomputes the stored gross total. Called by the setters and, as a safety net,
     * right before the row hits the database.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function recalculateTotalWithVat(): void
    {
        $this->totalWithVat = bcadd(
            $this->amount ?? '0.00',
            $this->vatAmount ?? '0.00',
            self::SCALE
        );
    }

    public function __toString(): string
    {
        return (string) $this->description;
    }
}
