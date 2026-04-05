<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404223210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY `avis_ibfk_1`');
        $this->addSql('ALTER TABLE billet DROP FOREIGN KEY `billet_ibfk_1`');
        $this->addSql('ALTER TABLE face_data DROP FOREIGN KEY `fk_face_user`');
        $this->addSql('ALTER TABLE inscriptionactivite DROP FOREIGN KEY `inscriptionactivite_ibfk_1`');
        $this->addSql('ALTER TABLE inscriptionactivite DROP FOREIGN KEY `inscriptionactivite_ibfk_2`');
        $this->addSql('ALTER TABLE paiement DROP FOREIGN KEY `fk_paiement_reservation`');
        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY `fk_token_user`');
        $this->addSql('ALTER TABLE photo_avis DROP FOREIGN KEY `photo_avis_ibfk_1`');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `reservation_ibfk_1`');
        $this->addSql('ALTER TABLE reservationhebergement DROP FOREIGN KEY `reservationhebergement_ibfk_1`');
        $this->addSql('ALTER TABLE reservationhebergement DROP FOREIGN KEY `reservationhebergement_ibfk_2`');
        $this->addSql('ALTER TABLE security_log DROP FOREIGN KEY `fk_log_user`');
        $this->addSql('DROP TABLE activite');
        $this->addSql('DROP TABLE avis');
        $this->addSql('DROP TABLE billet');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE face_data');
        $this->addSql('DROP TABLE hebergement');
        $this->addSql('DROP TABLE inscriptionactivite');
        $this->addSql('DROP TABLE paiement');
        $this->addSql('DROP TABLE paiement_reservation');
        $this->addSql('DROP TABLE password_reset_token');
        $this->addSql('DROP TABLE photo_avis');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE reservationhebergement');
        $this->addSql('DROP TABLE security_log');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite (id_activite INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, lieu VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, duree INT DEFAULT NULL, prix DOUBLE PRECISION DEFAULT NULL, capacite_max INT DEFAULT NULL, categorie VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (id_activite)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE avis (id_avis INT AUTO_INCREMENT NOT NULL, commentaire TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, note INT NOT NULL, date_publication DATETIME DEFAULT CURRENT_TIMESTAMP, type_service VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_service INT NOT NULL, id_client INT NOT NULL, INDEX id_client (id_client), PRIMARY KEY (id_avis)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE billet (id_billet INT AUTO_INCREMENT NOT NULL, type_transport VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, numero_billet VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_depart DATE NOT NULL, date_arrivee DATE NOT NULL, prix DOUBLE PRECISION NOT NULL, statut VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_reservation INT NOT NULL, INDEX id_reservation (id_reservation), PRIMARY KEY (id_billet)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prenom VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, email VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, telephone VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, nationalite VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_naissance DATE NOT NULL, role VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'USER\' NOT NULL COLLATE `utf8mb4_general_ci`, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'ACTIF\' NOT NULL COLLATE `utf8mb4_general_ci`, points_fidelite INT DEFAULT 0 NOT NULL, niveau_fidelite VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'BRONZE\' NOT NULL COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, derniere_connexion DATETIME DEFAULT NULL, failed_attempts INT DEFAULT 0 NOT NULL, google_id VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, email_confirmed TINYINT DEFAULT 0, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE face_data (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, face_token VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, face_encoding TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX user_id (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE hebergement (id_hebergement INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, adresse VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, ville VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, pays VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, capacite INT NOT NULL, equipements TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, tarif_par_nuit DOUBLE PRECISION NOT NULL, PRIMARY KEY (id_hebergement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE inscriptionactivite (id_inscription INT AUTO_INCREMENT NOT NULL, date_activite DATE DEFAULT NULL, nombre_participants INT DEFAULT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, id_client INT DEFAULT NULL, id_activite INT DEFAULT NULL, INDEX id_activite (id_activite), INDEX id_client (id_client), PRIMARY KEY (id_inscription)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE paiement (id_paiement INT AUTO_INCREMENT NOT NULL, date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, montant DOUBLE PRECISION NOT NULL, methode_paiement VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_reservation INT NOT NULL, INDEX fk_paiement_reservation (id_reservation), PRIMARY KEY (id_paiement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE paiement_reservation (id_paiement INT AUTO_INCREMENT NOT NULL, date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, montant DOUBLE PRECISION NOT NULL, methode_paiement VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_reservation INT NOT NULL, PRIMARY KEY (id_paiement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE password_reset_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, expiry_date DATETIME NOT NULL, used TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX fk_token_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE photo_avis (id_photo INT AUTO_INCREMENT NOT NULL, chemin_fichier VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, legende VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, id_avis INT NOT NULL, INDEX id_avis (id_avis), PRIMARY KEY (id_photo)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reservation (id_reservation INT AUTO_INCREMENT NOT NULL, date_reservation DATE NOT NULL, statut VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, modalites_paiement VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_client INT NOT NULL, paysdestination VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, INDEX id_client (id_client), PRIMARY KEY (id_reservation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reservationhebergement (id_reservation_hebergement INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, nombre_personnes INT NOT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_client INT NOT NULL, id_hebergement INT NOT NULL, INDEX id_client (id_client), INDEX id_hebergement (id_hebergement), PRIMARY KEY (id_reservation_hebergement)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE security_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, event_type VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, ip_address VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, details TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX fk_log_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (id_client) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT `billet_ibfk_1` FOREIGN KEY (id_reservation) REFERENCES reservation (id_reservation) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE face_data ADD CONSTRAINT `fk_face_user` FOREIGN KEY (user_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscriptionactivite ADD CONSTRAINT `inscriptionactivite_ibfk_1` FOREIGN KEY (id_activite) REFERENCES activite (id_activite) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscriptionactivite ADD CONSTRAINT `inscriptionactivite_ibfk_2` FOREIGN KEY (id_client) REFERENCES client (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paiement ADD CONSTRAINT `fk_paiement_reservation` FOREIGN KEY (id_reservation) REFERENCES reservationhebergement (id_reservation_hebergement) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT `fk_token_user` FOREIGN KEY (user_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE photo_avis ADD CONSTRAINT `photo_avis_ibfk_1` FOREIGN KEY (id_avis) REFERENCES avis (id_avis) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (id_client) REFERENCES client (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservationhebergement ADD CONSTRAINT `reservationhebergement_ibfk_1` FOREIGN KEY (id_hebergement) REFERENCES hebergement (id_hebergement) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservationhebergement ADD CONSTRAINT `reservationhebergement_ibfk_2` FOREIGN KEY (id_client) REFERENCES client (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE security_log ADD CONSTRAINT `fk_log_user` FOREIGN KEY (user_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
