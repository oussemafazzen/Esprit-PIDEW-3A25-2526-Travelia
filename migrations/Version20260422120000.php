<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add booked flight snapshot columns on billet (trip, class, fare, stops, duration, codes, return date)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billet ADD booked_trip_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE billet ADD booked_travel_class VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE billet ADD booked_fare_label VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE billet ADD booked_stops_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE billet ADD booked_duration_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE billet ADD booked_origin_code VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE billet ADD booked_destination_code VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE billet ADD booked_return_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billet DROP booked_trip_type');
        $this->addSql('ALTER TABLE billet DROP booked_travel_class');
        $this->addSql('ALTER TABLE billet DROP booked_fare_label');
        $this->addSql('ALTER TABLE billet DROP booked_stops_count');
        $this->addSql('ALTER TABLE billet DROP booked_duration_minutes');
        $this->addSql('ALTER TABLE billet DROP booked_origin_code');
        $this->addSql('ALTER TABLE billet DROP booked_destination_code');
        $this->addSql('ALTER TABLE billet DROP booked_return_date');
    }
}
