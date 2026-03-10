<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enhance AuditLog entity with additional fields for comprehensive audit logging';
    }

    public function up(Schema $schema): void
    {
        // Add new columns to audit_logs table
        $this->addSql('ALTER TABLE audit_logs ADD entity_type VARCHAR(100) NOT NULL AFTER action');
        $this->addSql('ALTER TABLE audit_logs ADD entity_id INT DEFAULT NULL AFTER entity_type');
        $this->addSql('ALTER TABLE audit_logs ADD old_values JSON DEFAULT NULL AFTER entity_id');
        $this->addSql('ALTER TABLE audit_logs ADD new_values JSON DEFAULT NULL AFTER old_values');
        $this->addSql('ALTER TABLE audit_logs ADD ip_address VARCHAR(45) DEFAULT NULL AFTER new_values');
        $this->addSql('ALTER TABLE audit_logs ADD user_agent TEXT DEFAULT NULL AFTER ip_address');
        $this->addSql('ALTER TABLE audit_logs ADD description TEXT DEFAULT NULL AFTER user_agent');
        $this->addSql('ALTER TABLE audit_logs ADD created_at DATETIME NOT NULL AFTER user_id');
        
        // Add indexes for better performance
        $this->addSql('CREATE INDEX IDX_audit_logs_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX IDX_audit_logs_action ON audit_logs (action)');
        $this->addSql('CREATE INDEX IDX_audit_logs_created_at ON audit_logs (created_at)');
        $this->addSql('CREATE INDEX IDX_audit_logs_user_id ON audit_logs (user_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove indexes
        $this->addSql('DROP INDEX IDX_audit_logs_entity ON audit_logs');
        $this->addSql('DROP INDEX IDX_audit_logs_action ON audit_logs');
        $this->addSql('DROP INDEX IDX_audit_logs_created_at ON audit_logs');
        $this->addSql('DROP INDEX IDX_audit_logs_user_id ON audit_logs');
        
        // Remove columns
        $this->addSql('ALTER TABLE audit_logs DROP entity_type');
        $this->addSql('ALTER TABLE audit_logs DROP entity_id');
        $this->addSql('ALTER TABLE audit_logs DROP old_values');
        $this->addSql('ALTER TABLE audit_logs DROP new_values');
        $this->addSql('ALTER TABLE audit_logs DROP ip_address');
        $this->addSql('ALTER TABLE audit_logs DROP user_agent');
        $this->addSql('ALTER TABLE audit_logs DROP description');
        $this->addSql('ALTER TABLE audit_logs DROP created_at');
    }
}