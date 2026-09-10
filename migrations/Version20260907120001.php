<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907120001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align OrderFinancialReversal index names with Doctrine ORM generated names.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE order_financial_reversals RENAME INDEX uniq_order_financial_reversal_order TO UNIQ_C99FA1D8D9F6D38'
        );
        $this->addSql(
            'ALTER TABLE order_financial_reversals RENAME INDEX idx_order_financial_reversal_user TO IDX_C99FA1DA76ED395'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE order_financial_reversals RENAME INDEX UNIQ_C99FA1D8D9F6D38 TO uniq_order_financial_reversal_order'
        );
        $this->addSql(
            'ALTER TABLE order_financial_reversals RENAME INDEX IDX_C99FA1DA76ED395 TO idx_order_financial_reversal_user'
        );
    }
}
