<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

///////////////////////////////////
// Validation de la requête : id //
///////////////////////////////////

// Récupération du lien
if ( !isset($_REQUEST['id']) || !ctype_digit($id = $_REQUEST['id']) )
  exit('Mauvais paramètre d\'accès à cette page.');
$mysqli = connectsql();
// Les documents "non visibles" (protection 32), sauf pour les professeurs
// associés à la matière, ne sont pas accessibles.
$resultat = $mysqli->query("SELECT d.id, d.parents, d.nom, d.lien, d.ext, d.protection, d.matiere AS mid, m.nom AS mat, m.cle
                            FROM docs AS d LEFT JOIN matieres AS m ON d.matiere = m.id
                            WHERE d.id = $id ".(( $autorisation == 5 ) ? "AND ( d.protection != 32 AND dispo < NOW() OR FIND_IN_SET(d.matiere,'${_SESSION['matieres']}') )" : 'AND d.protection != 32 AND dispo < NOW()'));
if ( $resultat->num_rows )  {
  $f = $resultat->fetch_assoc();
  $resultat->free();
}
else  {
  debut($mysqli,'Documents à télécharger','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}

/////////////////////////////
// Vérification de l'accès //
/////////////////////////////
acces($f['protection'],$f['mid'],$f['mat'] ? "Documents à télécharger - ${f['mat']}" : 'Documents à télécharger',$f['mat'] ? "docs?${f['cle']}" : 'docs',$mysqli);

// Définition du type de fichier et du type d'attachement (entête HTML à envoyer)
$attachment = 'attachment';
switch ( $f['ext'] )  {
  case '.pdf':
    $type = 'application/pdf';
    $attachment = 'inline';
    break;
  case '.doc':
    $type = 'application/msword';
    break;
  case '.odt':
    $type = 'application/vnd.oasis.opendocument.text';
    break;
  case '.docx':
    $type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    break;
  case '.xls':
    $type = 'application/vnd.ms-excel';
    break;
  case '.ods':
    $type = 'application/vnd.oasis.opendocument.spreadsheet';
    break;
  case '.xlsx':
    $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    break;
  case '.ppt':
    $type = 'application/vnd.ms-powerpoint';
    break;
  case '.odp':
    $type = 'application/vnd.oasis.opendocument.presentation';
    break;
  case '.pptx':
    $type = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    break;
  case '.jpg':
  case '.jpeg':
    $type = 'image/jpeg';
    $attachment = 'inline';
    break;
  case '.png':
    $type = 'image/png';
    $attachment = 'inline';
    break;
  case '.gif':
    $type = 'image/gif';
    break;
  case '.svg':
    $type = 'image/svg+xml';
    break;
  case '.tif':
  case '.tiff':
    $type = 'image/tiff';
    break;
  case '.bmp':
    $type = 'image/x-ms-bmp';
    break;
  case '.ps':
  case '.eps':
    $type = 'application/postscript';
    break;
  case '.avi':
    $type = 'video/x-msvideo';
    $attachment = 'inline';
    break;
  case '.mpeg':
  case '.mpg':
    $type = 'video/mpeg';
    $attachment = 'inline';
    break;
  case '.wmv':
    $type = 'video/x-ms-wmv';
    break;
  case '.mp4':
    $type = 'video/mp4';
    $attachment = 'inline';
    break;
  case '.ogv':
    $type = 'video/ogg';
    break;
  case '.qt':
  case '.mov':
    $type = 'video/quicktime';
    break;
  case '.mkv':
    $type = 'video/x-matroska';
    break;
  case '.mp3':
    $type = 'audio/mpeg';
    break;
  case '.ogg':
  case '.oga':
    $type = 'audio/ogg';
    break;
  case '.wma':
    $type = 'audio/x-ms-wma';
    break;
  case '.wav':
    $type = 'audio/x-wav';
    break;
  case '.ra':
  case '.rm':
    $type = 'audio/x-pn-realaudio';
    break;
  case '.txt':
    $type = 'text/plain';
    break;
  case '.rtf':
    $type = 'application/rtf';
    break;
  case '.zip':
    $type = 'application/zip';
    break;
  case '.rar':
    $type = 'application/rar';
    break;
  case '.7z':
    $type = 'application/x-7z-compressed';
    break;
  case '.sh':
    $type = 'text/x-sh';
    break;
  case '.py':
    $type = 'text/x-python';
    break;
  case '.swf':
    $type = 'application/x-shockwave-flash';
    $attachment = 'inline';
    break;
  default :
    $type = 'application/octet-stream';
}
// Download forcé
if ( isset($_REQUEST['dl']) )
  $attachment = 'attachment';

// Mise à disposition du fichier
header("Content-Type: $type");
header("Content-Disposition: $attachment; filename=\"${f['nom']}${f['ext']}\"");
readfile("documents/${f['lien']}/${f['nom']}${f['ext']}");
?>
