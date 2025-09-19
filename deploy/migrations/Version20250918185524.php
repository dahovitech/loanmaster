<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table event_store pour l'Event Sourcing
 */
final class Version20250918185524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event_store table for Event Sourcing infrastructure';
    }

    public function up(Schema $schema): void
    {
        // Table principale event_store
        $this->addSql('
            CREATE TABLE event_store (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                aggregate_id VARCHAR(36) NOT NULL,
                event_type VARCHAR(255) NOT NULL,
                event_data JSON NOT NULL,
                version INT NOT NULL,
                occurred_at TIMESTAMP(6) NOT NULL,
                metadata JSON,
                INDEX idx_aggregate_id (aggregate_id),
                INDEX idx_event_type (event_type),
                INDEX idx_occurred_at (occurred_at),
                UNIQUE KEY unique_aggregate_version (aggregate_id, version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Table pour les snapshots (optimisation)
        $this->addSql('
            CREATE TABLE aggregate_snapshots (
                aggregate_id VARCHAR(36) PRIMARY KEY,
                aggregate_type VARCHAR(255) NOT NULL,
                snapshot_data JSON NOT NULL,
                version INT NOT NULL,
                created_at TIMESTAMP(6) NOT NULL,
                INDEX idx_aggregate_type (aggregate_type),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Table pour les projections (vues matérialisées)
        $this->addSql('
            CREATE TABLE loan_projections (
                loan_id VARCHAR(36) PRIMARY KEY,
                customer_id VARCHAR(36) NOT NULL,
                status VARCHAR(50) NOT NULL,
                requested_amount DECIMAL(10,2) NOT NULL,
                approved_amount DECIMAL(10,2) DEFAULT 0,
                current_balance DECIMAL(10,2) DEFAULT 0,
                interest_rate DECIMAL(5,3) DEFAULT 0,
                risk_score INT DEFAULT 0,
                risk_level VARCHAR(20),
                created_at TIMESTAMP(6) NOT NULL,
                updated_at TIMESTAMP(6) NOT NULL,
                funded_at TIMESTAMP(6) NULL,
                completed_at TIMESTAMP(6) NULL,
                INDEX idx_customer_id (customer_id),
                INDEX idx_status (status),
                INDEX idx_risk_level (risk_level),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Table pour l'audit trail complet
        $this->addSql('
            CREATE TABLE audit_trail (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(36) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                old_values JSON,
                new_values JSON,
                user_id VARCHAR(36),
                ip_address VARCHAR(45),
                user_agent TEXT,
                correlation_id VARCHAR(100),
                occurred_at TIMESTAMP(6) NOT NULL,
                context JSON,
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_event_type (event_type),
                INDEX idx_user_id (user_id),
                INDEX idx_occurred_at (occurred_at),
                INDEX idx_correlation_id (correlation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Table pour les métriques de performance
        $this->addSql('
            CREATE TABLE event_store_metrics (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL,
                metric_value DECIMAL(15,4) NOT NULL,
                tags JSON,
                recorded_at TIMESTAMP(6) NOT NULL,
                INDEX idx_metric_name (metric_name),
                INDEX idx_recorded_at (recorded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS event_store_metrics');
        $this->addSql('DROP TABLE IF EXISTS audit_trail');
        $this->addSql('DROP TABLE IF EXISTS loan_projections');
        $this->addSql('DROP TABLE IF EXISTS aggregate_snapshots');
        $this->addSql('DROP TABLE IF EXISTS event_store');
    }
}
