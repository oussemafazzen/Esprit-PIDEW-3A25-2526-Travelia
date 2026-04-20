<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add billet destination column for per-ticket destination display';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billet ADD destination VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billet DROP destination');
    }
}
