<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\InvoiceDto;
use App\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueInvoiceNumberValidator extends ConstraintValidator
{
    public function __construct(private readonly InvoiceRepositoryInterface $invoices)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueInvoiceNumber) {
            throw new UnexpectedTypeException($constraint, UniqueInvoiceNumber::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof InvoiceDto) {
            throw new UnexpectedValueException($value, InvoiceDto::class);
        }

        if (null === $value->invoiceNumber) {
            return; // NotNull already reports it.
        }

        $existing = $this->invoices->findOneByInvoiceNumber($value->invoiceNumber);

        if (null === $existing || $existing->getId() === $value->id) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('invoiceNumber')
            ->addViolation();
    }
}
