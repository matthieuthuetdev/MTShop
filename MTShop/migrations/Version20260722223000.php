<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add two-factor authentication fields to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD two_factor_code_hash VARCHAR(255) DEFAULT NULL, ADD two_factor_code_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP two_factor_enabled, DROP two_factor_code_hash, DROP two_factor_code_expires_at');
    }
}
