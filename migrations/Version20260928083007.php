<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928083007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_attributes (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, attribute_name VARCHAR(100) NOT NULL, attribute_value VARCHAR(255) NOT NULL, sort_order INT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL, INDEX idx_product_attribute_product (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product_attributes ADD CONSTRAINT FK_A2FCC15B4584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_attributes DROP FOREIGN KEY FK_A2FCC15B4584665A');
        $this->addSql('DROP TABLE product_attributes');
    }
}
