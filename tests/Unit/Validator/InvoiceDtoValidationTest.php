<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Entity\Invoice;
use App\Repository\InvoiceRepositoryInterface;
use App\Validator\UniqueInvoiceNumber;
use App\Validator\UniqueInvoiceNumberValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The constraints declared on the DTOs, exercised through a real validator:
 * this covers both the built-in rules and the custom UniqueInvoiceNumber one.
 */
#[CoversClass(UniqueInvoiceNumberValidator::class)]
#[CoversClass(UniqueInvoiceNumber::class)]
#[CoversClass(InvoiceDto::class)]
#[CoversClass(InvoiceLineDto::class)]
class InvoiceDtoValidationTest extends TestCase
{
    public function testAValidInvoicePassesEveryConstraint(): void
    {
        self::assertSame([], $this->violations($this->validInvoice()));
    }

    public function testTheHeaderFieldsAreRequired(): void
    {
        $dto = $this->validInvoice();
        $dto->invoiceDate = null;
        $dto->invoiceNumber = null;
        $dto->customerId = null;

        self::assertSame([
            'invoiceDate' => 'Please provide the invoice date.',
            'invoiceNumber' => 'Please provide the invoice number.',
            'customerId' => 'Please provide the customer id.',
        ], $this->violations($dto));
    }

    public function testTheNumbersMustBePositive(): void
    {
        $dto = $this->validInvoice();
        $dto->invoiceNumber = 0;
        $dto->customerId = -1;

        self::assertSame([
            'invoiceNumber' => 'The invoice number must be greater than zero.',
            'customerId' => 'The customer id must be greater than zero.',
        ], $this->violations($dto));
    }

    public function testAnInvoiceNeedsAtLeastOneLine(): void
    {
        $dto = $this->validInvoice();
        $dto->lines = [];

        self::assertSame(['lines' => 'An invoice must contain at least one line.'], $this->violations($dto));
    }

    public function testTheLinesAreValidatedToo(): void
    {
        $dto = $this->validInvoice();
        $dto->lines[0]->description = '';
        $dto->lines[0]->quantity = 0;
        $dto->lines[0]->amount = '-5.00';

        self::assertSame([
            'lines[0].description' => 'Please provide a description.',
            'lines[0].quantity' => 'The quantity must be greater than zero.',
            'lines[0].amount' => 'The amount cannot be negative.',
        ], $this->violations($dto));
    }

    public function testANumberAlreadyUsedByAnotherInvoiceIsRejected(): void
    {
        $dto = $this->validInvoice();

        self::assertSame(
            ['invoiceNumber' => 'An invoice with this number already exists.'],
            $this->violations($dto, storedInvoiceId: 7)
        );
    }

    public function testAnInvoiceKeepingItsOwnNumberIsAccepted(): void
    {
        $dto = $this->validInvoice();
        $dto->id = 7;

        self::assertSame([], $this->violations($dto, storedInvoiceId: 7));
    }

    private function validInvoice(): InvoiceDto
    {
        $line = new InvoiceLineDto();
        $line->description = 'Consulting services';
        $line->quantity = 1;
        $line->amount = '1500.00';
        $line->vatAmount = '330.00';

        $dto = new InvoiceDto();
        $dto->invoiceNumber = 1001;
        $dto->customerId = 42;
        $dto->invoiceDate = new \DateTimeImmutable('2026-08-20');
        $dto->addLine($line);

        return $dto;
    }

    /**
     * @return array<string, string> property path => first message
     */
    private function violations(InvoiceDto $dto, ?int $storedInvoiceId = null): array
    {
        $violations = [];

        foreach ($this->validator($storedInvoiceId)->validate($dto) as $violation) {
            $violations[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        return $violations;
    }

    /**
     * A real validator, with the custom constraint wired to a repository that
     * reports the given invoice as the holder of any number looked up.
     */
    private function validator(?int $storedInvoiceId): ValidatorInterface
    {
        $repository = $this->createStub(InvoiceRepositoryInterface::class);
        $repository->method('findOneByInvoiceNumber')->willReturn(
            null === $storedInvoiceId ? null : $this->storedInvoice($storedInvoiceId)
        );

        $validator = new UniqueInvoiceNumberValidator($repository);

        return Validation::createValidatorBuilder()
            // true = attributes only, no Doctrine annotation reader needed
            ->enableAnnotationMapping(true)
            ->setConstraintValidatorFactory(new class($validator) implements ConstraintValidatorFactoryInterface {
                /** @var array<string, ConstraintValidatorInterface> */
                private array $validators = [];

                public function __construct(private readonly UniqueInvoiceNumberValidator $custom)
                {
                }

                public function getInstance(Constraint $constraint): ConstraintValidatorInterface
                {
                    $class = $constraint->validatedBy();

                    if (UniqueInvoiceNumberValidator::class === $class) {
                        return $this->custom;
                    }

                    return $this->validators[$class] ??= new $class();
                }
            })
            ->getValidator();
    }

    private function storedInvoice(int $id): Invoice
    {
        $invoice = new Invoice();

        $property = new \ReflectionProperty(Invoice::class, 'id');
        $property->setValue($invoice, $id);

        return $invoice;
    }
}
