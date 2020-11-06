<?php
declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Migrations\AbortMigrationException;

class Version20201106143839 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Add cloudflare stream meta data';
    }

    /**
     * @param Schema $schema
     * @return void
     * @throws AbortMigrationException
     */
    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('CREATE TABLE punktde_cloudflare_stream_domain_model_videometadata (persistence_object_identifier VARCHAR(40) NOT NULL, video VARCHAR(40) DEFAULT NULL, cloudflareuid VARCHAR(255) NOT NULL, thumbnailuri VARCHAR(255) NOT NULL, hlsuri VARCHAR(255) NOT NULL, dashuri VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_F6B477A27CC7DA2C (video), PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE punktde_cloudflare_stream_domain_model_videometadata ADD CONSTRAINT FK_F6B477A27CC7DA2C FOREIGN KEY (video) REFERENCES neos_media_domain_model_video (persistence_object_identifier)');
    }

    /**
     * @param Schema $schema
     * @return void
     * @throws AbortMigrationException
     */
    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('DROP TABLE punktde_cloudflare_stream_domain_model_videometadata');
    }
}
