<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260718231000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial database schema for MTShop.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, shipping_address LONGTEXT NOT NULL, billing_address LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, price NUMERIC(10, 2) NOT NULL, order_count INT NOT NULL, stock INT NOT NULL, image_name VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) NOT NULL, slug VARCHAR(255) NOT NULL, category VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_B3BA5A5A989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE carts (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, INDEX IDX_4E004AAC9395C3F3 (customer_id), INDEX IDX_4E004AAC4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, status VARCHAR(50) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, shipping_address LONGTEXT NOT NULL, billing_address LONGTEXT NOT NULL, payment_method VARCHAR(100) DEFAULT NULL, payment_status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_E52FFDEE9395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_items (id INT AUTO_INCREMENT NOT NULL, order_ref_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, total_price NUMERIC(10, 2) NOT NULL, INDEX IDX_62809DB0E238517C (order_ref_id), INDEX IDX_62809DB04584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE carts ADD CONSTRAINT FK_4E004AAC9395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE carts ADD CONSTRAINT FK_4E004AAC4584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE9395C3F3 FOREIGN KEY (customer_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB0E238517C FOREIGN KEY (order_ref_id) REFERENCES orders (id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB0E238517C');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB04584665A');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE9395C3F3');
        $this->addSql('ALTER TABLE carts DROP FOREIGN KEY FK_4E004AAC9395C3F3');
        $this->addSql('ALTER TABLE carts DROP FOREIGN KEY FK_4E004AAC4584665A');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE carts');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE users');
    }
}
