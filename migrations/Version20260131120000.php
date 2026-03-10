<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enhance DirectoryContact entity with additional fields for directory management';
    }

    public function up(Schema $schema): void
    {
        // Add new columns to directory_contacts table
        $this->addSql('ALTER TABLE directory_contacts ADD position VARCHAR(255) NOT NULL AFTER name');
        $this->addSql('ALTER TABLE directory_contacts ADD email VARCHAR(255) NOT NULL AFTER position');
        $this->addSql('ALTER TABLE directory_contacts ADD phone VARCHAR(20) DEFAULT NULL AFTER email');
        $this->addSql('ALTER TABLE directory_contacts ADD address TEXT DEFAULT NULL AFTER phone');
        $this->addSql('ALTER TABLE directory_contacts ADD created_at DATETIME NOT NULL AFTER office_id');
        $this->addSql('ALTER TABLE directory_contacts ADD updated_at DATETIME NOT NULL AFTER created_at');
        
        // Add indexes for better performance
        $this->addSql('CREATE INDEX IDX_directory_contacts_email ON directory_contacts (email)');
        $this->addSql('CREATE INDEX IDX_directory_contacts_name ON directory_contacts (name)');
        $this->addSql('CREATE INDEX IDX_directory_contacts_position ON directory_contacts (position)');
    }

    public function down(Schema $schema): void
    {
        // Remove indexes
        $this->addSql('DROP INDEX IDX_directory_contacts_email ON directory_contacts');
        $this->addSql('DROP INDEX IDX_directory_contacts_name ON directory_contacts');
        $this->addSql('DROP INDEX IDX_directory_contacts_position ON directory_contacts');
        
        // Remove columns
        $this->addSql('ALTER TABLE directory_contacts DROP position');
        $this->addSql('ALTER TABLE directory_contacts DROP email');
        $this->addSql('ALTER TABLE directory_contacts DROP phone');
        $this->addSql('ALTER TABLE directory_contacts DROP address');
        $this->addSql('ALTER TABLE directory_contacts DROP created_at');
        $this->addSql('ALTER TABLE directory_contacts DROP updated_at');
    }
}