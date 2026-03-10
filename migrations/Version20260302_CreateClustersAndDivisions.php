<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302_CreateClustersAndDivisions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create office_clusters and divisions tables, add cluster_id to offices';
    }

    public function up(Schema $schema): void
    {
        // Create office_clusters table
        $this->addSql('CREATE TABLE office_clusters (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            color VARCHAR(7) DEFAULT NULL,
            display_order INT NOT NULL,
            is_active TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_cluster_code (code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create divisions table
        $this->addSql('CREATE TABLE divisions (
            id INT AUTO_INCREMENT NOT NULL,
            office_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            display_order INT NOT NULL,
            is_active TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_division_code (code),
            INDEX IDX_divisions_office (office_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add cluster_id to offices table
        $this->addSql('ALTER TABLE offices ADD cluster_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE offices ADD INDEX IDX_offices_cluster (cluster_id)');

        // Add foreign keys
        $this->addSql('ALTER TABLE divisions ADD CONSTRAINT FK_divisions_office 
            FOREIGN KEY (office_id) REFERENCES offices (id)');
        $this->addSql('ALTER TABLE offices ADD CONSTRAINT FK_offices_cluster 
            FOREIGN KEY (cluster_id) REFERENCES office_clusters (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign keys
        $this->addSql('ALTER TABLE divisions DROP FOREIGN KEY FK_divisions_office');
        $this->addSql('ALTER TABLE offices DROP FOREIGN KEY FK_offices_cluster');

        // Remove cluster_id from offices
        $this->addSql('ALTER TABLE offices DROP INDEX IDX_offices_cluster');
        $this->addSql('ALTER TABLE offices DROP cluster_id');

        // Drop tables
        $this->addSql('DROP TABLE divisions');
        $this->addSql('DROP TABLE office_clusters');
    }
}
