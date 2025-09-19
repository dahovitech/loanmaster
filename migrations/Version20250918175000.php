<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour les modèles d'IA et Machine Learning
 */
final class Version20250918175000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables pour les modèles d\'IA/ML et le scoring automatique';
    }

    public function up(Schema $schema): void
    {
        // Table des modèles de scoring ML
        $this->addSql('CREATE TABLE loan_scoring_models (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            version VARCHAR(50) NOT NULL,
            algorithm VARCHAR(50) NOT NULL,
            performance_metrics LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            model_data LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            feature_importance LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            training_options LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            status VARCHAR(50) NOT NULL DEFAULT "training",
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            deployed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            retired_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            training_samples INT DEFAULT NULL,
            accuracy DOUBLE PRECISION DEFAULT NULL,
            precision_score DOUBLE PRECISION DEFAULT NULL,
            recall_score DOUBLE PRECISION DEFAULT NULL,
            f1_score DOUBLE PRECISION DEFAULT NULL,
            auc DOUBLE PRECISION DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_by VARCHAR(100) DEFAULT NULL,
            validation_results LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            drift_metrics LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            last_used_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            usage_count INT NOT NULL DEFAULT 0,
            UNIQUE INDEX UNIQ_SCORING_MODELS_MODEL_ID (model_id),
            INDEX idx_model_status (status),
            INDEX idx_model_version (version),
            INDEX idx_model_created (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table des prédictions de scoring
        $this->addSql('CREATE TABLE loan_scoring_predictions (
            id INT AUTO_INCREMENT NOT NULL,
            customer_id INT DEFAULT NULL,
            loan_id INT DEFAULT NULL,
            model_id VARCHAR(255) NOT NULL,
            input_features LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            predicted_score INT NOT NULL,
            predicted_risk_level VARCHAR(50) NOT NULL,
            confidence_score DOUBLE PRECISION NOT NULL,
            calculation_method VARCHAR(50) NOT NULL,
            execution_time_ms DOUBLE PRECISION NOT NULL,
            recommendations LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            created_by VARCHAR(100) DEFAULT NULL,
            request_ip VARCHAR(45) DEFAULT NULL,
            api_version VARCHAR(20) DEFAULT NULL,
            INDEX IDX_SCORING_PREDICTIONS_CUSTOMER (customer_id),
            INDEX IDX_SCORING_PREDICTIONS_LOAN (loan_id),
            INDEX idx_predictions_model (model_id),
            INDEX idx_predictions_created (created_at),
            INDEX idx_predictions_risk_level (predicted_risk_level),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table des métriques de performance des modèles
        $this->addSql('CREATE TABLE model_performance_metrics (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            metric_date DATE NOT NULL,
            predictions_count INT NOT NULL DEFAULT 0,
            avg_confidence DOUBLE PRECISION DEFAULT NULL,
            avg_execution_time_ms DOUBLE PRECISION DEFAULT NULL,
            error_rate DOUBLE PRECISION DEFAULT NULL,
            risk_distribution LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            drift_score DOUBLE PRECISION DEFAULT NULL,
            accuracy_degradation DOUBLE PRECISION DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_performance_model (model_id),
            INDEX idx_performance_date (metric_date),
            UNIQUE INDEX UNIQ_PERFORMANCE_MODEL_DATE (model_id, metric_date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table des événements d'entraînement de modèles
        $this->addSql('CREATE TABLE model_training_events (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            status VARCHAR(50) NOT NULL,
            started_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            completed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            error_message TEXT DEFAULT NULL,
            training_samples_count INT DEFAULT NULL,
            validation_samples_count INT DEFAULT NULL,
            created_by VARCHAR(100) DEFAULT NULL,
            INDEX idx_training_model (model_id),
            INDEX idx_training_status (status),
            INDEX idx_training_started (started_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table des alertes de dérive de modèles
        $this->addSql('CREATE TABLE model_drift_alerts (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            drift_type VARCHAR(50) NOT NULL,
            drift_score DOUBLE PRECISION NOT NULL,
            affected_features LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            severity VARCHAR(20) NOT NULL,
            alert_message TEXT NOT NULL,
            detected_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            acknowledged_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            acknowledged_by VARCHAR(100) DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            resolution_notes TEXT DEFAULT NULL,
            INDEX idx_drift_model (model_id),
            INDEX idx_drift_severity (severity),
            INDEX idx_drift_detected (detected_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Ajout des contraintes de clés étrangères
        $this->addSql('ALTER TABLE loan_scoring_predictions 
            ADD CONSTRAINT FK_SCORING_PREDICTIONS_CUSTOMER 
            FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE loan_scoring_predictions 
            ADD CONSTRAINT FK_SCORING_PREDICTIONS_LOAN 
            FOREIGN KEY (loan_id) REFERENCES loan (id) ON DELETE SET NULL');

        // Ajout de colonnes à la table customer pour le scoring
        $this->addSql('ALTER TABLE customer 
            ADD COLUMN IF NOT EXISTS digital_engagement_score INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS average_response_time_to_requests INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS number_of_application_modifications INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS application_completion_time INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS has_opted_for_auto_payment TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS prefers_paperless_communication TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS application_channel VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS communication_preference VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS number_of_support_contacts INT DEFAULT 0,
            ADD COLUMN IF NOT EXISTS employment_type VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS education_level VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS number_of_dependents INT DEFAULT 0,
            ADD COLUMN IF NOT EXISTS housing_status VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS credit_card_limit DECIMAL(10,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS credit_card_balance DECIMAL(10,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS financial_assets_value DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS external_credit_score INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS number_of_credit_inquiries INT DEFAULT 0,
            ADD COLUMN IF NOT EXISTS has_bankruptcy_history TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS has_litigation_history TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS employer_size VARCHAR(20) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS employment_sector VARCHAR(50) DEFAULT NULL');

        // Ajout de colonnes à la table loan pour le scoring
        $this->addSql('ALTER TABLE loan 
            ADD COLUMN IF NOT EXISTS ai_score INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS ai_risk_level VARCHAR(20) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS ai_confidence DOUBLE PRECISION DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS scoring_model_version VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS risk_factors LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL');

        // Index pour améliorer les performances
        $this->addSql('CREATE INDEX idx_customer_ai_score ON customer (external_credit_score)');
        $this->addSql('CREATE INDEX idx_customer_engagement ON customer (digital_engagement_score)');
        $this->addSql('CREATE INDEX idx_loan_ai_score ON loan (ai_score)');
        $this->addSql('CREATE INDEX idx_loan_ai_risk_level ON loan (ai_risk_level)');
    }

    public function down(Schema $schema): void
    {
        // Suppression des contraintes de clés étrangères
        $this->addSql('ALTER TABLE loan_scoring_predictions DROP FOREIGN KEY FK_SCORING_PREDICTIONS_CUSTOMER');
        $this->addSql('ALTER TABLE loan_scoring_predictions DROP FOREIGN KEY FK_SCORING_PREDICTIONS_LOAN');

        // Suppression des tables
        $this->addSql('DROP TABLE model_drift_alerts');
        $this->addSql('DROP TABLE model_training_events');
        $this->addSql('DROP TABLE model_performance_metrics');
        $this->addSql('DROP TABLE loan_scoring_predictions');
        $this->addSql('DROP TABLE loan_scoring_models');

        // Suppression des colonnes ajoutées
        $this->addSql('ALTER TABLE customer 
            DROP COLUMN IF EXISTS digital_engagement_score,
            DROP COLUMN IF EXISTS average_response_time_to_requests,
            DROP COLUMN IF EXISTS number_of_application_modifications,
            DROP COLUMN IF EXISTS application_completion_time,
            DROP COLUMN IF EXISTS has_opted_for_auto_payment,
            DROP COLUMN IF EXISTS prefers_paperless_communication,
            DROP COLUMN IF EXISTS application_channel,
            DROP COLUMN IF EXISTS communication_preference,
            DROP COLUMN IF EXISTS number_of_support_contacts,
            DROP COLUMN IF EXISTS employment_type,
            DROP COLUMN IF EXISTS education_level,
            DROP COLUMN IF EXISTS number_of_dependents,
            DROP COLUMN IF EXISTS housing_status,
            DROP COLUMN IF EXISTS credit_card_limit,
            DROP COLUMN IF EXISTS credit_card_balance,
            DROP COLUMN IF EXISTS financial_assets_value,
            DROP COLUMN IF EXISTS external_credit_score,
            DROP COLUMN IF EXISTS number_of_credit_inquiries,
            DROP COLUMN IF EXISTS has_bankruptcy_history,
            DROP COLUMN IF EXISTS has_litigation_history,
            DROP COLUMN IF EXISTS employer_size,
            DROP COLUMN IF EXISTS employment_sector');

        $this->addSql('ALTER TABLE loan 
            DROP COLUMN IF EXISTS ai_score,
            DROP COLUMN IF EXISTS ai_risk_level,
            DROP COLUMN IF EXISTS ai_confidence,
            DROP COLUMN IF EXISTS scoring_model_version,
            DROP COLUMN IF EXISTS risk_factors,
            DROP COLUMN IF EXISTS rejection_reason');

        // Suppression des index
        $this->addSql('DROP INDEX IF EXISTS idx_customer_ai_score ON customer');
        $this->addSql('DROP INDEX IF EXISTS idx_customer_engagement ON customer');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_ai_score ON loan');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_ai_risk_level ON loan');
    }
}
