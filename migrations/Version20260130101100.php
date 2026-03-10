<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130101100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create holidays table for holiday display system';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE holidays (
            id INT AUTO_INCREMENT NOT NULL, 
            name VARCHAR(255) NOT NULL, 
            date DATE NOT NULL, 
            description TEXT DEFAULT NULL, 
            type VARCHAR(50) NOT NULL, 
            color VARCHAR(7) NOT NULL, 
            is_recurring TINYINT(1) NOT NULL, 
            recurrence_pattern JSON DEFAULT NULL, 
            is_active TINYINT(1) NOT NULL, 
            year INT DEFAULT NULL, 
            country VARCHAR(100) DEFAULT NULL, 
            region VARCHAR(100) DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            PRIMARY KEY(id),
            INDEX IDX_9A7455E0AA9E377A (date),
            INDEX IDX_9A7455E08CDE5729 (type),
            INDEX IDX_9A7455E05373C966 (country),
            INDEX IDX_9A7455E0F62F176 (region),
            INDEX IDX_9A7455E0BB827337 (is_active)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE holidays');
    }
}