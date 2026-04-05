<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404165726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE processed_events (id INT AUTO_INCREMENT NOT NULL, event_id VARCHAR(255) NOT NULL, service_name VARCHAR(255) NOT NULL, processed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX unique_event_service (event_id, service_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE promotion (id INT AUTO_INCREMENT NOT NULL, description VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, value DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(100) NOT NULL, quantity INT NOT NULL, is_active TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_4B365660F9038C4 (sku), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_lot (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(100) NOT NULL, quantity_initial INT NOT NULL, quantity_remaining INT NOT NULL, purchase_price DOUBLE PRECISION NOT NULL, selling_price DOUBLE PRECISION NOT NULL, date_entry DATETIME NOT NULL, import_reference VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7DEF9C8DF362FDAF (import_reference), INDEX idx_sku_date (sku, date_entry), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_movement (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(100) NOT NULL, lot_id INT DEFAULT NULL, type VARCHAR(10) NOT NULL, quantity INT NOT NULL, source VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE processed_events');
        $this->addSql('DROP TABLE promotion');
        $this->addSql('DROP TABLE stock');
        $this->addSql('DROP TABLE stock_lot');
        $this->addSql('DROP TABLE stock_movement');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
