<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928133000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add supplemental informational product attributes without changing product core logic'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_attributes (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, product_id BIGINT UNSIGNED NOT NULL, attribute_name VARCHAR(100) NOT NULL, attribute_value VARCHAR(255) NOT NULL, sort_order INT UNSIGNED NOT NULL, INDEX idx_product_attribute_product (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE product_attributes ADD CONSTRAINT FK_PRODUCT_ATTRIBUTE_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_attributes');
    }
}
