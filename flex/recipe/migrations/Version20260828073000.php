<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a database-level unique constraint on translation(language_code, trans_key).
 *
 * Previously uniqueness was only enforced by the UniqueEntity validator (application level),
 * so concurrent inserts could create duplicates. If your table already contains duplicate
 * (language_code, trans_key) pairs, deduplicate them before running this migration — it will
 * fail otherwise.
 */
final class Version20260828073000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on translation(language_code, trans_key)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE translation DROP INDEX language_code_trans_key_idx');
        $this->addSql('ALTER TABLE translation ADD UNIQUE INDEX translation_language_code_trans_key_uniq (language_code, trans_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE translation DROP INDEX translation_language_code_trans_key_uniq');
        $this->addSql('ALTER TABLE translation ADD INDEX language_code_trans_key_idx (language_code, trans_key)');
    }
}
