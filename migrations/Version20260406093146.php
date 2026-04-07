<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406093146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo ADD date_taken DATETIME DEFAULT NULL, ADD location VARCHAR(255) DEFAULT NULL, ADD camera_model VARCHAR(255) DEFAULT NULL, CHANGE mime_type mime_type VARCHAR(50) NOT NULL, CHANGE prestation_reference prestation_reference VARCHAR(255) DEFAULT NULL, CHANGE internal_order internal_order VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo DROP date_taken, DROP location, DROP camera_model, CHANGE mime_type mime_type VARCHAR(100) NOT NULL, CHANGE prestation_reference prestation_reference VARCHAR(100) DEFAULT NULL, CHANGE internal_order internal_order VARCHAR(100) DEFAULT NULL');
    }
}
