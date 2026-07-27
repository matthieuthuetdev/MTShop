<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724234000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove delivery defaults from orders to match Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders CHANGE delivery_method delivery_method VARCHAR(50) NOT NULL, CHANGE delivery_fee delivery_fee NUMERIC(10, 2) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE orders CHANGE delivery_method delivery_method VARCHAR(50) NOT NULL DEFAULT 'basic', CHANGE delivery_fee delivery_fee NUMERIC(10, 2) NOT NULL DEFAULT 0");
    }
}
