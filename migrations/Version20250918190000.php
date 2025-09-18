<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table des modèles de scoring IA/ML
 */
final class Version20250918190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table loan_scoring_models pour les modèles IA/ML de scoring automatique';
    }

    public function up(Schema $schema): void
    {
        // Table principale des modèles de scoring
        $this->addSql('CREATE TABLE loan_scoring_models (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL UNIQUE,
            version VARCHAR(50) NOT NULL,
            algorithm VARCHAR(50) NOT NULL,
            performance_metrics JSON NOT NULL,
            model_data JSON NOT NULL,
            feature_importance JSON NOT NULL,
            training_options JSON NOT NULL,
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
            validation_results JSON DEFAULT NULL,
            drift_metrics JSON DEFAULT NULL,
            last_used_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            usage_count INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Index pour optimiser les requêtes courantes
        $this->addSql('CREATE INDEX idx_model_status ON loan_scoring_models (status)');
        $this->addSql('CREATE INDEX idx_model_version ON loan_scoring_models (version)');
        $this->addSql('CREATE INDEX idx_model_created ON loan_scoring_models (created_at)');
        $this->addSql('CREATE INDEX idx_model_algorithm ON loan_scoring_models (algorithm)');
        $this->addSql('CREATE INDEX idx_model_accuracy ON loan_scoring_models (accuracy)');
        $this->addSql('CREATE INDEX idx_model_deployed ON loan_scoring_models (deployed_at)');

        // Table pour l'historique des prédictions (audit trail)
        $this->addSql('CREATE TABLE scoring_predictions_history (
            id BIGINT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            customer_id INT DEFAULT NULL,
            prediction_data JSON NOT NULL,
            input_features JSON NOT NULL,
            prediction_result JSON NOT NULL,
            execution_time_ms DOUBLE PRECISION NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            api_version VARCHAR(20) NOT NULL DEFAULT "v1",
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            created_by VARCHAR(100) DEFAULT NULL,
            session_id VARCHAR(128) DEFAULT NULL,
            request_id VARCHAR(64) DEFAULT NULL,
            INDEX idx_prediction_model (model_id),
            INDEX idx_prediction_customer (customer_id),
            INDEX idx_prediction_created (created_at),
            INDEX idx_prediction_session (session_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table pour le monitoring de performance des modèles
        $this->addSql('CREATE TABLE model_performance_monitoring (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            monitoring_date DATE NOT NULL,
            total_predictions INT NOT NULL DEFAULT 0,
            successful_predictions INT NOT NULL DEFAULT 0,
            failed_predictions INT NOT NULL DEFAULT 0,
            average_execution_time_ms DOUBLE PRECISION DEFAULT NULL,
            accuracy_estimate DOUBLE PRECISION DEFAULT NULL,
            drift_score DOUBLE PRECISION DEFAULT NULL,
            performance_degradation DOUBLE PRECISION DEFAULT NULL,
            alert_triggered BOOLEAN NOT NULL DEFAULT FALSE,
            alert_type VARCHAR(50) DEFAULT NULL,
            alert_message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_monitoring_model (model_id),
            INDEX idx_monitoring_date (monitoring_date),
            INDEX idx_monitoring_alert (alert_triggered),
            UNIQUE KEY unique_model_date (model_id, monitoring_date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table pour stocker les configurations d\'algorithmes
        $this->addSql('CREATE TABLE ai_algorithm_configurations (
            id INT AUTO_INCREMENT NOT NULL,
            algorithm_name VARCHAR(50) NOT NULL,
            configuration_name VARCHAR(100) NOT NULL,
            hyperparameters JSON NOT NULL,
            performance_baseline JSON DEFAULT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            created_by VARCHAR(100) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            INDEX idx_algorithm_name (algorithm_name),
            INDEX idx_algorithm_active (is_active),
            INDEX idx_algorithm_default (is_default),
            UNIQUE KEY unique_algorithm_config (algorithm_name, configuration_name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Insertion des configurations d\'algorithmes par défaut
        $this->addSql("INSERT INTO ai_algorithm_configurations (algorithm_name, configuration_name, hyperparameters, is_default, is_active, created_at, updated_at, description) VALUES
            ('gradient_boosting', 'default', '{\"learning_rate\": 0.1, \"max_depth\": 6, \"n_estimators\": 100, \"subsample\": 1.0}', true, true, NOW(), NOW(), 'Configuration par défaut pour Gradient Boosting'),
            ('random_forest', 'default', '{\"n_estimators\": 100, \"max_depth\": 10, \"min_samples_split\": 2, \"min_samples_leaf\": 1}', true, true, NOW(), NOW(), 'Configuration par défaut pour Random Forest'),
            ('neural_network', 'default', '{\"hidden_layers\": \"100,50\", \"learning_rate\": 0.001, \"batch_size\": 32, \"epochs\": 100}', true, true, NOW(), NOW(), 'Configuration par défaut pour Réseau de Neurones'),
            ('logistic_regression', 'default', '{\"C\": 1.0, \"penalty\": \"l2\", \"solver\": \"lbfgs\"}', true, true, NOW(), NOW(), 'Configuration par défaut pour Régression Logistique')");

        // Table pour gérer les features et leur importance
        $this->addSql('CREATE TABLE ai_feature_importance (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            feature_name VARCHAR(100) NOT NULL,
            importance_score DOUBLE PRECISION NOT NULL,
            feature_type VARCHAR(50) NOT NULL,
            feature_group VARCHAR(50) DEFAULT NULL,
            is_selected BOOLEAN NOT NULL DEFAULT TRUE,
            calculation_method VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_feature_model (model_id),
            INDEX idx_feature_importance (importance_score DESC),
            INDEX idx_feature_type (feature_type),
            INDEX idx_feature_group (feature_group),
            UNIQUE KEY unique_model_feature (model_id, feature_name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table pour la gestion des versions de modèles et deployments
        $this->addSql('CREATE TABLE model_deployment_history (
            id INT AUTO_INCREMENT NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            deployment_type VARCHAR(50) NOT NULL,
            previous_model_id VARCHAR(255) DEFAULT NULL,
            deployment_status VARCHAR(50) NOT NULL,
            deployed_by VARCHAR(100) DEFAULT NULL,
            deployment_notes TEXT DEFAULT NULL,
            rollback_possible BOOLEAN NOT NULL DEFAULT TRUE,
            deployment_config JSON DEFAULT NULL,
            health_check_passed BOOLEAN DEFAULT NULL,
            performance_validation JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            completed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_deployment_model (model_id),
            INDEX idx_deployment_status (deployment_status),
            INDEX idx_deployment_type (deployment_type),
            INDEX idx_deployment_date (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table pour les alertes et notifications IA/ML
        $this->addSql('CREATE TABLE ai_ml_alerts (
            id INT AUTO_INCREMENT NOT NULL,
            alert_type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            model_id VARCHAR(255) DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            alert_data JSON DEFAULT NULL,
            is_resolved BOOLEAN NOT NULL DEFAULT FALSE,
            resolved_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            resolved_by VARCHAR(100) DEFAULT NULL,
            resolution_notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_alert_type (alert_type),
            INDEX idx_alert_severity (severity),
            INDEX idx_alert_model (model_id),
            INDEX idx_alert_resolved (is_resolved),
            INDEX idx_alert_created (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Suppression des tables dans l'ordre inverse pour éviter les contraintes
        $this->addSql('DROP TABLE ai_ml_alerts');
        $this->addSql('DROP TABLE model_deployment_history');
        $this->addSql('DROP TABLE ai_feature_importance');
        $this->addSql('DROP TABLE ai_algorithm_configurations');
        $this->addSql('DROP TABLE model_performance_monitoring');
        $this->addSql('DROP TABLE scoring_predictions_history');
        $this->addSql('DROP TABLE loan_scoring_models');
    }
}
