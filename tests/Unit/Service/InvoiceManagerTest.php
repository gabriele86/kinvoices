<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\InvoiceDto;
use App\Dto\InvoiceLineDto;
use App\Entity\Invoice;
use App\Exception\InvoiceNotFoundException;
use App\Mapper\InvoiceMapper;
use App\Mapper\InvoiceMapperInterface;
use App\Repository\InvoiceRepositoryInterface;
use App\Service\InvoiceManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * The service in isolation: the repository, the mapper and the entity manager
 * are test doubles of their interfaces, so no database is involved.
 */
#[CoversClass(InvoiceManager::class)]
class InvoiceManagerTest extends TestCase
{
    public function testGetInvoicesReturnsMappedDtos(): void
    {
        $entities = [new Invoice()];
        $dtos = [new InvoiceDto()];

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects(self::once())->method('findAllWithLines')->willReturn($entities);

        $mapper = $this->createMock(InvoiceMapperInterface::class);
        $mapper->expects(self::once())->method('toDtoList')->with($entities)->willReturn($dtos);

        $manager = $this->manager($repository, $mapper);

        self::assertSame($dtos, $manager->getInvoices());
    }

    public function testGetInvoiceMapsTheStoredEntity(): void
    {
        $invoice = new Invoice();
        $dto = new InvoiceDto();

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with(7)->willReturn($invoice);

        $mapper = $this->createMock(InvoiceMapperInterface::class);
        $mapper->expects(self::once())->method('toDto')->with($invoice)->willReturn($dto);

        self::assertSame($dto, $this->manager($repository, $mapper)->getInvoice(7));
    }

    public function testGetInvoiceThrowsWhenTheInvoiceIsMissing(): void
    {
        $repository = $this->createStub(InvoiceRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(InvoiceNotFoundException::class);
        $this->expectExceptionMessage('Invoice "404" does not exist.');

        $this->manager($repository)->getInvoice(404);
    }

    public function testTheDraftIsNumberedAndHasOneEmptyLine(): void
    {
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects(self::once())->method('findNextInvoiceNumber')->willReturn(1004);

        $draft = $this->manager($repository)->createDraft();

        self::assertNull($draft->id, 'a draft is not persisted');
        self::assertSame(1004, $draft->invoiceNumber);
        self::assertCount(1, $draft->lines);
        self::assertInstanceOf(InvoiceLineDto::class, $draft->lines[0]);
    }

    public function testCreateMapsPersistsAndFlushesOnce(): void
    {
        $dto = new InvoiceDto();
        $entity = new Invoice();
        $saved = new InvoiceDto();

        $mapper = $this->createMock(InvoiceMapperInterface::class);
        $mapper->expects(self::once())->method('toEntity')->with($dto)->willReturn($entity);
        $mapper->expects(self::once())->method('toDto')->with($entity)->willReturn($saved);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($entity);
        $entityManager->expects(self::once())->method('flush');

        $manager = $this->manager($this->createStub(InvoiceRepositoryInterface::class), $mapper, $entityManager);

        self::assertSame($saved, $manager->create($dto));
    }

    public function testUpdateAppliesTheDtoOnTheStoredEntityWithoutPersisting(): void
    {
        $dto = new InvoiceDto();
        $dto->id = 5;
        $entity = new Invoice();

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects(self::once())->method('findById')->with(5)->willReturn($entity);

        $mapper = $this->createMock(InvoiceMapperInterface::class);
        $mapper->expects(self::once())->method('applyToEntity')->with($dto, $entity)->willReturn($entity);
        $mapper->expects(self::once())->method('toDto')->with($entity)->willReturn($dto);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        self::assertSame($dto, $this->manager($repository, $mapper, $entityManager)->update($dto));
    }

    public function testUpdatingADtoWithoutIdIsRejected(): void
    {
        $repository = $this->createStub(InvoiceRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(InvoiceNotFoundException::class);

        $this->manager($repository)->update(new InvoiceDto());
    }

    public function testDeleteRemovesTheStoredEntity(): void
    {
        $entity = new Invoice();

        $repository = $this->createStub(InvoiceRepositoryInterface::class);
        $repository->method('findById')->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($entity);
        $entityManager->expects(self::once())->method('flush');

        $this->manager($repository, null, $entityManager)->delete(5);
    }

    public function testDeletingAnUnknownInvoiceThrows(): void
    {
        $repository = $this->createStub(InvoiceRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(InvoiceNotFoundException::class);

        $this->manager($repository)->delete(404);
    }

    public function testNextInvoiceNumberComesFromTheRepository(): void
    {
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects(self::once())->method('findNextInvoiceNumber')->willReturn(99);

        self::assertSame(99, $this->manager($repository)->nextInvoiceNumber());
    }

    private function manager(
        InvoiceRepositoryInterface|MockObject|Stub $repository,
        InvoiceMapperInterface|MockObject|Stub|null $mapper = null,
        EntityManagerInterface|MockObject|Stub|null $entityManager = null,
    ): InvoiceManager {
        return new InvoiceManager(
            $repository,
            $mapper ?? new InvoiceMapper(),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
        );
    }
}
