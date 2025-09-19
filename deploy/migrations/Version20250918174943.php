<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250918174943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bank (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, logo VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, notary VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, manager_name VARCHAR(255) NOT NULL, sign_bank VARCHAR(255) NOT NULL, sign_notary VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE bank_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, translatable_id INTEGER NOT NULL, language_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, sign_bank VARCHAR(255) DEFAULT NULL, sign_notary VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_62F3D9C2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_62F3D9C82F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_62F3D9C2C2AC5D3 ON bank_translations (translatable_id)');
        $this->addSql('CREATE INDEX IDX_62F3D9C82F1BAF4 ON bank_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX bank_language_unique ON bank_translations (translatable_id, language_id)');
        $this->addSql('CREATE TABLE brand (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image VARCHAR(255) NOT NULL, is_enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE contact (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, fullname VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, message CLOB NOT NULL)');
        $this->addSql('CREATE TABLE faq (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, question VARCHAR(255) NOT NULL, answer CLOB NOT NULL, is_enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE faq_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, translatable_id INTEGER NOT NULL, language_id INTEGER NOT NULL, question VARCHAR(255) NOT NULL, answer CLOB NOT NULL, CONSTRAINT FK_99569DA22C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES faq (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_99569DA282F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_99569DA22C2AC5D3 ON faq_translations (translatable_id)');
        $this->addSql('CREATE INDEX IDX_99569DA282F1BAF4 ON faq_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX faq_language_unique ON faq_translations (translatable_id, language_id)');
        $this->addSql('CREATE TABLE language (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, native_name VARCHAR(255) DEFAULT NULL, dir VARCHAR(8) NOT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D4DB71B577153098 ON language (code)');
        $this->addSql('CREATE TABLE loan (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, loan_type_id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, pay_file_id INTEGER DEFAULT NULL, contract_sign_file_id INTEGER DEFAULT NULL, bank_id INTEGER DEFAULT NULL, loan_number VARCHAR(255) NOT NULL, start_date DATETIME DEFAULT NULL, end_date DATETIME DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, interest_rate NUMERIC(5, 2) NOT NULL, duration_months INTEGER NOT NULL, status VARCHAR(20) NOT NULL, pay_status VARCHAR(20) DEFAULT NULL, pay_contract_status VARCHAR(20) DEFAULT NULL, contract_status VARCHAR(20) DEFAULT NULL, amount_repaid NUMERIC(10, 2) NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, price_contract NUMERIC(10, 2) DEFAULT NULL, last_payment_date DATETIME DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by VARCHAR(255) DEFAULT NULL, updated_by VARCHAR(255) DEFAULT NULL, bank_info CLOB DEFAULT NULL, project_name VARCHAR(255) DEFAULT NULL, project_description CLOB DEFAULT NULL, project_start_date DATE DEFAULT NULL, project_end_date DATE DEFAULT NULL, project_budget DOUBLE PRECISION DEFAULT NULL, project_location VARCHAR(255) DEFAULT NULL, project_manager VARCHAR(255) DEFAULT NULL, project_team CLOB DEFAULT NULL, project_milestones CLOB DEFAULT NULL, project_risks CLOB DEFAULT NULL, project_benefits CLOB DEFAULT NULL, CONSTRAINT FK_C5D30D03EB0302E7 FOREIGN KEY (loan_type_id) REFERENCES loan_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C5D30D03A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C5D30D037521BEA7 FOREIGN KEY (pay_file_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C5D30D03C3342A45 FOREIGN KEY (contract_sign_file_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C5D30D0311C8FB41 FOREIGN KEY (bank_id) REFERENCES bank (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C5D30D036D13EA29 ON loan (loan_number)');
        $this->addSql('CREATE INDEX IDX_C5D30D03EB0302E7 ON loan (loan_type_id)');
        $this->addSql('CREATE INDEX IDX_C5D30D03A76ED395 ON loan (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C5D30D037521BEA7 ON loan (pay_file_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C5D30D03C3342A45 ON loan (contract_sign_file_id)');
        $this->addSql('CREATE INDEX IDX_C5D30D0311C8FB41 ON loan (bank_id)');
        $this->addSql('CREATE TABLE loan_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, is_active BOOLEAN NOT NULL, default_interest_rate NUMERIC(5, 2) DEFAULT NULL, default_duration_months INTEGER DEFAULT NULL, sort_order INTEGER DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5D732D5D77153098 ON loan_type (code)');
        $this->addSql('CREATE TABLE loan_type_translation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, translatable_id INTEGER NOT NULL, language_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, CONSTRAINT FK_9B6BF6192C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES loan_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9B6BF61982F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9B6BF6192C2AC5D3 ON loan_type_translation (translatable_id)');
        $this->addSql('CREATE INDEX IDX_9B6BF61982F1BAF4 ON loan_type_translation (language_id)');
        $this->addSql('CREATE UNIQUE INDEX loan_type_language_unique ON loan_type_translation (translatable_id, language_id)');
        $this->addSql('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, file_name VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, extension VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, payment_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE TABLE notification (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, content CLOB NOT NULL, subject VARCHAR(255) NOT NULL, create_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BF5476CAA76ED395 ON notification (user_id)');
        $this->addSql('CREATE TABLE notification_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, translatable_id INTEGER NOT NULL, language_id INTEGER NOT NULL, subject VARCHAR(255) NOT NULL, content CLOB NOT NULL, CONSTRAINT FK_C7B930B22C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES notification (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C7B930B282F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C7B930B22C2AC5D3 ON notification_translations (translatable_id)');
        $this->addSql('CREATE INDEX IDX_C7B930B282F1BAF4 ON notification_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX notification_language_unique ON notification_translations (translatable_id, language_id)');
        $this->addSql('CREATE TABLE page (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image VARCHAR(255) DEFAULT NULL, is_enabled BOOLEAN DEFAULT NULL)');
        $this->addSql('CREATE TABLE page_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, page_id INTEGER NOT NULL, language_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, resume CLOB DEFAULT NULL, slug VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL, CONSTRAINT FK_78AB76C9C4663E4 FOREIGN KEY (page_id) REFERENCES page (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_78AB76C982F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_78AB76C9C4663E4 ON page_translations (page_id)');
        $this->addSql('CREATE INDEX IDX_78AB76C982F1BAF4 ON page_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PAGE_LANGUAGE ON page_translations (page_id, language_id)');
        $this->addSql('CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category_id INTEGER DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, slug VARCHAR(128) NOT NULL, is_enabled BOOLEAN NOT NULL, content CLOB DEFAULT NULL, resume CLOB DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_5A8A6C8D12469DE2 FOREIGN KEY (category_id) REFERENCES post_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8D989D9B62 ON post (slug)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8D12469DE2 ON post (category_id)');
        $this->addSql('CREATE TABLE post_category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(128) NOT NULL, is_enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B9A19060989D9B62 ON post_category (slug)');
        $this->addSql('CREATE TABLE post_category_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, object_id INTEGER DEFAULT NULL, locale VARCHAR(8) NOT NULL, field VARCHAR(32) NOT NULL, content CLOB DEFAULT NULL, CONSTRAINT FK_BCD02517232D562B FOREIGN KEY (object_id) REFERENCES post_category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BCD02517232D562B ON post_category_translations (object_id)');
        $this->addSql('CREATE INDEX post_category_translation_idx ON post_category_translations (locale, object_id, field)');
        $this->addSql('CREATE TABLE post_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, object_id INTEGER DEFAULT NULL, locale VARCHAR(8) NOT NULL, field VARCHAR(32) NOT NULL, content CLOB DEFAULT NULL, CONSTRAINT FK_6D8AA754232D562B FOREIGN KEY (object_id) REFERENCES post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6D8AA754232D562B ON post_translations (object_id)');
        $this->addSql('CREATE INDEX post_translation_idx ON post_translations (locale, object_id, field)');
        $this->addSql('CREATE TABLE seo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, seo_image VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE seo_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, seo_id INTEGER NOT NULL, language_id INTEGER NOT NULL, seo_home_title VARCHAR(255) DEFAULT NULL, seo_home_keywords VARCHAR(255) DEFAULT NULL, seo_home_description CLOB DEFAULT NULL, seo_about_title VARCHAR(255) DEFAULT NULL, seo_about_keywords VARCHAR(255) DEFAULT NULL, seo_about_description CLOB DEFAULT NULL, seo_service_title VARCHAR(255) DEFAULT NULL, seo_service_keywords VARCHAR(255) DEFAULT NULL, seo_service_description CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL, CONSTRAINT FK_227D72A597E3DD86 FOREIGN KEY (seo_id) REFERENCES seo (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_227D72A582F1BAF4 FOREIGN KEY (language_id) REFERENCES language (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_227D72A597E3DD86 ON seo_translations (seo_id)');
        $this->addSql('CREATE INDEX IDX_227D72A582F1BAF4 ON seo_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SEO_LANGUAGE ON seo_translations (seo_id, language_id)');
        $this->addSql('CREATE TABLE service (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(128) NOT NULL, resume CLOB DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, is_enabled BOOLEAN NOT NULL, CONSTRAINT FK_E19D9AD212469DE2 FOREIGN KEY (category_id) REFERENCES service_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E19D9AD2989D9B62 ON service (slug)');
        $this->addSql('CREATE INDEX IDX_E19D9AD212469DE2 ON service (category_id)');
        $this->addSql('CREATE TABLE serviceCategory_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, object_id INTEGER DEFAULT NULL, locale VARCHAR(8) NOT NULL, field VARCHAR(32) NOT NULL, content CLOB DEFAULT NULL, CONSTRAINT FK_2672BF6C232D562B FOREIGN KEY (object_id) REFERENCES service_category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2672BF6C232D562B ON serviceCategory_translations (object_id)');
        $this->addSql('CREATE INDEX serviceCategory_translation_idx ON serviceCategory_translations (locale, object_id, field)');
        $this->addSql('CREATE TABLE service_category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(128) NOT NULL, is_enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FF3A42FC989D9B62 ON service_category (slug)');
        $this->addSql('CREATE TABLE service_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, object_id INTEGER DEFAULT NULL, locale VARCHAR(8) NOT NULL, field VARCHAR(32) NOT NULL, content CLOB DEFAULT NULL, CONSTRAINT FK_191BAF62232D562B FOREIGN KEY (object_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_191BAF62232D562B ON service_translations (object_id)');
        $this->addSql('CREATE INDEX service_translation_idx ON service_translations (locale, object_id, field)');
        $this->addSql('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, logo_dark VARCHAR(255) DEFAULT NULL, logo_light VARCHAR(255) DEFAULT NULL, email_img VARCHAR(255) DEFAULT NULL, favicon VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, email_sender VARCHAR(255) DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, devise VARCHAR(10) NOT NULL, theme VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE slider (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, subtitle VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, btn_text VARCHAR(255) DEFAULT NULL, btn_url VARCHAR(255) DEFAULT NULL, is_enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE slider_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, object_id INTEGER DEFAULT NULL, locale VARCHAR(8) NOT NULL, field VARCHAR(32) NOT NULL, content CLOB DEFAULT NULL, CONSTRAINT FK_ECA13F55232D562B FOREIGN KEY (object_id) REFERENCES slider (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_ECA13F55232D562B ON slider_translations (object_id)');
        $this->addSql('CREATE INDEX slider_translation_idx ON slider_translations (locale, object_id, field)');
        $this->addSql('CREATE TABLE social (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, position INTEGER DEFAULT NULL, is_enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE step (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB NOT NULL, image VARCHAR(255) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, position INTEGER DEFAULT NULL, is_enabled BOOLEAN DEFAULT NULL)');
        $this->addSql('CREATE TABLE step_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, object_id INTEGER DEFAULT NULL, locale VARCHAR(8) NOT NULL, field VARCHAR(32) NOT NULL, content CLOB DEFAULT NULL, CONSTRAINT FK_FDD82D55232D562B FOREIGN KEY (object_id) REFERENCES step (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FDD82D55232D562B ON step_translations (object_id)');
        $this->addSql('CREATE INDEX step_translation_idx ON step_translations (locale, object_id, field)');
        $this->addSql('CREATE TABLE testimonial (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, author VARCHAR(255) DEFAULT NULL, position INTEGER DEFAULT NULL, rating INTEGER DEFAULT NULL, message CLOB DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, country VARCHAR(255) DEFAULT NULL, is_enabled BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE testimonial_translations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, object_id INTEGER DEFAULT NULL, locale VARCHAR(8) NOT NULL, field VARCHAR(32) NOT NULL, content CLOB DEFAULT NULL, CONSTRAINT FK_9BE970A9232D562B FOREIGN KEY (object_id) REFERENCES testimonial (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9BE970A9232D562B ON testimonial_translations (object_id)');
        $this->addSql('CREATE INDEX testimonial_translation_idx ON testimonial_translations (locale, object_id, field)');
        $this->addSql('CREATE TABLE theme (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, primary_color VARCHAR(255) DEFAULT NULL, secondary_color VARCHAR(255) DEFAULT NULL, header VARCHAR(255) DEFAULT NULL, slider VARCHAR(255) DEFAULT NULL, footer VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9775E708989D9B62 ON theme (slug)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, avatar_id INTEGER DEFAULT NULL, id_document_front_id INTEGER DEFAULT NULL, id_document_back_id INTEGER DEFAULT NULL, proof_of_address_id INTEGER DEFAULT NULL, integrity_document_id INTEGER DEFAULT NULL, business_license_id INTEGER DEFAULT NULL, business_registration_id INTEGER DEFAULT NULL, tax_certificate_id INTEGER DEFAULT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , lastname VARCHAR(190) DEFAULT NULL, firstname VARCHAR(190) DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, is_verified BOOLEAN NOT NULL, telephone VARCHAR(255) DEFAULT NULL, civility VARCHAR(190) DEFAULT NULL, birthdate DATETIME DEFAULT NULL, nationality VARCHAR(190) DEFAULT NULL, address VARCHAR(190) DEFAULT NULL, zipcode VARCHAR(10) DEFAULT NULL, city VARCHAR(190) DEFAULT NULL, country VARCHAR(190) DEFAULT NULL, professionnal_situation VARCHAR(190) DEFAULT NULL, monthly_income VARCHAR(190) DEFAULT NULL, is_enabled BOOLEAN NOT NULL, confirmation_token VARCHAR(255) DEFAULT NULL, reset_token VARCHAR(255) DEFAULT NULL, id_document_type VARCHAR(255) DEFAULT NULL, proof_of_address_type VARCHAR(255) DEFAULT NULL, integrity_document_type VARCHAR(255) DEFAULT NULL, verification_status VARCHAR(50) DEFAULT NULL, account_type VARCHAR(20) NOT NULL, company_name VARCHAR(255) DEFAULT NULL, registration_number VARCHAR(255) DEFAULT NULL, company_address VARCHAR(255) DEFAULT NULL, company_email VARCHAR(255) DEFAULT NULL, company_telephone VARCHAR(255) DEFAULT NULL, company_legal_status VARCHAR(255) DEFAULT NULL, company_city VARCHAR(255) DEFAULT NULL, company_country VARCHAR(255) DEFAULT NULL, company_zipcode VARCHAR(255) DEFAULT NULL, company_professional_experience VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, payment_at DATETIME DEFAULT NULL, CONSTRAINT FK_8D93D64986383B10 FOREIGN KEY (avatar_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D93D649D45A7153 FOREIGN KEY (id_document_front_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D93D649AEBDCCFD FOREIGN KEY (id_document_back_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D93D6496E7ADFEC FOREIGN KEY (proof_of_address_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D93D6492B663074 FOREIGN KEY (integrity_document_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D93D64986AC98E0 FOREIGN KEY (business_license_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D93D64951BA3D5C FOREIGN KEY (business_registration_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D93D6496F655D8D FOREIGN KEY (tax_certificate_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64986383B10 ON "user" (avatar_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649D45A7153 ON "user" (id_document_front_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649AEBDCCFD ON "user" (id_document_back_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6496E7ADFEC ON "user" (proof_of_address_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6492B663074 ON "user" (integrity_document_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64986AC98E0 ON "user" (business_license_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64951BA3D5C ON "user" (business_registration_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D6496F655D8D ON "user" (tax_certificate_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE bank');
        $this->addSql('DROP TABLE bank_translations');
        $this->addSql('DROP TABLE brand');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE faq');
        $this->addSql('DROP TABLE faq_translations');
        $this->addSql('DROP TABLE language');
        $this->addSql('DROP TABLE loan');
        $this->addSql('DROP TABLE loan_type');
        $this->addSql('DROP TABLE loan_type_translation');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE notification_translations');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE page_translations');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_category');
        $this->addSql('DROP TABLE post_category_translations');
        $this->addSql('DROP TABLE post_translations');
        $this->addSql('DROP TABLE seo');
        $this->addSql('DROP TABLE seo_translations');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE serviceCategory_translations');
        $this->addSql('DROP TABLE service_category');
        $this->addSql('DROP TABLE service_translations');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE slider');
        $this->addSql('DROP TABLE slider_translations');
        $this->addSql('DROP TABLE social');
        $this->addSql('DROP TABLE step');
        $this->addSql('DROP TABLE step_translations');
        $this->addSql('DROP TABLE testimonial');
        $this->addSql('DROP TABLE testimonial_translations');
        $this->addSql('DROP TABLE theme');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
