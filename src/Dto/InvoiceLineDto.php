<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input/output model for one invoice line.
 *
 * Controllers, forms and templates work with this object; the Doctrine entity
 * never leaves the persistence layer. Amounts stay decimal strings, exactly as
 * the DECIMAL(12,2) columns store them.
 */
class InvoiceLineDto
{
    /**
     * Number of decimals of the money fields, mirroring DECIMAL(12,2).
     */
    public const SCALE = 2;

    /**
     * Identifier of the persisted line, or null for a line added in the browser.
     * It is what lets the mapper tell an update from an insert.
     */
    public ?int $id = null;

    #[Assert\NotBlank(message: 'Please provide a description.')]
    public ?string $description = null;

    #[Assert\NotNull(message: 'Please provide a quantity.')]
    #[Assert\Positive(message: 'The quantity must be greater than zero.')]
    public ?int $quantity = 1;

    #[Assert\NotNull(message: 'Please provide an amount.')]
    #[Assert\PositiveOrZero(message: 'The amount cannot be negative.')]
    public ?string $amount = '0.00';

    #[Assert\NotNull(message: 'Please provide the VAT amount.')]
    #[Assert\PositiveOrZero(message: 'The VAT amount cannot be negative.')]
    public ?string $vatAmount = '0.00';

    /**
     * Derived, never submitted: the form field is disabled and the value is
     * recomputed here and again in the entity before the row is written.
     */
    public function getTotalWithVat(): string
    {
        return bcadd($this->amount ?? '0.00', $this->vatAmount ?? '0.00', self::SCALE);
    }
}
