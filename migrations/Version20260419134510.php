<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419134510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make client fields nullable to support Google Sign-In';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client MODIFY telephone VARCHAR(20) NULL');
        $this->addSql('ALTER TABLE client MODIFY nationalite VARCHAR(150) NULL');
        $this->addSql('ALTER TABLE client MODIFY date_naissance DATE NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client MODIFY telephone VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE client MODIFY nationalite VARCHAR(150) NOT NULL');
        $this->addSql('ALTER TABLE client MODIFY date_naissance DATE NOT NULL');
    }
}
