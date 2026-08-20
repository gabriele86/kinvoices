<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that no other invoice already uses the number carried by an InvoiceDto.
 *
 * UniqueEntity cannot be used here: it validates a Doctrine entity, while the
 * form binds a DTO. The identifier on the DTO is what lets the check ignore the
 * invoice currently being edited.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueInvoiceNumber extends Constraint
{
    public string $message = 'An invoice with this number already exists.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
