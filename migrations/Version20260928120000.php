<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store import batch processing error details for the admin detail page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE import_batches ADD error_code VARCHAR(100) DEFAULT NULL, ADD error_message VARCHAR(1000) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_batches DROP error_code, DROP error_message');
    }
}
