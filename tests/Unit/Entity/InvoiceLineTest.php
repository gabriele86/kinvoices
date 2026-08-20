<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceLine::class)]
class InvoiceLineTest extends TestCase
{
    public function testANewLineStartsEmptyButConsistent(): void
    {
        $line = new InvoiceLine();

        self::assertNull($line->getId());
        self::assertSame(1, $line->getQuantity());
        self::assertSame('0.00', $line->getAmount());
        self::assertSame('0.00', $line->getVatAmount());
        self::assertSame('0.00', $line->getTotalWithVat());
    }

    public function testTotalIsRecomputedWhenTheAmountChanges(): void
    {
        $line = new InvoiceLine();
        $line->setAmount('100.00');

        self::assertSame('100.00', $line->getTotalWithVat());

        $line->setVatAmount('22.00');

        self::assertSame('122.00', $line->getTotalWithVat());
    }

    #[DataProvider('amountProvider')]
    public function testTotalWithVatIsTheSumOfAmountAndVat(string $amount, string $vat, string $expected): void
    {
        $line = (new InvoiceLine())
            ->setAmount($amount)
            ->setVatAmount($vat);

        self::assertSame($expected, $line->getTotalWithVat());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function amountProvider(): array
    {
        return [
            'whole numbers' => ['1500.00', '330.00', '1830.00'],
            'cents' => ['89.90', '19.78', '109.68'],
            'reduced rate' => ['240.50', '24.05', '264.55'],
            'zero vat' => ['50.00', '0.00', '50.00'],
            // Values that a float based sum would get wrong (0.1 + 0.2).
            'no float drift' => ['0.10', '0.20', '0.30'],
            'large amount' => ['9999999.99', '0.01', '10000000.00'],
        ];
    }

    public function testNullValuesAreTreatedAsZero(): void
    {
        $line = (new InvoiceLine())
            ->setAmount(null)
            ->setVatAmount(null);

        self::assertSame('0.00', $line->getTotalWithVat());
    }

    public function testTotalIsRecomputedBeforeTheRowIsWritten(): void
    {
        $line = new InvoiceLine();

        // Simulates a value set by Doctrine hydration, bypassing the setters.
        $property = new \ReflectionProperty(InvoiceLine::class, 'amount');
        $property->setValue($line, '10.00');

        self::assertSame('0.00', $line->getTotalWithVat(), 'stale before the callback runs');

        $line->recalculateTotalWithVat();

        self::assertSame('10.00', $line->getTotalWithVat());
    }

    public function testLineKnowsItsInvoice(): void
    {
        $invoice = new Invoice();
        $line = new InvoiceLine();

        $line->setInvoice($invoice);

        self::assertSame($invoice, $line->getInvoice());
    }
}
