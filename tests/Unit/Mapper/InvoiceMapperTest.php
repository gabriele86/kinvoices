<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mapper;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Mapper\InvoiceMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceMapper::class)]
class InvoiceMapperTest extends TestCase
{
    private InvoiceMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new InvoiceMapper();
    }

    public function testAnEntityIsMappedToItsDto(): void
    {
        $invoice = $this->persistedInvoice(3, 1001, [
            [10, 'Consulting', '1500.00', '330.00'],
            [11, 'Hosting', '89.90', '19.78'],
        ]);

        $dto = $this->mapper->toDto($invoice);

        self::assertSame(3, $dto->id);
        self::assertSame(1001, $dto->invoiceNumber);
        self::assertSame(42, $dto->customerId);
        self::assertSame('2026-08-20', $dto->invoiceDate->format('Y-m-d'));

        self::assertCount(2, $dto->lines);
        self::assertSame(10, $dto->lines[0]->id);
        self::assertSame('Consulting', $dto->lines[0]->description);
        self::assertSame('1500.00', $dto->lines[0]->amount);
        self::assertSame('330.00', $dto->lines[0]->vatAmount);
        self::assertSame('1830.00', $dto->lines[0]->getTotalWithVat());
        self::assertSame('1939.68', $dto->getTotalWithVat());
    }

    public function testAListOfEntitiesIsMapped(): void
    {
        $dtos = $this->mapper->toDtoList([
            $this->persistedInvoice(1, 1001, [[10, 'A', '10.00', '2.20']]),
            $this->persistedInvoice(2, 1002, [[11, 'B', '20.00', '4.40']]),
        ]);

        self::assertCount(2, $dtos);
        self::assertContainsOnlyInstancesOf(InvoiceDto::class, $dtos);
        self::assertSame([1001, 1002], array_map(static fn (InvoiceDto $d): int => $d->invoiceNumber, $dtos));
    }

    public function testANewEntityIsBuiltFromADto(): void
    {
        $dto = new InvoiceDto();
        $dto->invoiceNumber = 1001;
        $dto->customerId = 42;
        $dto->invoiceDate = new \DateTimeImmutable('2026-08-20');
        $dto->addLine($this->lineDto(null, 'Consulting', 2, '1500.00', '330.00'));

        $invoice = $this->mapper->toEntity($dto);

        self::assertNull($invoice->getId());
        self::assertSame(1001, $invoice->getInvoiceNumber());
        self::assertCount(1, $invoice->getLines());

        $line = $invoice->getLines()->first();
        self::assertSame('Consulting', $line->getDescription());
        self::assertSame(2, $line->getQuantity());
        self::assertSame('1830.00', $line->getTotalWithVat(), 'the entity recomputes its own total');
        self::assertSame($invoice, $line->getInvoice(), 'the owning side is set');
    }

    public function testTheSubmittedTotalIsNeverCopiedFromTheDto(): void
    {
        $dto = new InvoiceDto();
        $dto->invoiceNumber = 1001;
        $dto->customerId = 42;
        $dto->addLine($this->lineDto(null, 'Consulting', 1, '100.00', '22.00'));

        $line = $this->mapper->toEntity($dto)->getLines()->first();

        self::assertSame('122.00', $line->getTotalWithVat());
    }

    public function testAKnownLineIsUpdatedInPlace(): void
    {
        $invoice = $this->persistedInvoice(3, 1001, [[10, 'Consulting', '1500.00', '330.00']]);
        $original = $invoice->getLines()->first();

        $dto = $this->mapper->toDto($invoice);
        $dto->lines[0]->description = 'Consulting (revised)';
        $dto->lines[0]->amount = '1800.00';
        $dto->lines[0]->vatAmount = '396.00';

        $this->mapper->applyToEntity($dto, $invoice);

        self::assertCount(1, $invoice->getLines());
        self::assertSame($original, $invoice->getLines()->first(), 'the same row is reused, not replaced');
        self::assertSame('Consulting (revised)', $original->getDescription());
        self::assertSame('2196.00', $original->getTotalWithVat());
    }

    public function testALineWithoutIdIsAdded(): void
    {
        $invoice = $this->persistedInvoice(3, 1001, [[10, 'Consulting', '1500.00', '330.00']]);

        $dto = $this->mapper->toDto($invoice);
        $dto->addLine($this->lineDto(null, 'Brand new', 1, '100.00', '22.00'));

        $this->mapper->applyToEntity($dto, $invoice);

        self::assertCount(2, $invoice->getLines());
        self::assertSame('1952.00', $invoice->getTotalWithVat());
    }

    public function testALineMissingFromTheDtoIsDetached(): void
    {
        $invoice = $this->persistedInvoice(3, 1001, [
            [10, 'Consulting', '1500.00', '330.00'],
            [11, 'Hosting', '89.90', '19.78'],
        ]);
        $removed = $invoice->getLines()->last();

        $dto = $this->mapper->toDto($invoice);
        unset($dto->lines[1]);

        $this->mapper->applyToEntity($dto, $invoice);

        self::assertCount(1, $invoice->getLines());
        self::assertNull($removed->getInvoice(), 'the orphan is detached so orphanRemoval deletes it');
    }

    public function testHeaderFieldsAreCopiedOnUpdate(): void
    {
        $invoice = $this->persistedInvoice(3, 1001, [[10, 'Consulting', '1500.00', '330.00']]);

        $dto = $this->mapper->toDto($invoice);
        $dto->invoiceNumber = 2002;
        $dto->customerId = 77;
        $dto->invoiceDate = new \DateTimeImmutable('2027-01-15');

        $this->mapper->applyToEntity($dto, $invoice);

        self::assertSame(2002, $invoice->getInvoiceNumber());
        self::assertSame(77, $invoice->getCustomerId());
        self::assertSame('2027-01-15', $invoice->getInvoiceDate()->format('Y-m-d'));
    }

    public function testAnUnknownLineIdIsTreatedAsANewLine(): void
    {
        $invoice = $this->persistedInvoice(3, 1001, [[10, 'Consulting', '1500.00', '330.00']]);

        $dto = $this->mapper->toDto($invoice);
        // A line id that does not belong to this invoice must not be trusted.
        $dto->addLine($this->lineDto(9999, 'Injected', 1, '1.00', '0.00'));

        $this->mapper->applyToEntity($dto, $invoice);

        self::assertCount(2, $invoice->getLines());
        self::assertNull($invoice->getLines()->last()->getId(), 'it is inserted, not silently reused');
    }

    /**
     * @param list<array{int, string, string, string}> $lines id, description, amount, VAT
     */
    private function persistedInvoice(int $id, int $number, array $lines): Invoice
    {
        $invoice = (new Invoice())
            ->setInvoiceNumber($number)
            ->setCustomerId(42)
            ->setInvoiceDate(new \DateTimeImmutable('2026-08-20'));

        $this->setId($invoice, $id);

        foreach ($lines as [$lineId, $description, $amount, $vat]) {
            $line = (new InvoiceLine())
                ->setDescription($description)
                ->setQuantity(1)
                ->setAmount($amount)
                ->setVatAmount($vat);

            $this->setId($line, $lineId);
            $invoice->addLine($line);
        }

        return $invoice;
    }

    private function lineDto(?int $id, string $description, int $quantity, string $amount, string $vat): InvoiceLineDto
    {
        $line = new InvoiceLineDto();
        $line->id = $id;
        $line->description = $description;
        $line->quantity = $quantity;
        $line->amount = $amount;
        $line->vatAmount = $vat;

        return $line;
    }

    /**
     * Doctrine assigns the identifier on flush; here it is set by reflection so
     * the mapper can be tested without a database.
     */
    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity::class, 'id');
        $property->setValue($entity, $id);
    }
}
