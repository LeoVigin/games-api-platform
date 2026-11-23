<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120131659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game_platform (game_id INTEGER NOT NULL, platform_id INTEGER NOT NULL, PRIMARY KEY (game_id, platform_id), CONSTRAINT FK_92162FEDE48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_92162FEDFFE6496F FOREIGN KEY (platform_id) REFERENCES platform (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_92162FEDE48FD905 ON game_platform (game_id)');
        $this->addSql('CREATE INDEX IDX_92162FEDFFE6496F ON game_platform (platform_id)');
        $this->addSql('CREATE TABLE game_genre (game_id INTEGER NOT NULL, genre_id INTEGER NOT NULL, PRIMARY KEY (game_id, genre_id), CONSTRAINT FK_B1634A77E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B1634A774296D31F FOREIGN KEY (genre_id) REFERENCES genre (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B1634A77E48FD905 ON game_genre (game_id)');
        $this->addSql('CREATE INDEX IDX_B1634A774296D31F ON game_genre (genre_id)');
        $this->addSql('CREATE TABLE genre (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_835033F85E237E06 ON genre (name)');
        $this->addSql('CREATE TABLE platform (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3952D0CB5E237E06 ON platform (name)');
        $this->addSql('CREATE TABLE publishers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_842DC37B5E237E06 ON publishers (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__game AS SELECT id, name, description, release_date FROM game');
        $this->addSql('DROP TABLE game');
        $this->addSql('CREATE TABLE game (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(400) NOT NULL, release_date VARCHAR(10) NOT NULL, rawg_id VARCHAR(50) DEFAULT NULL, publishers_id INTEGER NOT NULL, CONSTRAINT FK_232B318C9BED9AFD FOREIGN KEY (publishers_id) REFERENCES publishers (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO game (id, name, description, release_date) SELECT id, name, description, release_date FROM __temp__game');
        $this->addSql('DROP TABLE __temp__game');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_232B318C51302DC4 ON game (rawg_id)');
        $this->addSql('CREATE INDEX IDX_232B318C9BED9AFD ON game (publishers_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE game_platform');
        $this->addSql('DROP TABLE game_genre');
        $this->addSql('DROP TABLE genre');
        $this->addSql('DROP TABLE platform');
        $this->addSql('DROP TABLE publishers');
        $this->addSql('CREATE TEMPORARY TABLE __temp__game AS SELECT id, name, description, release_date FROM game');
        $this->addSql('DROP TABLE game');
        $this->addSql('CREATE TABLE game (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(400) NOT NULL, release_date VARCHAR(10) NOT NULL)');
        $this->addSql('INSERT INTO game (id, name, description, release_date) SELECT id, name, description, release_date FROM __temp__game');
        $this->addSql('DROP TABLE __temp__game');
    }
}
