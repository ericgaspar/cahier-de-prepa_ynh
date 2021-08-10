<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

// Test de connexion light avant envoi d'un document
if ( ( $autorisation == 5 ) && isset($_REQUEST['connexion']) && connexionlight() )  {
  // Si connexion complète, modification du timeout pour autoriser un 
  // envoi sur une durée de 1h
  $_SESSION['time'] = max($_SESSION['time'],time()+3600);
  // Message inutile, non affiché
  exit('{"etat":"ok","message":"Envoi du document"}'); 
}

//////////////////////////////////////////////////////
// Validation de la requête : répertoire ou matière //
//////////////////////////////////////////////////////

// Ordre d'affichage des documents (à supprimer avant d'analyser la requête)
$ordre = 'ORDER BY nom_nat ASC';
$ordrerep = 'ORDER BY nom ASC';
if ( isset($_REQUEST['ordre']) )  {
  switch ($_REQUEST['ordre'])  {
    case 'alpha-inv':  $ordre = 'ORDER BY nom_nat DESC'; $ordrerep = 'ORDER BY nom DESC'; break;
    case 'chrono':     $ordre = 'ORDER BY docs.upload ASC'; break;
    case 'chrono-inv': $ordre = 'ORDER BY docs.upload DESC'; break;
  }
  unset($_REQUEST['ordre']);
}

// Récupération des données du répertoire demandé
$mysqli = connectsql();
// Requête non nulle : soit un numéro de répertoire, soit une clé de matière
// Les répertoires "non visibles" (protection 32), sauf pour les professeurs
// associés à la matière, ne sont pas accessibles (il ne faut pas que l'on
// obtienne "accès non autorisé" mais "mauvais paramètre").
$requete = 'SELECT r.id, r.nom, r.parent, r.parents, r.menu, r.protection, IFNULL(m.id,0) AS mid, m.cle, m.nom AS mat
            FROM reps AS r LEFT JOIN matieres AS m ON r.matiere = m.id';
$restriction = ( $autorisation == 5 ) ? "AND ( r.protection != 32 OR FIND_IN_SET(r.matiere,'${_SESSION['matieres']}') )" : 'AND r.protection != 32';
if ( isset($_REQUEST['rep']) && ctype_digit($rid = $_REQUEST['rep']) )  {
  $resultat = $mysqli->query("$requete WHERE r.id = $rid $restriction");
  if ( $resultat->num_rows )  {
    $rep = $resultat->fetch_assoc();
    $resultat->free();
  }
}
elseif ( !empty($_REQUEST) )  {
  $resultat = $mysqli->query("$requete WHERE r.parent = 0 $restriction");
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )
      if ( isset($_REQUEST[$r['cle']]) )  {
        $rep = $r;
        $rid = $r['id'];
        break;
      }
    $resultat->free();
  }
}
// Pas d'argument : répertoire "Général" (peut être non visible)
else  {
  $resultat = $mysqli->query("$requete WHERE r.id = 1 $restriction");
  if ( $resultat->num_rows )  {
    $rep = $resultat->fetch_assoc();
    $rid = $rep['id'];
    $resultat->free();
  }
}
// Si aucun répertoire trouvé
if ( !isset($rep) )  {
  debut($mysqli,'Documents à télécharger','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}

/////////////////////////////
// Vérification de l'accès //
/////////////////////////////
$edition = acces($rep['protection'],$rep['mid'],($rep['mat']) ? "Documents à télécharger - ${rep['mat']}" : 'Documents à télécharger',($rep['mat']) ? "docs?${rep['cle']}" : 'docs',$mysqli);

////////////
/// HTML ///
////////////
if ( $edition && $rep['protection'] )
  $icone = ( $rep['protection'] == 32 ) ? '<span class="icon-locktotal"></span>' : '<span class="icon-lock"></span>';
else  $icone = '';
debut($mysqli,($rep['mat']) ? "Documents à télécharger - ${rep['mat']}$icone" : 'Documents à télécharger',$message,$autorisation,($rep['mat']) ? "docs?${rep['cle']}" : 'docs',false,( $edition ) ? 'datetimepicker' : false);

// Répertoires parents (pas de vérification de protection a priori)
$resultat = $mysqli->query("SELECT GROUP_CONCAT(CONCAT('<a href=\"docs?rep=',id,'\">',nom,'</a>') SEPARATOR '&nbsp;/&nbsp;')
                            FROM reps WHERE FIND_IN_SET(id,'${rep['parents']},$rid')");
$r = $resultat->fetch_row();
$resultat->free();

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


// Affichage public sans édition
if ( !$edition )  {
  echo <<< FIN

  <p id="parentsdoc" class="topbarre">
    <a class="icon-chronodesc ordre" title="Classer les documents par ordre chronologique inversé"></a>
    <a class="icon-chronoasc ordre" title="Classer les documents par ordre chronologique"></a>
    <a class="icon-alphadesc ordre" title="Classer les documents par ordre alphabétique inversé"></a>
    <a class="icon-alphaasc ordre" title="Classer les documents par ordre alphabétique"></a>
    <span class="icon-rep-open"></span><span class="nom">${r['0']}</span>
  </p>

FIN;

  // Affichage du répertoire et de son contenu
  // Sous-répertoires
  $resultat = $mysqli->query('SELECT id, nom, IF('.requete_protection($autorisation).",0,1) AS protection
                              FROM reps WHERE parent = $rid AND protection != 32 $ordrerep");
  if ( $nr = $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )
      // Si protégé, pas de détails et lien que si utilisateur non connecté
      if ( $r['protection'] == 1 )  {
        if ( $autorisation )
          echo "\n  <p class=\"rep\"><span class=\"icon-rep\"></span><span class=\"icon-minilock\"></span><span class=\"nom\">${r['nom']}</span></p>\n";
        else
          echo "\n  <p class=\"rep\"><a href=\"?rep=${r['id']}\"><span class=\"icon-rep\"></span><span class=\"icon-minilock\"></span><span class=\"nom\">${r['nom']}</span></a></p>\n";
      }
      else  {
        $resultatbis = $mysqli->query("SELECT id FROM reps WHERE parent = ${r['id']} AND protection != 32");
        $contenu = ( ($n = $resultatbis->num_rows) > 0 ) ? "$n répertoire".( ($n>1) ? 's' : '' ) : '';
        $resultatbis->free();
        $resultatbis = $mysqli->query("SELECT id FROM docs WHERE parent = ${r['id']} AND protection != 32");
        $contenu .= ( ($n = $resultatbis->num_rows) > 0 ) ? ", $n document".( ($n>1) ? 's' : '' ) : '';
        $resultatbis->free();
        if ( strlen($contenu) == 0 )
          $contenu = 'vide';
        elseif ( $contenu[0] == ',' )
          $contenu = substr($contenu,2);
        echo "\n  <p class=\"rep\"><span class=\"repcontenu\">($contenu)</span> <a href=\"?rep=${r['id']}\"><span class=\"icon-rep\"></span><span class=\"nom\">${r['nom']}</span></a></p>\n";
      }
    $resultat->free();
  }

  // Documents
  $resultat = $mysqli->query('SELECT id, nom, taille, DATE_FORMAT(GREATEST(upload,dispo),\'%d/%m/%Y\') AS upload, LOWER(ext) AS ext, IF('.requete_protection($autorisation).",0,1) AS protection
                              FROM docs WHERE parent = $rid AND protection != 32 AND dispo < NOW() $ordre");
  if ( $nd = $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )  {
      $icone = $icones[$ext = substr($r['ext'],1)] ?? '';
      // Si protégé, pas de détails et lien que si utilisateur non connecté
      if ( $r['protection'] == 1 )  {
        if ( $autorisation )
          echo "\n  <p class=\"doc\"><span class=\"icon-doc$icone\"></span><span class=\"icon-minilock\"></span><span class=\"nom\">${r['nom']}</span></p>\n";
        else
          echo "\n  <p class=\"doc\"><a href=\"download?id=${r['id']}\"><span class=\"icon-doc$icone\"></span><span class=\"icon-minilock\"></span><span class=\"nom\">${r['nom']}</span></a></p>\n";
      }
      else
        echo "\n  <p class=\"doc\"><span class=\"docdonnees\">($ext, ${r['upload']}, ${r['taille']})</span> <a href=\"download?id=${r['id']}\"><span class=\"icon-doc$icone\"></span><span class=\"nom\">${r['nom']}</span></a></p>\n";
    }
    $resultat->free();
  }
  // Répertoire vide
  if ( $nr+$nd == 0 )
    echo "\n  <h2>Ce répertoire est vide.</h2>\n";
}

// Affichage professeur éditeur
else  {
  switch ( $rep['protection'] )  {
    case 0:  $protection = ''; break;
    case 32: $protection = '<span class="icon-locktotal"></span>'; break;
    default: $protection = '<span class="icon-lock"></span>';
  }
  echo <<< FIN

  <div id="icones">
    <a class="icon-aide" title="Aide pour les modifications des répertoires et documents"></a>
  </div>

  <p id="parentsdoc" class="topbarre" data-id="reps|$rid" data-donnees="${rep['parent']}|${rep['menu']}|${rep['protection']}">
    <a class="icon-editerep formulaire" title="Modifier ce répertoire"></a>
    <a class="icon-ajoutedoc formulaire" title="Ajouter un document dans ce répertoire"></a>
    <a class="icon-ajouterep formulaire" title="Ajouter un sous-répertoire"></a>
    <a class="icon-chronodesc ordre" title="Classer les documents par ordre chronologique inversé"></a>
    <a class="icon-chronoasc ordre" title="Classer les documents par ordre chronologique"></a>
    <a class="icon-alphadesc ordre" title="Classer les documents par ordre alphabétique inversé"></a>
    <a class="icon-alphaasc ordre" title="Classer les documents par ordre alphabétique"></a>
    <span class="icon-rep-open"></span>$protection<span class="nom">${r[0]}</span>
  </p>

FIN;

  // Affichage du répertoire et de son contenu
  // Sous-répertoires
  $resultat = $mysqli->query("SELECT id, nom, parent, menu, protection FROM reps WHERE parent = $rid $ordrerep");
  if ( $nr = $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )  {
      switch ( $r['protection'] )  {
        case 0:  $protection = ''; break;
        case 32: $protection = '<span class="icon-locktotal"></span>'; break;
        default: $protection = '<span class="icon-minilock"></span>';
      }
      $resultatbis = $mysqli->query("SELECT id FROM reps WHERE parent = ${r['id']}");
      $contenu = ( ($n = $resultatbis->num_rows) > 0 ) ? "$n répertoire".( ($n>1) ? 's' : '' ) : '';
      $resultatbis->free();
      $resultatbis = $mysqli->query("SELECT id FROM docs WHERE parent = ${r['id']}");
      $contenu .= ( ($n = $resultatbis->num_rows) > 0 ) ? ", $n document".( ($n>1) ? 's' : '' ) : '';
      $resultatbis->free();
      if ( strlen($contenu) == 0 )
        $contenu = 'vide';
      elseif ( $contenu[0] == ',' )
        $contenu = substr($contenu,2);
      // data-donnees : parent|menu|protection
      echo <<<FIN

  <p class="rep" data-id="reps|${r['id']}" data-donnees="${r['parent']}|${r['menu']}|${r['protection']}">
    <a class="icon-editerep formulaire" title="Modifier ce répertoire"></a>
    <a class="icon-ajoutedoc formulaire" title="Ajouter un document dans ce répertoire"></a>
    <a class="icon-supprime" title="Supprimer ce répertoire et son contenu"></a>
    <span class="repcontenu">($contenu)</span> 
    <a href="?rep=${r['id']}"><span class="icon-rep"></span></a>$protection<span class="nom editable" data-id="reps|nom|${r['id']}">${r['nom']}</span>
  </p>

FIN;
    }
    $resultat->free();
  }

  // Documents
  $resultat = $mysqli->query("SELECT id, nom, taille, DATE_FORMAT(upload,'%d/%m/%Y') AS upload, LOWER(ext) AS ext, protection,
                              IF(dispo>NOW(),1,0) AS affdiff, IF(dispo,DATE_FORMAT(dispo,'%d/%m/%Y %kh%i'),0) AS dispo
                              FROM docs WHERE parent = $rid $ordre");
  if ( $nd = $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )  {
      $icone = isset($icones[$ext = substr($r['ext'],1)]) ? $icones[$ext] : '';
      switch ( $r['protection'] )  {
        case 0:  $protection = ''; break;
        case 32: $protection = '<span class="icon-locktotal"></span>'; break;
        default: $protection = '<span class="icon-minilock"></span>';
      }
      $dispo = ( $r['affdiff'] ) ? " <span class=\"dispo\" title=\"Visible des élèves seulement à partir du ${r['dispo']}\">${r['dispo']}</span>," : '';
      $classdispo = ( $r['affdiff'] ) ? ' nodispo' : '';
      echo <<<FIN

  <p class="doc$classdispo" data-id="docs|${r['id']}" data-protection="${r['protection']}" data-dispo="${r['dispo']}">
    <a class="icon-editedoc formulaire" title="Modifier ce document"></a>
    <a class="icon-download" href="download?id=${r['id']}&amp;dl" title="Télécharger ce document"></a>
    <a class="icon-supprime" title="Supprimer ce document"></a>
    <span class="docdonnees">($ext, ${r['upload']},$dispo ${r['taille']})</span>
    <span class="icon-doc$icone"></span>$protection<span class="nom editable" data-id="docs|nom|${r['id']}">${r['nom']}</span>
  </p>

FIN;
    }
    $resultat->free();
  }
  // Répertoire vide
  if ( $nr+$nd == 0 )
    echo "\n  <h2>Ce répertoire est vide.</h2>\n";

  // Select sur les répertoires (pour les déplacements)
  function liste($rid,$n)  {
    $resultat = $GLOBALS['mysqli']->query("SELECT id, nom, parents FROM reps WHERE parent = $rid");
    while ( $r = $resultat->fetch_assoc() )  {
      $GLOBALS['select_reps'] .= "\n        <option value=\"${r['id']}\" data-parents=\"${r['parents']},${r['id']},\">".str_repeat('&rarr;',$n)."${r['nom']}</option>";
      liste($r['id'],$n+1);
    }
    $resultat->free();
  }
  $select_reps = '';
  $resultat = $mysqli->query("SELECT r.id, r.nom, parents FROM reps AS r LEFT JOIN matieres AS m ON m.id = r.matiere WHERE r.parent = 0 AND FIND_IN_SET(r.matiere,'${_SESSION['matieres']}') ORDER BY m.ordre");
  while ( $r = $resultat->fetch_assoc() )  {
    $select_reps .= "\n        <option value=\"${r['id']}\" data-parents=\"${r['parents']},${r['id']},\">${r['nom']}</option>";
    liste($r['id'],1);
  }
  $resultat->free();
                              
  // Taille maximale de fichier (pour l'aide)
  $taille = min(ini_get('upload_max_filesize'),ini_get('post_max_size'));
  if ( stristr($taille,'m') )
    $taille = substr($taille,0,-1)*1048576;
  elseif ( stristr($taille,'k') )
    $taille = substr($taille,0,-1)*1024;
  $taille = ( $taille < 1048576 ) ? intval($taille/1024).'&nbsp;ko' : intval($taille/1048576).'&nbsp;Mo';

  // Options du select multiple d'accès
  $select_protection = '
          <option value="0">Accès public</option>
          <option value="6">Utilisateurs identifiés</option>
          <option value="1">Invités</option>
          <option value="2">Élèves</option>
          <option value="3">Colleurs</option>
          <option value="4">Lycée</option>
          <option value="5">Professeurs</option>
          <option value="32">Répertoire invisible</option>';
  // Protection des nouveaux répertoires = protection globale du répertoire
  $p = $rep['protection'];
  if ( ( $p == 0 ) || ( $p == 32 ) )
    $sel_protection = str_replace("\"$p\"","\"$p\" selected",$select_protection);
  else  {
    $sel_protection = str_replace('"6"','"6" selected',$select_protection);
    for ( $a=1; $a<6; $a++ )
      if ( ( ($p-1)>>($a-1) & 1 ) == 0 )
        $sel_protection = str_replace("\"$a\"","\"$a\" selected",$sel_protection);
  }
  // Aide et formulaire d'ajout
?>

  <form id="form-editerep" data-action="reps">
    <h3 class="edition">Modifier le répertoire <em></em></h3>
    <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text"name="nom" value="" size="50"></p>
    <p class="ligne"><label for="parent">Déplacer&nbsp;: </label>
      <select name="parent">
        <option value="0">Ne pas déplacer</option><?php echo $select_reps; ?>
      </select>
    </p>
    <p class="ligne"><label for="menurep">Affichage du répertoire dans le menu&nbsp;: </label><input type="checkbox" name="menurep" value="1"></p>
    <p class="ligne"><label for="protection">Accès&nbsp;: </label>
      <select name="protection[]" multiple><?php echo $select_protection; ?>
      </select>
    </p>
    <p class="ligne"><label for="propage">Propager ce choix d'accès à chaque document/sous-répertoire&nbsp;: </label><input type="checkbox" name="propage" value="1"></p>
    <input type="button" class="ligne" value="Vider ce répertoire">
  </form>

  <form id="form-ajouterep" data-action="ajout-rep">
    <h3 class="edition">Ajouter un répertoire</h3>
    <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text" name="nom" value="" size="50"></p>
    <p class="ligne"><label for="menurep">Affichage du répertoire dans le menu&nbsp;: </label><input type="checkbox" name="menurep" value="1"></p>
    <p class="ligne"><label for="protection">Accès&nbsp;: </label>
      <select name="protection[]" multiple><?php echo $sel_protection; ?>
      </select>
    </p>
  </form>

  <form id="form-editedoc" data-action="docs">
    <h3 class="edition">Modifier le document <em></em></h3>
    <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text" name="nom" value="" size="50"></p>
    <p class="ligne"><label for="fichier">Mettre à jour&nbsp;: </label><input type="file" name="fichier"></p>
    <p class="ligne"><label for="publi">Publier en tant que mise à jour&nbsp;: </label><input type="checkbox" name="publi" value="1" checked></p>
    <p class="ligne"><label for="protection">Accès&nbsp;: </label>
      <select name="protection[]" multiple><?php echo str_replace('Répertoire','Document',$select_protection); ?>
      </select>
    </p>
    <p class="ligne"><label for="affdiff">Affichage différé&nbsp;: </label><input type="checkbox" name="affdiff" value="1"></p>
    <p class="ligne"><label for="dispo">Disponibilité&nbsp;: </label><input type="text" name="dispo" value="" size="15"></p>
    <p class="ligne"><label for="parent">Déplacer&nbsp;: </label>
      <select name="parent">
        <option value="0">Ne pas déplacer</option><?php echo str_replace("\"$rid\"","\"$rid\" disabled",$select_reps); ?>
      </select>
    </p>
  </form>

  <form id="form-ajoutedoc" data-action="ajout-doc">
    <h3 class="edition">Ajouter des documents</h3>
    <p>Ces documents seront positionnés dans le répertoire <em></em>.</p>
    <p class="ligne"><label for="fichier[]">Fichiers&nbsp;: </label><input type="file" name="fichier[]" multiple></p>
    <p class="ligne"><label for="protection">Accès&nbsp;: </label>
      <select name="protection[]" multiple><?php echo str_replace('Répertoire','Document',$select_protection); ?>
      </select>
    </p>
    <p class="ligne"><label for="affdiff">Affichage différé&nbsp;: </label><input type="checkbox" name="affdiff" value="1"></p>
    <p class="ligne"><label for="dispo">Disponibilité &nbsp;: </label><input type="text" name="dispo" value="" size="15"></p>
  </form>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici de modifier le contenu des répertoires et les propriétés des répertoires et des documents présents. Vous avez la possibilité de modifier les documents &laquo;&nbsp;toutes matières&nbsp;&raquo; ainsi que dans les matières qui vous sont associées. Le réglage de ces matières s'effectue à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
    <p>Les noms des répertoires et documents contenus dans le répertoire affiché sur cette page sont modifiables directement, en cliquant sur le bouton <span class="icon-edite"></span> situé dans la case encadrée de pointillés.</p>
    <p>Les répertoires sont indiqués par l'icône <span class="icon-rep"></span>, cliquer dessus affiche le répertoire correspondant. Les documents sont indiqués par l'icône correspondant à leur type (<span class="icon-doc-pdf"></span> pour les <code>pdf</code>, <span class="icon-doc-doc"></span> pour les textes <code>doc</code> ou <code>odt</code>...). Le contenu des répertoires et les principales propriétés des documents sont indiqués à droite.</p>
    <p>La taille des fichiers envoyés est limitée à <?php echo $taille; ?>.</p>
    <h4>Actions possibles</h4>
    <p>Les différentes actions possibles sont&nbsp;:</p>
    <ul>
      <li><span class="icon-ajouterep"></span>&nbsp;: ajouter un répertoire à l'intérieur du répertoire affiché sur cette page. Une aide sera disponible sur le formulaire qui s'affichera.</li>
      <li><span class="icon-ajoutedoc"></span>&nbsp;: ajouter des documents à l'intérieur du répertoire choisi. Une aide sera disponible sur le formulaire qui s'affichera.</li>
      <li><span class="icon-edite"></span>&nbsp;: modifier les propriétés du répertoire ou du document choisi. Il est notamment possible de modifier individuellement l'accès à chaque répertoire/document (explications ci-dessous) ou de vider un répertoire. Une aide sera disponible sur le formulaire qui s'affichera.</li>
      <li><span class="icon-supprime"></span>&nbsp;: supprimer le répertoire ou document choisi (une confirmation sera demandée). Supprimer un répertoire supprime automatiquement tout son contenu (sous-répertoires et documents).</li>
      <li><span class="icon-download"></span>&nbsp;: télécharger un document pour le voir.</li>
    </ul>
    <p>Les liens vers les répertoires et les documents sont garantis&nbsp;: aucune modification (changement de nom, mise à jour de document, déplacement...) réalisée sur les répertoires ou les documents ne peut modifier ces liens. Si vous souhaitez mettre à jour un document, surtout ne le supprimez pas pour le recréer&nbsp;: cela changerait le lien, les liens existants ne seraient plus valables. Modifiez plutôt le document.</p>
    <h4>Gestion de l'accès</h4>
    <p>L'accès à chaque répertoire et chaque document peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: ressource accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: ressource accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> est alors affiché à côté de l'icône de la ressource. Pour les répertoires/documents associés à une matière, seuls les utilisateurs associés à cette matière peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Répertoire/Document invisible</em>&nbsp;: ressource entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont la ressource peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché à côté de l'icône de la ressource.</li>
    </ul>
    <h4>Affichage différé pour les documents</h4>
    <p>Il est possible de régler un affichage différé pour un document&nbsp;: le document n'apparaît qu'à une heure précise dans le futur. Avant cette heure-là, il n'apparaît que pour vous. Après cette heure-là, il apparaît selon l'accès qui est défini pour lui.</p>
    <p>Les documents en affichage différés sont indiqués par une couleur grise dans la liste des documents. Les date et heure d'affichage y sont écrites en rouge.</p>
    <h4>Liens dans le menu</h4>
    <p>Le lien dans le menu vers le répertoire racine de chaque matière est généré automatiquement. Il ne s'affiche que si ce répertoire est non vide&nbsp;:</p>
    <ul>
      <li>pour les visiteurs non identifiés, si des documents éventuellement protégés sont présents (les répertoires et documents protégés apparaissent avec un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span>).</li>
      <li>pour les utilisateurs identifiés, si des documents sont présents et si le répertoire racine est accessible.</li>
    </ul>
    <p>Il est possible de rajouter des liens dans le menu vers des sous-répertoires. C'est une des propriétés que vous pouvez modifier pour chaque répertoire en cliquant sur le bouton <span class="icon-edite"></span> correspondant.</p>
  </div>

  <div id="aide-editerep">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier les propriétés du répertoire concerné. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Les répertoires racines des matières sont très peu modifiables&nbsp;: seul l'<em>accès</em> peut être modifié. Pour les autres répertoires, il est possible de les renommer, de les déplacer à l'intérieur d'un autre répertoire ou d'afficher un lien supplémentaire dans le menu.</p>
    <p>Le <em>nom</em> du répertoire peut comporter des espaces, des accents... Vous pouvez donc l'écrire en français&nbsp;! Ce nom est aussi modifiable directement en cliquant sur le bouton <span class="icon-edite"></span> dans la case entourée de pointillés.</p>
    <p>Vous pouvez <em>déplacer</em> le répertoire dans un autre répertoire, qui peut éventuellement appartenir à une autre matière si elle vous est associée. Le menu déroulant contient la liste des répertoires où le déplacement est possible. L'ensemble du contenu du répertoire déplacé est bien sûr automatiquement déplacé. L'accès n'est pas modifié.</p>
    <p>La case à cocher <em>Affichage du répertoire dans le menu</em> permet d'afficher un lien direct dans le menu vers la page correspondant au répertoire. Ce lien sera situé en-dessous du lien <em>Documents à télécharger</em> qui permet d'arriver sur cette page. Sa visibilité pour les utilisateurs dépend du choix de l'<em>accès</em> explicité ci-dessous.</p>
    <h4>Boutons d'action</h4>
    <p>Le bouton <em>Propager le choix d'accès à chaque document/sous-répertoire</em> permet de copier l'accès du répertoire à l'ensemble de son contenu. Attention&nbsp;: si vous venez de modifier la valeur dans la case <em>accès</em>, vous devez valider votre modification avant de <em>propager</em> le réglage.</p>
    <p>Le bouton <em>Vider ce répertoire</em> permet de supprimer l'ensemble du contenu du répertoire&nbsp;: sous-répertoires et documents. Une confirmation est demandée.</p>
    <h4>Gestion de l'accès</h4>
    <p>L'accès à ce répertoire peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: répertoire et son contenu accessibles de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: répertoire et son contenu accessibles uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> est alors affiché à côté de l'icône du répertoire. Pour les répertoires associés à une matière, seuls les utilisateurs associés à cette matière peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Répertoire invisible</em>&nbsp;: répertoire entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont le répertoire peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché à côté de l'icône du répertoire.</li>
    </ul>
    <p>À moins d'être <em>invisible</em>, le répertoire apparaît pour tout visiteur non identifié comme utilisateur identifié, éventuellement avec un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> indiquant qu'il est protégé. Un répertoire <em>invisible</em> n'est plus du tout visible sans être connecté en tant que professeur, éventuellement de la matière concernée.</p>
    <p>Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
  </div>

  <div id="aide-ajouterep">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer un nouveau répertoire au sein du répertoire <em><?php echo $rep['nom']; ?></em>. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Le <em>nom</em> du répertoire peut comporter des espaces, des accents... Vous pouvez donc l'écrire en français&nbsp;!</p>
    <p>La case à cocher <em>Affichage du répertoire dans le menu</em> permet d'afficher un lien direct dans le menu vers la page correspondant au répertoire. Ce lien sera situé en-dessous du lien <em>Documents à télécharger</em> qui permet d'arriver sur cette page. Sa visibilité dépend du choix de l'<em>accès</em> explicité ci-dessous.</p>
    <h4>Gestion de l'accès</h4>
    <p>L'accès à ce répertoire peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: répertoire et son contenu accessibles de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: répertoire et son contenu accessibles uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> est alors affiché à côté de l'icône du répertoire. Pour les répertoires associés à une matière, seuls les utilisateurs associés à cette matière peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Répertoire invisible</em>&nbsp;: répertoire entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont le répertoire peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché à côté de l'icône du répertoire.</li>
    </ul>
    <p>À moins d'être <em>invisible</em>, le répertoire apparaît pour tout visiteur non identifié comme utilisateur identifié, éventuellement avec un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> indiquant qu'il est protégé. Un répertoire <em>invisible</em> n'est plus du tout visible sans être connecté en tant que professeur, éventuellement de la matière concernée.</p>
  </div>
  
  <div id="aide-editedoc">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier les propriétés du document concerné. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Le <em>nom</em> du document peut comporter des espaces, des accents... Vous pouvez donc l'écrire en français&nbsp;! Ce nom est aussi modifiable directement en cliquant sur le bouton <span class="icon-edite"></span> dans la case entourée de pointillés. Il ne doit pas comporter l'extension, qui est ajoutée lorsqu'on le télécharge.</p>
    <h4>Mise à jour</h4>
    <p>Vous pouvez <em>mettre à jour</em> le document en envoyant un nouveau fichier. C'est bien mieux que de supprimer/recréer le document, car les liens existants restent valables. Une fois le formulaire validé, le document apparaîtra à nouveau tout en haut des informations récentes, comme s'il venait d'être envoyé. La date et la taille du document seront modifiées.</p>
    <p>La taille maximale du fichier envoyé est <?php echo $taille; ?>. Le fichier envoyé doit être de même extension que le fichier originel.</p>
    <p>La case à cocher <em>Publier en tant que mise à jour</em> permet de mentionner dans les informations récentes que le document a été mis à jour. Cela fait monter le document tout en haut de la page des informations récentes (accessible par le bouton <span class="icon-recent"></span>) et renvoie le document dans le flux RSS <span class="icon-rss"></span>. Cela est utile par exemple si le document a été corrigé, mais peu pour un simple faute de frappe.</p>
    <h4>Gestion de l'accès</h4>
    <p>L'accès à ce document peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: document téléchargeable de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: document téléchargeable uniquement par les utilisateurs identifiés, en fonction de leur type de compte, simplement visible pour les autres. Un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> est alors affiché à côté de l'icône du document. Pour les documents associés à une matière, seuls les utilisateurs associés à cette matière peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Document invisible</em>&nbsp;: document entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont le document peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché à côté de l'icône du document.</li>
    </ul>
    <p>À moins d'être <em>invisible</em>, le document est listé pour tout visiteur non identifié comme utilisateur identifié, éventuellement avec un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> indiquant qu'il est protégé. Un document <em>invisible</em> n'est plus du tout visible sans être connecté en tant que professeur, éventuellement de la matière concernée.</p>
    <h4>Affichage différé</h4>
    <p>Il est possible de ne laisser le document apparaître qu'à une heure précise dans le futur. Avant cette heure-là, le document n'apparaît que pour vous. Après cette heure-là, le document apparaît selon l'accès défini dans la gestion de l'accès. </p>
    <p>Le document apparaît de même simultanément, à l'heure indiquée, dans les informations récentes et le flux RSS.</p>
    <p>Les documents en affichage différés sont indiqués par une couleur grise dans la liste des documents. Les date et heure d'affichage y sont écrites en rouge.</p>
    <p>Il n'est logiquement pas possible de régler une heure d'affichage différé dans le passé.</p>
    <h4>Déplacement</h4>
    <p>Vous pouvez <em>déplacer</em> le document dans un autre répertoire, qui peut éventuellement appartenir à une autre matière si elle vous est associée. La liste des répertoires où le déplacement est possible est dans le menu déroulant.</p>
  </div>

  <div id="aide-ajoutedoc">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet d'ajouter des documents dans le répertoire concerné. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>En cliquant sur le bouton de chargement des fichiers, une fenêtre gérée par votre navigateur va s'ouvrir. Vous pourrez y choisir plusieurs documents, par exemple en appuyant sur la touche <code>Ctrl</code> tout en cliquant sur les documents.</p>
    <p>La taille maximale de l'ensemble des <em>fichiers</em> envoyés est <?php echo $taille; ?>.</p>
    <p>Après validation des fichiers choisis, autant de cases de <em>noms à afficher</em> appraîtront. Il s'agit du nom, pour chaque document, affiché sur le site et au téléchargement. Ils valent par défaut le nom du fichier sur votre ordinateur, mais vous pouvez les modifier.</p>
    <p>Les <em>noms à afficher</em> des documents peuvent comporter des espaces, des accents... Vous pouvez donc les écrire en français&nbsp;!</p>
    <h4>Gestion de l'accès</h4>
    <p>L'accès à ce document peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: documents téléchargeables de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: documents téléchargeables uniquement par les utilisateurs identifiés, en fonction de leur type de compte, simplement visible pour les autres. Un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> est alors affiché à côté de l'icône de chaque document. Pour les documents associés à une matière, seuls les utilisateurs associés à cette matière peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Documents invisibles</em>&nbsp;: documents entièrement invisibles pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont le document peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché à côté de l'icône de chaque document.</li>
    </ul>
    <p>À moins d'être <em>invisibles</em>, les documents sont listés pour tout visiteur non identifié comme utilisateur identifié, éventuellement avec un cadenas &nbsp;<span class="icon-minilock">&nbsp;</span> indiquant qu'il est protégé. Un document <em>invisible</em> n'est plus du tout visible sans être connecté en tant que professeur, éventuellement de la matière concernée.</p>
    <p>Tous les documents envoyés simultanément sont protégés de façon identique choisie ici. Leur accès est modifiable individuellement a posteriori.</p>
    <h4>Affichage différé</h4>
    <p>Il est possible de ne laisser les documents apparaître qu'à une heure précise dans le futur. Avant cette heure-là, les documents n'apparaissent que pour vous. Après cette heure-là, les documents apparaissent selon l'accès défini dans la gestion de l'accès. </p>
    <p>Les documents apparaissent de même simultanément, à l'heure indiquée, dans les informations récentes et le flux RSS.</p>
    <p>Les documents en affichage différés sont indiqués par une couleur grise dans la liste des documents. Les date et heure d'affichage y sont écrites en rouge.</p>
    <p>Il n'est logiquement pas possible de régler une heure d'affichage différé dans le passé.</p>
  </div>
  
  <p id="log"></p>
  <script type="text/javascript" src="js/datetimepicker.min.js"></script>
<?php
}
?>

  <script type="text/javascript">
$( function() {
  $('a.ordre').on("click",function() {
    var h = window.location.href;
    var i = h.indexOf('ordre=');
    if ( $(this).hasClass('icon-alphaasc') )  {
      if ( i > 0 )
        window.location = ( h.indexOf('&',i) > 0 ) ? h.substr(0,i)+h.substr(h.indexOf('&',i)+1) : h.substr(0,i-1);
      return; // Rien à faire si pas d'ordre déjà réglé
    }
    if ( $(this).hasClass('icon-alphadesc') )   var o = 'alpha-inv';
    if ( $(this).hasClass('icon-chronoasc') )   var o = 'chrono';
    if ( $(this).hasClass('icon-chronodesc') )  var o = 'chrono-inv';
    if ( i > 0 )
      window.location = ( h.indexOf('&',i) > 0 ) ? h.substr(0,i+6)+o+h.substr(h.indexOf('&',i)) : h.substr(0,i+6)+o;
    else
      window.location = ( h.indexOf('?') > 0 ) ? h+'&ordre='+o : h+'?ordre='+o;
  });
});
  </script>
<?php
$mysqli->close();
fin($edition);
?>
