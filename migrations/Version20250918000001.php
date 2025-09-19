<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table audit_logs pour le système d'audit trail
 */
final class Version20250918000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table audit_logs pour le système d\'audit trail et conformité RGPD';
    }

    public function up(Schema $schema): void
    {
        // Créer la table audit_logs
        $this->addSql('CREATE TABLE audit_logs (
            id INT AUTO_INCREMENT NOT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) NOT NULL,
            entity_id VARCHAR(100) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            user_name VARCHAR(255) DEFAULT NULL,
            old_data JSON DEFAULT NULL,
            new_data JSON DEFAULT NULL,
            changed_fields JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            route VARCHAR(255) DEFAULT NULL,
            http_method VARCHAR(10) NOT NULL,
            description TEXT DEFAULT NULL,
            severity VARCHAR(50) NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            session_id VARCHAR(100) DEFAULT NULL,
            gdpr_data JSON DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Créer les index pour optimiser les performances
        $this->addSql('CREATE INDEX idx_audit_action ON audit_logs (action)');
        $this->addSql('CREATE INDEX idx_audit_entity_type ON audit_logs (entity_type)');
        $this->addSql('CREATE INDEX idx_audit_entity_id ON audit_logs (entity_id)');
        $this->addSql('CREATE INDEX idx_audit_user_id ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_audit_created_at ON audit_logs (created_at)');
        $this->addSql('CREATE INDEX idx_audit_ip ON audit_logs (ip_address)');
        $this->addSql('CREATE INDEX idx_audit_severity ON audit_logs (severity)');
        $this->addSql('CREATE INDEX idx_audit_session ON audit_logs (session_id)');
        
        // Index composite pour les requêtes fréquentes
        $this->addSql('CREATE INDEX idx_audit_entity_composite ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_user_date ON audit_logs (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_action_date ON audit_logs (action, created_at)');
    }

    public function down(Schema $schema): void
    {
        // Supprimer les index d'abord
        $this->addSql('DROP INDEX idx_audit_action ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_entity_type ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_entity_id ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_user_id ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_created_at ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_ip ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_severity ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_session ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_entity_composite ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_user_date ON audit_logs');
        $this->addSql('DROP INDEX idx_audit_action_date ON audit_logs');
        
        // Supprimer la table
        $this->addSql('DROP TABLE audit_logs');
    }
}
