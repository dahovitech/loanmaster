<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table user_consents pour la gestion des consentements RGPD
 */
final class Version20250918000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table user_consents pour la gestion des consentements RGPD';
    }

    public function up(Schema $schema): void
    {
        // Créer la table user_consents
        $this->addSql('CREATE TABLE user_consents (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            consent_type VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            withdrawn_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            consent_text TEXT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            version VARCHAR(10) NOT NULL,
            locale VARCHAR(10) DEFAULT NULL,
            withdrawal_reason TEXT DEFAULT NULL,
            legal_basis VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Créer les index pour optimiser les performances
        $this->addSql('CREATE INDEX idx_consent_user_id ON user_consents (user_id)');
        $this->addSql('CREATE INDEX idx_consent_type ON user_consents (consent_type)');
        $this->addSql('CREATE INDEX idx_consent_status ON user_consents (status)');
        $this->addSql('CREATE INDEX idx_consent_created_at ON user_consents (created_at)');
        $this->addSql('CREATE INDEX idx_consent_expires_at ON user_consents (expires_at)');
        
        // Index unique pour éviter les doublons de consentement par utilisateur et type
        $this->addSql('CREATE UNIQUE INDEX unique_user_consent_type ON user_consents (user_id, consent_type)');
        
        // Index composites pour les requêtes fréquentes
        $this->addSql('CREATE INDEX idx_consent_user_status ON user_consents (user_id, status)');
        $this->addSql('CREATE INDEX idx_consent_type_status ON user_consents (consent_type, status)');
        $this->addSql('CREATE INDEX idx_consent_expires_status ON user_consents (expires_at, status)');
    }

    public function down(Schema $schema): void
    {
        // Supprimer les index d'abord
        $this->addSql('DROP INDEX idx_consent_user_id ON user_consents');
        $this->addSql('DROP INDEX idx_consent_type ON user_consents');
        $this->addSql('DROP INDEX idx_consent_status ON user_consents');
        $this->addSql('DROP INDEX idx_consent_created_at ON user_consents');
        $this->addSql('DROP INDEX idx_consent_expires_at ON user_consents');
        $this->addSql('DROP INDEX unique_user_consent_type ON user_consents');
        $this->addSql('DROP INDEX idx_consent_user_status ON user_consents');
        $this->addSql('DROP INDEX idx_consent_type_status ON user_consents');
        $this->addSql('DROP INDEX idx_consent_expires_status ON user_consents');
        
        // Supprimer la table
        $this->addSql('DROP TABLE user_consents');
    }
}
