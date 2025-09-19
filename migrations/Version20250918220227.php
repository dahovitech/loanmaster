<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 * Ajout de la colonne isEnabled à la table language pour corriger le bug Doctrine
 */
final class Version20250918220227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne isEnabled à la table language et synchronisation avec isActive';
    }

    public function up(Schema $schema): void
    {
        // Ajout de la colonne isEnabled avec valeur par défaut true
        $this->addSql('ALTER TABLE language ADD is_enabled TINYINT(1) NOT NULL DEFAULT 1');
        
        // Copie des valeurs de is_active vers is_enabled pour synchroniser
        $this->addSql('UPDATE language SET is_enabled = is_active');
    }

    public function down(Schema $schema): void
    {
        // Suppression de la colonne isEnabled
        $this->addSql('ALTER TABLE language DROP COLUMN is_enabled');
    }
}
