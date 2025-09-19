<?php

namespace App\Infrastructure\EventSourcing\Query;

use Doctrine\DBAL\Connection;

/**
 * Interface pour toutes les requêtes
 */
interface QueryInterface
{
    /**
     * Exécute la requête
     */
    public function execute(Connection $connection): mixed;
}
