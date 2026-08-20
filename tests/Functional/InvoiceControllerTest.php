<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Controller\InvoiceController;
use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Service\InvoiceManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(InvoiceController::class)]
class InvoiceControllerTest extends WebTestCase
{
    use DatabaseTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->clearDatabase();
    }

    public function testTheListIsEmptyAtFirst(): void
    {
        $crawler = $this->client->request('GET', '/invoices');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Invoices');
        self::assertStringContainsString('No invoice has been created yet', $crawler->filter('main')->text());
    }

    public function testTheHomePageRedirectsToTheList(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/invoices');
    }

    public function testTheCreateFormIsPrefilled(): void
    {
        $crawler = $this->client->request('GET', '/invoices/new');

        self::assertResponseIsSuccessful();
        self::assertSame('1', $crawler->filter('#invoice_invoiceNumber')->attr('value'));
        self::assertCount(1, $crawler->filter('.invoice-line'), 'one empty line is offered');
        self::assertNotEmpty(
            $crawler->filter('#invoice-lines')->attr('data-prototype'),
            'the collection prototype is needed to add lines from the browser'
        );
    }

    public function testAnInvoiceIsCreatedWithSeveralLines(): void
    {
        $this->submitInvoiceForm('/invoices/new', 'Create invoice', [
            'invoiceDate' => '2026-08-20',
            'invoiceNumber' => '1001',
            'customerId' => '42',
        ], [
            ['description' => 'Consulting services', 'quantity' => '10', 'amount' => '1500.00', 'vatAmount' => '330.00'],
            ['description' => 'Server hosting', 'quantity' => '1', 'amount' => '89.90', 'vatAmount' => '19.78'],
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Invoice #1001');
        self::assertSelectorTextContains('.flash-success', 'Invoice #1001 has been created.');

        $invoice = $this->findInvoiceByNumber(1001);

        self::assertCount(2, $invoice->lines);
        self::assertSame(42, $invoice->customerId);
        self::assertSame('2026-08-20', $invoice->invoiceDate->format('Y-m-d'));
        self::assertSame('1939.68', $invoice->getTotalWithVat());
    }

    public function testTotalWithVatIsComputedServerSideAndCannotBeForged(): void
    {
        // The browser sends a tampered total: the server must ignore it.
        $this->submitInvoiceForm('/invoices/new', 'Create invoice', [
            'invoiceDate' => '2026-08-20',
            'invoiceNumber' => '1002',
            'customerId' => '42',
        ], [
            [
                'description' => 'Consulting services',
                'quantity' => '1',
                'amount' => '100.00',
                'vatAmount' => '22.00',
                'totalWithVat' => '1.00',
            ],
        ]);

        self::assertResponseRedirects();

        $invoice = $this->findInvoiceByNumber(1002);

        self::assertSame('122.00', $invoice->lines[0]->getTotalWithVat());
        self::assertSame(
            '122.00',
            $this->connection()->fetchOne('SELECT total_with_vat FROM invoice_line WHERE invoice_id = ?', [$invoice->id]),
            'the stored row is computed server side too'
        );
    }

    public function testAnInvalidInvoiceIsRedisplayedWithItsErrors(): void
    {
        $crawler = $this->submitInvoiceForm('/invoices/new', 'Create invoice', [
            'invoiceDate' => '2026-08-20',
            'invoiceNumber' => '1003',
            'customerId' => '0',
        ], [
            ['description' => '', 'quantity' => '0', 'amount' => '-5.00', 'vatAmount' => '1.00'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200, 'the form is redisplayed instead of redirecting');

        $text = $crawler->filter('form')->text();
        self::assertStringContainsString('Please provide a description.', $text);
        self::assertStringContainsString('The quantity must be greater than zero.', $text);
        self::assertStringContainsString('The amount cannot be negative.', $text);
        self::assertStringContainsString('The customer id must be greater than zero.', $text);

        self::assertSame(0, $this->countInvoices(), 'nothing must be written on a rejected form');
    }

    public function testTheInvoiceNumberMustBeUnique(): void
    {
        $this->persistInvoice(1004);

        $crawler = $this->submitInvoiceForm('/invoices/new', 'Create invoice', [
            'invoiceDate' => '2026-08-20',
            'invoiceNumber' => '1004',
            'customerId' => '42',
        ], [
            ['description' => 'Consulting', 'quantity' => '1', 'amount' => '10.00', 'vatAmount' => '2.20'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'An invoice with this number already exists.',
            $crawler->filter('form')->text()
        );
        self::assertSame(1, $this->countInvoices());
    }

    public function testEditingReplacesTheLines(): void
    {
        $invoice = $this->persistInvoice(1005, 2);
        $id = $invoice->id;
        $keptLineId = $invoice->lines[0]->id;

        // Keep the first line, drop the second one, add a third.
        $this->submitInvoiceForm("/invoices/{$id}/edit", 'Save changes', [
            'invoiceDate' => '2026-09-01',
            'invoiceNumber' => '1005',
            'customerId' => '77',
        ], [
            0 => ['description' => 'Updated line', 'quantity' => '3', 'amount' => '300.00', 'vatAmount' => '66.00'],
            2 => ['description' => 'Brand new line', 'quantity' => '1', 'amount' => '100.00', 'vatAmount' => '22.00'],
        ]);

        self::assertResponseRedirects("/invoices/{$id}");
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Invoice #1005 has been updated.');

        $updated = $this->findInvoiceByNumber(1005);
        $descriptions = array_map(
            static fn (InvoiceLineDto $line): string => $line->description,
            $updated->lines
        );

        self::assertCount(2, $descriptions);
        self::assertContains('Updated line', $descriptions);
        self::assertContains('Brand new line', $descriptions);
        self::assertSame('488.00', $updated->getTotalWithVat());
        self::assertSame(77, $updated->customerId);
        self::assertSame('2026-09-01', $updated->invoiceDate->format('Y-m-d'));
        self::assertSame(
            $keptLineId,
            $updated->lines[array_key_first($updated->lines)]->id,
            'the kept line keeps its identity instead of being deleted and reinserted'
        );
    }

    public function testAnInvoiceCanBeDeleted(): void
    {
        $invoice = $this->persistInvoice(1006);
        $id = $invoice->id;

        $crawler = $this->client->request('GET', "/invoices/{$id}/edit");
        $this->client->submit($crawler->selectButton('Delete invoice')->form());

        self::assertResponseRedirects('/invoices');
        self::assertSame(0, $this->countInvoices());
        self::assertSame(
            0,
            (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM invoice_line'),
            'the lines must go away with the invoice'
        );
    }

    public function testDeletingWithoutAValidCsrfTokenChangesNothing(): void
    {
        $invoice = $this->persistInvoice(1007);

        $this->client->request('POST', "/invoices/{$invoice->id}/delete", ['_token' => 'not-a-token']);

        self::assertResponseRedirects('/invoices');
        self::assertSame(1, $this->countInvoices());
    }

    public function testAnUnknownInvoiceReturnsNotFound(): void
    {
        $this->client->request('GET', '/invoices/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheListShowsTheInvoiceTotals(): void
    {
        $this->persistInvoice(1008, 2);

        $crawler = $this->client->request('GET', '/invoices');

        self::assertResponseIsSuccessful();
        $row = $crawler->filter('tbody tr')->first()->text();

        self::assertStringContainsString('#1008', $row);
        self::assertStringContainsString('244.00', $row, 'gross total of the two lines');
    }

    /**
     * Submits the invoice form, replacing the line collection with the given
     * rows (the keys are the collection indexes, exactly like the browser sends
     * them after lines have been added or removed).
     *
     * @param array<string, string>                $fields
     * @param array<int, array<string, string>>    $lines
     */
    private function submitInvoiceForm(string $url, string $button, array $fields, array $lines): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->client->request('GET', $url);
        $form = $crawler->selectButton($button)->form();

        $values = $form->getPhpValues();
        $values['invoice'] = array_merge($values['invoice'], $fields);
        $values['invoice']['lines'] = $lines;

        return $this->client->request($form->getMethod(), $form->getUri(), $values);
    }

    /**
     * Creates an invoice through the service, the same way the application does.
     */
    private function persistInvoice(int $number, int $lineCount = 1): InvoiceDto
    {
        $dto = new InvoiceDto();
        $dto->invoiceNumber = $number;
        $dto->customerId = 42;
        $dto->invoiceDate = new \DateTimeImmutable('2026-08-20');

        for ($i = 0; $i < $lineCount; ++$i) {
            $line = new InvoiceLineDto();
            $line->description = 'Line '.($i + 1);
            $line->quantity = 1;
            $line->amount = '100.00';
            $line->vatAmount = '22.00';
            $dto->addLine($line);
        }

        return $this->invoiceManager()->create($dto);
    }

    private function findInvoiceByNumber(int $number): InvoiceDto
    {
        $this->entityManager()->clear();

        $id = (int) $this->connection()->fetchOne('SELECT id FROM invoice WHERE invoice_number = ?', [$number]);

        return $this->invoiceManager()->getInvoice($id);
    }

    private function invoiceManager(): InvoiceManagerInterface
    {
        return static::getContainer()->get(InvoiceManagerInterface::class);
    }

    private function countInvoices(): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM invoice');
    }
}
