<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Exception\InvoiceNotFoundException;
use App\Service\InvoiceManager;
use App\Service\InvoiceManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The service wired to the real container and the test database: mapping,
 * cascade and orphan removal are exactly what a mock cannot prove.
 */
#[CoversClass(InvoiceManager::class)]
class InvoiceManagerPersistenceTest extends KernelTestCase
{
    use DatabaseTrait;

    private InvoiceManagerInterface $manager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->manager = static::getContainer()->get(InvoiceManagerInterface::class);
        $this->clearDatabase();
    }

    public function testTheContainerInjectsTheInterface(): void
    {
        self::assertInstanceOf(InvoiceManager::class, $this->manager);
    }

    public function testCreatingAnInvoiceCascadesToItsLines(): void
    {
        $created = $this->manager->create($this->dto(1001, [
            ['Consulting', '1500.00', '330.00'],
            ['Hosting', '89.90', '19.78'],
        ]));

        self::assertNotNull($created->id);
        self::assertCount(2, $created->lines);
        self::assertNotNull($created->lines[0]->id, 'the returned DTO carries the generated ids');

        $reloaded = $this->manager->getInvoice($created->id);

        self::assertCount(2, $reloaded->lines);
        self::assertSame('1589.90', $reloaded->getTotalAmount());
        self::assertSame('349.78', $reloaded->getTotalVatAmount());
        self::assertSame('1939.68', $reloaded->getTotalWithVat());
    }

    public function testTotalWithVatIsStoredInTheDatabase(): void
    {
        $created = $this->manager->create($this->dto(1001, [['Consulting', '1500.00', '330.00']]));

        $stored = $this->connection()
            ->fetchOne('SELECT total_with_vat FROM invoice_line WHERE invoice_id = ?', [$created->id]);

        self::assertSame('1830.00', $stored);
    }

    public function testUpdatingKeepsTheLineRowAndItsId(): void
    {
        $created = $this->manager->create($this->dto(1001, [['Consulting', '1500.00', '330.00']]));
        $lineId = $created->lines[0]->id;

        $created->lines[0]->description = 'Consulting (revised)';
        $created->lines[0]->amount = '1800.00';
        $created->lines[0]->vatAmount = '396.00';
        $this->manager->update($created);

        $reloaded = $this->manager->getInvoice($created->id);

        self::assertCount(1, $reloaded->lines);
        self::assertSame($lineId, $reloaded->lines[0]->id, 'the row is updated, not replaced');
        self::assertSame('Consulting (revised)', $reloaded->lines[0]->description);
        self::assertSame('2196.00', $reloaded->getTotalWithVat());
    }

    public function testRemovingALineDeletesTheRow(): void
    {
        $created = $this->manager->create($this->dto(1001, [
            ['Consulting', '1500.00', '330.00'],
            ['Hosting', '89.90', '19.78'],
        ]));

        unset($created->lines[1]);
        $this->manager->update($created);

        $rows = $this->connection()
            ->fetchOne('SELECT COUNT(*) FROM invoice_line WHERE invoice_id = ?', [$created->id]);

        self::assertSame(1, (int) $rows, 'orphanRemoval must delete the detached line');
    }

    public function testAddingALineInsertsANewRow(): void
    {
        $created = $this->manager->create($this->dto(1001, [['Consulting', '1500.00', '330.00']]));

        $created->addLine($this->lineDto('Training day', '600.00', '132.00'));
        $updated = $this->manager->update($created);

        self::assertCount(2, $updated->lines);
        self::assertSame('2562.00', $updated->getTotalWithVat());
    }

    public function testDeletingAnInvoiceDeletesItsLines(): void
    {
        $created = $this->manager->create($this->dto(1001, [['Consulting', '1500.00', '330.00']]));

        $this->manager->delete($created->id);

        self::assertSame(
            0,
            (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM invoice_line WHERE invoice_id = ?', [$created->id])
        );

        $this->expectException(InvoiceNotFoundException::class);
        $this->manager->getInvoice($created->id);
    }

    public function testTheFirstInvoiceNumberIsOne(): void
    {
        self::assertSame(1, $this->manager->nextInvoiceNumber());
    }

    public function testTheNextInvoiceNumberFollowsTheHighestOne(): void
    {
        $this->manager->create($this->dto(1001, [['A', '10.00', '2.20']]));
        $this->manager->create($this->dto(1007, [['B', '10.00', '2.20']]));

        self::assertSame(1008, $this->manager->nextInvoiceNumber());
    }

    public function testTheDraftUsesTheNextFreeNumber(): void
    {
        $this->manager->create($this->dto(1001, [['A', '10.00', '2.20']]));

        $draft = $this->manager->createDraft();

        self::assertSame(1002, $draft->invoiceNumber);
        self::assertCount(1, $draft->lines);
        self::assertNull($draft->id);
    }

    public function testInvoicesAreListedMostRecentFirst(): void
    {
        $this->manager->create($this->dto(1001, [['A', '10.00', '2.20']], '2026-01-31'));
        $this->manager->create($this->dto(1002, [['B', '10.00', '2.20']], '2026-03-31'));
        $this->manager->create($this->dto(1003, [['C', '10.00', '2.20']], '2026-02-28'));

        $numbers = array_map(
            static fn (InvoiceDto $invoice): int => $invoice->invoiceNumber,
            $this->manager->getInvoices()
        );

        self::assertSame([1002, 1003, 1001], $numbers);
    }

    public function testTheListCarriesTheLinesOfEachInvoice(): void
    {
        $this->manager->create($this->dto(1001, [['A', '10.00', '2.20'], ['B', '20.00', '4.40']]));

        $invoices = $this->manager->getInvoices();

        self::assertCount(1, $invoices);
        self::assertCount(2, $invoices[0]->lines);
        self::assertSame('36.60', $invoices[0]->getTotalWithVat());
    }

    /**
     * @param list<array{string, string, string}> $lines description, amount, VAT amount
     */
    private function dto(int $number, array $lines, string $date = '2026-08-20'): InvoiceDto
    {
        $dto = new InvoiceDto();
        $dto->invoiceNumber = $number;
        $dto->customerId = 42;
        $dto->invoiceDate = new \DateTimeImmutable($date);

        foreach ($lines as [$description, $amount, $vat]) {
            $dto->addLine($this->lineDto($description, $amount, $vat));
        }

        return $dto;
    }

    private function lineDto(string $description, string $amount, string $vat): InvoiceLineDto
    {
        $line = new InvoiceLineDto();
        $line->description = $description;
        $line->quantity = 1;
        $line->amount = $amount;
        $line->vatAmount = $vat;

        return $line;
    }
}
