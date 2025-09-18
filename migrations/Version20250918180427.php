<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter les index de performance optimisés
 */
final class Version20250918180427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout d\'index de performance pour optimiser les requêtes LoanMaster';
    }

    public function up(Schema $schema): void
    {
        // Index composites pour les requêtes les plus fréquentes sur les prêts
        $this->addSql('CREATE INDEX idx_loan_user_status ON loan (user_id, status)');
        $this->addSql('CREATE INDEX idx_loan_status_created ON loan (status, created_at DESC)');
        $this->addSql('CREATE INDEX idx_loan_amount_status ON loan (amount, status)');
        $this->addSql('CREATE INDEX idx_loan_due_date_status ON loan (due_date, status) WHERE status IN (\'APPROVED\', \'DISBURSED\')');
        
        // Index pour les recherches par montant et date
        $this->addSql('CREATE INDEX idx_loan_created_at_desc ON loan (created_at DESC)');
        $this->addSql('CREATE INDEX idx_loan_amount_range ON loan (amount) WHERE amount > 0');
        
        // Index sur les utilisateurs pour les jointures fréquentes
        $this->addSql('CREATE INDEX idx_user_email_verified ON "user" (email, is_verified)');
        $this->addSql('CREATE INDEX idx_user_created_at ON "user" (created_at DESC)');
        $this->addSql('CREATE INDEX idx_user_roles ON "user" USING gin(roles)'); // Index GIN pour les arrays JSON
        
        // Index sur le KYC pour les filtres de validation
        $this->addSql('CREATE INDEX idx_user_kyc_status ON user_kyc (status, validated_at DESC)');
        $this->addSql('CREATE INDEX idx_user_kyc_user_status ON user_kyc (user_id, status)');
        
        // Index pour les sessions et la sécurité
        $this->addSql('CREATE INDEX idx_user_last_login ON "user" (last_login_at) WHERE last_login_at IS NOT NULL');
        
        // Index pour les calculs de prêts et statistiques
        $this->addSql('CREATE INDEX idx_loan_stats_composite ON loan (status, amount, created_at)');
        
        // Extensions PostgreSQL pour la recherche full-text
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS unaccent');
        
        // Index full-text pour la recherche de prêts par description
        $this->addSql('CREATE INDEX idx_loan_description_fts ON loan USING gin(to_tsvector(\'french\', description))');
        
        // Index full-text pour la recherche d\'utilisateurs
        $this->addSql('CREATE INDEX idx_user_name_fts ON "user" USING gin(to_tsvector(\'french\', first_name || \' \' || last_name))');
        $this->addSql('CREATE INDEX idx_user_email_trigram ON "user" USING gin(email gin_trgm_ops)');
        
        // Index partiel pour les prêts actifs (optimisation mémoire)
        $this->addSql('CREATE INDEX idx_loan_active_only ON loan (user_id, created_at DESC) WHERE status IN (\'PENDING\', \'APPROVED\', \'DISBURSED\')');
        
        // Index pour les requêtes de dashboard admin
        $this->addSql('CREATE INDEX idx_loan_monthly_stats ON loan (EXTRACT(YEAR FROM created_at), EXTRACT(MONTH FROM created_at), status)');
        
        // Optimisation des contraintes de clés étrangères
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_loan_user_id ON loan (user_id)');
        $this->addSql('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_kyc_user_id ON user_kyc (user_id)');
    }

    public function down(Schema $schema): void
    {
        // Suppression des index en ordre inverse
        $this->addSql('DROP INDEX IF EXISTS idx_user_kyc_user_id');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_user_id');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_monthly_stats');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_active_only');
        $this->addSql('DROP INDEX IF EXISTS idx_user_email_trigram');
        $this->addSql('DROP INDEX IF EXISTS idx_user_name_fts');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_description_fts');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_stats_composite');
        $this->addSql('DROP INDEX IF EXISTS idx_user_last_login');
        $this->addSql('DROP INDEX IF EXISTS idx_user_kyc_user_status');
        $this->addSql('DROP INDEX IF EXISTS idx_user_kyc_status');
        $this->addSql('DROP INDEX IF EXISTS idx_user_roles');
        $this->addSql('DROP INDEX IF EXISTS idx_user_created_at');
        $this->addSql('DROP INDEX IF EXISTS idx_user_email_verified');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_amount_range');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_created_at_desc');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_due_date_status');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_amount_status');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_status_created');
        $this->addSql('DROP INDEX IF EXISTS idx_loan_user_status');
        
        // Note: Les extensions ne sont pas supprimées car elles peuvent être utilisées ailleurs
    }
}
