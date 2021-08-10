-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 2.2.0 à Cahier de Prépa 3.0.0.
-- 

DELETE FROM infos WHERE auto != '';
UPDATE infos SET page = page+1 ORDER BY page DESC;
UPDATE pages SET id = id+1 ORDER BY id DESC;
UPDATE matieres SET cdt = cdt - MOD(cdt,2) + IF((SELECT id FROM cdt WHERE matiere = matieres.id AND cache = 0 LIMIT 1),1,0);
UPDATE matieres SET colles = colles - MOD(colles,2) + IF((SELECT id FROM colles WHERE matiere = matieres.id AND cache = 0 LIMIT 1),1,0);
UPDATE matieres SET docs = (SELECT IF(nbfic+nbrep,1,0) FROM reps WHERE parent=0 AND matiere = matieres.id);
ALTER TABLE utilisateurs CHANGE matiere matieres VARCHAR( 15 ) NOT NULL;
ALTER TABLE utilisateurs ADD protection TINYINT( 1 ) UNSIGNED NOT NULL;
ALTER TABLE docs CHANGE id id SMALLINT( 4 ) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE infos CHANGE id id SMALLINT( 4 ) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE infos DROP auto;

CREATE TABLE recents (
  id smallint(5) unsigned NOT NULL PRIMARY KEY,
  heure datetime NOT NULL,
  titre varchar(50) NOT NULL,
  lien varchar(20) NOT NULL,
  texte text NOT NULL,
  KEY heure (heure)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 3.0.0 à Cahier de Prépa 3.0.1.
-- 

ALTER TABLE recents CHANGE titre titre VARCHAR( 200 ) NOT NULL;

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 3.0.1 à Cahier de Prépa 3.0.2.
-- 

ALTER TABLE reps CHANGE nbfic nbdoc_v TINYINT( 2 ) UNSIGNED NOT NULL;
ALTER TABLE reps ADD nbdoc_nv TINYINT( 2 ) UNSIGNED NOT NULL;
UPDATE reps SET nbdoc_nv = ( SELECT COUNT(docs.id) FROM docs WHERE docs.parent = reps.id AND docs.protection = 2 );

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 3.0.3 à Cahier de Prépa 3.1.0.
-- 

ALTER TABLE `cdt-types` CHANGE h_fin deb_fin_pour TINYINT( 1 ) UNSIGNED NOT NULL;
UPDATE `cdt-types` SET deb_fin_pour = 2 WHERE MOD(id,7) = 0;
UPDATE cdt AS c,`cdt-types` AS ct SET c.h_debut = '0:00' WHERE c.type = ct.id AND ct.deb_fin_pour = 2;
UPDATE cdt AS c,`cdt-types` AS ct SET c.h_fin = '0:00' WHERE c.type = ct.id AND ct.deb_fin_pour != 1;
UPDATE cdt AS c,`cdt-types` AS ct SET c.pour = '0000-00-00' WHERE c.type = ct.id AND ct.deb_fin_pour != 2;
ALTER TABLE cdt ORDER BY jour,matiere,pour,h_debut,h_fin,type;
ALTER TABLE docs ADD nom_nat VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER nom;
ALTER TABLE docs ADD INDEX ( nom_nat );
DROP FUNCTION IF EXISTS ZPAD;
DELIMITER $$
CREATE FUNCTION ZPAD(s VARCHAR(100))
  RETURNS VARCHAR(100)
BEGIN
  DECLARE i INT DEFAULT 1;
  DECLARE n INT DEFAULT 1;
  WHILE i <= LENGTH(s) DO
    IF FIND_IN_SET(SUBSTRING(s,i,1),"0,1,2,3,4,5,6,7,8,9") THEN
      SET n = i + 1;
      WHILE FIND_IN_SET(SUBSTRING(s,n,1),"0,1,2,3,4,5,6,7,8,9") DO
        SET n = n + 1;
      END WHILE;
      SET s = CONCAT(LEFT(s,i-1),REPEAT("0",10-n+i),RIGHT(s,CHAR_LENGTH(s)+1-i));
      SET i = i + 10;
    END IF;
    SET i = i + 1;
  END WHILE;
  RETURN s;
END
$$
DELIMITER ;
UPDATE docs SET nom_nat=ZPAD(nom);
DROP FUNCTION ZPAD;
ALTER TABLE docs ORDER BY parents, nom_nat;
UPDATE recents SET titre = CONCAT('<img class="icone" src="icones/info.png"> ',SUBSTRING(titre,8,CHAR_LENGTH(titre))) WHERE id < 2000;
UPDATE recents SET titre = CONCAT('<img class="icone" src="icones/colle.png"> ',titre) WHERE id > 2000 AND id < 3000;
UPDATE recents AS r, docs AS d SET 
  texte = CONCAT('<p>Nouveau document&nbsp;: <a href="download?id=',d.id,'">',r.titre,'</a></p>'),
  titre  = CONCAT('<img class="icone" src="icones/',
  CASE LOWER(d.ext)
    WHEN '.pdf' OR '.ps' THEN 'pdf' 
    WHEN '.doc' OR '.odt' OR '.docx' THEN 'doc'
    WHEN '.xls' OR '.ods' OR '.xlsx' THEN 'xls'
    WHEN '.ppt' OR '.odp' OR '.pptx' THEN 'ppt'
    WHEN '.jpg' OR '.jpeg' OR '.png' OR '.gif' OR '.svg' OR '.tif' OR '.tiff' OR '.bmp' OR '.ps' OR '.eps' THEN 'jpg'
    WHEN '.py' THEN 'python'
    WHEN '.avi' OR '.mpeg' OR '.mpg' OR '.wmv' OR '.mp4' OR '.ogv' OR '.qt' OR '.mov' OR '.mkv' OR 'flv' THEN 'avi'
    WHEN '.mp3' OR '.ogg' OR '.oga' OR '.wma' OR '.wav' OR '.ra' OR '.rm' THEN 'mp3'
    WHEN '.txt' OR '.rtf' THEN 'txt'
    WHEN '.zip' OR '.rar' OR '.7z' THEN 'zip'
    WHEN '.exe' OR '.sh' OR '.ml' OR '.mw' OR '' THEN 'exe'
    ELSE 'defaut' END
  ,'.png"> ',r.titre)
  WHERE r.id > 3000 AND d.id = r.id - 3000;
-- À effectuer avec les droits root
UPDATE mysql.db SET Create_routine_priv = 'Y' WHERE db.Db = '[base]' AND db.User = '[base]-adm';
FLUSH PRIVILEGES;

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 3.1.0 à Cahier de Prépa 3.1.1.
-- 

ALTER TABLE recents CHANGE lien lien VARCHAR( 30 ) NOT NULL;

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 3.1.1 à Cahier de Prépa 3.2.0.
-- 

ALTER TABLE pages ADD mat TINYINT( 2 ) UNSIGNED NOT NULL AFTER cle;
ALTER TABLE reps ADD menu TINYINT( 1 ) UNSIGNED NOT NULL;

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 3.2.0 à Cahier de Prépa 4.0.0.
-- 

ALTER TABLE utilisateurs
  DROP protection,
  CHANGE nom login VARCHAR( 50 ) NOT NULL,
  ADD UNIQUE (login),
  ADD nom VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER login,
  ADD prenom VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER nom,
  ADD genre TINYINT( 1 ) UNSIGNED NOT NULL AFTER prenom,
  ADD mail VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER genre,
  ADD autorisation TINYINT( 1 ) UNSIGNED NOT NULL AFTER mail,
  ADD timeout SMALLINT( 4 ) UNSIGNED NOT NULL,
  ADD mailexp TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD mailcopy TINYINT( 1 ) UNSIGNED NOT NULL;
UPDATE utilisateurs SET autorisation = IF(matieres,3,1), matieres = IF(matieres,matieres,''), timeout = 900, mailexp = 1, mailcopy = 1;

ALTER TABLE matieres
  ADD notes TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD colles_protection TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD cdt_protection TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD INDEX(notes);
UPDATE matieres SET colles_protection = colles DIV 2, cdt_protection = cdt DIV 2,
  colles = MOD( colles, 2 ), cdt = MOD( cdt, 2 ), notes = 0;

ALTER TABLE reps
  ADD nbrep_v TINYINT( 2 ) UNSIGNED NOT NULL AFTER nbrep,
  CHANGE nbdoc_nv nbdoc TINYINT( 2 ) UNSIGNED NOT NULL AFTER nbrep_v,
  CHANGE nbdoc_v nbdoc_v TINYINT( 2 ) UNSIGNED NOT NULL AFTER nbdoc;
UPDATE reps SET nbrep_v = nbrep, nbdoc = nbdoc + nbdoc_v;

UPDATE docs SET protection = 4 WHERE protection = 2; -- aucun répertoire ne pouvait avoir protection = 2

ALTER TABLE `cdt-types`
  ADD nb TINYINT( 3 ) UNSIGNED NOT NULL,
  ADD nb_v TINYINT( 3 ) UNSIGNED NOT NULL;

-- Semaines 2014-2015 -> Suppression des cahiers de texte et programmes de colles
TRUNCATE TABLE cdt;
TRUNCATE TABLE colles;
UPDATE matieres SET colles = 0, cdt = 0
TRUNCATE TABLE semaines;
INSERT INTO semaines (debut) VALUES ('2014-09-02'),('2014-09-08'),('2014-09-15'),('2014-09-22'),('2014-09-29'),
  ('2014-10-06'),('2014-10-13'),('2014-10-20'),('2014-10-27'),('2014-11-03'),('2014-11-10'),('2014-11-17'),('2014-11-24'),
  ('2014-12-01'),('2014-12-08'),('2014-12-15'),('2014-12-22'),('2014-12-29'),('2015-01-05'),('2015-01-12'),('2015-01-19'),('2015-01-26'),
  ('2015-02-02'),('2015-02-09'),('2015-02-16'),('2015-02-23'),('2015-03-02'),('2015-03-09'),('2015-03-16'),('2015-03-23'),('2015-03-30'),
  ('2015-04-07'),('2015-04-13'),('2015-04-20'),('2015-04-27'),('2015-05-04'),('2015-05-11'),('2015-05-18'),('2015-05-26'),
  ('2015-06-01'),('2015-06-08'),('2015-06-15'),('2015-06-22'),('2015-06-29');
UPDATE semaines SET colle = 1;

CREATE TABLE notes (
  id SMALLINT( 5 ) UNSIGNED NOT NULL PRIMARY KEY,
  semaine TINYINT( 2 ) UNSIGNED NOT NULL,
  eleve TINYINT( 2 ) UNSIGNED NOT NULL,
  colleur TINYINT( 2 ) UNSIGNED NOT NULL,
  matiere TINYINT( 2 ) UNSIGNED NOT NULL,
  note VARCHAR( 2 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 4.0.5 à Cahier de Prépa 4.1.0.
-- 

UPDATE utilisateurs SET autorisation = autorisation+1;
UPDATE pages SET protection = protection+1 WHERE protection;
UPDATE reps SET protection = protection+1 WHERE protection;
UPDATE docs SET protection = protection+1 WHERE protection;
UPDATE matieres SET cdt_protection = cdt_protection+1 WHERE cdt_protection;
UPDATE matieres SET colles_protection = colles_protection+1 WHERE colles_protection;
UPDATE utilisateurs SET mailexp = 1, mailcopy = 1 WHERE autorisation = 3;
UPDATE utilisateurs SET mailexp = 0, mailcopy = 0 WHERE autorisation = 2;

CREATE TABLE groupes (
  `id` TINYINT( 2 ) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `nom` VARCHAR( 10 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `nom_nat` VARCHAR( 30 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `colle` TINYINT( 1 ) UNSIGNED NOT NULL,
  `eleves` VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- 
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 4.1.3 à Cahier de Prépa 5.0.0.
-- 

-- Semaines 2015-2016 -> Suppression des cahiers de texte et programmes de colles
TRUNCATE TABLE cdt;
TRUNCATE TABLE colles;
TRUNCATE TABLE notes;
UPDATE matieres SET colles = 0, cdt = 0;
TRUNCATE TABLE semaines;
INSERT INTO semaines (debut) VALUES ('2015-09-01'),('2015-09-07'),('2015-09-14'),('2015-09-21'),('2015-09-28'),
  ('2015-10-05'),('2015-10-12'),('2015-10-19'),('2015-10-26'),('2015-11-02'),('2015-11-09'),('2015-11-16'),('2015-11-23'),('2015-11-30'),
  ('2015-12-07'),('2015-12-14'),('2015-12-21'),('2015-12-28'),('2016-01-04'),('2016-01-11'),('2016-01-18'),('2016-01-25'),
  ('2016-02-01'),('2016-02-08'),('2016-02-15'),('2016-02-22'),('2016-02-29'),('2016-03-07'),('2016-03-14'),('2016-03-21'),('2016-03-29'),
  ('2016-04-04'),('2016-04-11'),('2016-04-18'),('2016-04-25'),('2016-05-02'),('2016-05-09'),('2016-05-17'),('2016-05-23'),('2016-05-30'),
  ('2016-06-06'),('2016-06-13'),('2016-06-20'),('2016-06-27'),('2016-07-04');
UPDATE semaines SET colle = 1;
-- Autres modifications
ALTER TABLE utilisateurs
  MODIFY mdp VARCHAR(41),
  DROP genre,
  CHANGE matieres matieres VARCHAR( 30 ) NOT NULL,
  CHANGE mailexp mailexp VARCHAR( 50 ) NOT NULL;
UPDATE utilisateurs SET matieres = CONCAT_WS(",","0",IF(matieres="",(SELECT GROUP_CONCAT(id) FROM matieres),matieres));
UPDATE utilisateurs SET mailexp = CONCAT(prenom,' ',nom) WHERE autorisation > 2;
UPDATE utilisateurs SET mailexp = '' WHERE autorisation < 3;
UPDATE utilisateurs SET login = SUBSTRING(login,9), mail = CONCAT('@',mail), mdp = CONCAT('*',mdp) WHERE login RLIKE "^tmp[0-9]{5}";
UPDATE utilisateurs SET mdp='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' WHERE mdp='pas de mot de passe';
ALTER TABLE groupes CHANGE colle mailnotes TINYINT(1) UNSIGNED NOT NULL;
UPDATE groupes SET mailnotes = 1+2*mailnotes;
ALTER TABLE notes CHANGE note note VARCHAR( 4 ) NOT NULL;
ALTER TABLE recents
  ADD protection TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD matiere TINYINT( 2 ) UNSIGNED NOT NULL;
UPDATE recents LEFT JOIN docs ON recents.id=3000+docs.id SET recents.protection=docs.protection, recents.matiere=docs.matiere WHERE recents.id>3000;
UPDATE recents LEFT JOIN colles ON recents.id=2000+colles.id LEFT JOIN matieres ON colles.matiere = matieres.id SET recents.protection=matieres.colles_protection, recents.matiere=colles.matiere WHERE recents.id BETWEEN 2000 AND 3000;
UPDATE recents LEFT JOIN infos ON recents.id=1000+infos.id LEFT JOIN pages ON infos.page = pages.id SET recents.protection=pages.protection, recents.matiere=pages.mat WHERE recents.id<2000;
UPDATE recents SET titre = CONCAT('<span class="icon-doc-pdf"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%pdf.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-doc-doc"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%doc.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-doc-xls"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%xls.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-doc-ppt"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%ppt.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-doc-jpg"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%jpg.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-doc-pyt"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%python.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-doc-zip"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%zip.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-infos"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%info.png%';
UPDATE recents SET titre = CONCAT('<span class="icon-colles"></span> ',SUBSTRING(titre,LOCATE('>',titre)+2)) WHERE titre LIKE '%colle.png%';

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 5.1.0 à Cahier de Prépa 6.0.0
--

-- Semaines 2016-2017 -> Suppression des cahiers de texte et programmes de colles
TRUNCATE TABLE cdt;
TRUNCATE TABLE colles;
TRUNCATE TABLE notes;
UPDATE matieres SET colles = 0, cdt = 0;
TRUNCATE TABLE semaines;
INSERT INTO semaines (debut) VALUES ('2016-09-01'),('2016-09-05'),('2016-09-12'),('2016-09-19'),('2016-09-26'),
  ('2016-10-03'),('2016-10-10'),('2016-10-17'),('2016-10-20'),('2016-11-03'),('2016-11-07'),('2016-11-14'),('2016-11-21'),('2016-11-28'),
  ('2016-12-05'),('2016-12-12'),('2016-12-19'),('2016-12-26'),('2017-01-03'),('2017-01-09'),('2017-01-16'),('2017-01-23'),
  ('2017-01-30'),('2017-02-06'),('2017-02-13'),('2017-02-20'),('2017-02-27'),('2017-03-06'),('2017-03-13'),('2017-03-20'),('2017-03-27'),
  ('2017-04-03'),('2017-04-10'),('2017-04-18'),('2017-04-24'),('2017-05-02'),('2017-05-09'),('2017-05-15'),('2017-05-22'),('2017-05-29'),
  ('2017-06-05'),('2017-06-12'),('2017-06-19'),('2017-06-26'),('2017-07-03');
UPDATE semaines SET colle = 1;
-- Nouvelles tables
CREATE TABLE `prefs` (
  `nom` varchar(50) NOT NULL,
  `val` tinyint(2) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
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
-- Remplissage des nouvelles tables
INSERT INTO prefs (nom,val)
  VALUES ('creation_compte',1),
         ('nb_agenda_index',10),
         ('protection_globale',0),
         ('protection_agenda',0);
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
INSERT INTO agenda (id,matiere,debut,fin,type,texte)
  VALUES (1, 0, '2016-08-15 00:00:00', '2016-08-15 00:00:00', 7, '<p>Assomption</p>'),
         (2, 0, '2016-11-01 00:00:00', '2016-11-01 00:00:00', 7, '<p>Toussaint</p>'),
         (3, 0, '2016-11-11 00:00:00', '2016-11-11 00:00:00', 7, '<p>Armistice 1918</p>'),
         (4, 0, '2016-12-25 00:00:00', '2016-12-25 00:00:00', 7, '<p>Noël</p>'),
         (5, 0, '2017-01-01 00:00:00', '2017-01-01 00:00:00', 7, '<p>Jour de l''an</p>'),
         (6, 0, '2017-04-17 00:00:00', '2017-04-17 00:00:00', 7, '<p>Pâques</p>'),
         (7, 0, '2017-05-01 00:00:00', '2017-05-01 00:00:00', 7, '<p>Fête du travail</p>'),
         (8, 0, '2017-05-08 00:00:00', '2017-05-08 00:00:00', 7, '<p>Armistice 1945</p>'),
         (9, 0, '2017-05-25 00:00:00', '2017-05-25 00:00:00', 7, '<p>Ascension</p>'),
         (10, 0, '2017-06-05 00:00:00', '2017-06-05 00:00:00', 7, '<p>Pentecôte</p>'),
         (11, 0, '2017-07-14 00:00:00', '2017-07-14 00:00:00', 7, '<p>Fête Nationale</p>'),
         (12, 0, '2016-07-06 00:00:00', '2016-08-31 00:00:00', 8, '<p>Vacances d''été</p>'),
         (13, 0, '2016-10-20 00:00:00', '2016-11-02 00:00:00', 8, '<p>Vacances de la Toussaint</p>'),
         (14, 0, '2016-12-18 00:00:00', '2017-01-02 00:00:00', 8, '<p>Vacances de Noël</p>'),
         (15, 0, '2017-02-19 00:00:00', '2017-03-05 00:00:00', 8, '<p>Vacances d''hiver, zone A</p>'),
         (16, 0, '2017-02-10 00:00:00', '2017-02-26 00:00:00', 8, '<p>Vacances d''hiver, zone B</p>'),
         (17, 0, '2017-02-03 00:00:00', '2017-02-19 00:00:00', 8, '<p>Vacances d''hiver, zone C</p>'),
         (18, 0, '2017-04-16 00:00:00', '2017-05-01 00:00:00', 8, '<p>Vacances de printemps, zone A</p>'),
         (19, 0, '2017-04-09 00:00:00', '2017-04-23 00:00:00', 8, '<p>Vacances de printemps, zone B</p>'),
         (20, 0, '2017-04-02 00:00:00', '2017-04-17 00:00:00', 8, '<p>Vacances de printemps, zone C</p>'),
         (21, 0, '2017-07-09 00:00:00', '2017-08-31 00:00:00', 8, '<p>Vacances d''été</p>'),
         (22, 0, '2016-09-01 00:00:00', '2016-09-01 00:00:00', 1, '<div class="annonce">C''est la rentrée ! Bon courage pour cette nouvelle année&nbsp;!</div>');
-- Autres modifications
ALTER TABLE groupes CHANGE eleves eleves VARCHAR( 250 ) NOT NULL;

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 6.0.0 à Cahier de Prépa 6.1.0
--
-- Semaines 2017-2018 -> Suppression des cahiers de texte, programmes de colles, notes, événements ; renouvellement du planning
TRUNCATE TABLE cdt;
TRUNCATE TABLE colles;
TRUNCATE TABLE notes;
DELETE FROM agenda WHERE fin < '2017-09-01';
UPDATE matieres SET colles = 0, cdt = 0, notes = 0;
TRUNCATE TABLE semaines;
INSERT INTO semaines (debut) VALUES ('2017-09-04'),('2017-09-11'),('2017-09-18'),('2017-09-25'),
  ('2017-10-02'),('2017-10-09'),('2017-10-16'),('2017-10-23'),('2017-10-30'),('2017-11-06'),('2017-11-13'),('2017-11-20'),('2017-11-27'),
  ('2017-12-04'),('2017-12-11'),('2017-12-18'),('2017-12-25'),('2018-01-01'),('2018-01-08'),('2018-01-15'),('2018-01-22'),
  ('2018-01-29'),('2018-02-05'),('2018-02-12'),('2018-02-19'),('2018-02-26'),('2018-03-05'),('2018-03-12'),('2018-03-19'),('2018-03-26'),
  ('2018-04-03'),('2018-04-09'),('2018-04-16'),('2018-04-23'),('2018-04-30'),('2018-05-07'),('2018-05-14'),('2018-05-22'),('2018-05-28'),
  ('2018-06-04'),('2018-06-11'),('2018-06-18'),('2018-06-25'),('2018-07-02');
INSERT INTO agenda (id,matiere,debut,fin,type,texte)
  VALUES 
         (1, 0, '2017-09-04 00:00:00', '2017-09-04 00:00:00', 3, '<div class="annonce">C''est la rentrée ! Bon courage pour cette nouvelle année&nbsp;!</div>'),
         (2, 0, '2017-08-15 00:00:00', '2017-08-15 00:00:00', 7, '<p>Assomption</p>'),
         (3, 0, '2017-11-01 00:00:00', '2017-11-01 00:00:00', 7, '<p>Toussaint</p>'),
         (4, 0, '2017-11-11 00:00:00', '2017-11-11 00:00:00', 7, '<p>Armistice 1918</p>'),
         (5, 0, '2017-12-25 00:00:00', '2017-12-25 00:00:00', 7, '<p>Noël</p>'),
         (6, 0, '2018-01-01 00:00:00', '2018-01-01 00:00:00', 7, '<p>Jour de l''an</p>'),
         (7, 0, '2018-04-02 00:00:00', '2018-04-02 00:00:00', 7, '<p>Pâques</p>'),
         (8, 0, '2018-05-01 00:00:00', '2018-05-01 00:00:00', 7, '<p>Fête du travail</p>'),
         (9, 0, '2018-05-08 00:00:00', '2018-05-08 00:00:00', 7, '<p>Armistice 1945</p>'),
         (10, 0, '2018-05-10 00:00:00', '2018-05-10 00:00:00', 7, '<p>Ascension</p>'),
         (11, 0, '2018-05-21 00:00:00', '2018-05-21 00:00:00', 7, '<p>Pentecôte</p>'),
         (12, 0, '2018-07-14 00:00:00', '2018-07-14 00:00:00', 7, '<p>Fête Nationale</p>'),
         (13, 0, '2017-07-06 00:00:00', '2017-09-03 00:00:00', 8, '<p>Vacances d''été</p>'),
         (14, 0, '2017-10-22 00:00:00', '2017-11-05 00:00:00', 8, '<p>Vacances de la Toussaint</p>'),
         (15, 0, '2017-12-24 00:00:00', '2018-01-07 00:00:00', 8, '<p>Vacances de Noël</p>'),
         (16, 0, '2018-02-11 00:00:00', '2018-02-25 00:00:00', 8, '<p>Vacances d''hiver, zone A</p>'),
         (17, 0, '2018-02-25 00:00:00', '2018-03-11 00:00:00', 8, '<p>Vacances d''hiver, zone B</p>'),
         (18, 0, '2018-02-18 00:00:00', '2018-03-04 00:00:00', 8, '<p>Vacances d''hiver, zone C</p>'),
         (19, 0, '2018-04-08 00:00:00', '2018-04-22 00:00:00', 8, '<p>Vacances de printemps, zone A</p>'),
         (20, 0, '2018-04-22 00:00:00', '2018-05-06 00:00:00', 8, '<p>Vacances de printemps, zone B</p>'),
         (21, 0, '2018-04-15 00:00:00', '2018-04-29 00:00:00', 8, '<p>Vacances de printemps, zone C</p>'),
         (22, 0, '2018-07-09 00:00:00', '2018-08-31 00:00:00', 8, '<p>Vacances d''été</p>');
UPDATE semaines SET colle = 1;

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 6.1.0 à Cahier de Prépa 6.2.0 (non publiée)
--
-- Semaines 2018-2019 -> Suppression des cahiers de texte, programmes de colles, notes, événements ; renouvellement du planning
TRUNCATE TABLE cdt;
TRUNCATE TABLE colles;
TRUNCATE TABLE notes;
DELETE FROM agenda WHERE fin < '2018-09-01';
UPDATE matieres SET colles = 0, cdt = 0, notes = 0;
TRUNCATE TABLE semaines;
INSERT INTO semaines (debut) VALUES ('2018-09-03'),('2018-09-10'),('2018-09-17'),('2018-09-24'),
  ('2018-10-01'),('2018-10-08'),('2018-10-15'),('2018-10-22'),('2018-10-29'),('2018-11-05'),('2018-11-12'),('2018-11-19'),('2018-11-26'),
  ('2018-12-03'),('2018-12-10'),('2018-12-17'),('2018-12-24'),('2018-12-31'),('2019-01-07'),('2019-01-14'),('2019-01-21'),
  ('2019-01-28'),('2019-02-04'),('2019-02-11'),('2019-02-18'),('2019-02-25'),('2019-03-04'),('2019-03-11'),('2019-03-18'),('2019-03-25'),
  ('2019-04-02'),('2019-04-08'),('2019-04-15'),('2019-04-22'),('2019-04-29'),('2019-05-06'),('2019-05-13'),('2019-05-21'),('2019-05-27'),
  ('2019-06-03'),('2019-06-10'),('2019-06-17'),('2019-06-24'),('2019-07-01');
INSERT INTO agenda (id,matiere,debut,fin,type,texte)
  VALUES 
         (1, 0, '2018-09-03 00:00:00', '2018-09-03 00:00:00', 3, '<div class="annonce">C''est la rentrée ! Bon courage pour cette nouvelle année&nbsp;!</div>'),
         (2, 0, '2018-08-15 00:00:00', '2018-08-15 00:00:00', 7, '<p>Assomption</p>'),
         (3, 0, '2018-11-01 00:00:00', '2018-11-01 00:00:00', 7, '<p>Toussaint</p>'),
         (4, 0, '2018-11-11 00:00:00', '2018-11-11 00:00:00', 7, '<p>Armistice 1918</p>'),
         (5, 0, '2018-12-25 00:00:00', '2018-12-25 00:00:00', 7, '<p>Noël</p>'),
         (6, 0, '2019-01-01 00:00:00', '2019-01-01 00:00:00', 7, '<p>Jour de l''an</p>'),
         (7, 0, '2019-04-22 00:00:00', '2019-04-22 00:00:00', 7, '<p>Lundi de Pâques</p>'),
         (8, 0, '2019-05-01 00:00:00', '2019-05-01 00:00:00', 7, '<p>Fête du travail</p>'),
         (9, 0, '2019-05-08 00:00:00', '2019-05-08 00:00:00', 7, '<p>Armistice 1945</p>'),
         (10, 0, '2019-05-30 00:00:00', '2019-05-30 00:00:00', 7, '<p>Jeudi de l''Ascension</p>'),
         (11, 0, '2019-06-10 00:00:00', '2019-06-10 00:00:00', 7, '<p>Lundi de Pentecôte</p>'),
         (12, 0, '2019-07-14 00:00:00', '2019-07-14 00:00:00', 7, '<p>Fête Nationale</p>'),
         (13, 0, '2018-07-08 00:00:00', '2018-09-02 00:00:00', 8, '<p>Vacances d''été</p>'),
         (14, 0, '2018-10-21 00:00:00', '2018-11-04 00:00:00', 8, '<p>Vacances de la Toussaint</p>'),
         (15, 0, '2018-12-23 00:00:00', '2019-01-06 00:00:00', 8, '<p>Vacances de Noël</p>'),
         (16, 0, '2019-02-17 00:00:00', '2019-03-03 00:00:00', 8, '<p>Vacances d''hiver, zone A</p>'),
         (17, 0, '2019-02-10 00:00:00', '2019-02-24 00:00:00', 8, '<p>Vacances d''hiver, zone B</p>'),
         (18, 0, '2019-02-24 00:00:00', '2019-03-10 00:00:00', 8, '<p>Vacances d''hiver, zone C</p>'),
         (19, 0, '2019-04-14 00:00:00', '2019-04-28 00:00:00', 8, '<p>Vacances de printemps, zone A</p>'),
         (20, 0, '2019-04-07 00:00:00', '2019-04-22 00:00:00', 8, '<p>Vacances de printemps, zone B</p>'),
         (21, 0, '2019-04-21 00:00:00', '2019-05-05 00:00:00', 8, '<p>Vacances de printemps, zone C</p>'),
         (22, 0, '2019-07-07 00:00:00', '2019-09-01 00:00:00', 8, '<p>Vacances d''été</p>');
UPDATE semaines SET colle = 1;

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 6.2.0 à Cahier de Prépa 8.0.1
--
-- Nouvelles colonnes et tables
ALTER TABLE groupes
  CHANGE nom nom VARCHAR( 50 ) NOT NULL,
  CHANGE nom_nat nom_nat VARCHAR( 50 ) NOT NULL,
  CHANGE eleves utilisateurs VARCHAR( 250 ) NOT NULL,
  CHANGE mailnotes mails TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD notes TINYINT( 1 ) UNSIGNED NOT NULL AFTER mails;
UPDATE groupes SET notes = mails>1, mails = mails%2;
ALTER TABLE matieres
  ADD docs_protection TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD dureecolle TINYINT(2) UNSIGNED NOT NULL;
UPDATE matieres SET docs_protection = (SELECT protection FROM reps WHERE parent=0 AND matiere=matieres.id);
ALTER TABLE utilisateurs
  CHANGE mailcopy mailcopie TINYINT( 1 ) UNSIGNED NOT NULL,
  CHANGE matieres matieres VARCHAR(50) NOT NULL,
  ADD mailenvoi TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD mailliste TINYINT( 1 ) UNSIGNED NOT NULL,
  ADD permconn VARCHAR( 10 ) NOT NULL,
  ADD lastconn DATETIME NOT NULL;
ALTER TABLE infos
  ADD protection TINYINT( 1 ) UNSIGNED NOT NULL;
ALTER TABLE reps
  DROP nbrep,
  DROP nbrep_v,
  DROP nbdoc,
  DROP nbdoc_v;
ALTER TABLE `cdt-types`
  DROP nb_v;
-- Nouvelle gestion des notes
ALTER TABLE notes 
  CHANGE eleve eleve SMALLINT( 3 ) UNSIGNED NOT NULL,
  CHANGE colleur colleur SMALLINT( 3 ) UNSIGNED NOT NULL,
  ADD heure SMALLINT( 1 ) UNSIGNED NOT NULL AFTER semaine,
  ADD KEY semaine (semaine),
  ADD KEY heure (heure),
  ADD KEY eleve (eleve),
  ADD KEY colleur (colleur),
  ADD KEY matiere (matiere);
CREATE TABLE heurescolles (
  id smallint(5) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  colleur SMALLINT(3) UNSIGNED NOT NULL,
  matiere TINYINT(2) UNSIGNED NOT NULL,
  jour DATE NOT NULL,
  heure TIME NOT NULL,
  duree SMALLINT(3) UNSIGNED NOT NULL,
  releve DATE NOT NULL,
  KEY colleur (colleur),
  KEY matiere (matiere)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
INSERT INTO heurescolles (colleur, matiere, jour, duree) 
  SELECT colleur, matiere, debut, COUNT(eleve)*20
  FROM notes JOIN semaines ON notes.semaine=semaines.id
  GROUP BY semaine,colleur,matiere;
UPDATE notes AS n, heurescolles AS h, semaines AS s SET n.heure=h.id
  WHERE n.semaine = s.id AND s.debut = h.jour AND n.colleur = h.colleur AND n.matiere = h.matiere;
UPDATE matieres SET dureecolle = 20;
-- Modifications sur la gestion des utilisateurs et des protections
UPDATE utilisateurs SET autorisation = 5 WHERE autorisation = 4;
UPDATE matieres SET colles_protection = POW(2,colles_protection-IF(colles_protection<4,1,0)) WHERE colles_protection > 0;
UPDATE matieres SET cdt_protection = POW(2,cdt_protection-IF(cdt_protection<4,1,0)) WHERE cdt_protection > 0;
UPDATE pages SET protection = POW(2,protection-IF(protection<4,1,0)) WHERE protection > 0;
UPDATE reps SET protection = POW(2,protection-IF(protection<4,1,0)) WHERE protection > 0;
UPDATE docs SET protection = POW(2,protection-IF(protection<4,1,0)) WHERE protection > 0;
UPDATE recents SET protection = POW(2,protection-IF(protection<4,1,0)) WHERE protection > 0;
UPDATE prefs SET val = POW(2,val-IF(val<4,1,0)) WHERE nom='protection_agenda' AND val > 0;
DELETE FROM utilisateurs WHERE mdp = '';
UPDATE utilisateurs set mdp = "?" WHERE mdp = "*" ; 
UPDATE utilisateurs SET mailenvoi = 1 WHERE autorisation > 2 AND mail > '';
UPDATE utilisateurs SET mailliste = 1 WHERE autorisation > 1 AND mail > '';

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 8.0.2 à Cahier de Prépa 8.1.0
--
ALTER TABLE heurescolles 
  ADD description VARCHAR(200) NOT NULL AFTER duree;

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 8.1.0 à Cahier de Prépa 9.0.0
--

-- Nouvelle table : affichage configurable des vacances
CREATE TABLE vacances (
  id tinyint(2) unsigned NOT NULL PRIMARY KEY,
  nom varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
-- Modifications des tables existantes
DELETE FROM prefs WHERE nom = 'protection_globale' OR nom = 'envoi_mail_defaut';
ALTER TABLE prefs CHANGE val val SMALLINT( 3 ) UNSIGNED NOT NULL;
INSERT INTO prefs (nom,val) VALUES ('autorisation_mails',61440);
ALTER TABLE utilisateurs DROP mailenvoi, DROP mailliste;
UPDATE utilisateurs SET mailexp = CONCAT(prenom,' ',nom) WHERE LENGTH(mailexp) = 0;
-- Nouvelle structure des contenus récents
ALTER TABLE recents 
  DROP PRIMARY KEY,
  DROP KEY heure,
  ADD type TINYINT( 1 ) UNSIGNED NOT NULL AFTER id,
  CHANGE heure publi DATETIME NOT NULL,
  ADD maj DATETIME NOT NULL AFTER publi,
  ADD PRIMARY KEY (id, type),
  ADD KEY publi (publi),
  ADD KEY maj (maj);
UPDATE recents SET type = 1, id = id-1000,
                   titre = ( SELECT CONCAT( IF(LENGTH(i.titre),i.titre,'Information'),' [',IF(mat=0,'',CONCAT(m.nom,'/')),p.nom,']')
                             FROM infos AS i LEFT JOIN pages AS p ON page=p.id LEFT JOIN matieres AS m ON mat=m.id WHERE i.id = recents.id )
WHERE id < 2000;
DELETE FROM recents WHERE id > 2000;
INSERT INTO recents (id,type,publi,matiere,titre,lien,texte,protection)
  SELECT d.id, 3, upload, d.matiere, d.nom AS titre, CONCAT('download?id=',d.id) AS lien, 
  CONCAT(ext,'|',taille,'|',d.parent,'|',GROUP_CONCAT( r.nom ORDER BY FIND_IN_SET(r.id,d.parents) SEPARATOR '/' )) AS texte, d.protection
  FROM docs AS d LEFT JOIN reps AS r ON FIND_IN_SET(r.id,d.parents) GROUP BY d.id;
ALTER TABLE recents ORDER BY publi DESC;
-- Semaines 2019-2020
TRUNCATE TABLE semaines;
TRUNCATE TABLE cdt;
TRUNCATE TABLE colles;
TRUNCATE TABLE notes;
DELETE FROM agenda WHERE fin < '2019-09-01';
UPDATE matieres SET colles = 0, cdt = 0, notes = 0;
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
UPDATE semaines SET vacances = 1 WHERE id = 8 OR id = 9;
UPDATE semaines SET vacances = 2 WHERE id = 17 OR id = 18;
UPDATE semaines SET vacances = 3 WHERE id = 24 OR id = 25;
UPDATE semaines SET vacances = 4 WHERE id = 32 OR id = 33;
UPDATE semaines SET colle = 1 WHERE vacances = 0;
INSERT INTO vacances (id, nom) VALUES
  (0, ''),
  (1, 'Vacances de la Toussaint'),
  (2, 'Vacances de Noël'),
  (3, "Vacances d'hiver"),
  (4, 'Vacances de printemps');

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

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 9.0.2 à Cahier de Prépa 9.1.0
--

-- Nouvelle table : système de récupération de copies dématérialisées
ALTER TABLE matieres 
  ADD copies TINYINT( 1 ) UNSIGNED NOT NULL AFTER notes;
CREATE TABLE devoirs (
  id SMALLINT(5) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  matiere TINYINT(2) UNSIGNED NOT NULL,
  deadline DATETIME NOT NULL,
  description VARCHAR(100) NOT NULL,
  nom VARCHAR(15) NOT NULL,
  nom_nat VARCHAR(20) NOT NULL,
  lien CHAR(15) NOT NULL,
  KEY matiere (matiere)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
CREATE TABLE copies (
  id SMALLINT(5) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  devoir TINYINT(2) UNSIGNED NOT NULL,
  eleve SMALLINT(3) UNSIGNED NOT NULL,
  matiere TINYINT(2) UNSIGNED NOT NULL,
  numero TINYINT(3) UNSIGNED NOT NULL,
  upload DATETIME NOT NULL,
  taille VARCHAR(12) NOT NULL,
  ext VARCHAR(5) NOT NULL,
  KEY devoir (devoir),
  KEY matiere (matiere),
  KEY eleve (eleve)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

--
-- Voilà les modifications à effectuer sur chaque base pour passer de Cahier de
-- Prépa 9.1.0 à Cahier de Prépa 9.1.1
--
ALTER TABLE docs
  ADD dispo DATETIME NOT NULL;
ALTER TABLE devoirs 
  ADD indications TEXT NOT NULL,
  ADD dispo DATETIME NOT NULL;

