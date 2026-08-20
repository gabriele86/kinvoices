<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceDto::class)]
#[CoversClass(InvoiceLineDto::class)]
class InvoiceDtoTest extends TestCase
{
    public function testANewDraftIsDatedTodayAndHasNoLines(): void
    {
        $dto = new InvoiceDto();

        self::assertNull($dto->id);
        self::assertSame([], $dto->lines);
        self::assertSame(
            (new \DateTimeImmutable())->format('Y-m-d'),
            $dto->invoiceDate->format('Y-m-d')
        );
    }

    #[DataProvider('lineProvider')]
    public function testTheLineTotalIsAmountPlusVat(?string $amount, ?string $vat, string $expected): void
    {
        $line = new InvoiceLineDto();
        $line->amount = $amount;
        $line->vatAmount = $vat;

        self::assertSame($expected, $line->getTotalWithVat());
    }

    /**
     * @return array<string, array{?string, ?string, string}>
     */
    public static function lineProvider(): array
    {
        return [
            'standard rate' => ['1500.00', '330.00', '1830.00'],
            'cents' => ['89.90', '19.78', '109.68'],
            'no vat' => ['50.00', '0.00', '50.00'],
            'no float drift' => ['0.10', '0.20', '0.30'],
            'nulls count as zero' => [null, null, '0.00'],
        ];
    }

    public function testInvoiceTotalsAddUpTheLines(): void
    {
        $dto = new InvoiceDto();
        $dto->addLine($this->line('1500.00', '330.00'));
        $dto->addLine($this->line('89.90', '19.78'));
        $dto->addLine($this->line('240.50', '24.05'));

        self::assertSame('1830.40', $dto->getTotalAmount());
        self::assertSame('373.83', $dto->getTotalVatAmount());
        self::assertSame('2204.23', $dto->getTotalWithVat());
    }

    public function testTotalsOfADraftAreZero(): void
    {
        $dto = new InvoiceDto();

        self::assertSame('0.00', $dto->getTotalAmount());
        self::assertSame('0.00', $dto->getTotalVatAmount());
        self::assertSame('0.00', $dto->getTotalWithVat());
    }

    public function testAddLineIsChainable(): void
    {
        $dto = (new InvoiceDto())
            ->addLine($this->line('1.00', '0.22'))
            ->addLine($this->line('2.00', '0.44'));

        self::assertCount(2, $dto->lines);
        self::assertSame('3.66', $dto->getTotalWithVat());
    }

    private function line(string $amount, string $vat): InvoiceLineDto
    {
        $line = new InvoiceLineDto();
        $line->description = 'Line';
        $line->quantity = 1;
        $line->amount = $amount;
        $line->vatAmount = $vat;

        return $line;
    }
}
