<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Invoice::class)]
class InvoiceTest extends TestCase
{
    public function testANewInvoiceHasTodaysDateAndNoLines(): void
    {
        $invoice = new Invoice();

        self::assertNull($invoice->getId());
        self::assertCount(0, $invoice->getLines());
        self::assertSame(
            (new \DateTimeImmutable())->format('Y-m-d'),
            $invoice->getInvoiceDate()->format('Y-m-d')
        );
    }

    public function testAddingALineSetsBothSidesOfTheAssociation(): void
    {
        $invoice = new Invoice();
        $line = new InvoiceLine();

        $invoice->addLine($line);

        self::assertCount(1, $invoice->getLines());
        self::assertSame($invoice, $line->getInvoice(), 'the owning side must be updated');
    }

    public function testTheSameLineIsNotAddedTwice(): void
    {
        $invoice = new Invoice();
        $line = new InvoiceLine();

        $invoice->addLine($line);
        $invoice->addLine($line);

        self::assertCount(1, $invoice->getLines());
    }

    public function testRemovingALineDetachesItFromTheInvoice(): void
    {
        $invoice = new Invoice();
        $line = new InvoiceLine();
        $invoice->addLine($line);

        $invoice->removeLine($line);

        self::assertCount(0, $invoice->getLines());
        self::assertNull($line->getInvoice(), 'the orphan must lose its parent so orphanRemoval deletes it');
    }

    public function testTotalsAreTheSumOfTheLines(): void
    {
        $invoice = $this->invoiceWithLines(
            ['1500.00', '330.00'],
            ['89.90', '19.78'],
            ['240.50', '24.05'],
        );

        self::assertSame('1830.40', $invoice->getTotalAmount());
        self::assertSame('373.83', $invoice->getTotalVatAmount());
        self::assertSame('2204.23', $invoice->getTotalWithVat());
    }

    public function testTotalsOfAnEmptyInvoiceAreZero(): void
    {
        $invoice = new Invoice();

        self::assertSame('0.00', $invoice->getTotalAmount());
        self::assertSame('0.00', $invoice->getTotalVatAmount());
        self::assertSame('0.00', $invoice->getTotalWithVat());
    }

    public function testTotalsDoNotDriftOnManySmallAmounts(): void
    {
        $lines = array_fill(0, 10, ['0.10', '0.02']);

        $invoice = $this->invoiceWithLines(...$lines);

        self::assertSame('1.00', $invoice->getTotalAmount());
        self::assertSame('0.20', $invoice->getTotalVatAmount());
        self::assertSame('1.20', $invoice->getTotalWithVat());
    }

    public function testStringRepresentation(): void
    {
        $invoice = (new Invoice())->setInvoiceNumber(1001);

        self::assertSame('Invoice #1001', (string) $invoice);
    }

    /**
     * @param array{string, string} ...$lines amount and VAT amount of each line
     */
    private function invoiceWithLines(array ...$lines): Invoice
    {
        $invoice = new Invoice();

        foreach ($lines as [$amount, $vat]) {
            $invoice->addLine(
                (new InvoiceLine())
                    ->setDescription('Line')
                    ->setQuantity(1)
                    ->setAmount($amount)
                    ->setVatAmount($vat)
            );
        }

        return $invoice;
    }
}
