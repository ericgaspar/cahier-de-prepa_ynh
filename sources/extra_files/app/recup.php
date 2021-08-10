<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

// Vérification du token CSRF
//if ( !isset($_REQUEST['csrf-token']) || isset($_SESSION['csrf-token']) && ( $_REQUEST['csrf-token'] != $_SESSION['csrf-token'] ) )
//  exit('{"etat":"nok","message":"Accès non autorisé"}');

// Récupération de l'action
if ( !isset($_REQUEST['action']) || !in_array($action = $_REQUEST['action'],array('docs','prefs','compteglobal','copies')) )
  exit('{"etat":"nok","message":"Mauvais paramètrage"}');

///////////////////////////////////////////////
// Récupération des répertoires et documents //
///////////////////////////////////////////////
if ( ( $action == 'docs' ) && ( $autorisation == 5 ) )  {

  $mysqli = connectsql(false);
  $mats = '<option value="-1">[Choisissez une matière]</option>';
  $reps = array( -1 =>'<option value="-1">[Choisissez une matière]</option>');
  $docs = array( -1 => '<option value="0">[Choisissez une matière]</option>', 0 => '<option value="0">[Choisissez un répertoire]</option>' );
  // Récupération des répertoires, avec le chemin complet
  $resultat = $mysqli->query("SELECT r.id, r.nom, r.matiere,
                              CONCAT( ( SELECT GROUP_CONCAT(reps.nom SEPARATOR '/') FROM reps WHERE FIND_IN_SET(reps.id,r.parents) ) ,'/') AS parents
                              FROM reps AS r LEFT JOIN matieres AS m ON m.id = r.matiere
                              WHERE FIND_IN_SET(r.matiere,'${_SESSION['matieres']}') ORDER BY m.ordre, CONCAT(parents, r.nom)");
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )  {
      if ( !isset($reps[$r['matiere']]) )  {
        $reps[$r['matiere']] = '<option value="0">[Choisissez un répertoire]</option>';
        $mats .= "<option value=\"${r['matiere']}\">".( ( strpos($r['parents'],'/') ) ? strstr($r['parents'],'/',true) : $r['nom'] ).'</option>';
      }
      $reps[$r['matiere']] .= "<option value=\"${r['id']}\">${r['parents']}${r['nom']}</option>";
      // Récupération des documents
      $docs[$r['id']] = '<option value="0">[Choisissez un document]</option>';
      $res = $mysqli->query("SELECT id, nom, SUBSTRING(ext,2) AS ext FROM docs WHERE parent = ${r['id']} AND protection < 32");
      while ( $d = $res->fetch_assoc() )  {
        $docs[$r['id']] .= "<option value=\"${d['id']}\">${d['nom']} (${d['ext']})</option>";
      }
      $res->free();
    }
    $resultat->free();
  }
  exit(json_encode(array('recupok'=>1,'mats'=>$mats,'reps'=>$reps,'docs'=>$docs)));

}

///////////////////////////////////////////////
// Récupération des données d'un utilisateur //
///////////////////////////////////////////////
elseif ( ( $action == 'prefs' ) && ( $autorisation == 5 ) && connexionlight() && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {

  // Vérification que l'identifiant est valide
  $mysqli = connectsql(false);
  $resultat = $mysqli->query("SELECT nom, prenom, login, matieres, mail as mail1, (LENGTH(mdp)=40) AS valide, (LEFT(mdp,1)='*') AS demande, (LENGTH(mdp)=1) AS invitation, autorisation, mailexp, mailcopie FROM utilisateurs WHERE id = $id");
  if ( $resultat->num_rows )  {
    $r = $resultat->fetch_assoc();
    $resultat->free();
    // Problème d'encodage des entiers, renvoyés en tant que chaîne.
    // Semble dépendre du driver MySQL, ne prenons pas de risque
    $r['valide'] = intval($r['valide']);
    $r['demande'] = intval($r['demande']);
    $r['invitation'] = intval($r['invitation']);
    $r['autorisation'] = intval($r['autorisation']);
    $r['mailcopie'] = intval($r['mailcopie']);
    // Récupération des autorisations d'envoi
    $resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
    $r['mailenvoi'] = ( $r['autorisation'] > 1 ) ? intval(( $resultat->fetch_row()[0] >> 4*($r['autorisation']-2) & 15 ) > 0) : 0;
    $resultat->free();
    $r['mail2'] = '';
    $r['etat'] = 'recupok';
    exit(json_encode($r));
  }
  exit('{"etat":"nok","message":"Identifiant non valide"}');

}

///////////////////////////////////////////////
// Récupération des données d'un utilisateur //
///////////////////////////////////////////////
elseif ( ( $action == 'compteglobal' ) && ( $autorisation > 1 ) && $interfaceglobale )  {

  // $_SESSION['compteglobal'] contient l'identifiant du compte à utiliser
  // La deuxième partie de la requête sert de vérification : 
  //  * compte contenant une connexion vers cet utilisateur de ce Cahier
  //  * compte contenant au moins une autre connexion 
  $mysqli = connectsql(false,$interfaceglobale);
  $resultat = $mysqli->query("SELECT connexions FROM comptes 
                              WHERE id = ${_SESSION['compteglobal']} 
                                AND FIND_IN_SET((SELECT id FROM cahiers WHERE rep = TRIM(BOTH '/' FROM '${GLOBALS['chemin']}'))*1000+${_SESSION['id']}, connexions)
                                AND LOCATE(',',connexions)");
  if ( $resultat->num_rows )  {
    $cahiers = implode(',', array_filter(array_map( function($v){return ($v>0)?intdiv($v,1000):false;}, explode(',',$resultat->fetch_row()[0]) )) );
    $resultat->free();
    $resultat = $mysqli->query("SELECT rep, CONCAT(classe,' - ',nom,' (',ville,') ') AS classe
                                FROM cahiers LEFT JOIN lycees ON lycee = lycees.id
                                WHERE FIND_IN_SET(cahiers.id,'$cahiers') ORDER BY FIND_IN_SET(cahiers.id,'$cahiers')");
    $reps = array();
    while ( $r = $resultat->fetch_assoc() ) 
      if ( "/${r['rep']}/" != $chemin )
        $reps[$r['rep']] = $r['classe'];
    $resultat->free();
    $mysqli->close();
    exit(json_encode(array('etat'=>'recupok','cahiers'=>$reps)));
  }
  exit('{"etat":"nok","message":"Identifiant non valide"}');
}

///////////////////////////////////////////////////////
// Récupération des données de copies pour un devoir //
///////////////////////////////////////////////////////
elseif ( ( $action == 'copies' ) && ( $autorisation == 5 ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  
  // Gestion de l'ordre d'affichage
  switch ( $_REQUEST['ordre'] ?? '' )  {
    case 'alphadesc':          $ordre = 'ORDER BY nomcomplet DESC, c1.type, num'; break;
    case 'chronoasc':          $ordre = 'ORDER BY upload DESC';      break;
    case 'chronodesc':         $ordre = 'ORDER BY upload';           break;
    case 'alphaasc': default:  $ordre = 'ORDER BY nomcomplet, c1.type, num';
  }
  // Gestion du type demandé, sélection sur les numéros 
  $numero = '';
  if ( isset($_REQUEST['types']) && ($types = array_filter(explode(',',$_REQUEST['types']),function($id) { return in_array($id,array(0,1,2)); })) )  {
    switch ( count($types) )  {
      case 1:
        switch ( $types[0] )  {
          case 0: $numero = 'AND numero < 100'; break;
          case 1: $numero = 'AND numero > 100 AND numero < 200'; break;
          case 2: $numero = 'AND numero > 200';
        }
        break;
      case 2:
        if ( $types[0] == 0 )  {
          if ( $types[1] == 1 )  $numero = 'AND numero < 200';
          else                   $numero = 'AND ( numero < 100 OR numero > 200 )';
        }
        else                     $numero = 'AND numero > 100';
    }
  }
  // Récupération
  $mysqli = connectsql(false);
  $resultat = $mysqli->query("SELECT c1.id, CONCAT(nom,' ',prenom) AS nomcomplet, c1.type, num, date, taille, ext, n, c1.eleve
                              FROM ( SELECT id, devoir, eleve, (numero DIV 100) AS type, (numero MOD 100) AS num, upload,
                                     DATE_FORMAT(upload,'%e/%m/%Y %kh%i') AS date, LOWER(SUBSTRING(ext,2)) AS ext, taille
                                     FROM copies WHERE devoir = $id $numero ) AS c1
                              LEFT JOIN utilisateurs ON eleve = utilisateurs.id
                              LEFT JOIN ( SELECT COUNT(*) AS n, eleve, (numero DIV 100) AS type FROM copies
                                          WHERE devoir = $id GROUP BY eleve, type ) AS c2
                                        ON c1.eleve = c2.eleve AND c1.type = c2.type
                              $ordre");
  $lignes = array();
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_row() )  {
      // Problème d'encodage des entiers, renvoyés en tant que chaîne.
      // Semble dépendre du driver MySQL, ne prenons pas de risque
      // Pas d'affichage du numéro s'il n'y a qu'un document du même type pour l'élève
      $r[2] = intval($r[2]);
      $r[3] = ( $r[7] == 1 ) ? '' : intval($r[3]);
      $r[8] = intval($r[8]);
      // Enregistrement
      $lignes[] = $r;
    }
    $resultat->free();
  }
  exit(json_encode(array('etat' => 'recupok', 'lignes' => $lignes)));
}

// Réponse par défaut
exit( strlen($message) ? $message : '{"etat":"nok","message":"Accès interdit"}' );
?>
