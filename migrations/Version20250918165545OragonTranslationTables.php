<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer les nouvelles tables de traduction Oragon
 * Phase 2: PageTranslation et SeoTranslation
 */
final class Version20250918165545OragonTranslationTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables page_translations et seo_translations pour le système Oragon';
    }

    public function up(Schema $schema): void
    {
        // Page translations table
        $this->addSql('CREATE TABLE page_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            page_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            resume TEXT DEFAULT NULL,
            slug VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            CONSTRAINT FK_PAGE_TRANS_PAGE FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_PAGE_TRANS_LANGUAGE FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAGE_LANGUAGE ON page_translations (page_id, language_id)');
        $this->addSql('CREATE INDEX IDX_PAGE_TRANS_PAGE ON page_translations (page_id)');
        $this->addSql('CREATE INDEX IDX_PAGE_TRANS_LANGUAGE ON page_translations (language_id)');

        // SEO translations table
        $this->addSql('CREATE TABLE seo_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            seo_id INTEGER NOT NULL,
            language_id INTEGER NOT NULL,
            seo_home_title VARCHAR(255) DEFAULT NULL,
            seo_home_keywords VARCHAR(255) DEFAULT NULL,
            seo_home_description TEXT DEFAULT NULL,
            seo_about_title VARCHAR(255) DEFAULT NULL,
            seo_about_keywords VARCHAR(255) DEFAULT NULL,
            seo_about_description TEXT DEFAULT NULL,
            seo_service_title VARCHAR(255) DEFAULT NULL,
            seo_service_keywords VARCHAR(255) DEFAULT NULL,
            seo_service_description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            CONSTRAINT FK_SEO_TRANS_SEO FOREIGN KEY (seo_id) REFERENCES seo (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_SEO_TRANS_LANGUAGE FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_SEO_LANGUAGE ON seo_translations (seo_id, language_id)');
        $this->addSql('CREATE INDEX IDX_SEO_TRANS_SEO ON seo_translations (seo_id)');
        $this->addSql('CREATE INDEX IDX_SEO_TRANS_LANGUAGE ON seo_translations (language_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE seo_translations');
        $this->addSql('DROP TABLE page_translations');
    }
}
