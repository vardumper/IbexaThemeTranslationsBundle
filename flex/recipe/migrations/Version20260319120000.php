<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260319120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates translation and translation_draft tables for IbexaThemeTranslationsBundle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE translation (
                id INT AUTO_INCREMENT NOT NULL,
                language_code VARCHAR(255) NOT NULL,
                translation VARCHAR(1000) DEFAULT NULL,
                trans_key VARCHAR(255) NOT NULL,
                INDEX language_code_trans_key_idx (language_code, trans_key),
                INDEX translation_idx (translation(191)),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE translation_draft (
                id INT AUTO_INCREMENT NOT NULL,
                language_code VARCHAR(255) NOT NULL,
                trans_key VARCHAR(255) NOT NULL,
                translation VARCHAR(1000) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX draft_language_code_trans_key_idx (language_code, trans_key),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE translation');
        $this->addSql('DROP TABLE translation_draft');
    }
}
