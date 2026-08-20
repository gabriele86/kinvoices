<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An invoice header, holding one or more invoice lines.
 *
 * Persistence model: it is never handed to a controller or a template, and it
 * carries no validation constraint — those live on App\Dto\InvoiceDto, the
 * input model. What is enforced here is what the database enforces.
 */
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoice')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_number', columns: ['invoice_number'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'invoice_date', type: 'date')]
    private ?\DateTimeInterface $invoiceDate = null;

    #[ORM\Column(name: 'invoice_number', type: 'integer')]
    private ?int $invoiceNumber = null;

    #[ORM\Column(name: 'customer_id', type: 'integer')]
    private ?int $customerId = null;

    /**
     * @var Collection<int, InvoiceLine>
     */
    #[ORM\OneToMany(
        mappedBy: 'invoice',
        targetEntity: InvoiceLine::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->invoiceDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceDate(): ?\DateTimeInterface
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?\DateTimeInterface $invoiceDate): self
    {
        $this->invoiceDate = $invoiceDate;

        return $this;
    }

    public function getInvoiceNumber(): ?int
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?int $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    /**
     * @return Collection<int, InvoiceLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }

        return $this;
    }

    public function removeLine(InvoiceLine $line): self
    {
        if ($this->lines->removeElement($line)) {
            // Keep both sides of the association in sync.
            if ($line->getInvoice() === $this) {
                $line->setInvoice(null);
            }
        }

        return $this;
    }

    /**
     * Net total of the invoice (sum of the line amounts), as a decimal string.
     */
    public function getTotalAmount(): string
    {
        return $this->sumLines(static fn (InvoiceLine $line): string => $line->getAmount() ?? '0.00');
    }

    /**
     * VAT total of the invoice, as a decimal string.
     */
    public function getTotalVatAmount(): string
    {
        return $this->sumLines(static fn (InvoiceLine $line): string => $line->getVatAmount() ?? '0.00');
    }

    /**
     * Gross total of the invoice, as a decimal string.
     */
    public function getTotalWithVat(): string
    {
        return $this->sumLines(static fn (InvoiceLine $line): string => $line->getTotalWithVat() ?? '0.00');
    }

    /**
     * @param callable(InvoiceLine): string $extractor
     */
    private function sumLines(callable $extractor): string
    {
        $total = '0.00';
        foreach ($this->lines as $line) {
            $total = bcadd($total, $extractor($line), 2);
        }

        return $total;
    }

    public function __toString(): string
    {
        return sprintf('Invoice #%s', $this->invoiceNumber ?? '-');
    }
}
