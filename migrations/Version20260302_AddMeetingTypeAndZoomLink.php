<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302_AddMeetingTypeAndZoomLink extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meeting_type and zoom_link columns to events table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD meeting_type VARCHAR(50) DEFAULT \'in-person\' NULL');
        $this->addSql('ALTER TABLE events ADD zoom_link VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events DROP meeting_type');
        $this->addSql('ALTER TABLE events DROP zoom_link');
    }
}
