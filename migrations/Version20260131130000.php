<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create forms and form_fields tables for Form Builder System';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE forms (id INT AUTO_INCREMENT NOT NULL, creator_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, `schema` JSON NOT NULL, tags JSON NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, assigned_to VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FD3F0E0A989D9B62 (slug), INDEX IDX_FD3F0E0A61220EA6 (creator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE form_fields (id INT AUTO_INCREMENT NOT NULL, form_id INT NOT NULL, name VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, placeholder VARCHAR(255) DEFAULT NULL, default_value LONGTEXT DEFAULT NULL, is_required TINYINT(1) NOT NULL, is_active TINYINT(1) NOT NULL, sort_order INT NOT NULL, options JSON NOT NULL, validation_rules JSON NOT NULL, attributes JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_7A0C5F155FF69B7D (form_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE forms ADD CONSTRAINT FK_FD3F0E0A61220EA6 FOREIGN KEY (creator_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE form_fields ADD CONSTRAINT FK_7A0C5F155FF69B7D FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE forms DROP FOREIGN KEY FK_FD3F0E0A61220EA6');
        $this->addSql('ALTER TABLE form_fields DROP FOREIGN KEY FK_7A0C5F155FF69B7D');
        $this->addSql('DROP TABLE forms');
        $this->addSql('DROP TABLE form_fields');
    }
}