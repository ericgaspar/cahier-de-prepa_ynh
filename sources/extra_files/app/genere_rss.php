<?php
// Sécurité : ne peut qu'être inclus par les fichiers rss
if ( !defined('OK') )  exit();

///////////////////////////////////////////////////////////////////////
/// Script de régénération des flux RSS, lancé par des fichiers RSS ///
/// en cas d'élément à affichage différé qu'il faut afficher        ///
///////////////////////////////////////////////////////////////////////

// Attention, le chemin des inclusions et des écritures est celui du
// répertoire rss : $chemin/rss/[numéro unique]/

// La seule variable déjà définie est la combinaison $c, sous la
// forme autorisation|liste-de-matières correspondant au flux concerné.

// Configuration -- Attention, chemin relatif au premier fichier appelé
include('../../config.php');

// Connexion
$mysqli = new mysqli($serveur,$base,$mdp,$base);
$mysqli->set_charset('utf8');

// Génération des flux RSS
// Code recopié en partie de la fonction rss() dans fonctions.php

// Préambule du flux RSS - Titre du flux : titre du site
$resultat = $mysqli->query('SELECT titre FROM pages WHERE id = 1');
$titre = $resultat->fetch_row()[0];
$resultat->free();
$d = date(DATE_RSS);
$site = "https://$domaine$chemin";
$preambule = <<<FIN
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>$titre</title>
    <atom:link href="https://$domaine${_SERVER['PHP_SELF']}" rel="self" type="application/rss+xml" />
    <link>$site</link>
    <description>$titre - Flux RSS</description>
    <lastBuildDate>$d</lastBuildDate>
    <language>fr-FR</language>


FIN;

// Log
$mois = date('Y-m');
$heure = date('d/m/Y à H:i:s');
if ( file_exists("../log.$mois.php") )
  $fichierlog = fopen("../log.$mois.php",'ab');
else  {
  $fichierlog = fopen("../log.$mois.php",'wb');
  fwrite($fichierlog,'<?php exit(); ?>');
}
fwrite($fichierlog, "\n-- Génération le $heure -- depuis un rss.xml - $c\n");

// Récupération des éléments
list($autorisation,$matieres) = explode('|',$c);
$requete = ( $autorisation ) ? "FIND_IN_SET(matiere,'$matieres') AND ( ( protection = 0 ) OR ( ( (protection-1)>>($autorisation-1) & 1 ) = 0 ) )" : 'protection = 0';
$resultat = $mysqli->query("SELECT type, UNIX_TIMESTAMP(publi) AS publi, UNIX_TIMESTAMP(maj) AS maj, titre, lien, texte FROM recents
                            WHERE $requete AND publi < NOW() AND ( DATEDIFF(NOW(),publi) < 90 OR DATEDIFF(NOW(),maj) < 90 ) ORDER BY IF(maj>0,maj,publi) DESC");
$rss = '';
if ( $resultat->num_rows )  {
  while ( $r = $resultat->fetch_assoc() )  {
    $d = date(DATE_RSS,$r['maj'] ?: $r['publi']);
    // Modification pour les documents
    if ( $r['type'] == 3 )  {
      $r['titre'] .= strtok($r['texte'],'|');
      $r['texte'] = 'Document de '.strtok('|')." dans le répertoire <a href=\"${site}docs?rep=".strtok('|').'">'.strtok('|').'</a>';
    }
    if ( $r['maj'] )
      $r['titre'] .= ' (mise à jour)';
    $texte = preg_replace(array('/href="([^h])/','/\<\?/'),array("href=\"$site\\1",'<!?'),$r['texte']);
    $rss .= <<<FIN
    <item>
      <title><![CDATA[${r['titre']}]]></title>
      <link>$site${r['lien']}</link>
      <guid isPermaLink="false">${r['publi']}</guid>
      <description><![CDATA[$texte]]></description>
      <pubDate>$d</pubDate>
    </item>


FIN;
  }
  $resultat->free();
}

// Envoi du fichier généré
header('Content-Type: application/xml');
header('Content-Disposition: inline; filename="rss.xml"');
echo "$preambule$rss  </channel>\n</rss>\n";

// Vérification des éventuels affichages différés
$debut = '';
$resultat = $mysqli->query("SELECT UNIX_TIMESTAMP(publi) AS publi FROM recents WHERE $requete AND publi > NOW() ORDER BY publi LIMIT 1");
if ( $resultat->num_rows )  {
  $debut = '<?php if ( time() > '.($resultat->fetch_row()[0])." )  { define('OK',1); \$c='$c'; include('../../genere_rss.php'); exit(); } ?>\n";
  $resultat->free();
}

// Mise à jour du flux RSS
$fichier = fopen('rss.xml','wb');
fwrite($fichier, "$debut$preambule$rss  </channel>\n</rss>\n");

// Fermetures
fclose($fichier);
fclose($fichierlog);
$mysqli->close();
?>
