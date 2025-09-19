<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour les notifications temps réel avec Mercure
 */
final class Version20250918174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables pour les notifications temps réel et abonnements Mercure';
    }

    public function up(Schema $schema): void
    {
        // Table des notifications
        $this->addSql('CREATE TABLE notifications (
            id INT AUTO_INCREMENT NOT NULL,
            notification_id VARCHAR(255) NOT NULL,
            type VARCHAR(100) NOT NULL,
            recipients LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            data LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            options LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            status VARCHAR(50) NOT NULL DEFAULT "pending",
            delivered_count INT NOT NULL DEFAULT 0,
            failed_count INT NOT NULL DEFAULT 0,
            channel_results LONGTEXT NOT NULL COMMENT "(DC2Type:json)",
            errors LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            execution_time DOUBLE PRECISION NOT NULL DEFAULT 0,
            metadata LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            sent_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX idx_notification_type (type),
            INDEX idx_notification_status (status),
            INDEX idx_notification_created (created_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table des abonnements aux notifications
        $this->addSql('CREATE TABLE notification_subscriptions (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            topic VARCHAR(100) NOT NULL,
            options LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)",
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            PRIMARY KEY(id),
            INDEX idx_user_topic (user_id, topic),
            INDEX idx_topic_active (topic, is_active),
            INDEX IDX_E9C96E0FA76ED395 (user_id),
            CONSTRAINT FK_E9C96E0FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Ajout de la colonne notification_preferences à la table user si elle n'existe pas
        $this->addSql('ALTER TABLE user ADD COLUMN IF NOT EXISTS notification_preferences LONGTEXT DEFAULT NULL COMMENT "(DC2Type:json)"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_subscriptions');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('ALTER TABLE user DROP COLUMN IF EXISTS notification_preferences');
    }
}
