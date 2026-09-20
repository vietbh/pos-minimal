<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260919235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add product categories persistence and nullable product category relation.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('product_categories')) {
            $this->addSql(
                'CREATE TABLE product_categories (
                    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    is_active TINYINT DEFAULT 1 NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE INDEX UNIQ_PRODUCT_CATEGORY_NAME (name),
                    INDEX idx_product_category_active_name (is_active, name),
                    PRIMARY KEY (id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
            );
        }

        $products = $schema->getTable('products');

        if (!$products->hasColumn('category_id')) {
            $this->addSql(
                'ALTER TABLE products ADD category_id BIGINT UNSIGNED DEFAULT NULL'
            );
        }

        if (!$products->hasIndex('idx_product_category')) {
            $this->addSql(
                'CREATE INDEX idx_product_category ON products (category_id)'
            );
        }

        if (!$this->foreignKeyExists($products, 'FK_PRODUCT_CATEGORY')) {
            $this->addSql(
                'ALTER TABLE products ADD CONSTRAINT FK_PRODUCT_CATEGORY FOREIGN KEY (category_id) REFERENCES product_categories (id) ON DELETE RESTRICT'
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('products')) {
            $products = $schema->getTable('products');

            if ($this->foreignKeyExists($products, 'FK_PRODUCT_CATEGORY')) {
                $this->addSql('ALTER TABLE products DROP FOREIGN KEY FK_PRODUCT_CATEGORY');
            }

            if ($products->hasIndex('idx_product_category')) {
                $this->addSql('DROP INDEX idx_product_category ON products');
            }

            if ($products->hasColumn('category_id')) {
                $this->addSql('ALTER TABLE products DROP category_id');
            }
        }

        if ($schema->hasTable('product_categories')) {
            $this->addSql('DROP TABLE product_categories');
        }
    }

    private function foreignKeyExists(\Doctrine\DBAL\Schema\Table $table, string $name): bool
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getName() === $name) {
                return true;
            }
        }

        return false;
    }
}
