<?php
// Définition de la base de données
// À utiliser en tant qu'utilisateur root
// Champs à remplacer : $base, $mdp, $serveur,
// $login, $nom, $prenom, $mail, $titre, $cle_matiere, $nom_matiere
//
// Ces définitions sont utilisables en php directement ou en shell linux par
// sed '1,/[ ]FIN/d ; N;$!P;$!D;$d' def_sql.php | sed "s/\\\$base/$BASE/g;s/\\\$serveur/$SERVEUR/g;..."

$requete = <<< FIN

DROP DATABASE IF EXISTS `$base`;
CREATE DATABASE `$base` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
DELETE FROM mysql.user WHERE User = '$base' OR User = '$base-adm';
INSERT INTO mysql.user (Host, User, Authentication_string) 
  VALUES ('$serveur', '$base', PASSWORD('$mdp')),
         ('$serveur', '$base-adm', PASSWORD('$mdp'));
DELETE FROM mysql.db WHERE Db = '$base';
INSERT INTO mysql.db (Host, Db, User, Select_priv, Insert_priv, Update_priv, Delete_priv, Alter_priv, Drop_priv) 
  VALUES ('$serveur', '$base', '$base', 'Y', 'N', 'N', 'N', 'N', 'N'),
         ('$serveur', '$base','$base-adm', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
FLUSH PRIVILEGES;
USE `$base`;

-- colles, cdt, docs : 0 si vide, 1 si présent
-- notes,copies : 0 si vide, 1 si présent, 2 si désactivées
-- *_protection : valeur numérique de gestion de la protection. Si nul, autorisation à tous, sans
--    nécessité de connexion identifiée. Si entre 1 et 32, conversion de la valeur binaire PLCEI
--    (profs,lycée,colleurs,élèves,invités) à laquelle on a ajouté 1. Chaque 0 correspond
--    aux accès autorisés, chaque 1 correspond aux protections (accès interdit pour ce type de compte).
--    Exemple : 10->PLCEI=9=01001->autorisé pour P,C,E et interdit pour L et I.
--    Le code 32 (interdit pour tous) correspond aux fonctions désactivées et aux documents/répertoires
--    non visibles : plus d'affichage dans le menu, plus d'accès.
-- dureecolle : durée pour un élève, en minutes
CREATE TABLE `matieres` (
  `id` tinyint(2) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `ordre` tinyint(2) unsigned NOT NULL,
  `cle` varchar(50) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `colles` tinyint(1) unsigned NOT NULL,
  `cdt` tinyint(1) unsigned NOT NULL,
  `docs` tinyint(1) unsigned NOT NULL,
  `notes` tinyint(1) unsigned NOT NULL,
  `copies` tinyint(1) unsigned NOT NULL,
  `colles_protection` tinyint(1) unsigned NOT NULL,
  `cdt_protection` tinyint(1) unsigned NOT NULL,
  `docs_protection` tinyint(1) unsigned NOT NULL,
  `dureecolle` tinyint(2) unsigned NOT NULL,
  KEY `colles` (`colles`),
  KEY `cdt` (`cdt`),
  KEY `docs` (`docs`),
  KEY `notes` (`notes`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- autorisation : type d'utilisateur (1:invité, 2:élève, 3:colleur, 4:lycée, 5:professeur)
-- mdp : stockage du mot de passe sur 40 caractères
--       * si commence par un ? : invitation non répondue (mot de passe non défini)
--       * si commence par un * : compte demandé en attente de validation
--       * si commence par un ! : compte suspendu
-- mailexp : nom d'expédition des courriels
-- mailcopie : si par défaut envoi personnel d'une copie de ses courriels
-- permconn : token d'identification légère, par cookie
-- lastconn : horodatage de la connexion actuelle
CREATE TABLE `utilisateurs` (
  `id` smallint(3) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `login` varchar(50) NOT NULL UNIQUE,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `mail` varchar(50) NOT NULL,
  `autorisation` tinyint(1) UNSIGNED NOT NULL,
  `mdp` char(41) NOT NULL,
  `matieres` varchar(50) NOT NULL,
  `timeout` smallint(4) UNSIGNED NOT NULL,
  `mailexp` varchar(50) NOT NULL,
  `mailcopie` tinyint(1) UNSIGNED NOT NULL,
  `permconn` varchar(10) NOT NULL,
  `lastconn` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
  
CREATE TABLE `pages` (
  `id` tinyint(2) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `ordre` tinyint(2) unsigned NOT NULL,
  `cle` varchar(50) NOT NULL,
  `mat` tinyint(2) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `titre` text NOT NULL,
  `bandeau` text NOT NULL,
  `protection` tinyint(1) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `infos` (
  `id` smallint(4) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `ordre` tinyint(2) unsigned NOT NULL,
  `page` tinyint(2) unsigned NOT NULL,
  `cache` tinyint(1) unsigned NOT NULL,
  `titre` text NOT NULL,
  `texte` text NOT NULL,
  `protection` tinyint(1) unsigned NOT NULL,
  KEY `ordre` (`ordre`,`page`),
  KEY `cache` (`cache`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `semaines` (
  `id` tinyint(2) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `debut` date NOT NULL,
  `colle` tinyint(1) unsigned NOT NULL,
  `vacances` tinyint(1) unsigned NOT NULL,
  KEY `debut` (`debut`),
  KEY `colle` (`colle`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `vacances` (
  `id` tinyint(2) unsigned NOT NULL PRIMARY KEY,
  `nom` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `colles` (
  `id` tinyint(3) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `semaine` tinyint(2) unsigned NOT NULL,
  `matiere` tinyint(2) unsigned NOT NULL,
  `texte` text NOT NULL,
  `cache` tinyint(1) NOT NULL,
  KEY `semaine` (`semaine`),
  KEY `matiere` (`matiere`),
  KEY `cache` (`cache`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `cdt` (
  `id` smallint(3) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `matiere` tinyint(2) unsigned NOT NULL,
  `semaine` tinyint(2) unsigned NOT NULL,
  `jour` date NOT NULL,
  `h_debut` time NOT NULL,
  `h_fin` time NOT NULL,
  `pour` date NOT NULL,
  `type` tinyint(2) unsigned NOT NULL,
  `texte` text NOT NULL,
  `demigroupe` tinyint(1) unsigned NOT NULL,
  `cache` tinyint(1) unsigned NOT NULL,
  `protection` tinyint(1) unsigned NOT NULL,
  KEY `matiere` (`matiere`),
  KEY `semaine` (`semaine`),
  KEY `type` (`type`),
  KEY `cache` (`cache`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `cdt-types` (
  `id` tinyint(2) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `matiere` tinyint(2) unsigned NOT NULL,
  `ordre` tinyint(2) unsigned NOT NULL,
  `titre` varchar(50) NOT NULL,
  `cle` varchar(20) NOT NULL,
  `deb_fin_pour` tinyint(1) unsigned NOT NULL,
  `nb` tinyint(2) unsigned NOT NULL,
  KEY `matiere` (`matiere`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `cdt-seances` (
  `id` tinyint(2) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `matiere` tinyint(2) unsigned NOT NULL,
  `ordre` tinyint(2) unsigned NOT NULL,
  `nom` varchar(40) NOT NULL,
  `jour` tinyint(1) unsigned NOT NULL,
  `h_debut` time NOT NULL,
  `h_fin` time NOT NULL,
  `type` tinyint(3) unsigned NOT NULL,
  `demigroupe` tinyint(1) unsigned NOT NULL,
  `protection` tinyint(1) unsigned NOT NULL,
  `template` text NOT NULL,
  KEY `matiere` (`matiere`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `reps` (
  `id` smallint(3) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `parent` smallint(3) unsigned NOT NULL,
  `parents` varchar(50) NOT NULL,
  `matiere` tinyint(2) unsigned NOT NULL,
  `nom` varchar(100) NOT NULL,
  `protection` tinyint(1) unsigned NOT NULL,
  `menu` tinyint(1) unsigned NOT NULL,
  KEY `parent` (`parent`),
  KEY `matiere` (`matiere`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE `docs` (
  `id` smallint(3) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `parent` smallint(3) unsigned NOT NULL,
  `parents` varchar(50) NOT NULL,
  `matiere` tinyint(2) unsigned NOT NULL,
  `nom` varchar(100) NOT NULL,
  `nom_nat` VARCHAR(100) NOT NULL,
  `upload` date NOT NULL,
  `taille` varchar(12) NOT NULL,
  `lien` char(15) NOT NULL,
  `ext` varchar(5) NOT NULL,
  `protection` tinyint(1) unsigned NOT NULL,
  `dispo` datetime NOT NULL,
  KEY `parent` (`parent`),
  KEY `matiere` (`matiere`),
  KEY `nom_nat` (`nom_nat`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE `recents` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `type` tinyint(1) UNSIGNED NOT NULL,
  `publi` datetime NOT NULL,
  `maj` datetime NOT NULL,
  `titre` varchar(200) NOT NULL,
  `lien` varchar(30) NOT NULL,
  `texte` text NOT NULL,
  `protection` tinyint(1) UNSIGNED NOT NULL,
  `matiere` tinyint(2) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`,`type`),
  KEY `publi` (`publi`),
  KEY `maj` (`maj`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE notes (
  `id` smallint(5) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `semaine` tinyint(2) unsigned NOT NULL,
  `heure` smallint(3) unsigned NOT NULL,
  `eleve` smallint(3) unsigned NOT NULL,
  `colleur` smallint(3) unsigned NOT NULL,
  `matiere` tinyint(2) unsigned NOT NULL,
  `note` varchar(4) NOT NULL,
  KEY `semaine` (`semaine`),
  KEY `heure` (`heure`),
  KEY `eleve` (`eleve`),
  KEY `colleur` (`colleur`),
  KEY `matiere` (`matiere`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE heurescolles (
  `id` smallint(5) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `colleur` smallint(3) unsigned NOT NULL,
  `matiere` tinyint(2) unsigned NOT NULL,
  `jour` date NOT NULL,
  `heure` time NOT NULL,
  `duree` smallint(3) unsigned NOT NULL,
  `description` varchar(200) NOT NULL,
  `releve` date NOT NULL,
  KEY `colleur` (`colleur`),
  KEY `matiere` (`matiere`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE groupes (
  `id` tinyint(2) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `nom_nat` varchar(50) NOT NULL,
  `mails` tinyint(1) UNSIGNED NOT NULL,
  `notes` tinyint(1) UNSIGNED NOT NULL,
  `utilisateurs` varchar(250) NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE `agenda` (
  `id` smallint(3) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `matiere` tinyint(2) unsigned NOT NULL,
  `type` tinyint(2) unsigned NOT NULL,
  `debut` datetime NOT NULL,
  `fin` datetime NOT NULL,
  `texte` text NOT NULL,
  KEY `matiere` (`matiere`),
  KEY `type` (`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `agenda-types` (
  `id` tinyint(2) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `ordre` tinyint(2) unsigned NOT NULL,
  `cle` varchar(20) NOT NULL,
  `couleur` varchar(6) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `prefs` (
  `nom` varchar(50) NOT NULL,
  `val` smallint(3) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- description affichée comme titre du devoir
-- nom affiché en début de nom des fichiers
CREATE TABLE `devoirs` (
  `id` smallint(5) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `matiere` tinyint(2) unsigned NOT NULL,
  `deadline` datetime NOT NULL,
  `description` varchar(100) NOT NULL,
  `nom` varchar(15) NOT NULL,
  `nom_nat` varchar(20) NOT NULL,
  `lien` char(15) NOT NULL,
  `indications` text NOT NULL,
  `dispo` datetime NOT NULL,
  KEY `matiere` (`matiere`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- numero sert à numéroter les envois et à savoir s'il s'agit d'une
--  copie (numero<100) ou d'une correction (numero>100)
CREATE TABLE `copies` (
  `id` smallint(5) unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `devoir` tinyint(2) unsigned NOT NULL,
  `eleve` smallint(3) unsigned NOT NULL,
  `matiere` tinyint(2) unsigned NOT NULL,
  `numero` tinyint(2) unsigned NOT NULL,
  `upload` datetime NOT NULL,
  `taille` varchar(12) NOT NULL,
  `ext` varchar(5) NOT NULL,
  KEY `devoir` (`devoir`),
  KEY `matiere` (`matiere`),
  KEY `eleve` (`eleve`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

INSERT INTO prefs (nom,val)
  VALUES ('creation_compte',1),
         ('nb_agenda_index',10),
         ('protection_agenda',0),
         ('autorisation_mails',61440);

INSERT INTO utilisateurs (id,login,prenom,nom,mail,mdp,autorisation,matieres,timeout,mailexp,mailcopie)
  VALUES (1, '$login', '$prenom', '$nom', '$mail', '?', 5, '0,1', 900, '$prenom $nom', 1);

INSERT INTO matieres (id,ordre,cle,nom)
  VALUES (1, 1, '$cle_matiere', '$nom_matiere');

INSERT INTO reps (id,parents,matiere,nom)
  VALUES (1, '0', 0, 'Général'),
         (2, '0', 1, '$nom_matiere');

INSERT INTO pages (ordre,cle,nom,titre,bandeau)
  VALUES (1, 'accueil', 'Accueil', '$titre', 'Dernières informations importantes');

INSERT INTO `cdt-types` (matiere,ordre,cle,titre,deb_fin_pour)
  VALUES (1, 1, 'cours', 'Cours', 1),
         (1, 2, 'TD', 'Séance de travaux dirigés', 1),
         (1, 3, 'TP', 'Séance de travaux pratiques', 1),
         (1, 4, 'DS', 'Devoir surveillé', 1),
         (1, 5, 'interros', 'Interrogation de cours', 0),
         (1, 6, 'distributions', 'Distribution de document', 0),
         (1, 7, 'DM', 'Devoir maison', 2);

INSERT INTO `agenda-types` (id,ordre,nom,cle,couleur)
  VALUES (3, 1, 'Cours', 'cours', 'CC6633'),
         (4, 2, 'Pas de cours', 'pascours', 'CC9933'),
         (5, 3, 'Devoir surveillé', 'DS', '6633CC'),
         (6, 4, 'Devoir maison', 'DM', '99CC33'),
         (9, 5, 'Divers','div', 'CCCC33'),
         (1, 6, 'Déplacement de colle', 'depl_colle', '33CCCC'),
         (2, 7, 'Rattrapage de colle', 'ratt_colle', '3399CC'),
         (7, 8, 'Jour férié', 'fer', 'CC3333'),
         (8, 9, 'Vacances', 'vac', '66CC33');

INSERT INTO semaines (id,debut) VALUES 
   (1,'2019-09-02'), (2,'2019-09-09'), (3,'2019-09-16'), (4,'2019-09-23'), (5,'2019-09-30'),
   (6,'2019-10-07'), (7,'2019-10-14'), (8,'2019-10-21'), (9,'2019-10-28'),(10,'2019-11-04'),
  (11,'2019-11-11'),(12,'2019-11-18'),(13,'2019-11-25'),(14,'2019-12-02'),(15,'2019-12-09'),
  (16,'2019-12-16'),(17,'2019-12-23'),(18,'2019-12-30'),(19,'2020-01-06'),(20,'2020-01-13'),
  (21,'2020-01-20'),(22,'2020-01-27'),(23,'2020-02-03'),(24,'2020-02-10'),(25,'2020-02-17'),
  (26,'2020-02-24'),(27,'2020-03-02'),(28,'2020-03-09'),(29,'2020-03-16'),(30,'2020-03-23'),
  (31,'2020-03-30'),(32,'2020-04-06'),(33,'2020-04-13'),(34,'2020-04-20'),(35,'2020-04-27'),
  (36,'2020-05-04'),(37,'2020-05-11'),(38,'2020-05-18'),(39,'2020-05-25'),(40,'2020-06-01'),
  (41,'2020-06-08'),(42,'2020-06-15'),(43,'2020-06-22'),(44,'2020-06-29');

INSERT INTO vacances (id, nom) VALUES
  (0, ''),
  (1, 'Vacances de la Toussaint'),
  (2, 'Vacances de Noël'),
  (3, "Vacances d'hiver"),
  (4, 'Vacances de printemps');

-- Planning de la zone C
UPDATE semaines SET vacances = 1 WHERE id = 8 OR id = 9;
UPDATE semaines SET vacances = 2 WHERE id = 17 OR id = 18;
UPDATE semaines SET vacances = 3 WHERE id = 24 OR id = 25;
UPDATE semaines SET vacances = 4 WHERE id = 32 OR id = 33;
UPDATE semaines SET colle = 1 WHERE vacances = 0;  

INSERT INTO agenda (id,matiere,debut,fin,type,texte) VALUES 
  ( 1, 0, '2019-09-02 00:00:00', '2019-09-02 00:00:00', 3, '<div class="annonce">C''est la rentrée ! Bon courage pour cette nouvelle année&nbsp;!</div>'),
  ( 2, 0, '2019-11-01 00:00:00', '2019-11-01 00:00:00', 7, '<p>Toussaint</p>'),
  ( 3, 0, '2019-11-11 00:00:00', '2019-11-11 00:00:00', 7, '<p>Armistice 1918</p>'),
  ( 4, 0, '2019-12-25 00:00:00', '2019-12-25 00:00:00', 7, '<p>Noël</p>'),
  ( 5, 0, '2020-01-01 00:00:00', '2020-01-01 00:00:00', 7, '<p>Jour de l''an</p>'),
  ( 6, 0, '2020-04-13 00:00:00', '2020-04-13 00:00:00', 7, '<p>Lundi de Pâques</p>'),
  ( 7, 0, '2020-05-01 00:00:00', '2020-05-01 00:00:00', 7, '<p>Fête du travail</p>'),
  ( 8, 0, '2020-05-08 00:00:00', '2020-05-08 00:00:00', 7, '<p>Armistice 1945</p>'),
  ( 9, 0, '2020-05-21 00:00:00', '2020-05-24 00:00:00', 7, '<p>Pont de l''Ascension</p>'),
  (10, 0, '2020-06-01 00:00:00', '2020-06-01 00:00:00', 7, '<p>Lundi de Pentecôte</p>'),
  (11, 0, '2020-07-14 00:00:00', '2020-07-14 00:00:00', 7, '<p>Fête Nationale</p>'),
  (12, 0, '2019-07-07 00:00:00', '2019-09-01 00:00:00', 8, '<p>Vacances d''été</p>'),
  (13, 0, '2019-10-20 00:00:00', '2019-11-03 00:00:00', 8, '<p>Vacances de la Toussaint</p>'),
  (14, 0, '2019-12-22 00:00:00', '2020-01-05 00:00:00', 8, '<p>Vacances de Noël</p>'),
  (15, 0, '2020-02-09 00:00:00', '2020-02-23 00:00:00', 8, '<p>Vacances d''hiver</p>'),
  (16, 0, '2020-04-05 00:00:00', '2020-04-19 00:00:00', 8, '<p>Vacances de printemps</p>'),
  (17, 0, '2020-07-05 00:00:00', '2020-09-01 00:00:00', 8, '<p>Vacances d''été</p>');

FIN;
?>
