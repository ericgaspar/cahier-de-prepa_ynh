<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

//////////////////
// Autorisation //
//////////////////

$mysqli = connectsql();
// Test de connexion avant envoi d'un document
if ( isset($_REQUEST['connexion']) )  {
  if ( $autorisation == 0 )
    exit('{"etat":"login"}');
  // Test de connexion light
  connexionlight();
  // Si connexion complète, modification du timeout pour autoriser un 
  // envoi sur une durée de 1h
  $_SESSION['time'] = max($_SESSION['time'],time()+3600);
  // Message inutile, non affiché
  exit('{"etat":"ok","message":"Envoi du document"}'); 
}
// Accès aux professeurs et élèves connectés uniquement
if ( !$autorisation )  {
  $titre = 'Transfert de copies';
  $actuel = false;
  include('login.php');
}
elseif ( !( ( $autorisation == 2 ) || ( $autorisation == 5 ) ) )  {
  debut($mysqli,'Transfert de copies','Vous n\'avez pas accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}
// Si connexion light : on doit répéter son mot de passe pour aller plus loin
// login.php contient fin()
if ( $_SESSION['light'] )  {
  $titre = 'Transfert de copies';
  $actuel = 'copies';
  include('login.php');
}

////////////////////////
// Types de documents //
////////////////////////
// Correspond à la centaine du numéro du document, enregistré dans la base
$types = array('Copie', 'Correction', 'Sujet');

/////////////////////////////
// Récupération des copies //
/////////////////////////////
if ( isset($_REQUEST['dl']) && ctype_digit($id = $_REQUEST['dl']) )  {
  // Vérification de l'identifiant
  $resultat = $mysqli->query("SELECT lien, nom, devoir, eleve, ext,
                              (numero MOD 100) AS num, (numero DIV 100) AS type
                              FROM copies LEFT JOIN devoirs ON copies.devoir = devoirs.id
                              WHERE copies.id = $id AND FIND_IN_SET(copies.matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )  {
    debut($mysqli,'Transfert de copies','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
  $r = $resultat->fetch_assoc();
  $resultat->free();
  // Un élève ne peut télécharger que ses copies/corrections
  if ( ( $autorisation == 2 ) && ( $r['eleve'] != $_SESSION['id'] ) )  {
    debut($mysqli,'Transfert de copies','Mauvais paramètre d\'accès à cette page.',2,' ');
    $mysqli->close();
    fin();
  }
  // Récupération (et vérification) du nom de l'élève
  $resultat = $mysqli->query("SELECT CONCAT(nom, ' ', prenom) AS nom FROM utilisateurs WHERE id = ${r["eleve"]}");
  $eleve = $resultat->fetch_row()[0];
  $resultat->free();
  // Récupération du nombre de copies : si une seule, pas de numérotation
  $resultat = $mysqli->query("SELECT * FROM copies WHERE devoir = ${r['devoir']} AND eleve = ${r["eleve"]} AND ( numero DIV 100 ) = ${r['type']}");
  if ( $resultat->num_rows > 1 )
    $nom = "${r['nom']} - $eleve - ${types[$r['type']]} ${r['num']}${r['ext']}";
  else 
    $nom = "${r['nom']} - $eleve".( $r['type'] > 0 ? " - ${types[$r['type']]}" : '' ).$r['ext'];
  $resultat->free();
  
  // Définition du type de fichier et du type d'attachement (entête HTML à envoyer)
  switch ( $r['ext'] )  {
    case '.pdf':  $type = 'application/pdf'; break;
    case '.doc':  $type = 'application/msword'; break;
    case '.odt':  $type = 'application/vnd.oasis.opendocument.text'; break;
    case '.docx': $type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'; break;
    case '.xls':  $type = 'application/vnd.ms-excel'; break;
    case '.ods':  $type = 'application/vnd.oasis.opendocument.spreadsheet'; break;
    case '.xlsx': $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; break;
    case '.ppt':  $type = 'application/vnd.ms-powerpoint'; break;
    case '.odp':  $type = 'application/vnd.oasis.opendocument.presentation'; break;
    case '.pptx': $type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation'; break;
    case '.jpg':
    case '.jpeg': $type = 'image/jpeg'; break;
    case '.png':  $type = 'image/png'; break;
    case '.gif':  $type = 'image/gif'; break;
    case '.svg':  $type = 'image/svg+xml'; break;
    case '.tif':
    case '.tiff': $type = 'image/tiff'; break;
    case '.bmp':  $type = 'image/x-ms-bmp'; break;
    case '.ps':
    case '.eps':  $type = 'application/postscript'; break;
    case '.avi':  $type = 'video/x-msvideo'; break;
    case '.mpeg':
    case '.mpg':  $type = 'video/mpeg'; break;
    case '.wmv':  $type = 'video/x-ms-wmv'; break;
    case '.mp4':  $type = 'video/mp4'; break;
    case '.ogv':  $type = 'video/ogg'; break;
    case '.qt':
    case '.mov':  $type = 'video/quicktime'; break;
    case '.mkv':  $type = 'video/x-matroska'; break;
    case '.mp3':  $type = 'audio/mpeg'; break;
    case '.ogg':
    case '.oga':  $type = 'audio/ogg'; break;
    case '.wma':  $type = 'audio/x-ms-wma'; break;
    case '.wav':  $type = 'audio/x-wav'; break;
    case '.ra':
    case '.rm':   $type = 'audio/x-pn-realaudio'; break;
    case '.txt':  $type = 'text/plain'; break;
    case '.rtf':  $type = 'application/rtf'; break;
    case '.zip':  $type = 'application/zip'; break;
    case '.rar':  $type = 'application/rar'; break;
    case '.7z':   $type = 'application/x-7z-compressed'; break;
    case '.sh':   $type = 'text/x-sh'; break;
    case '.py':   $type = 'text/x-python'; break;
    case '.swf':  $type = 'application/x-shockwave-flash'; break;
    default :     $type = 'application/octet-stream';
  }
  header("Content-Type: $type");
  header("Content-Disposition: attachment; filename=\"$nom\"");
  ob_end_clean();
  $handle = fopen("documents/${r['lien']}/${r['eleve']}_$id${r['ext']}", "rb");
  fpassthru($handle);
  fclose($handle);
  $mysqli->close();
  exit();
}

//////////////////////////////////////////////////////////////////
// Récupération de toutes les copies d'un coup (prof seulement) //
//////////////////////////////////////////////////////////////////
if ( isset($_REQUEST['recupdevoir']) && ctype_digit($id = $_REQUEST['recupdevoir']) && ( $autorisation == 5 ) )  {
  // Vérification de l'identifiant du devoir
  $resultat = $mysqli->query("SELECT lien, nom FROM devoirs WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )  {
    debut($mysqli,'Transfert de copies','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
  $d = $resultat->fetch_assoc();
  $resultat->free();

  // Création de l'archive
  $zip = new ZipArchive();
  $nomzip = 'archive'.sha1($d['nom']);
  if ( $zip->open( "documents/${d['lien']}/$nomzip.zip", ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE ) !== True )  {
    debut($mysqli,'Transfert de copies','Le fichier zip ne peut pas être créé. Vous devriez contacter l\'administrateur.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
  // Récupération des copies+noms d'élèves
  $resultat = $mysqli->query("SELECT copies.eleve, numero, copies.id, ext, CONCAT(nom,' ',prenom) AS nom, n
                              FROM copies LEFT JOIN utilisateurs ON copies.eleve = utilisateurs.id
                              LEFT JOIN ( SELECT COUNT(*) AS n, eleve FROM copies WHERE devoir = $id AND numero < 100 GROUP BY eleve ) AS c ON copies.eleve = c.eleve 
                              WHERE devoir = $id AND numero < 100 ORDER BY nom, numero");
  while ( $r = $resultat->fetch_assoc() )  {
    $nom = ( ( $r['n'] == 1 ) ? "${d['nom']} - ${r['nom']}${r['ext']}" : "${d['nom']} - ${r['nom']} - Copie ${r['numero']}${r['ext']}" );
    $zip->addFile("documents/${d['lien']}/${r['eleve']}_${r['id']}${r['ext']}", $nom );
  }
  $resultat->free();
  $zip->close();
  $mysqli->close();
  // Téléchargement de l'archive
  header('Content-type: application/zip');
  header("Content-Disposition: attachment; filename=\"${d['nom']} - ".date('y-m-d H:i').'.zip"');
  ob_end_clean();
  $handle = fopen("documents/${d['lien']}/$nomzip.zip", "rb");
  fpassthru($handle);
  fclose($handle);
  exit();
}

/////////////////////////////////////////////////////////////////////
// Récupération simultanée de plusieurs documents (prof seulement) //
/////////////////////////////////////////////////////////////////////
if ( isset($_REQUEST['dlcopies']) && ( $autorisation == 5 ) && isset($_REQUEST['devoir']) && ctype_digit($did = $_REQUEST['devoir']) && isset($_REQUEST['ids']) && count($ids = array_filter(explode(',',$_REQUEST['ids']),function($id) { return ctype_digit($id); })) )  {
  // Vérification de l'identifiant du devoir
  $resultat = $mysqli->query("SELECT lien, nom FROM devoirs WHERE id = $did AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )  {
    debut($mysqli,'Transfert de copies','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
  $d = $resultat->fetch_assoc();
  $resultat->free();

  // Vérifications des identifiants demandés
  sort($ids);
  $ids = implode(',',$ids);
  $resultat = $mysqli->query("SELECT (numero DIV 100) AS type FROM copies WHERE devoir = $did AND FIND_IN_SET(copies.id,'$ids') GROUP BY type");
  if ( !$resultat->num_rows )  {
    debut($mysqli,'Transfert de copies','Il n\'y a pas de copies correspondant à cette demande.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
  // Affichage des types seulement si plusieurs présents
  $afftype = ( $resultat->num_rows > 1 );
  $resultat->free();

  // Création de l'archive
  $zip = new ZipArchive();
  $nomzip = 'archive'.sha1($ids);
  if ( $zip->open( "documents/${d['lien']}/$nomzip.zip", ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE ) !== True )  {
    debut($mysqli,'Transfert de copies','Le fichier zip ne peut pas être créé. Vous devriez contacter l\'administrateur.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
  // Récupération des copies+noms d'élèves
  $resultat = $mysqli->query("SELECT copies.eleve, copies.id, ext, CONCAT(nom,' ',prenom) AS nom,
                              ( numero MOD 100 ) AS num, ( numero DIV 100 ) AS type
                              FROM copies LEFT JOIN utilisateurs ON copies.eleve = utilisateurs.id
                              WHERE devoir = $did AND FIND_IN_SET(copies.id,'$ids') ORDER BY nom, numero");
  while ( $r = $resultat->fetch_assoc() )  {
    $resultat2 = $mysqli->query("SELECT * FROM copies WHERE devoir = $did AND eleve = ${r["eleve"]} AND ( numero DIV 100 ) = ${r['type']}");
    if ( $resultat2->num_rows > 1 )
      $nom = "${d['nom']} - ${r['nom']} - ${types[$r['type']]} ${r['num']}${r['ext']}";
    else
      $nom = "${d['nom']} - ${r['nom']}".( $afftype ? " - ${types[$r['type']]}" : '' ).$r['ext'];
    $resultat2->free();
    $zip->addFile("documents/${d['lien']}/${r['eleve']}_${r['id']}${r['ext']}", $nom );
  }
  $resultat->free();
  $zip->close();
  $mysqli->close();
  // Téléchargement de l'archive
  header('Content-type: application/zip');
  header("Content-Disposition: attachment; filename=\"${d['nom']} - ".date('y-m-d H:i').'.zip"');
  ob_end_clean();
  $handle = fopen("documents/${d['lien']}/$nomzip.zip", "rb");
  fpassthru($handle);
  fclose($handle);
  exit();
}

////////////////////////////////////////
// Validation de la requête : matière //
////////////////////////////////////////

// Recherche de la matière concernée, variable $matiere
// Si le compte n'est associé qu'à une matière, on la choisit automatiquement.
// Sinon, on cherche $_REQUEST['cle'] dans les matières disponibles.
// copies=0 : pas de devoir saisi
// copies=1 : déjà des devoirs saisis
// copies=2 : fonction désactivée, pas d'affichage
$resultat = $mysqli->query("SELECT id, cle, nom FROM matieres WHERE FIND_IN_SET(id,'${_SESSION['matieres']}') AND copies < 2");
if ( $resultat->num_rows == 1 )  {
  $matiere = $resultat->fetch_assoc();
  $resultat->free();
}
elseif ( $resultat->num_rows )  {
  if ( !empty($_REQUEST) )  {
    while ( $r = $resultat->fetch_assoc() )
      if ( isset($_REQUEST[$r['cle']]) )  {
        $matiere = $r;
        break;
      }
  }
  $resultat->free();
  // Si aucune matière trouvée
  if ( !isset($matiere) )  {
    debut($mysqli,'Transfert de copies','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
}
// Si aucune matière avec des devoirs n'est enregistrée
else  {
  debut($mysqli,'Transfert de copies','Cette page ne contient aucune information.',$autorisation,' ');
  $mysqli->close();
  fin();
}
$mid = $matiere['id'];

// Ordre d'affichage des documents (à supprimer avant d'analyser la requête)
$ordre = 'ORDER BY deadline ASC';
if ( isset($_REQUEST['ordre']) )  {
  switch ($_REQUEST['ordre'])  {
    case 'alpha':      $ordre = 'ORDER BY nom_nat ASC'; break;
    case 'alpha-inv':  $ordre = 'ORDER BY nom_nat DESC'; break;
    case 'chrono-inv': $ordre = 'ORDER BY deadline DESC'; break;
  }
  unset($_REQUEST['ordre']);
}

////////////
/// HTML ///
////////////
debut($mysqli,"Transfert de copies - ${matiere['nom']}",$message,$autorisation,"copies?${matiere['cle']}",$mid,( $autorisation == 5 ) ? 'datetimepicker' : false);

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

// Affichage pour les élèves
if ( $autorisation == 2 )  {

  // Icônes d'ordre
  echo <<< FIN

  <div id="icones">
    <a class="icon-chronodesc ordre" href="?${matiere['cle']}&amp;ordre=chrono-inv" title="Classer les devoirs par ordre chronologique inversé"></a>
    <a class="icon-chronoasc ordre" href="?${matiere['cle']}" title="Classer les devoirs par ordre chronologique"></a>
    <a class="icon-alphadesc ordre" href="?${matiere['cle']}&amp;ordre=alpha-inv" title="Classer les devoirs par ordre alphabétique inversé"></a>
    <a class="icon-alphaasc ordre" href="?${matiere['cle']}&amp;ordre=alpha" title="Classer les devoirs par ordre alphabétique"></a>
  </div>

FIN;

  // Récupération de l'ensemble des devoirs
  $devoirs = $mysqli->query("SELECT id, DATE_FORMAT(deadline,'%w%Y%m%e') AS date, deadline>NOW() AS encours,
                             DATE_FORMAT(deadline,'%kh%i') AS heure, description, indications
                             FROM devoirs WHERE matiere = $mid AND dispo < NOW() $ordre");
  
  if ( $devoirs->num_rows )  {
    while ( $d = $devoirs->fetch_assoc() )  {
      $date = format_date($d['date']);
      // Affichage du devoir
      echo "\n  <article class=\"devoir\" data-id=\"${d['id']}\">\n    <h3> ${d['description']} ($date)</h3>\n";
      if ( $d['encours'] )
        echo "    <p class=\"horaire\">Vous avez jusqu'au $date à ${d['heure']} pour envoyer une ou des copies.</p>\n";
      if ( $d['indications'] )
        echo "    <div class=\"indications\">${d['indications']}</div>\n";

      // Récupération des copies
      $resultat = $mysqli->query("SELECT id, numero, DATE_FORMAT(upload,'%e/%m/%y, %kh%i') AS upload, taille, ext FROM copies
                                  WHERE eleve = ${_SESSION['id']} AND devoir = ${d['id']} AND numero < 100 ORDER BY numero");
      if ( $n = $resultat->num_rows )  {
        while ( $r = $resultat->fetch_assoc() )  {
          $nom = ( ( $n == 1 ) ? 'Copie envoyée' : "Copie ${r['numero']}" );
          $icone = $icones[substr($r['ext'],1)] ?? '';
          echo "    <p class=\"copie\"><a><span class=\"icon-doc$icone\"></span><span class=\"nom\">$nom</span></a> <span class=\"date\">(${r['upload']} - ${r['taille']})</span> <a class=\"icon-download\"></a>&nbsp;<a class=\"icon-supprime\" title=\"Supprimer cette copie\" data-id=\"${r['id']}\"></a></p>\n";
        }
        $resultat->free();
      }
      else
        echo "    <p class=\"annonce\">Vous n'avez pas ".( $d['encours'] ? 'encore' : '' )." envoyé de copie pour ce devoir.</p>\n";
      
      // Récupération des corrections et sujets
      foreach ( $types as $t => $type )  {
        if ( !$t ) continue;
        $resultat = $mysqli->query("SELECT id, numero, DATE_FORMAT(upload,'%e/%m/%y, %kh%i') AS upload, taille, ext
                                    FROM copies WHERE eleve = ${_SESSION['id']} AND devoir = ${d['id']} AND ( numero DIV 100 ) = $t ORDER BY numero");
        if ( $n = $resultat->num_rows )  {
          while ( $r = $resultat->fetch_assoc() )  {
            $nom = $type.( ( $n > 1 ) ?  ' '.($r['numero']-100*$t) : '' );
            $icone = $icones[strtolower(substr($r['ext'],1))] ?? '';
            echo "    <p class=\"copie\"><a><span class=\"icon-doc$icone\"></span><span class=\"nom\">$nom</span></a> <span class=\"date\">(${r['upload']} - ${r['taille']})</span> <a class=\"icon-download\" data-id=\"${r['id']}\"></a></p>\n";
          }
          $resultat->free();
        }
      }
      
      // Formulaire d'envoi si deadline non dépassée
      if ( $d['encours'] )
        echo "
    <form>
      <p class=\"ligne\"><label for=\"fichier\">Envoyer une copie&nbsp;:</label> <input type=\"file\" name=\"fichier\"> <button class=\"icon-ok\" title=\"Envoyer\"></button></p>
    </form>\n";
      echo "    </article>\n";
      
    }
    $devoirs->free();
  }
  // Pas de devoirs 
  else
    echo "\n  <article>\n    <h2>Aucun transfert de copie n'a encore été organisé en ${matiere['nom']} cette année.</h2>\n  </article>\n";
  $mysqli->close();
  
}

// Affichage professeur
else  {

  // Icônes générales : ordre, ajout
  echo <<< FIN

  <div id="icones">
    <a class="icon-chronodesc ordre" href="?${matiere['cle']}&amp;ordre=chrono-inv" title="Classer les devoirs par ordre chronologique inversé"></a>
    <a class="icon-chronoasc ordre" href="?${matiere['cle']}" title="Classer les devoirs par ordre chronologique"></a>
    <a class="icon-alphadesc ordre" href="?${matiere['cle']}&amp;ordre=alpha-inv" title="Classer les devoirs par ordre alphabétique inversé"></a>
    <a class="icon-alphaasc ordre" href="?${matiere['cle']}&amp;ordre=alpha" title="Classer les devoirs par ordre alphabétique"></a>
    <a class="icon-ajoute formulaire" title="Ajouter un devoir"></a>    
  </div>

FIN;

  // Récupération de l'ensemble des devoirs
  $devoirs = $mysqli->query("SELECT id, description, nom, indications,
                             deadline>NOW() AS encours, DATE_FORMAT(deadline,'%d/%m/%Y %kh%i') AS limite,
                             DATE_FORMAT(deadline,'%w%Y%m%e') AS date_to, DATE_FORMAT(deadline,'%kh%i') AS heure_to,
                             dispo>NOW() AS bientot, IF(dispo,DATE_FORMAT(dispo,'%e/%m/%Y %kh%i'),0) AS dispo,
                             DATE_FORMAT(dispo,'%w%Y%m%e') AS date_from, DATE_FORMAT(dispo,'%kh%i') AS heure_from
                             FROM devoirs WHERE matiere = $mid $ordre");
  if ( $devoirs->num_rows )  {
    while ( $d = $devoirs->fetch_assoc() )  {
      // Affichage du devoir
      $date_to = format_date($d['date_to']);
      echo <<< FIN

  <article class="devoir" data-id="devoirs|${d['id']}" data-donnees="${d['nom']}|${d['limite']}|${d['dispo']}">
    <a class="icon-aide" title="Aide pour l'édition de ce devoir"></a>
    <a class="icon-supprime" title="Supprimer cette information"></a>
    <a class="icon-edite formulaire" title="Modifier ce devoir"></a>
    <a class="icon-download" href="?recupdevoir=${d['id']}" title="Télécharger l'ensemble des copies"></a>
    <a class="icon-voirtout" title="Voir le détail des copies"></a>
    <a class="icon-ajoute" title="Ajouter des documents"></a>
    <h3><span data-nom="${d['nom']}">${d['description']}</span> ($date_to)</h3>

FIN;
      // Dates
      if ( $d['bientot'] )
        echo '    <p class="horaire">Ce devoir n\'est pas encore visible, ne le sera qu\'à partir du '.format_date($d['date_from'])." à ${d['heure_from']} et jusqu'au $date_to à ${d['heure_to']}</p>\n";
      elseif ( $d['encours'] )
        echo "    <p class=\"horaire\">Ce devoir est en cours, les transferts de copies sont possibles, jusqu'au $date_to à ${d['heure_to']}.</p>\n";
      else 
        echo "    <p class=\"horaire\">Ce devoir est terminé, les transferts de copies ne sont plus possibles, depuis le $date_to à ${d['heure_to']}.</p>\n";
      // Indications
      $indications = ( $d['indications'] ) ? 'Indications pour les élèves&nbsp;:' : 'Vous n\'avez pas saisi d\'indication pour les élèves.';
      echo "    <div class=\"indications\"><em>$indications</em> ${d['indications']}</div>\n";

      // Récupération du nombre de participants
      $resultat = $mysqli->query("SELECT eleve FROM copies WHERE devoir = ${d['id']} AND numero < 100 GROUP BY eleve");
      if ( $n = $resultat->num_rows )  {
        echo "    <p>$n élève".( ( $n > 1 ) ? 's ont' : ' a' )." participé.</p>\n";
        $resultat->free();
      }
      else
        echo "    <p>Aucun élève n'a encore participé.</p>\n\n";
      // Récupération des nombres de corrections, sujets
      $resultat = $mysqli->query("SELECT eleve FROM copies WHERE devoir = ${d['id']} AND numero > 100 AND numero < 200");
      if ( $n1 = $resultat->num_rows )  {
        $message = "$n1 correction".(($n1>1)?'s':'');
        $resultat->free();
      }
      else 
        $message = '';
      $resultat = $mysqli->query("SELECT eleve FROM copies WHERE devoir = ${d['id']} AND numero > 200");
      if ( $n2 = $resultat->num_rows )  {
        $message = ( isset($message) ? "$message et " : '' )."$n2 sujet".(($n2>1)?'s':'');
        $resultat->free();
      }
      echo ( ( $n1+$n2 ) ? "    <p>Vous avez distribué $message.</p>\n" : "    <p>Vous n'avez pas distribué de documents personnels.</p>\n" );
      echo "  </article>\n\n";
    }
    $devoirs->free();
  }
  // Pas de devoirs 
  else
    echo "\n  <article>\n    <h2>Vous n'avez encore organisé aucun transfert de copie en ${matiere['nom']} cette année.</h2><p>Pour en créer un, cliquez sur l'icône <span class=\"icon-ajoute\"></span> en haut de cette page.</p>\n  </article>\n";
    
  // Récupération de la liste des élèves pour la construction du tableau d'envoi de corrections en javascript
  $resultat = $mysqli->query('SELECT id, CONCAT(nom,",",prenom) AS eleve FROM utilisateurs WHERE autorisation = 2 AND mdp > "0" ORDER BY IF(LENGTH(nom),nom,login)');
  $eids = array();
  $enoms = array();
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_row() )  {
      $eids[] = intval($r[0]);
      $enoms[] = $r[1];
    }
    $resultat->free();
  }  
  $mysqli->close();
  
  // Aide et formulaire d'ajout
?>

  <script type="text/javascript">
    eids = <?php echo json_encode($eids); ?>;
    enoms = <?php echo json_encode($enoms); ?>;
  </script>

  <form id="form-edite" data-action="devoirs">
    <input type="hidden" name="id" value="">
    <h3 class="edition">Modifier un devoir</h3>
    <p class="ligne"><label for="description">Titre&nbsp;: </label><input type="text" name="description" value="" size="100"></p>
    <p class="ligne"><label for="nom">Préfixe&nbsp;: </label><input type="text" name="nom" value="" size="15"></p>
    <p class="ligne"><label for="deadline">Date limite d'envoi&nbsp;: </label><input type="text" name="deadline" value="" size="15"></p>
    <p class="ligne"><label for="affdiff">Affichage différé&nbsp;: </label><input type="checkbox" name="affdiff" value="1"></p>
    <p class="ligne"><label for="dispo">Disponibilité &nbsp;: </label><input type="text" name="dispo" value="" size="15"></p>
    <p class="ligne"><label for="indications">Indications visibles pour les élèves&nbsp;: </label></p>
    <textarea name="indications" class="edithtml" rows="10" cols="100" placeholder="Indications visibles pour les élèves, lien vers le document que vous mettez en ligne..."></textarea>
  </form>
   
  <form id="form-ajoute" data-action="ajout-devoir">
    <input type="hidden" name="matiere" value="<?php echo $mid; ?>">
    <h3 class="edition">Ajouter un devoir</h3>
    <p class="ligne"><label for="description">Titre&nbsp;: </label><input type="text" name="description" value="" size="100" placeholder="Description (longue) du devoir"></p>
    <p class="ligne"><label for="nom">Préfixe&nbsp;: </label><input type="text" name="nom" value="" size="15" placeholder="Nom court du devoir"></p>
    <p class="ligne"><label for="deadline">Date limite d'envoi&nbsp;: </label><input type="text" name="deadline" value="" size="15"></p>
    <p class="ligne"><label for="affdiff">Affichage différé&nbsp;: </label><input type="checkbox" name="affdiff" value="1"></p>
    <p class="ligne"><label for="dispo">Disponibilité &nbsp;: </label><input type="text" name="dispo" value="" size="15"></p>
    <p class="ligne"><label for="indications">Indications visibles pour les élèves&nbsp;: </label></p>
    <textarea name="indications" class="edithtml" rows="10" cols="100" placeholder="Indications visibles pour les élèves, lien vers le document que vous mettez en ligne..."></textarea>
  </form>
  
  <form id="form-detail">
    <input type="hidden" name="id" value="">
    <table>
      <tbody>
        <tr>
          <th colspan="4"><label for="filtre">Filtre d'affichage&nbsp;: </label>
            <select name="filtre[]" multiple>
              <option value="0" selected>Copies</option>
              <option value="1">Corrections</option>
              <option value="2">Sujets</option>
            </select>
          </th>
          <th class="icones" colspan="3">
            <a class="icon-chronodesc" title="Classer par ordre chronologique inversé"></a>
            <a class="icon-chronoasc" title="Classer par ordre chronologique"></a>
            <a class="icon-alphadesc" title="Classer par ordre alphabétique inversé"></a>
            <a class="icon-alphaasc" title="Classer par ordre alphabétique"></a>
          </th>
        </tr>
        <tr>
          <th>Élève</th><th>Document</th><th>Date</th><th>Taille</th><th>Type</th>
          <th class="icones">
            <a class="icon-download" title="Télécharger l'ensemble des documents cochés"></a>
            <a class="icon-supprime" title="Supprimer l'ensemble des documents cochés"></a>
          </th>
          <th class="icones"><a class="icon-cocher" title="Tout cocher"></a></th>
        </tr>
      </tbody>
    </table>
  </form>

  <form id="form-ajoute-copies" data-action="ajout-devoir">
    <input type="hidden" name="id" value="">
    <input type="hidden" name="action" value="ajout-copies">
    <a class="icon-ferme" title="Fermer"></a>
    <a class="icon-ok" title="Envoyer"></a>
    <h3 class="edition">Ajouter des documents</h3>
    <p class="ligne"><label for="type">Type de document&nbsp;: </label>
      <select name="type">
        <option value="1">Corrections</option>
        <option value="2">Sujet</option>
      </select>
    </p>
    <p class="ligne"><label for="fichier[]">Fichiers&nbsp;: </label><input type="file" name="fichier[]" multiple></p>
  </form>

  <p id="log"></p>
  
  <script type="text/javascript" src="js/datetimepicker.min.js"></script>
<?php
}

fin($autorisation==5);
?>
