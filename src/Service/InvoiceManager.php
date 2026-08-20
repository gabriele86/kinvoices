<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Entity\Invoice;
use App\Exception\InvoiceNotFoundException;
use App\Mapper\InvoiceMapperInterface;
use App\Repository\InvoiceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for invoices.
 *
 * It is the only layer that sees the Doctrine entities: everything it hands out
 * and accepts is a DTO, so controllers, forms and templates never touch the
 * persistence model. It also owns the flush boundary.
 */
class InvoiceManager implements InvoiceManagerInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $repository,
        private readonly InvoiceMapperInterface $mapper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return InvoiceDto[]
     */
    public function getInvoices(): array
    {
        return $this->mapper->toDtoList($this->repository->findAllWithLines());
    }

    /**
     * @throws InvoiceNotFoundException if no invoice has that id
     */
    public function getInvoice(int $id): InvoiceDto
    {
        return $this->mapper->toDto($this->findEntity($id));
    }

    /**
     * A brand new invoice, pre-filled the way the create form expects it: the
     * next free number and one empty line.
     */
    public function createDraft(): InvoiceDto
    {
        $dto = new InvoiceDto();
        $dto->invoiceNumber = $this->nextInvoiceNumber();
        $dto->addLine(new InvoiceLineDto());

        return $dto;
    }

    public function nextInvoiceNumber(): int
    {
        return $this->repository->findNextInvoiceNumber();
    }

    /**
     * Persists a new invoice together with its lines (cascade persist).
     */
    public function create(InvoiceDto $dto): InvoiceDto
    {
        $invoice = $this->mapper->toEntity($dto);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $this->mapper->toDto($invoice);
    }

    /**
     * Applies the DTO to the stored invoice. Lines missing from the DTO are
     * deleted through orphanRemoval, new ones are inserted.
     *
     * @throws InvoiceNotFoundException if the DTO does not point at a stored invoice
     */
    public function update(InvoiceDto $dto): InvoiceDto
    {
        $invoice = $this->findEntity($dto->id ?? 0);

        $this->mapper->applyToEntity($dto, $invoice);
        $this->entityManager->flush();

        return $this->mapper->toDto($invoice);
    }

    /**
     * @throws InvoiceNotFoundException if no invoice has that id
     */
    public function delete(int $id): void
    {
        $this->entityManager->remove($this->findEntity($id));
        $this->entityManager->flush();
    }

    /**
     * @throws InvoiceNotFoundException
     */
    private function findEntity(int $id): Invoice
    {
        return $this->repository->findById($id) ?? throw new InvoiceNotFoundException($id);
    }
}
