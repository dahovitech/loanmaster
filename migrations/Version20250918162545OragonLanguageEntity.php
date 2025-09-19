<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration vers le système Oragon - Adaptation de l'entité Language
 * 
 * Cette migration adapte la table languages pour la rendre compatible
 * avec le nouveau système de traduction Oragon.
 * 
 * Modifications :
 * - Renommage isEnabled → isActive pour cohérence Oragon
 * - Ajout native_name pour les noms natifs des langues
 * - Ajout sort_order pour l'ordre d'affichage
 * - Ajout created_at et updated_at pour l'audit
 * - Mise à jour des données existantes
 * 
 * @author Prudence ASSOGBA
 */
final class Version20250918162545OragonLanguageEntity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration vers le système Oragon - Adaptation entité Language';
    }

    public function up(Schema $schema): void
    {
        // Vérifier si les colonnes existent déjà
        $table = $schema->getTable('language');
        
        // Ajouter native_name si elle n'existe pas
        if (!$table->hasColumn('native_name')) {
            $this->addSql('ALTER TABLE language ADD native_name VARCHAR(255) DEFAULT NULL');
        }
        
        // Ajouter sort_order si elle n'existe pas
        if (!$table->hasColumn('sort_order')) {
            $this->addSql('ALTER TABLE language ADD sort_order INT NOT NULL DEFAULT 0');
        }
        
        // Ajouter created_at si elle n'existe pas
        if (!$table->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE language ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT \'(DC2Type:datetime_immutable)\'');
        }
        
        // Ajouter updated_at si elle n'existe pas
        if (!$table->hasColumn('updated_at')) {
            $this->addSql('ALTER TABLE language ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT \'(DC2Type:datetime_immutable)\'');
        }
        
        // Renommer isEnabled en isActive si nécessaire (avec vérification)
        if ($table->hasColumn('is_enabled') && !$table->hasColumn('is_active')) {
            $this->addSql('ALTER TABLE language CHANGE is_enabled is_active TINYINT(1) NOT NULL DEFAULT 1');
        } elseif (!$table->hasColumn('is_active')) {
            $this->addSql('ALTER TABLE language ADD is_active TINYINT(1) NOT NULL DEFAULT 1');
        }
    }

    public function postUp(Schema $schema): void
    {
        // Mise à jour des données existantes après la migration structurelle
        $this->updateExistingLanguageData();
    }

    public function down(Schema $schema): void
    {
        // Supprimer les nouvelles colonnes ajoutées
        $this->addSql('ALTER TABLE language DROP COLUMN IF EXISTS native_name');
        $this->addSql('ALTER TABLE language DROP COLUMN IF EXISTS sort_order');
        $this->addSql('ALTER TABLE language DROP COLUMN IF EXISTS created_at');
        $this->addSql('ALTER TABLE language DROP COLUMN IF EXISTS updated_at');
        
        // Revenir à is_enabled si is_active existe
        $table = $schema->getTable('language');
        if ($table->hasColumn('is_active') && !$table->hasColumn('is_enabled')) {
            $this->addSql('ALTER TABLE language CHANGE is_active is_enabled TINYINT(1) NOT NULL DEFAULT 1');
        }
    }

    /**
     * Met à jour les données existantes des langues
     */
    private function updateExistingLanguageData(): void
    {
        // Mapping des codes langues vers leurs noms natifs
        $nativeNames = [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'da' => 'Dansk',
            'zh' => '中文',
            'ja' => '日本語',
            'ar' => 'العربية',
            'he' => 'עברית',
        ];

        // Mettre à jour les noms natifs
        foreach ($nativeNames as $code => $nativeName) {
            $this->addSql("UPDATE language SET native_name = :nativeName WHERE code = :code AND native_name IS NULL", [
                'nativeName' => $nativeName,
                'code' => $code
            ]);
        }

        // Définir l'ordre de tri par défaut basé sur l'ID
        $this->addSql('UPDATE language SET sort_order = id * 10 WHERE sort_order = 0');

        // S'assurer que les timestamps sont définis pour les enregistrements existants
        $currentTimestamp = date('Y-m-d H:i:s');
        $this->addSql("UPDATE language SET created_at = :timestamp WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'", [
            'timestamp' => $currentTimestamp
        ]);
        $this->addSql("UPDATE language SET updated_at = :timestamp WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'", [
            'timestamp' => $currentTimestamp
        ]);

        // S'assurer qu'il y a une langue par défaut
        $this->addSql("UPDATE language SET is_default = 1 WHERE code = 'fr' AND NOT EXISTS(SELECT 1 FROM language l2 WHERE l2.is_default = 1)");
    }

    /**
     * Vérifie si cette migration est nécessaire
     */
    public function isTransactional(): bool
    {
        return true;
    }
}
