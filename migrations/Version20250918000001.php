<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour optimiser les performances avec des index stratégiques
 */
final class Version20250918000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optimized indexes for LoanMaster performance';
    }

    public function up(Schema $schema): void
    {
        // Index composite pour les requêtes les plus fréquentes
        $this->addSql('CREATE INDEX idx_loan_user_status ON loan (user_id, status)');
        $this->addSql('CREATE INDEX idx_loan_status_created ON loan (status, created_at)');
        $this->addSql('CREATE INDEX idx_loan_type_amount ON loan (type, amount)');
        
        // Index pour les recherches par numéro (unique déjà créé, mais on s'assure)
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C5D30D03C1BA2D27 ON loan (number) IF NOT EXISTS');
        
        // Index pour les dates de paiement (gestion des échéances)
        $this->addSql('CREATE INDEX idx_loan_next_payment ON loan (next_payment_date) WHERE status = \'active\'');
        $this->addSql('CREATE INDEX idx_loan_last_payment ON loan (last_payment_date) WHERE status = \'active\'');
        
        // Index pour les prêts à risque
        $this->addSql('CREATE INDEX idx_loan_risk_analysis ON loan (status, last_payment_date, amount)');
        
        // Index pour les statistiques et rapports
        $this->addSql('CREATE INDEX idx_loan_reporting ON loan (created_at, status, type, amount)');
        
        // Index pour la recherche full-text sur la description
        $this->addSql('CREATE INDEX idx_loan_description_search ON loan USING gin(to_tsvector(\'french\', project_description))');
        
        // Index pour les utilisateurs
        $this->addSql('CREATE INDEX idx_user_email_active ON "user" (email) WHERE is_active = true');
        $this->addSql('CREATE INDEX idx_user_roles ON "user" USING gin(roles)');
        $this->addSql('CREATE INDEX idx_user_created ON "user" (created_at)');
        $this->addSql('CREATE INDEX idx_user_last_login ON "user" (last_login_at)');
        
        // Index pour les sessions et sécurité
        $this->addSql('CREATE INDEX idx_user_login_attempts ON login_attempt (ip_address, created_at)');
        $this->addSql('CREATE INDEX idx_user_session_active ON user_session (user_id, expires_at) WHERE is_active = true');
        
        // Index pour l'audit trail
        $this->addSql('CREATE INDEX idx_audit_log_entity ON audit_log (entity_type, entity_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_log_user ON audit_log (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_audit_log_action ON audit_log (action, created_at)');
    }

    public function down(Schema $schema): void
    {
        // Suppression des index dans l'ordre inverse
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_action');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_user');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_entity');
        
        $this->addSql('DROP INDEX IF EXISTS idx_user_session_active');
        $this->addSql('DROP INDEX IF EXISTS idx_user_login_attempts');
        
        $this->addSql('DROP INDEX IF EXISTS idx_user_last_login');
        $this->addSql('DROP INDEX IF EXISTS idx_user_created');
        $this->addSql('DROP INDEX IF EXISTS idx_user_roles');
        $this->addSql('DROP INDEX IF EXISTS idx_user_email_active');
        
        $this->addSql('DROP INDEX IF EXISTS idx_loan_description_search');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_reporting');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_risk_analysis');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_last_payment');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_next_payment');
        
        $this->addSql('DROP INDEX IF EXISTS idx_loan_type_amount');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_status_created');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_user_status');
    }
}
