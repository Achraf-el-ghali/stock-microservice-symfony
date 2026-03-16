<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316162159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_promotion (stock_id INT NOT NULL, promotion_id INT NOT NULL, INDEX IDX_59809B39DCD6110 (stock_id), INDEX IDX_59809B39139DF194 (promotion_id), PRIMARY KEY(stock_id, promotion_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE stock_promotion ADD CONSTRAINT FK_59809B39DCD6110 FOREIGN KEY (stock_id) REFERENCES stock (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_promotion ADD CONSTRAINT FK_59809B39139DF194 FOREIGN KEY (promotion_id) REFERENCES promotion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE promotion DROP FOREIGN KEY FK_C11D7DD1DCD6110');
        $this->addSql('DROP INDEX IDX_C11D7DD1DCD6110 ON promotion');
        $this->addSql('ALTER TABLE promotion DROP stock_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_promotion DROP FOREIGN KEY FK_59809B39DCD6110');
        $this->addSql('ALTER TABLE stock_promotion DROP FOREIGN KEY FK_59809B39139DF194');
        $this->addSql('DROP TABLE stock_promotion');
        $this->addSql('ALTER TABLE promotion ADD stock_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE promotion ADD CONSTRAINT FK_C11D7DD1DCD6110 FOREIGN KEY (stock_id) REFERENCES stock (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_C11D7DD1DCD6110 ON promotion (stock_id)');
    }
}
