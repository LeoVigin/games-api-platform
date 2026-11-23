<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120153440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__game AS SELECT id, name, description, release_date, rawg_id, publishers_id FROM game');
        $this->addSql('DROP TABLE game');
        $this->addSql('CREATE TABLE game (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(400) NOT NULL, release_date DATE NOT NULL, rawg_id VARCHAR(50) DEFAULT NULL, publishers_id INTEGER NOT NULL, CONSTRAINT FK_232B318C9BED9AFD FOREIGN KEY (publishers_id) REFERENCES publishers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO game (id, name, description, release_date, rawg_id, publishers_id) SELECT id, name, description, release_date, rawg_id, publishers_id FROM __temp__game');
        $this->addSql('DROP TABLE __temp__game');
        $this->addSql('CREATE INDEX IDX_232B318C9BED9AFD ON game (publishers_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C51302DC4 ON game (rawg_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__game AS SELECT id, rawg_id, name, description, release_date, publishers_id FROM game');
        $this->addSql('DROP TABLE game');
        $this->addSql('CREATE TABLE game (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, rawg_id VARCHAR(50) DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(400) NOT NULL, release_date VARCHAR(10) NOT NULL, publishers_id INTEGER NOT NULL, CONSTRAINT FK_232B318C9BED9AFD FOREIGN KEY (publishers_id) REFERENCES publishers (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO game (id, rawg_id, name, description, release_date, publishers_id) SELECT id, rawg_id, name, description, release_date, publishers_id FROM __temp__game');
        $this->addSql('DROP TABLE __temp__game');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C51302DC4 ON game (rawg_id)');
        $this->addSql('CREATE INDEX IDX_232B318C9BED9AFD ON game (publishers_id)');
    }
}
