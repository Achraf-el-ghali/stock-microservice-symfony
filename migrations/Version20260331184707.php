<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331184707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_lot (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(100) NOT NULL, quantity_initial INT NOT NULL, quantity_remaining INT NOT NULL, purchase_price DOUBLE PRECISION NOT NULL, selling_price DOUBLE PRECISION NOT NULL, date_entry DATETIME NOT NULL, import_reference VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7DEF9C8DF362FDAF (import_reference), INDEX idx_sku_date (sku, date_entry), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_movement (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(100) NOT NULL, lot_id INT DEFAULT NULL, type VARCHAR(10) NOT NULL, quantity INT NOT NULL, source VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock DROP reserved');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE stock_lot');
        $this->addSql('DROP TABLE stock_movement');
        $this->addSql('ALTER TABLE stock ADD reserved INT NOT NULL');
    }
}
