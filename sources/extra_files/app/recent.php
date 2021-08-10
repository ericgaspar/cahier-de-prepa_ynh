<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

////////////////////////////////////////////////
// Validation de la requête : matière et type //
////////////////////////////////////////////////

// Recherche de la matière concernée, variable $matiere
// Génération du sélecteur de matières
// Si $_REQUEST['cle'] existe, on la cherche dans les matières disponibles.
$mysqli = connectsql();
$select_matieres = '
      <option value="general">Pas de matière associée</option>';
$matieres = array('0'=>'general');
$resultat = $mysqli->query('SELECT id, cle, nom FROM matieres WHERE '.( $autorisation ? "FIND_IN_SET(id,'${_SESSION['matieres']}')" : '1' ));
if ( $resultat->num_rows )  {
  while ( $r = $resultat->fetch_assoc() )  {
    $matieres[$r['id']] = $r['cle'];
    $select_matieres .= "\n      <option value=\"${r['cle']}\">${r['nom']}</option>";
  }
  $resultat->free();
  if ( isset($_REQUEST['matiere']) && in_array($cle = $_REQUEST['matiere'],$matieres,true) )  {
    $mid = array_search($cle,$matieres);
    $select_matieres = str_replace("\"$cle\"","\"$cle\" selected",$select_matieres);
  }
}
// Recherche du type et génération du sélecteur de type
$select_types = <<<FIN

      <option value="infos">Les informations</option>
      <option value="colles">Les programmes de colles</option>
      <option value="docs">Les documents</option>
      <option value="agenda">L'agenda</option>
FIN;
$types = array('infos','colles','docs','agenda');
if ( isset($_REQUEST['type']) && in_array($cle = $_REQUEST['type'],$types,true) )  {
  $tid = 1+array_search($cle,$types);
  $select_types = str_replace("\"$cle\"","\"$cle\" selected",$select_types);
}
// Fabrication de la requête
if ( $autorisation )  
  $requete = '('.requete_protection($autorisation).') AND '.( isset($mid) ? "matiere = $mid" : "FIND_IN_SET(matiere,'${_SESSION['matieres']}')" );
else 
  $requete = 'protection = 0'.( isset($mid) ? " AND matiere = $mid" : '');
if ( isset($tid) )
  $requete .= " AND type = $tid";
// Demande de recherche sur les titres et textes
$recherche = '';
if ( isset($_REQUEST['recherche']) && $_REQUEST['recherche'] )
  $requete .= ' AND ( titre LIKE \'%'.$mysqli->real_escape_string($recherche = htmlspecialchars($_REQUEST['recherche'])).'%\') OR ( texte LIKE \'%'.$mysqli->real_escape_string($recherche).'%\') ';

//////////////
//// HTML ////
//////////////
debut($mysqli,'Derniers contenus',$message,$autorisation,'recent');

echo "\n  <div id=\"icones\"><a class=\"icon-rss\" title=\"Flux RSS\"></a></div>\n\n";

// Liste des icônes pour affichage
$icones = array(
  'pdf' => '-pdf', 'dvi' => '-pdf',
  'py' => '-py', 'sql' => '-sql',
  'db' => '-db', 'db3' => '-db', 'sqlite' => '-db', 'sq3' => '-db',
  'doc' => '-doc', 'odt' => '-doc', 'docx' => '-doc',
  'xls' => '-xls', 'ods' => '-xls', 'xlsx' => '-xls', 'csv' => '-xls',
  'ppt' => '-ppt', 'odp' => '-ppt', 'pptx' => '-ppt', 'pps' => 'ppt',
  'jpg' => '-jpg', 'jpeg' => '-jpg', 'jpe' => '-jpg', 'png' => '-jpg', 'gif' => '-jpg', 'svg' => '-jpg', 'tif' => '-jpg', 'tiff' => '-jpg', 'bmp' => '-jpg', 'ps' => '-jpg', 'eps' => '-jpg',
  'mp3' => '-mp3', 'ogg' => '-mp3', 'oga' => '-mp3', 'wma' => '-mp3', 'wav' => '-mp3', 'ra' => '-mp3', 'rm' => '-mp3',
  'mp4' => '-mp4', 'avi' => '-mp4', 'mpeg' => '-mp4', 'mpg' => '-mp4', 'wmv' => '-mp4', 'mp4' => '-mp4', 'ogv' => '-mp4', 'qt' => '-mp4', 'mov' => '-mp4', 'mkv' => '-mp4', 'flv' => '-mp4', 'swf' => '-mp4',
  'zip' => '-zip', 'rar' => '-zip', '7z' => '-zip', 'apk' => '-zip', 'dmg' => '-zip', 'jar' => '-zip', 
  'apk' => '-apk',
  'exe' => '-exe', 'sh' => '-exe', 'ml' => '-exe', 'mw' => '-exe', 'msi' => '-exe',
  'tex' => '-tex',
  'ggb' => '-cod', 'htm' => '-cod', 'mht' => '-cod', 'rw3' => '-cod', 'sce' => '-cod', 'slx' => '-cod', 'vpp' => '-cod'
);



// Affichage des éléments récents à afficher
$resultat = $mysqli->query("SELECT type, UNIX_TIMESTAMP(publi) AS publi, UNIX_TIMESTAMP(maj) AS maj, titre, lien, texte FROM recents
                            WHERE $requete AND publi < NOW() AND ( DATEDIFF(NOW(),publi) < 180 OR DATEDIFF(NOW(),maj) < 180 ) 
                            ORDER BY IF(maj>0,maj,publi) DESC LIMIT 100");
if ( $resultat->num_rows || isset($mid) || isset($tid) || isset($_REQUEST['recherche']) )  {

  // Barre de recherche
  echo <<<FIN
  <p id="rechercherecent" class="topbarre">
    <select id="type" onchange="window.location='?type='+this.value+'&amp;matiere='+$(this).next().val();">
      <option value="tout">Filtrer par type</option>$select_types
    </select>
    <select id="matiere" onchange="window.location='?type='+$(this).prev().val()+'&amp;matiere='+this.value;">
      <option value="tout">Filtrer par matière</option>$select_matieres
    </select>
    <span class="icon-recherche" onclick="if ( !$(this).prev().is(':visible')) $(this).prev().show(); else window.location='?recherche='+$(this).prev().val();"></span>
    <input type="text" value="$recherche" onchange="window.location='?recherche='+this.value;" title="Rechercher dans les titres et textes des contenus">
  </p>

FIN;

  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )  {
      $d = str_replace(' à 00h00','',date('d/m à H\hi',$r['maj'] ?: $r['publi']));
      // Icône et modification spécifique au type
      switch ( $r['type'] )  {
        case 1:
          $icone = '<span class="icon-infos"></span>';
          break;
        case 2:
          $icone = '<span class="icon-colles"></span>';
          break;
        case 3:
          $icone = '<span class="icon-doc'.( $icones[strtolower(substr(strtok($r['texte'],'|'),1))] ?? '' ).'"></span>';
          $r['texte'] = '<p>Document de '.strtok('|').', dans <a href="docs?rep='.strtok('|').'">'.strtok('|').'</a></p>';
          break;
        case 4 :
          $icone = '<span class="icon-agenda"></span>';
      }
      if ( $r['maj'] )  {
        $r['titre'] .= ' (mise à jour)';
        $d .= ' (publication initiale le '.str_replace(' à 00h00','',date('d/m à H\hi',$r['publi'])).')';
      }
      echo <<<FIN
    <article class="recents">
      <h3>$icone&nbsp;<a href="${r['lien']}">${r['titre']}</a></h3>
      <p class="publi">Publication le $d</p>
      <div>${r['texte']}</div>
    </article>


FIN;
    }
    $resultat->free();
  }
  
  // Recherche sans résultat
  else 
    echo <<<FIN
    <article>
      <h2>Aucun résultat n'a été trouvé pour cette recherche.</h2>
    </article>


FIN;
}
else
  echo <<<FIN
    <article>
      <h2>Aucun nouveau contenu n'a été ajouté récemment.</h2>
    </article>


FIN;
?>

  <div id="aide-rss">
    <h3>Flux RSS</h3>
    <p>Un flux RSS est une page web spécifique dont le contenu est mis à jour de façon permanente. Sa forme n'est pas très lisible directement dans votre navigateur, mais elle permet de récupérer le contenu d'un fil d'actualité à l'aide d'un logiciel prévu pour lire ce genre de page. Le logiciel va recharger tout seul la page à une période de quelques minutes et vous prévenir directement des nouveautés.</p>
    <p>Votre navigateur peut prendre en charge les flux RSS&nbsp;: <a href="https://firefox.com">Firefox</a> le fait nativement, <a href="https://www.google.fr/chrome/">Chrome</a> a besoin d'une  
    <a href="https://chrome.google.com/webstore/detail/rss-subscription-extensio/nlbjncdgjeocebhnmkbbbdekmmmcbfjd">extension</a> pour le faire.</p>
    <p>Un grand nombre d'applications pour Android et iOS existent, il faut taper «&nbsp;RSS&nbsp;» ou «&nbsp;feed&nbsp;» dans votre magasin d'application. Pour Android, l'application gratuite, sans pub <em>et libre</em> <a href="https://play.google.com/store/apps/details?id=net.frju.flym">Flym</a> est un très bon choix.</p>

<?php
if ( $autorisation )  {
  if ( !is_dir($rep = 'rss/'.substr(sha1("?!$mdp|$autorisation|${_SESSION['matieres']}"),0,20)) )
    rss($mysqli,explode(',',$_SESSION['matieres'])[1],32-(1<<$autorisation));
?>

    <p>Le flux RSS spécifique à votre compte est disponible à l'adresse</p>
    <p class="centre"><a href="<?php echo $rep; ?>/rss.xml">http://<?php echo "$domaine$chemin$rep"; ?>/rss.xml</a></p>
    <p>Ce flux liste l'ensemble des éléments que vous pouvez voir sur ce Cahier de Prépa. Cette adresse donne directement accès aux informations sur le site&nbsp;: merci de ne pas la divulguer à des personnes n'ayant pas d'accès au site.</p>
  </div>

<?php
}
else  {
  if ( !is_dir($rep = 'rss/'.substr(sha1("?!$mdp|0|toutes"),0,20)) )
    rss($mysqli,0,0);
?>

    <p>Le flux RSS public est disponible à l'adresse</p>
    <p class="centre"><a href="<?php echo $rep; ?>/rss.xml">http://<?php echo "$domaine$chemin$rep"; ?>/rss.xml</a></p>
    <p>Ce flux contient uniquement les éléments visibles sans identification sur ce Cahier de Prépa. Si vous avez un compte ici, vous avez intérêt à vous connecter pour connaître l'adresse du flux correspondant à tout ce à quoi vous pouvez accéder normalement.</p>
  </div>

<?php
}

$mysqli->close();
fin(false,false);
?>
