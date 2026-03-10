<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add event_offices table for many-to-many relationship between events and offices
 */
final class Version20260203120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_offices table for tagging multiple offices to events';
    }

    public function up(Schema $schema): void
    {
        // Create the event_offices junction table
        $this->addSql('CREATE TABLE event_offices (
            event_id INT NOT NULL, 
            office_id INT NOT NULL, 
            INDEX IDX_EVENT_OFFICES_EVENT (event_id), 
            INDEX IDX_EVENT_OFFICES_OFFICE (office_id), 
            PRIMARY KEY(event_id, office_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign key constraints
        $this->addSql('ALTER TABLE event_offices ADD CONSTRAINT FK_EVENT_OFFICES_EVENT FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_offices ADD CONSTRAINT FK_EVENT_OFFICES_OFFICE FOREIGN KEY (office_id) REFERENCES offices (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop the event_offices table
        $this->addSql('ALTER TABLE event_offices DROP FOREIGN KEY FK_EVENT_OFFICES_EVENT');
        $this->addSql('ALTER TABLE event_offices DROP FOREIGN KEY FK_EVENT_OFFICES_OFFICE');
        $this->addSql('DROP TABLE event_offices');
    }
}