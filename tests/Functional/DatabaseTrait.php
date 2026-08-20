<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Helpers shared by the tests that hit the real (test) database.
 */
trait DatabaseTrait
{
    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    /**
     * Empties the tables so each test starts from a known state.
     */
    private function clearDatabase(): void
    {
        $connection = $this->connection();

        $connection->executeStatement('DELETE FROM invoice_line');
        $connection->executeStatement('DELETE FROM invoice');
        $connection->executeStatement('ALTER TABLE invoice AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE invoice_line AUTO_INCREMENT = 1');

        $this->entityManager()->clear();
    }
}
