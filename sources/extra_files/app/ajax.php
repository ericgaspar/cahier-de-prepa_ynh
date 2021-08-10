<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

// Vérification du token CSRF
if ( !isset($_REQUEST['csrf-token']) || isset($_SESSION['csrf-token']) && ( $_REQUEST['csrf-token'] != $_SESSION['csrf-token'] ) )
  exit('{"etat":"nok","message":"Accès non autorisé"}');

// Récupération de l'action
if ( !isset($_REQUEST['action']) || !in_array($action = $_REQUEST['action'],array('deconnexion', 'courriel', 'prefsperso', 'ajout-copie', 'suppr-copie', 'notes', 'ajout-notes', 'releve-notes', 'infos', 'ajout-info', 'reps', 'ajout-rep', 'docs', 'ajout-doc', 'colles', 'ajout-colle', 'cdt-elems', 'cdt-types', 'ajout-cdt-types', 'cdt-raccourcis', 'ajout-cdt-raccourci', 'pages', 'ajout-page', 'matieres', 'ajout-matiere', 'utilisateur', 'utilisateurs','ajout-utilisateurs', 'utilisateur-matiere', 'utilisateurs-matieres', 'groupes', 'ajout-groupe', 'planning', 'agenda-elems', 'deplcolle', 'agenda-types', 'ajout-agenda-types', 'ajout-devoir', 'devoirs', 'ajout-copies', 'suppr-copies', 'prefsmatiere', 'prefsglobales')) )
  exit('{"etat":"nok","message":"Aucune action effectuée"}');

// Demande de déconnexion
if ( $action == 'deconnexion' )  {
  suppression_session();
  // Recharge immédiate, donc besoin de $_SESSION['message']
  exit($_SESSION['message'] = '{"etat":"ok","message":"Déconnexion réussie"}');
}
// Si non autorisé, la session a dû expirer : il faut se reconnecter
if ( $autorisation == 0 )
  exit('{"etat":"login"}');

///////////////////////
// Envoi de courriel //
///////////////////////
if ( ( $action == 'courriel' ) && connexionlight() && isset($_REQUEST['id-copie']) && isset($_REQUEST['sujet']) && isset($_REQUEST['texte']) )  {
  // Envoi possible seulement si autorisé
  $mysqli = connectsql();
  $resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
  if ( ( $aut_envoi = $resultat->fetch_row()[0] >> 4*($autorisation-2) & 15 ) == 0 )
    exit('{"etat":"nok","message":"L\'envoi de courriel n\'est pas autorisé."}');
  $aut_dest = implode(',',array_keys(str_split('00'.strrev(decbin($aut_envoi))),1));
  $resultat->free();
  // Vérification des données
  if ( !($sujet = $_REQUEST['sujet']) )
    exit('{"etat":"nok","message":"Pas de sujet : courriel non envoyé"}');
  elseif ( !($texte = $_REQUEST['texte']) )
    exit('{"etat":"nok","message":"Pas de texte : courriel non envoyé"}');
  // Vérification de l'adresse électronique
  $resultat = $mysqli->query("SELECT mailexp, mail FROM utilisateurs WHERE id = ${_SESSION['id']}");
  $u = $resultat->fetch_assoc();
  $resultat->free();
  if ( !$u['mailexp'] || !filter_var($u['mail'],FILTER_VALIDATE_EMAIL) )
    exit('{"etat":"nok","message":"Compte mal réglé&nbsp;: nom ou adresse d\'expédition manquants"}');
  // Récupération des destinataires, comptes valides uniquement
  $resultat = $mysqli->query("SELECT id, mail, mailexp FROM utilisateurs WHERE mail > '' AND mdp > '0' AND id != ${_SESSION['id']} AND FIND_IN_SET(autorisation,'$aut_dest') ORDER BY autorisation DESC, nom");
  $mysqli->close();
  while ( $r = $resultat->fetch_assoc() )
    $utilisateurs[$r['id']] = $r;
  $resultat->free();
  $dests = '';
  $ids = explode(',',$_REQUEST['id-copie']);
  foreach ( $ids as $i )
    if ( isset($utilisateurs[$i]) )  {
      $dests .= '=?UTF-8?B?'.base64_encode($utilisateurs[$i]['mailexp']).'?= <'.$utilisateurs[$i]['mail'].'>, ';
      unset($utilisateurs[$i]);
    }
  if ( !$dests )
    exit('{"etat":"nok","message":"Pas de destinataire valide : courriel non envoyé"}');
  // Fabrication du mail
  $dests = substr($dests,0,-2);
  $bcc = ( isset($_REQUEST['copie']) ) ? "${u['mailexp']} <${u['mail']}>, " : '';
  if ( $_REQUEST['id-bcc'] )
    $ids = explode(',',$_REQUEST['id-bcc']);
    foreach ( $ids as $i )
      if ( isset($utilisateurs[$i]) )  {
        $bcc .= '=?UTF-8?B?'.base64_encode($utilisateurs[$i]['mailexp']).'?= <'.$utilisateurs[$i]['mail'].'>, ';
        unset($utilisateurs[$i]);
      }
  $bcc = ( $bcc ) ? 'Bcc: '.substr($bcc,0,-2) : '';
  mail($dests,'=?UTF-8?B?'.base64_encode($sujet).'?=',$texte,'From: =?UTF-8?B?'.base64_encode($u['mailexp']).'?= <nepasrepondre'.strstr($mailadmin,'@').">\r\nReply-To: =?UTF-8?B?".base64_encode($u['mailexp'])."?= <${u['mail']}>\r\nContent-type: text/plain; charset=UTF-8\r\n$bcc","-f${u['mail']}");
  // Message de confirmation d'envoi
  $n1 = substr_count($dests,'<');
  $n2 = substr_count($bcc,'<') - isset($_REQUEST['copie']);
  if ( $n2 )
    $message = 'Le courriel a été envoyé à '.($n1+$n2).' destinataires (dont '.$n2.' en copie cachée).';
  else
    $message = ( $n1 > 1 ) ? "Le courriel a été envoyé à $n1 destinataires." : 'Le courriel a été envoyé à 1 destinataire.';
  exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"$message\"}");

}

//////////////////////////////
// Préférences personnelles //
//////////////////////////////
elseif ( ( $action == 'prefsperso' ) && ( $autorisation > 1 ) )  {

  // Spécifications pour les manipulations de caractères sur 2 octets (accents)
  mb_internal_encoding('UTF-8');
  // Vérification obligatoire du mot de passe et récupération des données de l'utilisateur
  if ( !isset($_REQUEST['mdp']) )
    exit('{"etat":"nok","message":"Mot de passe incorrect"}');
  $mysqli = connectsql(true);
  $resultat = $mysqli->query("SELECT * FROM utilisateurs WHERE id = ${_SESSION['id']}");
  $r = $resultat->fetch_assoc();
  $resultat->free();
  if ( sha1($mdp.$_REQUEST['mdp']) != $r['mdp'] )
    exit('{"etat":"nok","message":"Mot de passe incorrect"}');
  // Passage de la connexion light à normale si besoin
  if ( $_SESSION['light'] )
    enregistre_session(false,false,$r['timeout']);
  
  // Fonction de fabrication de la partie modifiante de la requête
  function fabriqueupdate($requete,$mysqli)  {
    $chaine = '';
    foreach ($requete as $champ=>$val)
      $chaine .= ",$champ = '".$mysqli->real_escape_string($val).'\'';
    return substr($chaine,1);
  }
  
  // Premier cadre de modifications de prefs.php : indentité
  if ( ( $_REQUEST['id'] == 1 ) && isset($_REQUEST['prenom']) && isset($_REQUEST['nom']) )  {
    // Vérification et nettoyage des données
    if ( !($prenom = mb_convert_case(strip_tags(trim($_REQUEST['prenom'])),MB_CASE_TITLE)) || !($nom = mb_convert_case(strip_tags(trim($_REQUEST['nom'])),MB_CASE_TITLE)) )
      exit('{"etat":"nok","message":"Votre compte n\'a pas été modifié. Le prénom et le nom doivent rester non vides."}');
    // Construction de la requête
    $requete = array();
    if ( $prenom != $r['prenom'] )
      $requete['prenom'] = $prenom;
    if ( $nom != $r['nom'] )
      $requete['nom'] = $nom;
    if ( !$requete )
      exit('{"etat":"nok","message":"Aucune modification à faire"}');
    if ( requete('utilisateurs','UPDATE utilisateurs SET ' .fabriqueupdate($requete,$mysqli) . " WHERE id = ${_SESSION['id']}",$mysqli) )  {
      // Si interface globale activée, mise à jour
      if ( $interfaceglobale )  {
        include("${interfaceglobale}majutilisateurs.php");
        majutilisateurs($_SESSION['id'],$requete);
      }
      exit('{"etat":"ok","message":"Vos préférences ont été modifiées."}');
    }
    exit('{"etat":"nok","message":"Votre compte n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Deuxième cadre de modifications de prefs.php : mot de passe
  if ( ( $_REQUEST['id'] == 2 ) && isset($_REQUEST['mdp1']) && isset($_REQUEST['mdp2']) )  {
    if ( !($mdp1 = $_REQUEST['mdp1']) || ( $mdp1 != $_REQUEST['mdp2'] ) )
      exit('{"etat":"nok","message":"Nouveau mot de passe et confirmation différents"}');
    $requete = array('mdp'=>sha1($mdp.$mdp1));
    // Token de connexion automatique à renouveler si existant
    if ( $_SESSION['permconn'] )  {
      $permconn = '';
      for ( $i = 0; $i < 10; $i++ )
        $permconn .= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0,61)];
      $requete['permconn'] = $permconn;
    }
    if ( !$requete )
      exit('{"etat":"nok","message":"Aucune modification à faire"}');
    # Éxécution
    if ( requete('utilisateurs','UPDATE utilisateurs SET ' .fabriqueupdate($requete,$mysqli) . " WHERE id = ${_SESSION['id']}",$mysqli) )  {
      if ( isset($permconn) )
        setcookie('CDP_SESSION_PERM',$permconn,time()+31536000,$chemin,$domaine,true);
      // Si interface globale activée, mise à jour
      if ( $interfaceglobale )  {
        include("${interfaceglobale}majutilisateurs.php");
        majutilisateurs($_SESSION['id'],$requete);
      }
      exit($_SESSION['message'] = '{"etat":"ok","message":"Votre mot de passe a été modifié."}');
    }
    exit('{"etat":"nok","message":"Votre mot de passe n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Troisième cadre de modifications de prefs.php : adresse électronique
  if ( ( $_REQUEST['id'] == 3 ) && isset($_REQUEST['mail']) )  {
    // Vérification et nettoyage des données
    if ( !filter_var($mail = mb_strtolower(trim($_REQUEST['mail'])),FILTER_VALIDATE_EMAIL) )
      exit('{"etat":"nok","message":"L\'adresse électronique doit être valide et non vide."}');
    // Vérification que l'adresse n'est pas déjà utilisée
    $resultat = $mysqli->query('SELECT id FROM utilisateurs WHERE mail = \''.$mysqli->real_escape_string($mail)."' AND id != ${_SESSION['id']}");
    if ( $resultat->num_rows )
      exit('{"etat":"nok","message":"Adresse électronique non disponible"}');
    // Si pas de code de confirmation : envoi de courriel
    if ( !isset($_REQUEST['confirmation']) )  {
      // On ajoute 15 minutes au temps utilisé : de xh00 à xh45,
      // on a jusqu'à (x+1)h, de xh45 à (x+1)h on a jusqu'à (x+2)h
      $t = time() + 900;
      $p = substr(sha1($chemin.$mdp.date('Y-m-d-H',$t).$mail),0,8);
      mail($mail,'=?UTF-8?B?'.base64_encode('[Cahier de Prépa] Changement d\'adresse électronique').'?=',
"Bonjour

Vous avez demandé à modifier l'adresse électronique liée à votre compte sur le Cahier de Prépa <https://$domaine$chemin>.

Cette demande nécessite la vérification que vous possédez l'adresse à laquelle vous recevez ce courriel.

Pour ce faire, vous devez copier, sur la page qui a généré ce courriel, le code suivant :

     $p

Ce code est valable jusqu'à ".date('G\h00',$t+3600).'.

Si cette demande ne vient pas de vous, merci d\'ignorer ce courriel et éventuellement de le signaler à l\'administrateur en répondant à ce courriel.

Cordialement,
-- 
Cahier de Prépa
  ','From: =?UTF-8?B?'.base64_encode('Cahier de Prépa')."?= <$mailadmin>\r\nContent-type: text/plain; charset=UTF-8","-f$mailadmin");
      exit('{"etat":"confirm_mail","message":"<strong>Un courriel vient de vous être envoyé à l\'adresse <code>'.$mail.'</code>.</strong><br>Il contient un code, valable jusqu\'à '.date('G\h00',$t+3600).', que vous devez copier-coller ci-dessous pour réaliser l\'opération.<br>Si vous ne voyez rien, pensez à regarder dans les courriels marqués comme spam. Certains serveurs retardent jusqu\'à 10 minutes l\'arrivée des messages, normalement la première fois uniquement."}');
    }
    // $_REQUEST['p'] est obligatoirement défini. Il s'agit du sha1 de $chemin.$mdp.date('Y-m-d-H').$mail
    if ( ( ($p=$_REQUEST['confirmation']) != substr(sha1($chemin.$mdp.date('Y-m-d-H').$mail),0,8) ) && ( $p != substr(sha1($chemin.$mdp.date('Y-m-d-H',time()+900).$mail),0,8) ) )
      exit('{"etat":"nok","message":"Le code saisi n\'est pas correct. Si vous avez dépassé le délai, vous devez recommencer la procédure."}');
    // Modification
    if ( requete('utilisateurs','UPDATE utilisateurs SET mail = \'' .$mysqli->real_escape_string($mail)."' WHERE id = ${_SESSION['id']}",$mysqli) )  {
      // Si interface globale activée, mise à jour
      if ( $interfaceglobale )  {
        include("${interfaceglobale}majutilisateurs.php");
        majutilisateurs($_SESSION['id'],array('mail'=>$mail));
      }
      exit($_SESSION['message'] = '{"etat":"ok","message":"Vos préférences ont été modifiées."}');
    }
    exit('{"etat":"nok","message":"Votre compte n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Quatrième cadre de modifications de prefs.php : connexion
  if ( ( $_REQUEST['id'] == 4 ) && isset($_REQUEST['login']) && isset($_REQUEST['timeout']) && ctype_digit($timeout = $_REQUEST['timeout']) )  {
    if ( !($login = mb_strtolower(str_replace(' ','_',strip_tags(trim($_REQUEST['login']))))) )
      exit('{"etat":"nok","message":"Votre compte n\'a pas été modifié. L\'identifiant doit être non vide."}');
    $requete = array();
    if ( $login != $_SESSION['login'] )  {
      // Vérification que le login n'existe pas déjà
      $resultat = $mysqli->query('SELECT id FROM utilisateurs WHERE login = \''.$mysqli->real_escape_string($login)."' AND id != ${_SESSION['id']}");
      if ( $resultat->num_rows )
        exit('{"etat":"nok","message":"Adresse électronique non disponible"}');
      $requete['login'] = $login;
    }
    // Token de connexion automatique à ajouter si non déjà présent et demandé
    if ( !$_SESSION['permconn'] && isset($_REQUEST['permconn']) )  {
      $permconn = '';
      for ( $i = 0; $i < 10; $i++ )
        $permconn .= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0,61)];
      $requete['permconn'] = $permconn;
    }
    elseif ( $_SESSION['permconn'] && !isset($_REQUEST['permconn']) )
      $requete['permconn'] = '';
    // Timeout
    if ( ( $timeout = ( $timeout > 15 ) ? $timeout : 900 ) != $_SESSION['timeout'] )
      $requete['timeout'] = $timeout;
    if ( !$requete )
      exit('{"etat":"nok","message":"Aucune modification à faire"}');
    # Éxécution
    if ( requete('utilisateurs','UPDATE utilisateurs SET ' .fabriqueupdate($requete,$mysqli) . " WHERE id = ${_SESSION['id']}",$mysqli) )  {
      // Mise à jour des données de session et cookies
      if ( isset($requete['login']) )
        $_SESSION['login'] = $login;
      if ( isset($requete['timeout']) )  {
        $_SESSION['timeout'] = $timeout;
        $_SESSION['time'] = time()+$_SESSION['timeout'];
      }
      if ( isset($requete['permconn']) )  {
        $_SESSION['permconn'] = $permconn;
        setcookie('CDP_SESSION_PERM',$permconn,( $permconn ) ? time()+31536000 : time()-3600,$chemin,$domaine,true);
      }
      // Si interface globale activée, mise à jour
      if ( $interfaceglobale )  {
        include("${interfaceglobale}majutilisateurs.php");
        majutilisateurs($_SESSION['id'],$requete);
      }
      exit('{"etat":"ok","message":"Vos préférences de connexion ont été modifiées."}');
    }
    exit('{"etat":"nok","message":"Votre compte n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Cinquième cadre de modifications de prefs.php : envoi de courriel
  if ( ( $_REQUEST['id'] == 5 ) && isset($_REQUEST['mailexp']) )  {
    // Réglages disponibles seulement si autorisé globalement
    $resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
    if ( !( $resultat->fetch_row()[0] >> 4*($autorisation-2) & 15 ) )
      exit('{"etat":"nok","message":"Ce réglage n\'est pas autorisé.'.$a.'-'.$autorisation.'"}');
    $resultat->free();
    // Pas d'envoi si l'adresse électronique est vide
    if ( !$r['mail'] )
      exit('{"etat":"nok","message":"Vous ne pourrez pas envoyer de courriels sans adresse électronique. Vous devez la saisir avant de modifier ces préférences."}');
    if ( !( $mailexp = $mysqli->real_escape_string(strip_tags(trim($_REQUEST['mailexp']))) ) )
      exit('{"etat":"nok","message":"Votre compte n\'a pas été modifié. Le nom d\'expéditeur doit être non vide."}');
    // Éxécution
    if ( requete('utilisateurs',"UPDATE utilisateurs SET mailexp = '$mailexp', mailcopie = ".intval(isset($_REQUEST['mailcopie']))." WHERE id = ${_SESSION['id']}",$mysqli) )
      exit('{"etat":"ok","message":"Vos préférences d\'envoi de courriel ont été modifiées."}');
    exit('{"etat":"nok","message":"Votre compte n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

}

///////////////////////////////////////////
// Transfert de copie : élèves seulement //
///////////////////////////////////////////
elseif ( ( $action == 'ajout-copie' ) && ( $autorisation == 2 ) && connexionlight() && isset($_FILES['fichier']['tmp_name']) && is_uploaded_file($_FILES['fichier']['tmp_name']) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  $mysqli = connectsql(true);
  // Vérification du devoir
  $resultat = $mysqli->query("SELECT lien, nom, deadline > NOW() AS ok, matiere FROM devoirs WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de devoir non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  if ( !$r['ok'] )
    exit('{"etat":"nok","message":"C\'est trop tard&nbsp;! Vous avez dépassé la date maximale."}');
  // Récupération de l'extension. $ext ne doit pas faire plus de
  // 5 caractères sinon fichier plus accessible
  setlocale(LC_CTYPE, "fr_FR.UTF-8");
  $ext = ( strpos($_FILES['fichier']['name'],'.') ) ? substr(strrchr($_FILES['fichier']['name'],'.'),0,5) : '';
  // Récupération du numéro
  $resultat = $mysqli->query("SELECT id FROM copies WHERE devoir = $id AND eleve = ${_SESSION['id']} AND numero < 100");
  if ( ( $n = $resultat->num_rows + 1 ) > 1 )
    $resultat->free();
  // Gestion de la taille
  $taille = ( ( $taille = intval($_FILES['fichier']['size']/1024) ) < 1024 ) ? "$taille&nbsp;ko" : intval($taille/1024).'&nbsp;Mo';
  // Déplacement du document uploadé au bon endroit
  if ( !move_uploaded_file($_FILES['fichier']['tmp_name'],"documents/${r['lien']}/${_SESSION['id']}_tmp$ext") )
    exit('{"etat":"nok","message":"Votre copie n\'a pas été ajoutée : problème d\'écriture du fichier. Vous devriez en informer l\'administrateur."}');
  // Écriture MySQL
  if ( requete('copies',"INSERT INTO copies SET devoir = $id, eleve = ${_SESSION['id']}, matiere = ${r['matiere']}, numero = $n, upload = NOW(), taille = '$taille', ext = '$ext'",$mysqli) )  {
    rename("documents/${r['lien']}/${_SESSION['id']}_tmp$ext","documents/${r['lien']}/${_SESSION['id']}_".$mysqli->insert_id.$ext);
    exit($_SESSION['message'] = '{"etat":"ok","message":"Votre copie a été ajoutée."}');
  }
  // Retour en arrière
  unlink("documents/${r['lien']}/${_SESSION['id']}_tmp$ext");
  exit('{"etat":"nok","message":"Votre copie n\'a pas été ajoutée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

/////////////////////////////////////////////////////////////////////////////
// Suppression individuelle des copies transférées : élèves ou professeurs //
/////////////////////////////////////////////////////////////////////////////
elseif ( ( $action == 'suppr-copie' ) && connexionlight() && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  $mysqli = connectsql(true);
  // Vérification de l'identifiant
  $resultat = $mysqli->query("SELECT eleve, lien, numero, ext, devoirs.id AS did, ( numero DIV 100 ) AS type
                              FROM copies LEFT JOIN devoirs ON copies.devoir = devoirs.id
                              WHERE copies.id = $id AND FIND_IN_SET(copies.matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de devoir non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  // Un élève ne peut supprimer que ses copies
  if ( ( $autorisation == 2 ) && ( ( $r['eleve'] != $_SESSION['id'] ) || ( $r['type'] > 0 ) ) )
    exit('{"etat":"nok","message":"Identifiant de copie non valide"}');
  // Suppression physique
  unlink("documents/${r['lien']}/${r['eleve']}_$id${r['ext']}");
  // Suppression dans la base
  exit( requete('copies',"DELETE FROM copies WHERE id = $id",$mysqli)
     && requete('copies',"UPDATE copies SET numero = numero-1 WHERE devoir = ${r['did']} AND eleve = ${r['eleve']} AND numero > ${r['numero']} AND ( numero DIV 100 ) = ${r['type']}",$mysqli)
     ? '{"etat":"ok","message":"Votre copie a été supprimée."}' : '{"etat":"nok","message":"Votre copie n\'a pas été supprimée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'».');
}

///////////////////////////////////////////////////////////
// Modification de notes (accès colleurs et professeurs) //
///////////////////////////////////////////////////////////
elseif ( ( $action == 'notes' ) && ( ( $autorisation == 3 ) || ( $autorisation == 5 ) ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {

  // Connexion normale obligatoire
  connexionlight();
  $mysqli = connectsql(true);
  // Spécifications pour les manipulations de caractères sur 2 octets (accents)
  mb_internal_encoding('UTF-8');

  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT matiere, DATE_FORMAT(jour,'%w%Y%m%e') AS date, DATE_FORMAT(jour,'%Y-%m-%d') AS jour,
                              TIME_FORMAT(heure,'%k:%i') AS heure, duree, releve>0 AS releve, description 
                              FROM heurescolles WHERE id = $id AND colleur = ${_SESSION['id']}");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  $matiere = $r['matiere'];
  
  // Suppression
  if ( isset($_REQUEST['supprime']) )  {
    if ( $r['releve'] > 0 )
      exit('{"etat":"nok","message":"La colle n\'a pas été supprimée car elle a déjà été relevée."}');
    // Heures en séances de TD (pas de notes associées)
    if ( strlen($r['description']) )
      exit( requete('heurescolles',"DELETE FROM heurescolles WHERE id = $id",$mysqli)
          ? '{"etat":"ok","message":"La séance du <em>'.format_date($r['date']).'</em> a été supprimée."}'
          : '{"etat":"nok","message":"La séance du <em>'.format_date($r['date']).'</em> n\'a pas été supprimée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    // Colle avec notes
    $resultat = $mysqli->query("SELECT * FROM notes WHERE heure = $id");
    $n = $resultat->num_rows;
    $resultat->free();
    exit( requete('notes',"DELETE FROM notes WHERE heure = $id",$mysqli)
       && requete('heurescolles',"DELETE FROM heurescolles WHERE id = $id",$mysqli)
       && $mysqli->query('UPDATE matieres SET notes = IF((SELECT id FROM notes WHERE matiere = matieres.id LIMIT 1),1,0)')
          ? "{\"etat\":\"ok\",\"message\":\"Les $n notes du <em>".format_date($r['date']).'</em> ont été supprimées."}'
          : "{\"etat\":\"nok\",\"message\":\"Les $n notes du <em>".format_date($r['date']).'</em> n\'ont pas été supprimées. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Modification
  if ( isset($_REQUEST['jour']) && isset($_REQUEST['heure']) )  {
    
    // Heures en séances de TD (pas de notes associées)
    if ( strlen($r['description']) )  {
      $requete = array();
      // Validation du jour
      if ( $r['jour'] != ( $jour = preg_filter('/(\d{2})\/(\d{2})\/(\d{4})/','$3-$2-$1',$_REQUEST['jour']) ) )  {
        // Vérification que le jour est bien dans l'année
        $resultat = $mysqli->query("SELECT id FROM semaines WHERE debut <= '$jour' AND debut >= SUBDATE('$jour',7) ORDER BY debut DESC LIMIT 1");
        if ( !$resultat->num_rows )
          exit('{"etat":"nok","message":"Le jour choisi ne se trouve pas dans l\'année scolaire. S\'il s\'agit d\'une erreur, il faut peut-être contacter l\'administrateur."}');
        $resultat->free();
        $requete[] = "jour = '$jour'";
      }
      // Validation de l'heure
      if ( ( $r['heure'] != ( $heure = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['heure']) ) ) && strlen($heure) )
        $requete[] = "heure = '$heure'";
      // Validation de la durée (séance non relevée uniquement)
      if ( ( $r['releve'] == 0 ) && isset($_REQUEST['duree']) && ( $r['duree'] != ( $duree = call_user_func_array(function ($h,$m) { return intval($h)*60+intval($m); }, array_pad(explode('h',$_REQUEST['duree']),2,0)) ) && ( $duree > 0 ) ) )
        $requete[] = "duree = '$duree'";
      // Validation de la description
      if ( ( $r['description'] != ( $description = trim(htmlspecialchars($_REQUEST['description'])) ) ) && strlen($description) )
        $requete[] = 'description = \''.$mysqli->real_escape_string(mb_strtoupper(mb_substr($description,0,1)).mb_substr($description,1)).'\'';
      // Écriture dans la table heurescolles
      if ( $requete )  {
        if ( requete('heurescolles','UPDATE heurescolles SET '.implode(', ',$requete)." WHERE id = $id",$mysqli) ) 
          exit($_SESSION['message'] = '{"etat":"ok","message":"La séance du <em>'.format_date($r['date']).'</em> a été modifiée."}');
        exit('{"etat":"nok","message":"La séance n\'a pas été modifiée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
      }
      else
        exit('{"etat":"nok","message":"La séance du <em>'.format_date($r['date']).'</em> n\'a pas été modifiée. Aucune modification demandée."}');
    }

    // Récupération des notes déjà existantes cette semaine
    $resultat = $mysqli->query("SELECT semaine, GROUP_CONCAT(eleve) AS dejanotes,
                                GROUP_CONCAT(IF(heure=$id,eleve,NULL)) AS eleves,
                                GROUP_CONCAT(IF(heure=$id,CONCAT(note,'-',id),NULL) SEPARATOR '|' ) AS notes
                                FROM notes WHERE semaine = (SELECT semaine FROM notes WHERE heure = $id LIMIT 1) AND matiere = $matiere");
    $s = $resultat->fetch_assoc();
    $resultat->free();
    $semaine = $s['semaine'];
    $notesperso = array_combine(explode(',',$s['eleves']),explode('|',$s['notes']));
    $dejanotes = $s['dejanotes'];
    $notes = array('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','0,5','1,5','2,5','3,5','4,5','5,5','6,5','7,5','8,5','9,5','10,5','11,5','12,5','13,5','14,5','15,5','16,5','17,5','18,5','19,5','abs','nn');
    $requete_generale = $requete_notes = $message = array();
    $etat = 'ok';
    
    // Validation du jour
    if ( $r['jour'] != ( $jour = preg_filter('/(\d{2})\/(\d{2})\/(\d{4})/','$3-$2-$1',$_REQUEST['jour']) ) )  {
      // Vérification que le jour est bien dans la semaine prévue
      $resultat = $mysqli->query("SELECT DATEDIFF('$jour',debut) FROM semaines WHERE id = $semaine OR id = $semaine+1");
      $s = $resultat->fetch_row();
      if ( ( $s[0] >= 0 ) && ( ( $resultat->num_rows == 1 ) && ( $s[0] < 7 ) || ( $s = $resultat->fetch_row() ) && ( $s[0] < 0 ) ) )
        $requete_generale[] = "jour = '$jour'";
      $resultat->free();
    }
    // Validation de l'heure
    if ( ( $r['heure'] != ( $heure = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['heure']) ) ) && strlen($heure) )
      $requete_generale[] = "heure = '$heure'";
    
    // Si colle déjà relevée : modification des notes déjà mises uniquement
    if ( $r['releve'] == 1 )  {
      foreach ( $notesperso as $eleve => $note )
        if ( isset($_REQUEST["e$eleve"]) && in_array($newnote = $_REQUEST["e$eleve"], $notes, true) && ( $newnote != strstr($note,'-',true) ) )
          $requete_notes[] = "UPDATE notes SET note = '$newnote' WHERE id = ". substr(strstr($note,'-'),1);
    }
    // Si colle non déjà relevée : modification de la durée possible, et
    // modification/ajout/suppression de notes possible 
    else  {
      // Validation de la durée
      if ( isset($_REQUEST['duree']) && ( $r['duree'] != ( $duree = call_user_func_array(function ($h,$m) { return intval($h)*60+intval($m); }, array_pad(explode('h',$_REQUEST['duree']),2,0))) ) && ( $duree > 0 ) )
        $requete_generale[] = "duree = '$duree'";
      // Validation des notes déjà mises à modifier/supprimer
      foreach ( $notesperso as $eleve => $note )  {
        if ( !isset($_REQUEST["e$eleve"]) || !in_array($newnote = $_REQUEST["e$eleve"], $notes, true) )
          $requete_notes[] = 'DELETE FROM notes WHERE id = '. substr(strstr($note,'-'),1);
        elseif ( $newnote != strstr($note,'-',true) )
          $requete_notes[] = "UPDATE notes SET note = '$newnote' WHERE id = ". substr(strstr($note,'-'),1);
      }
      // Récupération des élèves associés à la matière
      $resultat = $mysqli->query("SELECT GROUP_CONCAT(id) FROM utilisateurs WHERE autorisation = 2 AND FIND_IN_SET($matiere,matieres)");
      $s = $resultat->fetch_row();
      $resultat->free();
      // Insertion pour les élèves non déjà notés
      $elevesdispos = array_diff(explode(',',$s[0]),explode(',',$dejanotes));
      foreach ( $elevesdispos as $eleve ){
        if ( isset($_REQUEST["e$eleve"]) && in_array($note = $_REQUEST["e$eleve"], $notes, true) )
          $requete_notes[] = "INSERT INTO notes (semaine,heure,eleve,colleur,matiere,note) VALUES ($semaine,$id,$eleve,${_SESSION['id']},$matiere,'$note')";
        }
    }
    
    // Exécution
    if ( $requete_generale )  {
      if ( requete('heurescolles','UPDATE heurescolles SET '.implode(', ',$requete_generale
      )." WHERE id = $id",$mysqli) ) 
        $message[] = 'La colle du <em>'.format_date($r['date']).'</em> a été modifiée.';
      else  {
        $message[] = 'La colle du <em>'.format_date($r['date']).'</em> n\'a pas été modifiée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'».';
        $etat = 'nok';
      }
    }
    if ( ( $etat == 'ok' ) && $requete_notes )  {
      $nb_ok = 0;
      foreach ( $requete_notes as $requete )  {
        if ( requete('notes',$requete,$mysqli) ) 
          $nb_ok += 1;
        else  {
          $message[] = 'Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'».';
          $etat = 'nok';
        }
      }
      $message[] = ( ($nb_ok == 1) ? 'Une note a été modifiée/supprimée/ajoutée.' : "$nb_ok notes ont été modifiées/supprimées/ajoutées.");
    }
      
    // Reconstruction du message
    if ( !$message )
      exit('{"etat":"nok","message":"La colle du <em>'.format_date($r['date']).'</em> n\'a pas été modifiée. Aucune modification demandée."}');
    if ( $etat == 'nok')
      exit('{"etat":"nok","message":"'.implode('<br>',$message).'"}');
    // Mise à jour des champs 'notes' dans la table 'matieres' (pour le menu)
    $mysqli->query("UPDATE matieres SET notes = IF((SELECT id FROM notes WHERE matiere = $matiere LIMIT 1),1,0) WHERE id = $matiere");
    exit($_SESSION['message'] = '{"etat":"ok","message":"'.implode('<br>',$message).'"}');
  }
}

//////////////////////////////////////////////////////////////
// Ajout de notes de colles (accès colleurs et professeurs) //
//////////////////////////////////////////////////////////////
elseif ( ( $action == 'ajout-notes' ) && ( ( $autorisation == 3 ) || ( $autorisation == 5 ) ) && isset($_REQUEST['matiere']) && in_array($matiere = intval($_REQUEST['matiere']),explode(',',$_SESSION['matieres'])) && isset($_REQUEST['jour']) && strlen($jour = preg_filter('/(\d{2})\/(\d{2})\/(\d{4})/','$3-$2-$1',$_REQUEST['jour'])) && isset($_REQUEST['heure']) && isset($_REQUEST['duree']) )  {

  // Connexion normale obligatoire
  connexionlight();
  $mysqli = connectsql(true);
  // Spécifications pour les manipulations de caractères sur 2 octets (accents)
  mb_internal_encoding('UTF-8');

  // Vérification de l'heure (non obligatoire : chaîne vide éventuellement)
  $heure = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['heure']);
  // Vérification de la durée
  if ( ( $duree = call_user_func_array(function ($h,$m) { return intval($h)*60+intval($m); }, array_pad(explode('h',$_REQUEST['duree']),2,0)) ) == 0 )
    exit('{"etat":"nok","message":"La durée saisie ne peut pas être nulle."}');

  // Heures en séances de TD (pas de notes associées)
  if ( isset($_REQUEST['td']) && isset($_REQUEST['description']) )  {
    // Vérification que le jour est bien dans l'année
    $resultat = $mysqli->query("SELECT id FROM semaines WHERE debut <= '$jour' AND debut >= SUBDATE('$jour',7) ORDER BY debut DESC LIMIT 1");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Le jour choisi ne se trouve pas dans l\'année scolaire. S\'il s\'agit d\'une erreur, il faut peut-être contacter l\'administrateur."}');
    $resultat->free();
    // Vérification de la description
    if ( !strlen($description = trim(htmlspecialchars($_REQUEST['description']))) )
      exit('{"etat":"nok","message":"Pour les séances de TD sans note, la description de la séance doit obligatoirement être non vide."}');
    // Écriture dans la table heurescolles
    if ( requete('heurescolles',"INSERT INTO heurescolles SET colleur = ${_SESSION['id']}, matiere = $matiere, jour = '$jour', heure = '$heure', duree = '$duree', description = '".$mysqli->real_escape_string(mb_strtoupper(mb_substr($description,0,1)).mb_substr($description,1)).'\'',$mysqli) )
      exit($_SESSION['message'] = '{"etat":"ok","message":"La séance a été ajoutée."}');
    exit('{"etat":"nok","message":"La séance n\'a pas été ajoutée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Vérification de l'identifiant de la semaine
  if ( !isset($_REQUEST['sid']) || !ctype_digit($sid = $_REQUEST['sid']) )
    exit('{"etat":"nok","message":"Semaine non valide"}');
  $resultat = $mysqli->query("SELECT (SELECT GROUP_CONCAT(eleve) FROM notes WHERE semaine = semaines.id AND matiere = $matiere) FROM semaines WHERE id = $sid AND colle");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Semaine non valide"}');
  $r = $resultat->fetch_row();
  $resultat->free();
  $dejanotes = $r[0];
  
  // Vérification que le jour est bien dans la semaine prévue
  $resultat = $mysqli->query("SELECT DATEDIFF('$jour',debut) FROM semaines WHERE id = $sid OR id = $sid+1");
  $r = $resultat->fetch_row();
  if ( ( $r[0] < 0 ) || ( ( $resultat->num_rows == 1 ) && ( $r[0] >= 7 ) || ( $r = $resultat->fetch_row() ) && ( $r[0] >= 0 ) ) )
    exit('{"etat":"nok","message":"La date saisie ne se trouve pas dans la semaine choisie."}');
  $resultat->free();

  // Écriture de l'heure dans la table heurescolles
  if ( !requete('heurescolles',"INSERT INTO heurescolles SET colleur = ${_SESSION['id']}, matiere = $matiere, jour = '$jour', heure = '$heure', duree = '$duree', description = ''",$mysqli) )
    exit('{"etat":"nok","message":"La colle n\'a pas été ajoutée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  $heure = $mysqli->insert_id;
  
  // Récupération des élèves associés à la matière
  $resultat = $mysqli->query("SELECT GROUP_CONCAT(id) FROM utilisateurs WHERE autorisation = 2 AND FIND_IN_SET($matiere,matieres)");
  $r = $resultat->fetch_row();
  $resultat->free();
  $elevesdispos = array_diff(explode(',',$r[0]),explode(',',$dejanotes));
  $notes = array('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','0,5','1,5','2,5','3,5','4,5','5,5','6,5','7,5','8,5','9,5','10,5','11,5','12,5','13,5','14,5','15,5','16,5','17,5','18,5','19,5','abs','nn');

  // Insertion pour les élèves non déjà notés
  $requete = array();
  foreach ( $elevesdispos as $eleve )
    if ( isset($_REQUEST["e$eleve"]) && in_array($note = $_REQUEST["e$eleve"], $notes, true) )
      $requete[] = "($sid,$heure,$eleve,${_SESSION['id']},$matiere,'$note')";
  if ( !$requete )  {
    requete('heurescolles',"DELETE FROM heurescolles WHERE id=$heure",$mysqli);
    exit('{"etat":"nok","message":"La colle n\'a pas été ajoutée, car aucune note valable n\'a été saisie."}');
  }
  // Écriture des notes
  if ( requete('notes','INSERT INTO notes (semaine,heure,eleve,colleur,matiere,note) VALUES '.implode(',',$requete),$mysqli) && $mysqli->query("UPDATE matieres SET notes = IF((SELECT id FROM notes WHERE matiere = $matiere LIMIT 1),1,0) WHERE id = $matiere") )
    exit($_SESSION['message'] = '{"etat":"ok","message":"'.count($requete).' notes ont été ajoutées."}');
  exit('{"etat":"nok","message":"Toutes les notes saisies n\'ont pas été ajoutées. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

///////////////////////////////////////////////////////
// Relève des notes de colles (accès administration) //
///////////////////////////////////////////////////////
elseif ( ( $action == 'releve-notes' ) && ( $autorisation == 4 ) )  {

  // Connexion normale obligatoire
  connexionlight();
  $mysqli = connectsql(true);

  // Vérification qu'il y a des colles à relever
  $resultat = $mysqli->query('SELECT * FROM heurescolles WHERE releve=0');
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Il n\'a pas d\'heure de colle à relever aujourd\'hui."}');
  if ( requete('heurescolles','UPDATE heurescolles SET releve=CURDATE() WHERE releve=0', $mysqli) )
    exit($_SESSION['message'] = '{"etat":"ok","message":"De nouvelles heures de colles ont été relevées et apparaissent dans le tableau général."}');
  exit('{"etat":"nok","message":"La relève n\'a pas été réalisée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

/////////////////////////////////////////////////
// À partir d'ici, accès professeur uniquement //
/////////////////////////////////////////////////
if ( ( $autorisation < 5 ) )
  exit( '{"etat":"nok","message":"Aucune action effectuée"}' );
// Connexion normale obligatoire
connexionlight();
$mysqli = connectsql(true);
// Spécifications pour les manipulations de caractères sur 2 octets (accents)
mb_internal_encoding('UTF-8');

///////////////////////////////////
// Modification des informations //
///////////////////////////////////
if ( $action == 'infos' && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  
  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT i.ordre, i.titre, cache, texte, page, cle, i.protection, p.mat, ( SELECT COUNT(*) FROM infos WHERE page = i.page ) AS max FROM infos AS i LEFT JOIN pages AS p ON i.page = p.id WHERE i.id = $id AND FIND_IN_SET(p.mat,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de page non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Traitement d'une modification : titre, texte, protection
  if ( isset($_REQUEST['champ']) )  {
    $valeur = trim($mysqli->real_escape_string($_REQUEST['val']));
    $champ = $_REQUEST['champ'];
    if ( isset($r[$champ = $_REQUEST['champ']]) && ( $r[$champ] == $valeur ) )
      exit('{"etat":"nok","message":"L\'information n\'a pas été modifiée."}');
    if ( $champ == 'titre' )
      exit( requete('infos',"UPDATE infos SET titre = '$valeur' WHERE id = $id",$mysqli)
         && ( $r['cache'] || recent($mysqli,1,$id,array('titre'=>$valeur),false) )
         ? '{"etat":"ok","message":"Le titre de l\'information a été modifié."}'
         : '{"etat":"nok","message":"L\'information n\'a pas été modifiée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    if ( $champ == 'texte' )  {
      if ( !$valeur )
        exit('{"etat":"nok","message":"L\'information n\'a pas été modifiée. Le texte doit être non vide."}');
      exit( requete('infos',"UPDATE infos SET texte = '$valeur' WHERE id = $id",$mysqli)
         && ( $r['cache'] || recent($mysqli,1,$id,array('texte'=>$valeur),isset($_REQUEST['publi'])) )
         ? '{"etat":"ok","message":"Le texte de l\'information a été modifié."}'
         : '{"etat":"nok","message":"L\'information n\'a pas été modifiée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }
    if ( ( $champ == 'protection' ) && ctype_digit($valeur = $_REQUEST['val']) )
      exit( requete('infos',"UPDATE infos SET protection = $valeur WHERE id = $id",$mysqli)
         && ( $r['cache'] || recent($mysqli,1,$id,array('protection'=>$valeur),false) )
         ? '{"etat":"ok","message":"La protection de l\'information a été modifiée."}'
         : '{"etat":"nok","message":"L\'information n\'a pas été modifiée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Déplacement vers le haut
  if ( isset($_REQUEST['monte']) && ( $r['ordre'] > 1 ) )
    exit( requete('infos',"UPDATE infos SET ordre = (2*${r['ordre']}-1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}-1) ) AND page = ${r['page']}",$mysqli) && $mysqli->query('ALTER TABLE infos ORDER BY page,ordre')
      ? '{"etat":"ok","message":"L\'information a été déplacée."}'
      : '{"etat":"nok","message":"L\'information n\'a pas été déplacée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Déplacement vers le bas
  if ( isset($_REQUEST['descend']) && ( $r['ordre'] < $r['max'] ) )
    exit( requete('infos',"UPDATE infos SET ordre = (2*${r['ordre']}+1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}+1) ) AND page = ${r['page']}",$mysqli) && $mysqli->query('ALTER TABLE infos ORDER BY page,ordre')
      ? '{"etat":"ok","message":"L\'information a été déplacée."}'
      : '{"etat":"nok","message":"L\'information n\'a pas été déplacée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Positionnement "montré" (apparaît sur la partie publique)
  if ( isset($_REQUEST['montre']) )
    exit( requete('infos',"UPDATE infos SET cache = 0 WHERE id = $id",$mysqli) 
       && recent($mysqli,1,$id,array('matiere'=>$r['mat'],'titre'=>( strlen($r['titre']) ? $mysqli->real_escape_string($r['titre']) : 'Information'), 'lien'=>".?${r['cle']}", 'texte'=>$mysqli->real_escape_string($r['texte']), 'protection'=>$r['protection']) )
        ? '{"etat":"ok","message":"L\'information apparaît désormais sur la partie publique."}'
        : '{"etat":"nok","message":"L\'information n\'a pas été diffusée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Positionnement "caché" (n'apparaît pas sur la partie publique)
  if ( isset($_REQUEST['cache']) )
    exit( requete('infos',"UPDATE infos SET cache = 1 WHERE id = $id",$mysqli) && recent($mysqli,1,$id) 
        ? '{"etat":"ok","message":"L\'information n\'apparaît plus sur la partie publique mais est toujours disponible ici pour modification ou diffusion."}'
        : '{"etat":"nok","message":"L\'information n\'a pas été cachée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Suppression
  if ( isset($_REQUEST['supprime']) )
    exit( requete('infos',"DELETE FROM infos WHERE id = $id",$mysqli) && recent($mysqli,1,$id)
       && requete('infos',"UPDATE infos SET ordre = (ordre-1) WHERE ordre > ${r['ordre']} AND page = ${r['page']}",$mysqli)
        ? '{"etat":"ok","message":"Suppression réalisée"}'
        : '{"etat":"nok","message":"L\'information n\'a pas été supprimée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

/////////////////////////////
// Ajout d'une information //
/////////////////////////////
elseif ( ( $action == 'ajout-info' ) && isset($_REQUEST['titre']) && isset($_REQUEST['texte']) && isset($_REQUEST['protection']) && isset($_REQUEST['page']) && ctype_digit($page = $_REQUEST['page']) )  {
  // Vérification de l'identifiant de la page
  $resultat = $mysqli->query("SELECT cle,mat FROM pages WHERE id = $page AND FIND_IN_SET(mat,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de page non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  // Génération de la valeur de protection
  // $val est un tableau contenant soit 0, soit 32, soit 6 puis les valeurs des types de comptes autorisés
  if ( !count($val = array_filter($_REQUEST['protection'],function($id) { return ctype_digit($id); })) )
    exit('{"etat":"nok","message":"L\'information n\'a pas été ajoutée. La protection d\'accès est incorrecte."}');
  $protection = ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32);
  // Autres données
  $titre = trim($mysqli->real_escape_string($_REQUEST['titre']));
  $texte = trim($mysqli->real_escape_string($_REQUEST['texte']));
  $cache = intval(isset($_REQUEST['cache']));
  if ( !$texte )
    exit('{"etat":"nok","message":"L\'information n\'a pas été ajoutée. Le texte doit être non vide."}');
  // Écriture
  if ( requete('infos',"UPDATE infos SET ordre = (ordre+1) WHERE page = $page",$mysqli)
    && requete('infos',"INSERT INTO infos SET ordre = 1, page = $page, texte = '$texte', titre = '$titre', cache = $cache, protection = $protection",$mysqli) )  {
    $id = $mysqli->insert_id;
    if ( !$cache )  {
      $titre = ( $titre ?: 'Information');
      if ( $page > 1 )  {
        $resultat = $mysqli->query("SELECT CONCAT( ' [', IF(mat=0,'',CONCAT(m.nom,'/')),p.nom,']')
                                    FROM pages AS p LEFT JOIN matieres AS m ON mat=m.id WHERE p.id = $page");
        $titre .= $mysqli->real_escape_string($resultat->fetch_row()[0]);
        $resultat->free();
      }
      recent($mysqli,1,$id,array('matiere'=>$r['mat'], 'titre'=>$titre, 'lien'=>".?${r['cle']}", 'texte'=>$texte, 'protection'=>$protection));
    }
    $mysqli->query('ALTER TABLE infos ORDER BY page,ordre');
    exit($_SESSION['message'] = '{"etat":"ok","message":"L\'information a été ajoutée."}');
  }
  exit('{"etat":"nok","message":"L\'information n\'a pas été ajoutée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

//////////////////////////////////
// Modification des répertoires //
//////////////////////////////////
elseif ( ( $action == 'reps' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  
  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT parent, nom, menu, protection, matiere FROM reps WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de répertoire non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  
  // Traitement d'une modification unique (nom)
  if ( isset($_REQUEST['champ']) && ( $_REQUEST['champ'] == 'nom' ) )  {
    if ( !$r['parent'] )
      exit('{"etat":"nok","message":"Le nom des répertoires racine des matières ne sont pas modifiables."}');
    if ( !($valeur = trim($mysqli->real_escape_string($_REQUEST['val'])) ) )
      exit("{\"etat\":\"nok\",\"message\":\"Le répertoire <em>${r['nom']}</em> n'a pas été modifié. Le nom doit être non vide.\"}");
    exit( requete('reps',"UPDATE reps SET nom = '$valeur' WHERE id = $id",$mysqli)
       && requete('recents',"UPDATE recents SET texte = ( SELECT CONCAT(ext,'|',taille,'|',d.parent,'|',GROUP_CONCAT( r.nom ORDER BY FIND_IN_SET(r.id,d.parents) SEPARATOR '/' ))
                                                          FROM docs AS d LEFT JOIN reps AS r ON FIND_IN_SET(r.id,d.parents) WHERE d.id = recents.id GROUP BY d.id )
                                            WHERE type = 3 AND id IN (SELECT docs.id FROM docs WHERE FIND_IN_SET($id,parents))",$mysqli)
       && rss($mysqli,$r['matiere'],0)
        ? "{\"etat\":\"ok\",\"message\":\"Le nom du répertoire <em>${r['nom']}</em> a été modifié.}"
        : "{\"etat\":\"nok\",\"message\":\"Le nom du répertoire <em>${r['nom']}</em> n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
    
  // Traitement d'une modification globale
  if ( isset($_REQUEST['protection']) )  {
    $etat = 'nok';
    // Modification du nom et de l'affichage dans le menu uniquement si pas à la racine
    if ( $r['parent'] && isset($_REQUEST['nom']) )  {
      if ( ( $nom = trim($mysqli->real_escape_string($_REQUEST['nom'])) ) && ( $nom != $r['nom'] ) )  {
        if ( !requete('reps', "UPDATE reps SET nom = '$nom' WHERE id = $id",$mysqli)
          || !requete('recents',"UPDATE recents SET texte = ( SELECT CONCAT(ext,'|',taille,'|',d.parent,'|',GROUP_CONCAT( r.nom ORDER BY FIND_IN_SET(r.id,d.parents) SEPARATOR '/' ))
                                                              FROM docs AS d LEFT JOIN reps AS r ON FIND_IN_SET(r.id,d.parents) WHERE d.id = recents.id GROUP BY d.id )
                                                WHERE type = 3 AND id IN (SELECT docs.id FROM docs WHERE FIND_IN_SET($id,parents))",$mysqli) )
          exit("{\"etat\":\"nok\",\"message\":\"Le nom du répertoire <em>${r['nom']}</em> n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
        rss($mysqli,$r['matiere'],0);
        // Mise à jour de l'ordre des répertoires
        $mysqli->query('ALTER TABLE reps ORDER BY parents,nom');
        $etat = 'ok';
      }
      if ( ( $menu = intval(isset($_REQUEST['menurep']))) != $r['menu'] )  {
        requete('reps', "UPDATE reps SET menu = 1-menu WHERE id = $id",$mysqli);
        $etat = 'ok';
      }
    }
    
    // Génération de la valeur de protection
    // $val est un tableau contenant soit 0, soit 32, soit 6 puis les valeurs des types de comptes autorisés
    // Pas de modification de la table recents ici : seule la protection du répertoire change
    if ( count($val = array_filter($_REQUEST['protection'],function($id) { return ctype_digit($id); }))
      && ( $r['protection'] != ( $protection = ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32) ) ) )  {
      if ( !requete('reps', "UPDATE reps SET protection = $protection WHERE id = $id",$mysqli) )
        exit("{\"etat\":\"nok\",\"message\":\"La protection du répertoire <em>${r['nom']}</em> n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      // Modification de la matière si protection du répertoire racine modifié
      if ( !$r['parent'] && $r['matiere'] )
        requete('matieres',"UPDATE matieres SET docs_protection = $protection WHERE id = ${r['matiere']}",$mysqli);
      $etat = 'ok';
      // Pour une éventuelle propagation
      $r['protection'] = $protection;
    }

    // Déplacement du répertoire si $_REQUEST['parent'] non nul et si pas à la racine
    if ( $r['parent'] && isset($_REQUEST['parent']) && ctype_digit($parent = $_REQUEST['parent']) && !in_array($parent,array(0,$id,$r['parent'])) )  {
      // Vérification du nouveau répertoire parent
      $resultat = $mysqli->query("SELECT parents, matiere FROM reps WHERE id = $parent AND FIND_IN_SET(matiere,'${_SESSION['matieres']}') AND NOT FIND_IN_SET($id,parents)");
      if ( !$resultat->num_rows)
        exit("{\"etat\":\"nok\",\"message\":\"Le répertoire <em>${r['nom']}</em> n'a pas été déplacé. Identifiant de répertoire parent non valide.\"}");
      $s = $resultat->fetch_assoc();
      $resultat->free();
      $mat = $s['matiere'];
      $parents = "${s['parents']},$parent";
      if ( !requete('reps',"UPDATE reps SET matiere = $mat, parent = $parent, parents = '$parents' WHERE id = $id",$mysqli)
        || !requete('reps',"UPDATE reps SET matiere = $mat, parents = '$parents,$id' WHERE parent = $id",$mysqli)
        || !requete('docs',"UPDATE docs SET matiere = $mat, parents = '$parents,$id' WHERE parent = $id",$mysqli)
        || !requete('recents',"UPDATE recents SET matiere = $mat,
                                                  texte = ( SELECT CONCAT(ext,'|',taille,'|',d.parent,'|',GROUP_CONCAT( r.nom ORDER BY FIND_IN_SET(r.id,d.parents) SEPARATOR '/' ))
                                                            FROM docs AS d LEFT JOIN reps AS r ON FIND_IN_SET(r.id,d.parents) WHERE d.id = recents.id GROUP BY d.id )
                                              WHERE type = 3 AND id IN (SELECT docs.id FROM docs WHERE FIND_IN_SET($id,parents))",$mysqli)
        || !rss($mysqli, ( $r['matiere'] != $mat ) ? array($r['matiere'],$mat) : $mat, 0) )
        exit("{\"etat\":\"nok\",\"message\":\"Le répertoire <em>${r['nom']}</em> n'a pas été déplacé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      // Mise à jour du menu et de l'ordre des répertoires
      $mysqli->query("UPDATE matieres SET docs = (SELECT IF(COUNT(*),1,0) FROM docs WHERE matiere = matieres.id AND protection < 32) WHERE id = ${r['matiere']} OR id = $mat");
      $mysqli->query('ALTER TABLE reps ORDER BY parents,nom');
      $etat = 'ok';
    }
    
    // Propagation des droits d'accès aux sous-répertoires et documents
    if ( isset($_REQUEST['propage']) )  {
      if ( requete('reps',"UPDATE reps SET protection = ${r['protection']} WHERE FIND_IN_SET($id,parents)",$mysqli)
        && requete('docs',"UPDATE docs SET protection = ${r['protection']} WHERE FIND_IN_SET($id,parents)",$mysqli)
        && requete('recents',"UPDATE recents SET protection = ${r['protection']} WHERE type = 3 AND id IN (SELECT docs.id FROM docs WHERE FIND_IN_SET($id,parents))",$mysqli)
        && rss($mysqli,$r['matiere'],0) )  {
        // Mise à jour de la matière (menu)
        $mysqli->query("UPDATE matieres SET docs = (SELECT IF(COUNT(*),1,0) FROM docs WHERE matiere = ${r['matiere']} AND protection < 32) WHERE id = ${r['matiere']}");
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le réglage d'accès du répertoire <em>${r['nom']}</em> a été propagé à tous les documents et sous-répertoires qu'il contient.\"}");
      }
      exit("{\"etat\":\"nok\",\"message\":\"Le réglage d'accès du répertoire <em>${r['nom']}</em> n'a pas été propagé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    }
  
    // Si pas de modification
    if ( $etat == 'nok' )
      exit("{\"etat\":\"nok\",\"message\":\"Le répertoire <em>${r['nom']}</em> n'a pas été modifié. Aucune modification demandée.\"}");
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le répertoire <em>${r['nom']}</em> a été modifié.\"}");
  }
  
  // Suppression du répertoire ou de son contenu
  if ( isset($_REQUEST['supprime']) || isset($_REQUEST['vide']) )  {
    if ( isset($_REQUEST['supprime']) )  {
      if ( !$r['parent'] )
        exit('{"etat":"nok","message":"Les répertoires racine des matières ne sont pas supprimables."}');
      $action = 'supprimé';
      $requete = "id = $id OR";
    }
    else  {
      $action = 'vidé';
      $requete = '';
    }
    if ( requete('reps',"DELETE FROM reps WHERE $requete FIND_IN_SET($id,parents)",$mysqli) )  {
      // Suppression physique des documents
      $resultat = $mysqli->query("SELECT lien FROM docs WHERE FIND_IN_SET($id,parents)");
      if ( $resultat->num_rows )  {
        while ( $s = $resultat->fetch_row() )
          exec("rm -rf documents/${s[0]}");
        $resultat->free();
        if ( !requete('recents',"DELETE FROM recents WHERE type = 3 AND id IN (SELECT docs.id FROM docs WHERE FIND_IN_SET($id,parents))",$mysqli)
          || !requete('docs',"DELETE FROM docs WHERE FIND_IN_SET($id,parents)",$mysqli)
          || !rss($mysqli,$r['matiere'],0) )
          exit("{\"etat\":\"nok\",\"message\":\"Le répertoire <em>${r['nom']}</em> n'a pas été correctement $action. Certains documents sont encore dans la base de données. Vous devriez en informer l'administrateur. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      }
      // Mise à jour de la matière (menu)
      $mysqli->query("UPDATE matieres SET docs = (SELECT IF(COUNT(*),1,0) FROM docs WHERE matiere = ${r['matiere']} AND protection < 32) WHERE id = ${r['matiere']}");
      if ( $action == 'vidé' )
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le répertoire <em>${r['nom']}</em> a été vidé de son contenu, ainsi que tous ses sous-répertoires.\"}");
      exit("{\"etat\":\"ok\",\"message\":\"Le répertoire <em>${r['nom']}</em> a été supprimé, ainsi que tous ses sous-répertoires et ses documents.\"}");
    }
    exit("{\"etat\":\"nok\",\"message\":\"Le répertoire <em>${r['nom']}</em> n'a pas été $action. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
}

///////////////////////////
// Ajout d'un répertoire //
///////////////////////////
elseif ( ( $action == 'ajout-rep' ) && isset($_REQUEST['nom']) && isset($_REQUEST['protection']) && isset($_REQUEST['parent']) && strlen($nom = trim($mysqli->real_escape_string($_REQUEST['nom']))) && ctype_digit($parent=$_REQUEST['parent']) )  {
  // Vérification du répertoire parent
  $resultat = $mysqli->query("SELECT parents, matiere, protection FROM reps WHERE id = $parent AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de répertoire parent non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  $menu = intval(isset($_REQUEST['menurep']));
  // Génération de la valeur de protection
  $protection = ( count($val = array_filter($_REQUEST['protection'],function($id) { return ctype_digit($id); })) )
                ? ( ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32) )
                : $r['protection'];
  if ( !requete('reps',"INSERT INTO reps SET parent = $parent, parents = '${r['parents']},$parent', nom = '$nom', matiere = ${r['matiere']}, protection = $protection, menu = $menu",$mysqli) )
    exit('{"etat":"nok","message":"Le répertoire n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  // Mise à jour de l'ordre des répertoires
  $mysqli->query('ALTER TABLE reps ORDER BY parents,nom');
  exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le répertoire <em>$nom</em> a été ajouté.\"}");
}

////////////////////////////////
// Modification des documents //
////////////////////////////////
elseif ( ( $action == 'docs' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  // Vérification de l'identifiant
  $resultat = $mysqli->query("SELECT nom, protection, ext, lien, parent, matiere, IF(dispo,DATE_FORMAT(dispo,'%Y-%m-%d %k:%i'),0) AS dispo
                              FROM docs WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de document non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Traitement d'une modification unique (nom)
  if ( isset($_REQUEST['champ']) && ( $_REQUEST['champ'] == 'nom' ) )  {
    if ( !( $valeur = trim($_REQUEST['val']) ) )
      exit("{\"etat\":\"nok\",\"message\":\"Le nom du document <em>${r['nom']}</em> n'a pas été modifié. Le nom doit être non vide.\"}");
    if ( $valeur == $r['nom'] ) 
      exit("{\"etat\":\"nok\",\"message\":\"Le nom du document <em>${r['nom']}</em> n'a pas été modifié. Aucune modification demandée.\"}");
    setlocale(LC_CTYPE, "fr_FR.UTF-8");
    $nom = substr((str_replace(array($r['ext'],'\\','/'),array('','-','-'),$valeur)),0,100);
    // real_escape_string seulement pour la requête SQL
    $nouveau_nom = $mysqli->real_escape_string($nom);
    if ( !requete('docs',"UPDATE docs SET nom = '$nouveau_nom', nom_nat = '".zpad($nouveau_nom)."' WHERE id = $id",$mysqli) )
      exit("{\"etat\":\"nok\",\"message\":\"Le nom du document <em>${r['nom']}</em> n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    exec('mv documents/'.escapeshellarg("${r['lien']}/${r['nom']}${r['ext']}").' documents/'.escapeshellarg("${r['lien']}/$nom${r['ext']}"));
    $mysqli->query('ALTER TABLE docs ORDER BY parents,nom_nat');
    recent($mysqli,3,$id,array('titre'=>$nouveau_nom),false);
    exit("{\"etat\":\"ok\",\"message\":\"Le nom du document <em>${r['nom']}</em> a été modifié.\"}");
  }

  // Traitement d'une modification globale
  if ( isset($_REQUEST['nom']) && isset($_REQUEST['parent']) && isset($_REQUEST['protection']) && ($nom = trim($_REQUEST['nom'])) )  {
    $etat = 'nok';
    $message = '';
    
    // Modification du nom
    setlocale(LC_CTYPE, "fr_FR.UTF-8");
    $nom = substr(basename(str_replace(array($r['ext'],'\\'),array('','/'),$nom)),0,100);
    if ( $nom != $r['nom'] )  {
      // real_escape_string seulement pour la requête SQL
      $nouveau_nom = $mysqli->real_escape_string($nom);
      if ( !requete('docs',"UPDATE docs SET nom = '$nouveau_nom', nom_nat = '".zpad($nouveau_nom)."' WHERE id = $id",$mysqli) )
        exit("{\"etat\":\"nok\",\"message\":\"Le nom du document <em>${r['nom']}</em> n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      exec('mv documents/'.escapeshellarg("${r['lien']}/${r['nom']}${r['ext']}").' documents/'.escapeshellarg("${r['lien']}/$nom${r['ext']}"));
      $mysqli->query('ALTER TABLE docs ORDER BY parents,nom_nat');
      recent($mysqli,3,$id,array('titre'=>$nouveau_nom),false);
      $etat = 'ok';
      $message = "Le nom du document <em>${r['nom']}</em> a été modifié.<br>";
      // Modification du nom (nécessaire pour les éventuels affichages suivants et la mise à jour éventuelle du fichier)
      $r['nom'] = $nom;
    }
    
    // Modification de la protection
    if ( count($val = array_filter($_REQUEST['protection'],function($id) { return ctype_digit($id); })) )  {
      if ( $r['protection'] != ( $protection = ( ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32) ) ) )  {
        if ( !requete('docs',"UPDATE docs SET protection = $protection WHERE id = $id",$mysqli)
          || !recent($mysqli,3,$id,array('protection'=>$protection),false) )
          exit("{\"etat\":\"nok\",\"message\":\"$message L'accès au document <em>${r['nom']}</em> n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
        // Mise à jour du menu
        $mysqli->query("UPDATE matieres SET docs = (SELECT IF(COUNT(*),1,0) FROM docs WHERE matiere = ${r['matiere']} AND protection < 32) WHERE id = ${r['matiere']}");
        $etat = 'ok';
        $message .= "L'accès au document <em>${r['nom']}</em> a été modifié.<br>";
        // Modification de la protection (nécessaire pour les éventuels prochains appel à rss())
        $r['protection'] = $protection;
      }
    }
    
    // Modification de la date de disponibilité
    if ( isset($_REQUEST['affdiff']) && isset($_REQUEST['dispo']) && strlen($dispo = $_REQUEST['dispo']) )  {
      if ( strlen( $dispo = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$dispo) ) && ( $r['dispo'] != $dispo ) )  {
        // Modification uniquement si dispo est dans le futur
        if ( $dispo < date('Y-m-d H:i') )  {
          $etat = 'ok';
          $message .= "La date de disponiblité du document <em>${r['nom']}</em> n'a pas été modifiée car elle ne peut être déplacée dans le passé.<br>";
        }
        elseif ( !requete('docs',"UPDATE docs SET dispo = '$dispo' WHERE id = $id",$mysqli) || !recent($mysqli,3,$id,array('publi'=>$dispo,'maj'=>''),false) )
          exit("{\"etat\":\"nok\",\"message\":\"La date de disponibilité du document <em>${r['nom']}</em> n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
        else  {
          $etat = 'ok';
          $message .= "La date de disponiblité du document <em>${r['nom']}</em> a été modifiée.<br>";
        }
      }
    }
    // Suppression de l'affichage différé
    elseif ( $r['dispo'] )  {
      // Modification uniquement si dispo était dans le futur
      if ( $r['dispo'] < date('Y-m-d H:i') )  {
        $etat = 'ok';
        $message .= "La date de disponiblité du document <em>${r['nom']}</em> n'a pas été supprimée car elle est déjà passée.<br>";
      }
      else  {
        if ( !requete('docs',"UPDATE docs SET dispo = NOW() WHERE id = $id",$mysqli) || !recent($mysqli,3,$id,array('publi'=>date('Y-m-d H:i'),'maj'=>'')) )
          exit("{\"etat\":\"nok\",\"message\":\"La date de disponibilité du document <em>${r['nom']}</em> n'a pas été supprimée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
        $etat = 'ok';
        $message .= "Le document <em>${r['nom']}</em> a été marqué comme disponible dès maintenant.<br>";
      }
    }

    // Déplacement dans un autre répertoire
    if ( ctype_digit($parent = $_REQUEST['parent']) && $parent && ( $parent != $r['parent'] ) )  {
      // Vérification du nouveau répertoire parent
      $resultat = $mysqli->query("SELECT parents, matiere FROM reps WHERE id = $parent AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
      if ( !$resultat->num_rows )
        exit("{\"etat\":\"nok\",\"message\":\"$message Le document <em>${r['nom']}</em> n'a pas été déplacé. Identifiant de répertoire parent non valide.\"}");
      $s = $resultat->fetch_assoc();
      $resultat->free();
      $mat = $s['matiere'];
      if ( !requete('docs',"UPDATE docs SET parent = '$parent', parents = '${s['parents']},$parent', matiere = $mat WHERE id = $id",$mysqli) 
        || !requete('recents',"UPDATE recents SET matiere = $mat,
                                                  texte = ( SELECT CONCAT(ext,'|',taille,'|',d.parent,'|',GROUP_CONCAT( r.nom ORDER BY FIND_IN_SET(r.id,d.parents) SEPARATOR '/' ))
                                                            FROM docs AS d LEFT JOIN reps AS r ON FIND_IN_SET(r.id,d.parents) WHERE d.id = $id )
                                              WHERE type = 3 AND id = $id",$mysqli)
        || !rss($mysqli, ( $r['matiere'] != $mat ) ? array($r['matiere'],$mat) : $mat, $r['protection']) )
        exit("{\"etat\":\"nok\",\"message\":\"$message Le document <em>${r['nom']}</em> n'a pas été déplacé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      // Mise à jour du menu et de l'ordre des documents
      $mysqli->query("UPDATE matieres SET docs = (SELECT IF(COUNT(*),1,0) FROM docs WHERE matiere = matieres.id AND protection < 32) WHERE id = ${r['matiere']} OR id = $mat");
      $mysqli->query('ALTER TABLE reps ORDER BY parents,nom');
      $etat = 'ok';
      $message .= "Le document <em>${r['nom']}</em> a été déplacé.<br>";
      // Modification de la matière (nécessaire pour les éventuels prochains appel à rss())
      $r['matiere'] = $mat;
    }
    
    // Mise à jour d'un document
    if ( isset($_FILES['fichier']['tmp_name']) && is_uploaded_file($_FILES['fichier']['tmp_name']) )  {
      // Changement d'extension interdit
      if ( $r['ext'] != ( $ext = ( strrchr($_FILES['fichier']['name'],'.') ?: '' ) ) )
        exit("{\"etat\":\"nok\",\"message\":\"$message Le document <em>${r['nom']}</em> n'a pas été mis à jour. Le fichier envoyé est d'une extension différente.\"}");
      // Gestion de la taille
      $taille = ( ( $taille = intval($_FILES['fichier']['size']/1024) ) < 1024 ) ? "$taille&nbsp;ko" : intval($taille/1024).'&nbsp;Mo';
      // Déplacement du document uploadé au bon endroit
      if ( !move_uploaded_file($_FILES['fichier']['tmp_name'],"documents/${r['lien']}/${r['nom']}${r['ext']}") )
        exit("{\"etat\":\"nok\",\"message\":\"$message Le document <em>${r['nom']}</em> n'a pas été mis à jour : problème d'écriture du fichier. Vous devriez en informer l'administrateur.\"}");
      // Modifications dans la base de données
      // Mise à jour de la date seulement si document déjà disponible
      $majpubli = ( ( isset($_REQUEST['publi']) && ( $r['dispo'] < date('Y-m-d H:i') ) ) ? 'upload = NOW(),' : '' );
      if ( !requete('docs',"UPDATE docs SET $majpubli taille = '$taille' WHERE id = $id",$mysqli) )
        exit("{\"etat\":\"nok\",\"message\":\"Le document <em>${r['nom']}</em> n'a pas été mis à jour. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      requete('recents','UPDATE recents SET '.( $majpubli ? 'maj = NOW(), ' : '' )."texte = CONCAT(SUBSTRING_INDEX(texte,'|',1),'|$taille|',SUBSTRING_INDEX(texte,'|',-2)) WHERE type = 3 AND id = $id",$mysqli);
      rss($mysqli,$r['matiere'],$r['protection']);
      $etat = 'ok';
      $message .= "Le document <em>${r['nom']}</em> a été mis à jour.<br>";
    }
    
    // Message
    if ( $etat == 'nok' )
      exit("{\"etat\":\"nok\",\"message\":\"Le document <em>${r['nom']}</em> n'a pas été modifié. Aucune modification demandée.\"}");
    exit($_SESSION['message'] = '{"etat":"ok","message":"'.substr($message,0,-4).'"}');
  }
  
  // Suppression d'un document
  if ( isset($_REQUEST['supprime']) )  {
    if ( !requete('docs',"DELETE FROM docs WHERE id = $id",$mysqli) )
      exit("{\"etat\":\"nok\",\"message\":\"Le document <em>${r['nom']}</em> n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'».');
    // Suppression physique
    exec("rm -rf documents/${r['lien']}");
    recent($mysqli,3,$id);
    $mysqli->query("UPDATE matieres SET docs = (SELECT IF(COUNT(*),1,0) FROM docs WHERE matiere = ${r['matiere']} AND protection < 32) WHERE id = ${r['matiere']}");
    exit("{\"etat\":\"ok\",\"message\":\"Le document <em>${r['nom']}</em> a été supprimé.\"}");
  }
}

////////////////////////
// Ajout de documents //
////////////////////////
elseif ( ( $action == 'ajout-doc' ) && isset($_FILES['fichier']) && isset($_REQUEST['protection']) && isset($_REQUEST['parent']) && ctype_digit($parent = $_REQUEST['parent']) )  {
  // Vérification du répertoire parent
  $resultat = $mysqli->query("SELECT parents, matiere, protection FROM reps WHERE id = $parent AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de répertoire parent non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  $ok = 0;
  $message = '';
  // Génération de la valeur de protection
  $protection = ( count($val = array_filter($_REQUEST['protection'],function($id) { return ctype_digit($id); })) )
                ? ( ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32) )
                : $r['protection'];
  // Vérification de l'affichage différé et de la date de disponibilité, seulement si dans le futur
  if ( !isset($_REQUEST['affdiff']) || !isset($_REQUEST['dispo']) || !strlen( $dispo = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$_REQUEST['dispo']) ) || ($dispo < date('Y-m-d H:i') ) )
    $dispo = '';
  // Traitement de chaque fichier envoyé
  setlocale(LC_CTYPE, "fr_FR.UTF-8");
  for ( $i = 0 ; $i < ( $n = count($_FILES['fichier']['tmp_name']) ) ; $i++ )  {
    if ( !is_uploaded_file($_FILES['fichier']['tmp_name'][$i]) )  {
      $message .= '<br>Le document <em>'.$_FILES['fichier']['tmp_name'][$i].'</em> n\'a pas été ajouté : le fichier a mal été envoyé. Vous devriez en informer l\'administrateur.';
      continue;
    }
    // Vérifications des données envoyées (on fait confiance aux utilisateurs connectés pour ne pas envoyer de scripts malsains)
    // $ext ne doit pas faire plus de 5 caractères sinon fichier plus accessible
    $ext = ( strpos($_FILES['fichier']['name'][$i],'.') ) ? substr(strrchr($_FILES['fichier']['name'][$i],'.'),0,5) : '';
    $nom = trim(substr(basename(str_replace(array($ext,'\\'),array('','/'), ( $_REQUEST['nom'][$i] ?? '' ) ?: $_FILES['fichier']['name'][$i] )),0,100));
    // Création du répertoire particulier
    $lien = substr(sha1(mt_rand()),0,15);
    while ( is_dir("documents/$lien") )
      $lien = substr(sha1(mt_rand()),0,15);
    mkdir("documents/$lien");
    // Gestion de la taille
    $taille = ( ( $taille = intval($_FILES['fichier']['size'][$i]/1024) ) < 1024 ) ? "$taille&nbsp;ko" : intval($taille/1024).'&nbsp;Mo';
    // Déplacement du document uploadé au bon endroit
    if ( !move_uploaded_file($_FILES['fichier']['tmp_name'][$i],"documents/$lien/$nom$ext") )  {
      $message .= "<br>Le document <em>$nom</em> n'a pas été ajouté : problème d'écriture du fichier. Vous devriez en informer l'administrateur.";
      continue;
    }
    // Écriture MySQL
    // On doit garder $nom pour l'affichage
    $nom_sql = $mysqli->real_escape_string($nom);
    if ( requete('docs',"INSERT INTO docs SET parent = $parent, parents = '${r['parents']},$parent', matiere = ${r['matiere']},
                         nom = '$nom_sql', nom_nat = '".zpad($nom_sql)."', upload = CURDATE(), taille = '$taille',
                         lien = '$lien', ext='".$mysqli->real_escape_string($ext)."', protection = $protection, dispo = '$dispo'",$mysqli) )  {
      $id = $mysqli->insert_id;
      $resultat = $mysqli->query("SELECT GROUP_CONCAT(nom ORDER BY FIND_IN_SET(id,'${r['parents']},$parent') SEPARATOR '/' )
                                  FROM reps WHERE FIND_IN_SET(id,'${r['parents']},$parent')");
      recent($mysqli,3,$id,array('matiere'=>$r['matiere'], 'titre'=>$nom_sql, 'lien'=>"download?id=$id", 'texte'=>$mysqli->real_escape_string("$ext|$taille|$parent|".$resultat->fetch_row()[0]), 'protection'=>$protection, 'publi'=>$dispo));
      $resultat->free();
      $ok++;
    }
    else  {
      // Retour en arrière
      exec("rm -rf documents/$lien");
      $message .= "<br>Le document <em>$nom</em> n'a pas été ajouté. Erreur MySQL n°".$mysqli->errno.', «&nbsp;'.$mysqli->error.'&nbsp;».';
    }
  }
  // Traitement des échecs 
  if ( !$ok )
    exit("{\"etat\":\"nok\",\"message\":\"Aucun document n'a été envoyé.$message\"}");
  // Mise à jour de l'affichage de la matière dans le menu et de l'ordre des documents
  $mysqli->query("UPDATE matieres SET docs = 1 WHERE id = ${r['matiere']}");
  $mysqli->query('ALTER TABLE docs ORDER BY parents,nom_nat');
  if ( $n == 1 )
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le document <em>$nom</em> a été ajouté.\"}");
  if ( $ok < $n )
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Seuls $ok documents sur $n ont été ajoutés.$message\"}");
  exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Les $ok documents ont été ajoutés.\"}");
}

///////////////////////////////////////////
// Modification des programmes de colles //
///////////////////////////////////////////
elseif ( ( $action == 'colles' ) && isset($_REQUEST['id']) && ctype_digit($sid = strstr($_REQUEST['id'],'-',true)) && in_array($mid = intval(substr(strstr($_REQUEST['id'],'-'),1)), explode(',',$_SESSION['matieres'])) )  {
  // L'identifiant est sous la forme semaine-matiere, pour assurer une
  // cohérence entre les programmes saisis et ceux non saisis/supprimés
  $resultat = $mysqli->query("SELECT DATE_FORMAT(s.debut,'%e/%m') AS debut, c.id, c.cache, c.texte
                              FROM semaines AS s LEFT JOIN colles AS c ON c.semaine = s.id
                              WHERE s.id = $sid AND c.matiere = $mid");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  // Chaine de mise à jour de la matière
  $requete_maj = "UPDATE matieres SET colles = IF((SELECT id FROM colles WHERE matiere = $mid AND cache = 0 LIMIT 1),1,0) WHERE id = $mid AND colles_protection < 32";

  // Traitement d'une modification unique (texte)
  if ( isset($_REQUEST['champ']) && ( $_REQUEST['champ'] == 'texte' ) )  {
    if ( !( $valeur = $mysqli->real_escape_string($_REQUEST['val']) ) )
      exit("{\"etat\":\"nok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'a pas été modifié. Le texte doit être non vide.\"}");
    if ( !requete('colles',"UPDATE colles SET texte = '$valeur' WHERE id = ${r['id']}",$mysqli) )
      exit("{\"etat\":\"nok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    if ( !$r['cache'] )
      recent($mysqli,2,$r['id'],array('texte'=>$valeur),isset($_REQUEST['publi']));
    exit("{\"etat\":\"ok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} a été modifié.\"}");
  }

  // Positionnement "montré" (apparaît sur la partie publique)
  if ( isset($_REQUEST['montre']) )  {
    if ( !requete('colles',"UPDATE colles SET cache = 0 WHERE id = ${r['id']}",$mysqli) )
      exit("{\"etat\":\"nok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'a pas été diffusé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    // Fabrication des données pour les informations récentes
    $resultat = $mysqli->query("SELECT nom, cle, colles_protection FROM matieres WHERE id = $mid");
    $s = $resultat->fetch_assoc();
    $resultat->free();
    recent($mysqli,2,$r['id'],array('matiere'=>$mid, 'titre'=>"Colles du ${r['debut']} en ".$mysqli->real_escape_string($s['nom']), 'lien'=>"colles?${s['cle']}&amp;n=$sid", 'texte'=>$mysqli->real_escape_string($r['texte']), 'protection'=>$s['colles_protection']));
    $mysqli->query($requete_maj);
    exit("{\"etat\":\"ok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} apparaît désormais sur la partie publique.\"}");
  }

  // Positionnement "caché" (n'apparaît pas sur la partie publique)
  if ( isset($_REQUEST['cache']) )  {
    if ( !requete('colles',"UPDATE colles SET cache = 1 WHERE id = ${r['id']}",$mysqli) )
      exit("{\"etat\":\"nok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'a pas été caché. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    recent($mysqli,2,$r['id']);
    $mysqli->query($requete_maj);
    exit("{\"etat\":\"ok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'apparaît plus sur la partie publique mais est toujours disponible ici pour modification ou diffusion.\"}");
  }

  // Suppression
  if ( isset($_REQUEST['supprime']) )  {
    if ( !requete('colles',"DELETE FROM colles WHERE id = ${r['id']}",$mysqli) )
      exit("{\"etat\":\"nok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    recent($mysqli,2,$r['id']);
    $mysqli->query($requete_maj);
    exit("{\"etat\":\"ok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} a été supprimé.\"}");
  }
}

////////////////////////////////////
// Ajout d'un programme de colles //
////////////////////////////////////
elseif ( ( $action == 'ajout-colle' ) && isset($_REQUEST['id']) && ctype_digit($sid = strstr($_REQUEST['id'],'-',true)) && in_array($mid = intval(substr(strstr($_REQUEST['id'],'-'),1)), explode(',',$_SESSION['matieres'])) )  {
  // L'identifiant est sous la forme semaine-matiere, pour assurer une
  // cohérence entre les programmes saisis et ceux non saisis/supprimés
  $resultat = $mysqli->query("SELECT DATE_FORMAT(debut,'%e/%m') AS debut FROM semaines WHERE id = $sid AND colle");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  // Validation des données
  $cache = intval(isset($_REQUEST['cache']));
  if ( !( $texte = $mysqli->real_escape_string($_REQUEST['texte']) ) )
    exit("{\"etat\":\"nok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'a pas été ajouté. Le texte doit être non vide.\"}");
  if ( !requete('colles',"INSERT INTO colles SET texte = '$texte', semaine = $sid, matiere = $mid, cache = $cache",$mysqli) )
    exit("{\"etat\":\"nok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} n'a pas été ajouté. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  $id = $mysqli->insert_id;
  $mysqli->query('ALTER TABLE colles ORDER BY matiere,semaine');
  if ( !$cache )  {
    // Fabrication des données pour les informations récentes
    $resultat = $mysqli->query("SELECT nom, cle, colles_protection FROM matieres WHERE id = $mid");
    $s = $resultat->fetch_assoc();
    $resultat->free();
    recent($mysqli,2,$id,array('titre'=>"Colles du ${r['debut']} en ".$mysqli->real_escape_string($s['nom']), 'lien'=>"colles?${s['cle']}&amp;n=$sid", 'texte'=>$texte, 'matiere'=>$mid, 'protection'=>$s['colles_protection']));
    // Mise à jour de la matière
    $mysqli->query("UPDATE matieres SET colles = IF((SELECT id FROM colles WHERE matiere = $mid AND cache = 0 LIMIT 1),1,0) WHERE id = $mid AND colles_protection < 32");
  }
  exit("{\"etat\":\"ok\",\"message\":\"Le programme de colle de la semaine du ${r['debut']} a été ajouté.\"}");
}

/////////////////////////////////////////////////////////
// Modification ou ajout des éléments cahiers de texte //
/////////////////////////////////////////////////////////
elseif ( $action == 'cdt-elems' )  {

  // Traitement d'une modification de propriétés/d'un ajout d'élément :
  // validation préliminaire
  if ( isset($_REQUEST['tid']) && ctype_digit($tid = $_REQUEST['tid']) && isset($_REQUEST['jour']) )  {
    // Validation du jour de la semaine
    $jour = preg_filter('/(\d{2})\/(\d{2})\/(\d{4})/','$3-$2-$1',$_REQUEST['jour']);
    $resultat = $mysqli->query("SELECT id FROM semaines WHERE debut <= '$jour' AND debut >= SUBDATE('$jour',7) ORDER BY debut DESC LIMIT 1");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Le jour choisi ne se trouve pas dans l\'année scolaire. S\'il s\'agit d\'une erreur, il faut peut-être contacter l\'administrateur."}');
    $r = $resultat->fetch_assoc();
    $semaine = $r['id'];
    $resultat->free();
    // Validation du type de séance
    $resultat = $mysqli->query("SELECT id, deb_fin_pour FROM `cdt-types` WHERE id = $tid AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Type de séance non valide."}');
    $r = $resultat->fetch_assoc();
    $resultat->free();
    // Validation des horaires
    $h_debut = $h_fin = '0:00';
    $pour = '0000/00/00';
    $demigroupe = 0;
    switch ( $r['deb_fin_pour'] )  {
      case 1: $h_fin = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['h_fin']);
      case 0: $h_debut = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['h_debut']); $demigroupe = intval($_REQUEST['demigroupe'] == 1); break;
      case 2: $pour = preg_filter('/(\d{2})\/(\d{2})\/(\d{4})/','$3-$2-$1',$_REQUEST['pour']);
      case 3: $demigroupe = intval($_REQUEST['demigroupe'] == 1);
      // Cas 4 et 5 : h_debut, h_fin et pour restent nuls (cf l'aide)
    }
  }

  // Vérification que l'identifiant est valide
  if ( isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
    $resultat = $mysqli->query("SELECT id, type, matiere FROM cdt WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Identifiant de l\'élément de cahier de texte non valide"}');
    $r = $resultat->fetch_assoc();
    $resultat->free();

    // Traitement d'une modification de propriétés
    if ( isset($h_debut) )  {
      // Écriture dans la base de données
      if ( requete('cdt',"UPDATE cdt SET semaine = $semaine, jour = '$jour', h_debut = '$h_debut', h_fin = '$h_fin',
                          pour = '$pour', type = $tid, demigroupe = $demigroupe WHERE id = $id", $mysqli) )  {
        // Mise à jour du compte d'éléments si modification du type
        if ( $tid != $r['type'] )  {
          $mysqli->query("UPDATE `cdt-types` SET nb = nb-1 WHERE id = ${r['type']}");
          $mysqli->query("UPDATE `cdt-types` SET nb = nb+1 WHERE id = $tid");
        }
        // Mise en ordre : d'abord ce qui n'a pas de fin, ensuite les
        // séances "normales", ensuite les "pour le"
        $mysqli->query('ALTER TABLE cdt ORDER BY jour,matiere,pour,h_debut,h_fin,type');
        exit('{"etat":"ok","message":"Les propriétés de l\'élément du cahier de texte ont été modifiées."}');
      }
      exit('{"etat":"nok","message":"Les propriétés de l\'élément du cahier de texte n\'ont pas été modifiées. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Traitement d'une modification de texte
    if ( isset($_REQUEST['champ']) && ( $_REQUEST['champ'] == 'texte' ) )  {
      if ( !strlen($valeur = $mysqli->real_escape_string($_REQUEST['val'])) )
        exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été modifié. Le texte doit être non vide."}');
      if ( requete('cdt',"UPDATE cdt SET texte = '$valeur' WHERE id = $id",$mysqli) )
        exit('{"etat":"ok","message":"Le texte de l\'élément du cahier de texte a été modifié."}');
      exit('{"etat":"nok","message":"Le texte de l\'élément du cahier de texte n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Positionnement "montré" (apparaît sur la partie publique)
    if ( isset($_REQUEST['montre']) )  {
      if ( requete('cdt',"UPDATE cdt SET cache = 0 WHERE id = $id",$mysqli) )  {
        $mysqli->query("UPDATE matieres SET cdt = IF((SELECT id FROM cdt WHERE matiere = ${r['matiere']} AND cache = 0 LIMIT 1),1,0) WHERE id = ${r['matiere']} AND cdt_protection < 32");
        exit('{"etat":"ok","message":"L\'élément du cahier de texte apparaît désormais sur la partie publique."}');
      }
      exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été diffusé. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Positionnement "caché" (n'apparaît pas sur la partie publique)
    if ( isset($_REQUEST['cache']) )  {
      if ( requete('cdt',"UPDATE cdt SET cache = 1 WHERE id = $id",$mysqli) )  {
        $mysqli->query("UPDATE matieres SET cdt = IF((SELECT id FROM cdt WHERE matiere = ${r['matiere']} AND cache = 0 LIMIT 1),1,0) WHERE id = ${r['matiere']} AND cdt_protection < 32");
        exit('{"etat":"ok","message":"L\'élément du cahier de texte n\'apparaît plus sur la partie publique mais est toujours disponible ici pour modification ou diffusion."}');
      }
      exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été caché. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Suppression
    elseif ( isset($_REQUEST['supprime']) )  {
      if ( requete('cdt',"DELETE FROM cdt WHERE id = $id",$mysqli) )  {
        $mysqli->query("UPDATE `cdt-types` SET nb = nb-1 WHERE id = ${r['type']}");
        $mysqli->query("UPDATE matieres SET cdt = IF((SELECT id FROM cdt WHERE matiere = ${r['matiere']} AND cache = 0 LIMIT 1),1,0) WHERE id = ${r['matiere']} AND cdt_protection < 32");
        exit('{"etat":"ok","message":"L\'élément du cahier de texte a été supprimé."}');
      }
      exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été supprimé. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }
  }
  
  // Nouvel élément du cahier de texte
  elseif ( isset($demigroupe) && isset($_REQUEST['matiere']) && in_array($matiere = intval($_REQUEST['matiere']), explode(',',$_SESSION['matieres'])) )  {
    $cache = intval(isset($_REQUEST['cache']));
    if ( !strlen($texte = $mysqli->real_escape_string($_REQUEST['texte'])) )
      exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été ajouté. Le texte doit être non vide."}');
    if ( is_null($h_debut) )
      exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été ajouté. L\'horaire de début doit être non vide."}');
    if ( is_null($h_fin) )
      exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été ajouté. L\'horaire de fin doit être non vide."}');
    if ( is_null($pour) )
      exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été ajouté. La date d\'échéance doit être non vide."}');
    // Écriture dans la base de données
    if ( requete('cdt',"INSERT INTO cdt SET matiere = $matiere, semaine = $semaine, jour = '$jour', h_debut = '$h_debut', h_fin = '$h_fin',
                            pour = '$pour', type = $tid, texte = '$texte', demigroupe = $demigroupe, cache = $cache", $mysqli) )  {
      // Mises à jour : nombre d'éléments du type, ordre des éléments (d'abord
      // ce qui n'a pas de fin, ensuite les séances "normales", ensuite les
      // "pour le"), champ cdt de la matière
      $mysqli->query("UPDATE `cdt-types` SET nb = nb+1 WHERE id = $tid");
      $mysqli->query('ALTER TABLE cdt ORDER BY jour,matiere,pour,h_debut,h_fin,type');
      $mysqli->query("UPDATE matieres SET cdt = IF((SELECT id FROM cdt WHERE matiere = $matiere AND cache = 0 LIMIT 1),1,0) WHERE id = $matiere AND cdt_protection < 32");
      exit($_SESSION['message'] = '{"etat":"ok","message":"L\'élément du cahier de texte a été ajouté."}');
    }
    exit('{"etat":"nok","message":"L\'élément du cahier de texte n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
}

///////////////////////////////////////////////////////////
// Modification des types de séance des cahiers de texte //
///////////////////////////////////////////////////////////
elseif ( ( $action == 'cdt-types' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  
  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT ordre, titre, nb, cle, matiere, (SELECT COUNT(*) FROM `cdt-types` WHERE matiere = c.matiere) AS max FROM `cdt-types` AS c WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant du type d\'élément de cahier de texte non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Traitement d'une modification
  if ( isset($_REQUEST['titre']) && isset($_REQUEST['cle']) && isset($_REQUEST['deb_fin_pour']) && in_array($deb_fin_pour = intval($_REQUEST['deb_fin_pour']),array(0,1,2,3,4,5)) )  {
    $titre = mb_strtoupper(mb_substr($titre = strip_tags(trim($mysqli->real_escape_string($_REQUEST['titre']))),0,1)).mb_substr($titre,1);
    $cle = str_replace(' ','_',trim($mysqli->real_escape_string($_REQUEST['cle'])));
    if ( !strlen($titre) || !strlen($cle) )
      exit("{\"etat\":\"nok\",\"message\":\"Le type de séance <em>${r['titre']}</em> n'a pas été modifié. Le texte et la clé doivent être non vides.\"}");
    if ( $cle != $r['cle'] )  {
      // Vérification que la clé n'existe pas déjà
      $resultat = $mysqli->query("SELECT cle FROM `cdt-types` WHERE id != $id AND matiere = ${r['matiere']}");
      if ( $resultat->num_rows )  {
        while ( $r = $resultat->fetch_assoc() )
          if ( $r['cle'] == $cle )
            exit("{\"etat\":\"nok\",\"message\":\"Le type de séance <em>${r['titre']}</em> n'a pas été modifié. Cette clé existe déjà et doit être unique.\"}");
        $resultat->free();
      }
    }
    if ( requete('cdt-types',"UPDATE `cdt-types` SET titre = '$titre', cle = '$cle', deb_fin_pour = $deb_fin_pour WHERE id = $id",$mysqli) )
      exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le type de séance <em>${r['titre']}</em> a été modifié.\"}");
    exit("{\"etat\":\"nok\",\"message\":\"Le type de séance <em>${r['titre']}</em> n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Déplacement vers le haut
  if ( isset($_REQUEST['monte']) && ( $r['ordre'] > 1 ) )
    exit( ( requete('cdt-types',"UPDATE `cdt-types` SET ordre = (2*${r['ordre']}-1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}-1) ) AND matiere = ${r['matiere']}",$mysqli)
            && $mysqli->query('ALTER TABLE `cdt-types` ORDER BY matiere,ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement du type de séance <em>${r['titre']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"Le type de séance <em>${r['titre']}</em> n'a pas été déplacé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Déplacement vers le bas
  if ( isset($_REQUEST['descend']) && ( $r['ordre'] < $r['max'] ) )
    exit( ( requete('cdt-types',"UPDATE `cdt-types` SET ordre = (2*${r['ordre']}+1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}+1) ) AND matiere = ${r['matiere']}",$mysqli)
            && $mysqli->query('ALTER TABLE `cdt-types` ORDER BY matiere,ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement du type de séance <em>${r['titre']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"Le type de séance <em>${r['titre']}</em> n'a pas été déplacé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Suppression
  if ( isset($_REQUEST['supprime']) )  {
    if ( $r['max'] == 1 )
      exit("{\"etat\":\"ok\",\"message\":\"Le type de séance <em>${r['nom']}</em> n'a pas été supprimé. Il faut obligatoirement en garder au moins un.\"}");
    exit( ( requete('cdt',"DELETE FROM cdt WHERE type = $id",$mysqli) && requete('cdt-types',"DELETE FROM `cdt-types` WHERE id = $id",$mysqli)
            && requete('cdt-types',"UPDATE `cdt-types` SET ordre = (ordre-1) WHERE ordre > ${r['ordre']} AND matiere = ${r['matiere']}",$mysqli) )
        ? "{\"etat\":\"ok\",\"message\":\"Le type de séance <em>${r['titre']}</em> a été supprimé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"Le type de séance <em>${r['titre']}</em> n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
}

////////////////////////////////////////////////////
// Ajout d'un type de séance des cahiers de texte //
////////////////////////////////////////////////////
elseif ( ( $action == 'ajout-cdt-types' ) && isset($_REQUEST['titre']) && isset($_REQUEST['cle']) && isset($_REQUEST['deb_fin_pour']) && in_array($deb_fin_pour = intval($_REQUEST['deb_fin_pour']),array(0,1,2,3,4,5)) && isset($_REQUEST['matiere']) && in_array($matiere = intval($_REQUEST['matiere']),explode(',',$_SESSION['matieres'])) )  {
  $titre = mb_strtoupper(mb_substr($titre = strip_tags(trim($mysqli->real_escape_string($_REQUEST['titre']))),0,1)).mb_substr($titre,1);
  $cle = str_replace(' ','_',trim($mysqli->real_escape_string($_REQUEST['cle'])));
  if ( !strlen($titre) || !strlen($cle) )
    exit('{"etat":"nok","message":"Le type de séance n\'a pas été ajouté. Le texte et la clé doivent être non vides."}');
  // Vérification que la clé n'existe pas déjà
  $resultat = $mysqli->query("SELECT cle FROM `cdt-types` WHERE matiere = $matiere");
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )
      if ( $r['cle'] == $cle )
        exit('{"etat":"nok","message":"Le type de séance n\'a pas été ajouté. Cette clé existe déjà et doit être unique."}');
    $resultat->free();
  }
  // Écriture
  if ( requete('cdt-types',"INSERT INTO `cdt-types` SET matiere = $matiere, titre = '$titre', cle = '$cle', deb_fin_pour = $deb_fin_pour,
                              ordre = (SELECT max(ct.ordre)+1 FROM `cdt-types` AS ct WHERE ct.matiere = $matiere)",$mysqli) )
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le type de séance <em>$titre</em> a été ajouté.\"}");
  exit("{\"etat\":\"nok\",\"message\":\"Le type de séance <em>$titre</em> n'a pas été ajouté. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
}
    
//////////////////////////////////////////////////////
// Modification des raccourcis des cahiers de texte //
//////////////////////////////////////////////////////
elseif ( ( $action == 'cdt-raccourcis' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  
  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT ordre, nom, matiere, (SELECT COUNT(*) FROM `cdt-seances` WHERE matiere = c.matiere) AS max FROM `cdt-seances` AS c WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant du raccourci de cahier de texte non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Traitement d'une modification
  if ( isset($_REQUEST['nom']) && isset($_REQUEST['type']) && ctype_digit($type = $_REQUEST['type']) && isset($_REQUEST['jour']) && in_array( $jour = intval($_REQUEST['jour']),array(1,2,3,4,5,6,7)) )  {
    if ( !strlen($nom = $mysqli->real_escape_string(trim($_REQUEST['nom']))) )
      exit("{\"etat\":\"nok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> n'a pas été modifié. Le nom doit être non vide.\"}");
    // Validation du type de séance
    $resultat = $mysqli->query("SELECT deb_fin_pour FROM `cdt-types` WHERE id = $type AND matiere = ${r['matiere']}");
    if ( !$resultat->num_rows )
      exit("{\"etat\":\"nok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> n'a pas été modifié. Le type de séance n'est pas valide.\"}");
    $s = $resultat->fetch_assoc();
    $resultat->free();
    // Validation des horaires
    $h_debut = $h_fin = '0:00';
    $demigroupe = 0;
    switch ( $s['deb_fin_pour'] )  {
      case 1: $h_fin = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['h_fin']);
      case 0: $h_debut = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['h_debut']);
      case 2: 
      case 3: $demigroupe = intval($_REQUEST['demigroupe'] == 1);
      // Cas 4 et 5 : h_debut, h_fin et pour restent nuls (cf l'aide)
    }
    // Écriture
    if ( requete('cdt-seances',"UPDATE `cdt-seances` SET nom = '$nom', jour = $jour, h_debut = '$h_debut',
                                h_fin = '$h_fin', type = $type, demigroupe = $demigroupe WHERE id = $id",$mysqli) )
      exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> a été modifié.\"}");
    exit("{\"etat\":\"nok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Déplacement vers le haut
  if ( isset($_REQUEST['monte']) && ( $r['ordre'] > 1 ) )
    exit( ( requete('cdt-seances',"UPDATE `cdt-seances` SET ordre = (2*${r['ordre']}-1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}-1) ) AND matiere = ${r['matiere']}",$mysqli)
            && $mysqli->query('ALTER TABLE `cdt-seances` ORDER BY matiere,ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement du raccourci de séance <em>${r['nom']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> n'a pas été déplacé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Déplacement vers le bas
  if ( isset($_REQUEST['descend']) && ( $r['ordre'] < $r['max'] ) )
    exit( ( requete('cdt-seances',"UPDATE `cdt-seances` SET ordre = (2*${r['ordre']}+1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}+1) ) AND matiere = ${r['matiere']}",$mysqli)
            && $mysqli->query('ALTER TABLE `cdt-seances` ORDER BY matiere,ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement du raccourci de séance <em>${r['nom']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> n'a pas été déplacé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Suppression
  if ( isset($_REQUEST['supprime']) )
    exit( ( requete('cdt-seances',"DELETE FROM `cdt-seances` WHERE id = $id",$mysqli)
            && requete('cdt-seances',"UPDATE `cdt-seances` SET ordre = (ordre-1) WHERE ordre > ${r['ordre']} AND matiere = ${r['matiere']}",$mysqli) )
        ? "{\"etat\":\"ok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> a été supprimé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"Le raccourci de séance <em>${r['nom']}</em> n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
}

///////////////////////////////////////////////
// Ajout d'un raccourci des cahiers de texte //
///////////////////////////////////////////////
elseif ( ( $action == 'ajout-cdt-raccourci' ) && isset($_REQUEST['nom']) && isset($_REQUEST['type']) && ctype_digit($type = $_REQUEST['type']) && isset($_REQUEST['jour']) && in_array( $jour = intval($_REQUEST['jour']),array(1,2,3,4,5,6,7)) && isset($_REQUEST['matiere']) && in_array($matiere = intval($_REQUEST['matiere']),explode(',',$_SESSION['matieres'])) )  {
  if ( !strlen($nom = $mysqli->real_escape_string(trim($_REQUEST['nom']))) )
    exit('{"etat":"nok","message":"Le raccourci de séance n\'a pas été ajouté. Le nom doit être non vide."}');
  // Validation du type de séance
  $resultat = $mysqli->query("SELECT id, deb_fin_pour FROM `cdt-types` WHERE id = $type AND matiere = $matiere");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Le raccourci de séance n\'a pas été ajouté. Le type de séance n\'est pas valide."}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  // Validation des horaires
  $h_debut = $h_fin = '0:00';
  $demigroupe = 0;
  switch ( $r['deb_fin_pour'] )  {
    case 1: $h_fin = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['h_fin']);
    case 0: $h_debut = preg_filter('/(\d{1,2})h(\d{2})/','$1:$2',$_REQUEST['h_debut']);
    case 2: 
    case 3: $demigroupe = intval($_REQUEST['demigroupe'] == 1);
    // Cas 4 et 5 : h_debut, h_fin et pour restent nuls (cf l'aide)
  }
  // Écriture
  if ( requete('cdt-seances',"INSERT INTO `cdt-seances` SET matiere = $matiere, nom = '$nom', jour = $jour,
                              ordre = (SELECT IFNULL(max(cs.ordre)+1,1) FROM `cdt-seances` AS cs WHERE cs.matiere = $matiere),
                              h_debut = '$h_debut', h_fin = '$h_fin', type = $type, demigroupe = $demigroupe",$mysqli) )
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le raccourci de séance <em>$nom</em> a été ajouté.\"}");
  exit('{"etat":"nok","message":"Le raccourci de séance n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

////////////////////////////
// Modification des pages //
////////////////////////////
elseif ( ( $action == 'pages' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {

  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT ordre, mat, nom, cle, (SELECT COUNT(*) FROM pages WHERE mat = p.mat) AS max FROM pages AS p WHERE id = $id AND FIND_IN_SET(mat,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de page non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Traitement d'une modification globale (depuis index.php ou pages.php)
  if ( isset($_REQUEST['titre']) && isset($_REQUEST['nom']) && isset($_REQUEST['cle']) && isset($_REQUEST['bandeau']) && isset($_REQUEST['protection']) )  {
    $titre = mb_strtoupper(mb_substr($titre = trim(strip_tags($mysqli->real_escape_string($_REQUEST['titre']))) ,0,1)).mb_substr($titre,1);
    $nom = mb_strtoupper(mb_substr($nom = trim(strip_tags($mysqli->real_escape_string($_REQUEST['nom']))),0,1)).mb_substr($nom,1);
    $cle = str_replace(' ','_',strip_tags(trim($mysqli->real_escape_string($_REQUEST['cle']))));
    if ( !$titre || !$nom || !$cle )
      exit("{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> n'a pas été modifiée. Le titre, le nom et la clé doivent être non vides.\"}");
    // Partie de requête si modification de matière (impossible si première page)
    // N'existe qu'en provenance de pages.php (et non d'index.php)
    $requete_matiere = ( isset($_REQUEST['matiere']) && in_array($matiere = intval($_REQUEST['matiere']),explode(',',$_SESSION['matieres'])) && ( $matiere != $r['mat'] ) && ( $id > 1 ) ) ? ", mat = $matiere" : '';
    // Vérification que la clé n'existe pas déjà
    if ( $cle != $r['cle'] )  {
      $resultat = $mysqli->query('SELECT cle FROM pages WHERE mat = '.( $requete_matiere ? $matiere : $r['mat'] ));
      if ( $resultat->num_rows )  {
        while ( $s = $resultat->fetch_row() )
          if ( $s[0] == $cle )
            exit("{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> n'a pas été modifiée. La clé donnée existe déjà. Elle doit être différente de celles des autres pages.\"}"); 
        $resultat->free();
      }
    }
    $bandeau = trim($mysqli->real_escape_string($_REQUEST['bandeau']));
    // Génération de la valeur de protection
    if ( !count($val = array_filter($_REQUEST['protection'],function($id) { return ctype_digit($id); })) )
      exit("{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> n'a pas été modifiée. La protection d'accès est incorrecte.\"}");
    $protection = ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32);
    // Écriture
    if ( !requete('pages',"UPDATE pages SET titre = '$titre', nom = '$nom', cle = '$cle', bandeau = '$bandeau', protection = $protection $requete_matiere WHERE id = $id",$mysqli) )
      exit("{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    // Changement de matière : déplacement des pages sur les matières concernées
    if ( $requete_matiere )  {
      requete('pages',"UPDATE pages SET ordre = (SELECT COUNT(*) FROM (SELECT id FROM pages AS p WHERE p.mat = $matiere) AS p1) WHERE id = $id",$mysqli);
      requete('pages',"UPDATE pages SET ordre = (ordre-1) WHERE mat = ${r['mat']} AND ordre > ${r['ordre']}",$mysqli);
      // Mise à jour de la table recents, des flux RSS et de l'ordre des pages
      requete('recents',"UPDATE recents SET matiere = $matiere, 
                                            titre = CONCAT(SUBSTRING_INDEX(titre,'[',1), ( SELECT CONCAT('[',IF(mat=0,'',CONCAT(m.nom,'/')),p.nom,']')
                                                                                           FROM pages AS p LEFT JOIN matieres AS m ON mat=m.id WHERE p.id = $id ) )
                         WHERE type = 1 AND id IN (SELECT id FROM infos WHERE page = $id)",$mysqli);
      rss($mysqli,array($r['mat'],$matiere),0);
      $mysqli->query('ALTER TABLE pages ORDER BY mat,ordre');
    }
    // Changement de nom : modification de la table recents et des flux RSS (inutile si matière modifiée, impossible si première page)
    elseif ( ( $nom != $r['nom'] ) && ( $id > 1 ) )  {
      requete('recents',"UPDATE recents SET titre = CONCAT(SUBSTRING_INDEX(titre,'[',1), ( SELECT CONCAT('[',IF(mat=0,'',CONCAT(m.nom,'/')),p.nom,']')
                                                                                           FROM pages AS p LEFT JOIN matieres AS m ON mat=m.id WHERE p.id = $id ) )
                         WHERE type = 1 AND id IN (SELECT id FROM infos WHERE page = $id)",$mysqli);
      rss($mysqli,$r['mat'],0);
    }
    // Changement de clé donc de lien dans la table recents
    if ( $cle != $r['cle'] )  {
      requete('recents',"UPDATE recents SET lien = '?$cle' WHERE type = 1 AND id IN (SELECT id FROM infos WHERE page = $id)",$mysqli);
      rss($mysqli,$r['mat'],0);
    }
    // Propagation de la protection : modification des infos
    if ( isset($_REQUEST['propagation']) )  {
      requete('infos',"UPDATE infos SET protection = $protection WHERE page = $id",$mysqli);
      // Mise à jour de la table recents et des flux RSS
      requete('recents',"UPDATE recents SET protection = $protection WHERE type = 1 AND id IN (SELECT id FROM infos WHERE page = $id)",$mysqli);
      rss($mysqli,$r['mat'],0);
    }
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"La page <em>${r['nom']}</em> a été modifiée.\"}");
  }

  // Déplacement vers le haut
  if ( isset($_REQUEST['monte']) && ( $r['ordre'] > 1+!$r['mat'] ) )
    exit( ( requete('pages',"UPDATE pages SET ordre = (2*${r['ordre']}-1-ordre) WHERE mat = ${r['mat']} AND ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}-1) )",$mysqli) 
            && $mysqli->query('ALTER TABLE pages ORDER BY mat,ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement de la page <em>${r['nom']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> n'a pas été déplacée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Déplacement vers le bas
  if ( isset($_REQUEST['descend']) && ( $r['ordre'] < $r['max'] ) && ( $id > 1 ) )
    exit( ( requete('pages',"UPDATE pages SET ordre = (2*${r['ordre']}+1-ordre) WHERE mat = ${r['mat']} AND ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}+1) )",$mysqli) 
            && $mysqli->query('ALTER TABLE pages ORDER BY mat,ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement de la page <em>${r['nom']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> n'a pas été déplacée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Suppression
  if ( isset($_REQUEST['supprime']) )  {
    if ( $id == 1 )
      exit("{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> ne peut pas être supprimée.\"}");
    if ( requete('pages',"DELETE FROM pages WHERE id = $id",$mysqli)
      && requete('recents',"DELETE FROM recents WHERE type = 1 AND id IN (SELECT id FROM infos WHERE page = $id)",$mysqli)
      && requete('infos',"DELETE FROM infos WHERE page = $id",$mysqli)
      && requete('pages',"UPDATE pages SET ordre = (ordre-1) WHERE mat = ${r['mat']} AND ordre > ${r['ordre']}",$mysqli)
      && rss($mysqli,$r['mat'],0)
      && $mysqli->query('ALTER TABLE pages ORDER BY mat,ordre') )
      exit("{\"etat\":\"ok\",\"message\":\"La page <em>${r['nom']}</em> a été supprimée. Les informations contenues ont été supprimées.'\"}");
    exit("{\"etat\":\"nok\",\"message\":\"La page <em>${r['nom']}</em> n'a pas été supprimée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Suppression des informations
  if ( isset($_REQUEST['supprime_infos']) )
    exit( requete('recents',"DELETE FROM recents WHERE type = 1 AND id IN (SELECT id FROM infos WHERE page = $id)",$mysqli)
       && requete('infos',"DELETE FROM infos WHERE page = $id",$mysqli)
       && rss($mysqli,$r['mat'],0)
      ? "{\"etat\":\"ok\",\"message\":\"Les informations de la page <em>${r['nom']}</em> ont été supprimées.\"}"
      : "{\"etat\":\"nok\",\"message\":\"Les informations de la page <em>${r['nom']}</em> n'ont pas été supprimées. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
}
   
//////////////////////
// Ajout d'une page //
//////////////////////
elseif ( ( $action == 'ajout-page' ) && isset($_REQUEST['titre']) && isset($_REQUEST['nom']) && isset($_REQUEST['cle']) && isset($_REQUEST['bandeau']) && isset($_REQUEST['matiere']) && isset($_REQUEST['protection']) && in_array($matiere = intval($_REQUEST['matiere']),explode(',',$_SESSION['matieres'])) )  {
  $titre = strip_tags(trim($mysqli->real_escape_string($_REQUEST['titre'])));
  $nom = strip_tags(trim($mysqli->real_escape_string($_REQUEST['nom'])));
  $titre = mb_strtoupper(mb_substr($titre,0,1)).mb_substr($titre,1);
  $nom = mb_strtoupper(mb_substr($nom,0,1)).mb_substr($nom,1);
  $cle = str_replace(' ','_',strip_tags(trim($mysqli->real_escape_string($_REQUEST['cle']))));
  if ( !strlen($_REQUEST['titre']) || !strlen($_REQUEST['nom']) || !strlen($_REQUEST['cle']) )
    exit('{"etat":"nok","message":"La page n\'a pas été ajoutée. Le titre, le nom et la clé doivent être non vides."}');
  // Vérification que la clé n'existe pas déjà
  $resultat = $mysqli->query("SELECT cle FROM pages WHERE mat = $matiere");
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_row() )
      if ( $r[0] == $cle )
        exit("{\"etat\":\"nok\",\"message\":\"La page <em>$nom</em> n'a pas été ajoutée. La clé donnée existe déjà. Elle doit être différente de celles des autres pages.\"}");
    $resultat->free();
  }
  $bandeau = trim($mysqli->real_escape_string($_REQUEST['bandeau']));
  // Génération de la valeur de protection
  $val = array_filter($_REQUEST['protection'],function($id) { return ctype_digit($id); });
  if ( !count($val) )
    exit("{\"etat\":\"nok\",\"message\":\"La page <em>$nom</em> n'a pas été ajoutée. La protection d'accès est incorrecte.\"}");
  if ( ( $val[0] == 0 ) || ( $val[0] == 32 ) )
    $protection = $val[0];
  else  {
    $protection = 32;
    foreach (array_slice($val,1) as $v) 
      $protection = $protection-2**($v-1);
  }
  // Écriture
  if ( requete('pages',"INSERT INTO pages SET titre = '$titre', nom = '$nom', cle = '$cle', mat = $matiere, bandeau = '$bandeau', protection = $protection, ordre = (SELECT IFNULL(MAX(ordre)+1,1) FROM pages AS p WHERE p.mat = $matiere)",$mysqli) )
    exit("{\"etat\":\"ok\",\"message\":\"La page <em>$nom</em> a été ajoutée.\"}");
  exit("{\"etat\":\"nok\",\"message\":\"La page <em>$nom</em> n'a pas été ajoutée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
}

///////////////////////////////
// Modification des matières //
///////////////////////////////
elseif ( ( $action == 'matieres' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {

  // Déplacements (possibles pour toute matière, contrairement au reste)
  if ( isset($_REQUEST['monte']) || isset($_REQUEST['descend']) )  {
    $resultat = $mysqli->query("SELECT ordre, nom, (SELECT COUNT(*) FROM matieres) AS max FROM matieres WHERE id = $id");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Identifiant de matière non valide"}');
    $r = $resultat->fetch_assoc();
    $resultat->free();
    
    // Déplacement vers le haut
    if ( isset($_REQUEST['monte']) && ( $r['ordre'] > 1 ) )
      exit( ( requete('matieres',"UPDATE matieres SET ordre = (2*${r['ordre']}-1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}-1) )",$mysqli) && $mysqli->query('ALTER TABLE matieres ORDER BY ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement de la matière <em>${r['nom']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été déplacée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

    // Déplacement vers le bas
    if ( isset($_REQUEST['descend']) && ( $r['ordre'] < $r['max'] ) )
      exit( ( requete('matieres',"UPDATE matieres SET ordre = (2*${r['ordre']}+1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}+1) )",$mysqli) && $mysqli->query('ALTER TABLE matieres ORDER BY ordre') )
        ? "{\"etat\":\"ok\",\"message\":\"Le déplacement de la matière <em>${r['nom']}</em> a été réalisé.\"}"
        : "{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été déplacée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  $resultat = $mysqli->query("SELECT ordre, cle, nom, colles_protection, cdt_protection, docs_protection, (SELECT COUNT(*) FROM matieres) AS max FROM matieres WHERE id = $id");
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Traitement d'une modification
  if ( isset($_REQUEST['nom']) && isset($_REQUEST['cle']) && isset($_REQUEST['colles_protection']) && isset($_REQUEST['cdt_protection']) && isset($_REQUEST['docs_protection']) && isset($_REQUEST['notes']) && isset($_REQUEST['dureecolle']) && isset($_REQUEST['copies']) )  {
    $nom = mb_strtoupper(mb_substr($nom = trim(strip_tags($mysqli->real_escape_string($_REQUEST['nom']))),0,1)).mb_substr($nom,1);
    $cle = str_replace(' ','_',strip_tags(trim($mysqli->real_escape_string($_REQUEST['cle']))));
    if ( !$nom || !$cle )
      exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été modifiée. Le nom et la clé doivent être non vides.\"}");
    // Vérification que la clé n'existe pas déjà
    if ( $cle != $r['cle'] )  {
      $resultat = $mysqli->query('SELECT cle FROM matieres');
      if ( $resultat->num_rows )  {
        while ( $s = $resultat->fetch_row() )
          if ( $s[0] == $cle )
            exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été modifiée. La clé donnée existe déjà. Elle doit être différente de celles des autres matières.\"}");
        $resultat->free();
      }
    }
    // Génération de la valeur de protection
    $protection = array();
    foreach ( array('colles','cdt','docs') as $fonction )  {
      if ( !count($val = array_filter($_REQUEST[$fonction.'_protection'],function($id) { return ctype_digit($id); })) )
        exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été modifiée. Une des protections d'accès est incorrecte.\"}");
      $protection[$fonction] = ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32);
    }
    // Affichage des notes : 2-> désactivée ; 1-> possible (0 ou 1 dans la base)
    $notes = ( $_REQUEST['notes'] == 2 ) ? '2' : "IF( notes=2, IF((SELECT id FROM notes WHERE matiere = $id LIMIT 1),1,0), notes)";
    $dureecolle = intval($_REQUEST['dureecolle']) ?: 20;
    // Affichage des copies : 2-> désactivée ; 1-> possible (0 ou 1 dans la base)
    $copies = ( $_REQUEST['copies'] == 2 ) ? '2' : "IF( copies=2, IF((SELECT id FROM copies WHERE matiere = $id LIMIT 1),1,0), copies)";
    // Écriture
    if ( requete('matieres',"UPDATE matieres SET nom = '$nom', cle = '$cle', notes = $notes, copies = $copies, colles_protection = ${protection['colles']}, cdt_protection = ${protection['cdt']}, docs_protection = ${protection['docs']}, dureecolle = $dureecolle WHERE id = $id",$mysqli) )  {
      if ( $nom != $r['nom'] )  {
        requete('reps',"UPDATE reps SET nom = '$nom' WHERE matiere = $id AND parent = 0",$mysqli);
        // Modification de la table recents
        requete('recents',"UPDATE recents SET titre = ( SELECT CONCAT( IF(LENGTH(i.titre),i.titre,'Information'),' [',m.nom,'/',p.nom,']' )
                                                        FROM infos AS i LEFT JOIN pages AS p ON page=p.id LEFT JOIN matieres AS m ON mat=m.id WHERE i.id = recents.id )
                           WHERE type = 1 AND matiere = $id",$mysqli);
        requete('recents',"UPDATE recents SET titre = CONCAT( SUBSTRING_INDEX(titre,' ',4), ' en $nom' )
                           WHERE type = 2 AND matiere = $id",$mysqli);
        requete('recents',"UPDATE recents SET texte = ( SELECT CONCAT(ext,'|',taille,'|',d.parent,'|',GROUP_CONCAT( r.nom ORDER BY FIND_IN_SET(r.id,d.parents) SEPARATOR '/' ))
                                                        FROM docs AS d LEFT JOIN reps AS r ON FIND_IN_SET(r.id,d.parents) WHERE d.id = recents.id GROUP BY d.id )
                           WHERE type = 3 AND matiere = $id",$mysqli);
        rss($mysqli,$id,0);
      }
      if ( $protection['docs'] != $r['docs_protection'] )
        requete('reps',"UPDATE reps SET protection = ${protection['docs']} WHERE matiere = $id AND parent = 0",$mysqli);
      if ( $protection['colles'] != $r['colles_protection'] )
        requete('recents',"UPDATE recents SET protection = ${protection['colles']} WHERE type = 2 AND matiere = $id",$mysqli);
      if ( $cle != $r['cle'] )
        requete('recents',"UPDATE recents SET lien = CONCAT('colles?$cle&amp;n=',SUBSTRING_INDEX(lien,'&',-1)) WHERE type = 2 AND matiere = $id",$mysqli);
      exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"La matière <em>${r['nom']}</em> a été modifiée.\"}");
    }
    else
      exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Suppression
  if ( isset($_REQUEST['supprime']) )  {
    if ( $r['max'] == 1 )
      exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été supprimée. Il faut obligatoirement en garder au moins une.\"}");
    $resultat = $mysqli->query("SELECT id FROM reps WHERE parent = 0 AND matiere = $id");
    $rid = $resultat->fetch_row()[0];
    $resultat->free();
    // Suppression physique des éventuelles copies
    $resultat = $mysqli->query("SELECT lien FROM devoirs WHERE matiere = $id");
    if ( $resultat->num_rows )  {
      while ( $r = $resultat->fetch_row() )
        exec("rm -rf documents/${r[0]}");
      $resultat->free();
    }
    if ( requete('matieres',"DELETE FROM matieres WHERE id = $id",$mysqli) 
      && requete('matieres',"UPDATE matieres SET ordre = (ordre-1) WHERE ordre > ${r['ordre']}",$mysqli)
      && requete('colles',"DELETE FROM colles WHERE matiere = $id",$mysqli)
      && requete('recents',"DELETE FROM recents WHERE type = 2 AND matiere = $id",$mysqli)
      && requete('cdt',"DELETE FROM cdt WHERE matiere = $id",$mysqli)
      && requete('cdt-types',"DELETE FROM `cdt-types` WHERE matiere = $id",$mysqli)
      && requete('cdt-seances',"DELETE FROM `cdt-seances` WHERE matiere = $id",$mysqli)
      && requete('notes',"DELETE FROM notes WHERE matiere = $id",$mysqli)
      && requete('devoirs',"DELETE FROM devoirs WHERE matiere = $id",$mysqli)
      && requete('copies',"DELETE FROM copies WHERE matiere = $id",$mysqli)
      && requete('reps',"UPDATE reps SET matiere = 0, parent = 1, parents = '0,1' WHERE id = $rid",$mysqli)
      && requete('reps',"UPDATE reps SET matiere = 0, parents = CONCAT('0,1',SUBSTRING(parents,2)) WHERE matiere = $id AND id != $rid",$mysqli)
      && requete('docs',"UPDATE docs SET matiere = 0, parents = CONCAT('0,1',SUBSTRING(parents,2)) WHERE matiere = $id",$mysqli)
      && requete('recents',"UPDATE recents SET matiere = 0 WHERE type = 3 AND matiere = $id",$mysqli)
      && requete('pages',"UPDATE pages SET mat = 0 WHERE mat = $id",$mysqli)
      && requete('recents',"UPDATE recents SET matiere = 0, titre = ( SELECT CONCAT(IF(LENGTH(i.titre),i.titre,'Information'),' [',p.nom,']') 
                                                                      FROM infos AS i LEFT JOIN pages AS p ON page=p.id WHERE i.id = recents.id )
                            WHERE type = 1 AND matiere = $id",$mysqli)
      && requete('agenda',"UPDATE agenda SET matiere = 0 WHERE matiere = $id",$mysqli)
      && requete('recents',"UPDATE recents SET matiere = 0 WHERE type = 4 AND matiere = $id",$mysqli)
      && requete('utilisateurs',"UPDATE utilisateurs SET matieres = TRIM(TRAILING ',' FROM REPLACE(CONCAT(matieres,','),',$id,',',')) ",$mysqli)
      && rss($mysqli,0,0) )
      exit("{\"etat\":\"ok\",\"message\":\"La matière <em>${r['nom']}</em> a été supprimée. Les répertoires, documents, pages d'informations et éléments d'agenda associés à la matières n'ont pas été supprimés mais déplacés dans le contexte «&nbsp;général&nbsp;»'\"}");
    exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été supprimée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Suppression des programmes de colles d'une matière
  if ( isset($_REQUEST['supprime_colles']) )
    exit( requete('colles',"DELETE FROM colles WHERE matiere = $id",$mysqli)
       && requete('matieres',"UPDATE matieres SET colles = 0 WHERE id = $id",$mysqli)
       && requete('recents',"DELETE FROM recents WHERE type = 2 AND matiere = $id",$mysqli)
       && rss($mysqli,$id,0)
      ? "{\"etat\":\"ok\",\"message\":\"Les programmes de colle de la matière <em>${r['nom']}</em> ont été supprimés.\"}"
      : "{\"etat\":\"nok\",\"message\":\"Les programmes de colle de la matière <em>${r['nom']}</em> n'ont pas été supprimés. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  
  // Suppression du cahier de texte d'une matière
  if ( isset($_REQUEST['supprime_cdt']) )
    exit( requete('cdt',"DELETE FROM cdt WHERE matiere = $id",$mysqli) && requete('matieres',"UPDATE matieres SET cdt = 0 WHERE id = $id",$mysqli)
      ? "{\"etat\":\"ok\",\"message\":\"Le cahier de texte de la matière <em>${r['nom']}</em> a été supprimé.\"}"
      : "{\"etat\":\"nok\",\"message\":\"Le cahier de texte de la matière <em>${r['nom']}</em> n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Suppression des notes d'une matière
  if ( isset($_REQUEST['supprime_notes']) )
    exit( requete('notes',"DELETE FROM notes WHERE matiere = $id",$mysqli) && requete('matieres',"UPDATE matieres SET notes = 0 WHERE id = $id",$mysqli)
      ? "{\"etat\":\"ok\",\"message\":\"Les notes de la matière <em>${r['nom']}</em> ont été supprimées.\"}"
      : "{\"etat\":\"nok\",\"message\":\"Les notes de la matière <em>${r['nom']}</em> n'ont pas été supprimées. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Suppression des copies d'une matière
  if ( isset($_REQUEST['supprime_copies']) )  {
    // Suppression physique
    $resultat = $mysqli->query("SELECT lien FROM devoirs WHERE matiere = $id");
    if ( $resultat->num_rows )  {
      while ( $r = $resultat->fetch_row() )
        exec("rm -rf documents/${r[0]}");
      $resultat->free();
    }
    exit( requete('devoirs',"DELETE FROM devoirs WHERE matiere = $id",$mysqli) && requete('copies',"DELETE FROM copies WHERE matiere = $id",$mysqli) && requete('matieres',"UPDATE matieres SET copies = 0 WHERE id = $id",$mysqli)
      ? "{\"etat\":\"ok\",\"message\":\"Les notes de la matière <em>${r['nom']}</em> ont été supprimées.\"}"
      : "{\"etat\":\"nok\",\"message\":\"Les notes de la matière <em>${r['nom']}</em> n'ont pas été supprimées. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Suppression des documents d'une matière
  if ( isset($_REQUEST['supprime_docs']) )  {
    if ( requete('reps',"DELETE FROM reps WHERE matiere = $id AND parent > 0",$mysqli)
      && requete('matieres',"UPDATE matieres SET docs = 0 WHERE id = $id",$mysqli)
      && requete('recents',"DELETE FROM recents WHERE type = 3 AND matiere = $id",$mysqli)
      && rss($mysqli,$id,0) )  {
      // Suppression physique
      $resultat = $mysqli->query("SELECT lien FROM docs WHERE matiere = $id");
      if ( $resultat->num_rows )  {
        while ( $r = $resultat->fetch_row() )
          exec("rm -rf documents/${r[0]}");
        $resultat->free();
      }
      requete('docs',"DELETE FROM docs WHERE matiere = $id",$mysqli);
      exit("{\"etat\":\"ok\",\"message\":\"Les répertoires et documents de la matière <em>${r['nom']}</em> ont été supprimés.\"}");
    }
    exit("{\"etat\":\"nok\",\"message\":\"Les documents de la matière <em>${r['nom']}</em> n'ont pas été supprimés. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
}
  
/////////////////////////
// Ajout d'une matière //
/////////////////////////
elseif ( ( $action == 'ajout-matiere' ) && isset($_REQUEST['nom']) && isset($_REQUEST['cle']) && isset($_REQUEST['colles_protection']) && isset($_REQUEST['cdt_protection']) && isset($_REQUEST['docs_protection']) && isset($_REQUEST['notes']) && isset($_REQUEST['dureecolle']) )  {
  $nom = strip_tags(trim($mysqli->real_escape_string($_REQUEST['nom'])));
  $nom = mb_strtoupper(mb_substr($nom,0,1)).mb_substr($nom,1);
  $cle = str_replace(' ','_',strip_tags(trim($mysqli->real_escape_string($_REQUEST['cle']))));
  if ( !strlen($nom) || !strlen($cle) )
    exit('{"etat":"nok","message":"La matière n\'a pas été ajoutée. Le nom et la clé doivent être non vides."}');
  // Vérification que la clé n'existe pas déjà
  $resultat = $mysqli->query('SELECT cle FROM matieres');
  while ( $r = $resultat->fetch_row() )
    if ( $r[0] == $cle )
      exit("{\"etat\":\"nok\",\"message\":\"La matière <em>$nom</em> n'a pas été ajoutée. La clé donnée existe déjà. Elle doit être différente de celles des autres matières.\"}");
  $resultat->free();
  // Génération des valeurs de protection
  $protection = array();
  foreach ( array('colles','cdt','docs') as $fonction )  {
    $val = array_filter($_REQUEST[$fonction.'_protection'],function($id) { return ctype_digit($id); });
    if ( !count($val) )
      exit("{\"etat\":\"nok\",\"message\":\"La matière <em>$nom</em> n'a pas été ajoutée. Une des protections d'accès est incorrecte.\"}");
    if ( ( $val[0] == 0 ) || ( $val[0] == 32 ) )
      $protection[$fonction] = $val[0];
    else  {
      $p = 32;
      foreach (array_slice($val,1) as $v) 
        $p = $p-2**($v-1);
      $protection[$fonction] = $p;
    }
  }
  // Affichage des notes : 2-> désactivée ; 1-> possible (0 pour l'instant dans la base)
  $notes = ( $_REQUEST['notes'] == 2 ) ? '2' : '0';
  $dureecolle = intval($_REQUEST['dureecolle']) ?: 20;
  // Écriture
  if ( requete('matieres',"INSERT INTO matieres SET nom = '$nom', cle = '$cle', notes = $notes, colles_protection = ${protection['colles']}, cdt_protection = ${protection['cdt']}, docs_protection = ${protection['docs']}, dureecolle = $dureecolle, ordre = (SELECT MAX(ordre)+1 FROM matieres AS m)",$mysqli) )  {
    $id = $mysqli->insert_id;
    requete('reps',"INSERT INTO reps SET parent = 0, parents = '0', nom = '$nom', matiere = $id",$mysqli);
    requete('cdt-types',"INSERT INTO `cdt-types` (matiere, ordre, cle, titre, deb_fin_pour) VALUES
                         ($id, 1, 'cours', 'Cours', 1),
                         ($id, 2, 'TD', 'Séance de travaux dirigés', 1),
                         ($id, 3, 'TP', 'Séance de travaux pratiques', 1),
                         ($id, 4, 'DS', 'Devoir surveillé', 1),
                         ($id, 5, 'interros', 'Interrogation de cours', 0),
                         ($id, 6, 'distributions', 'Distribution de document', 0),
                         ($id, 7, 'DM', 'Devoir maison', 2)",$mysqli);
    exit("{\"etat\":\"ok\",\"message\":\"La matière <em>$nom</em> a été ajoutée.\"}");
  }
  exit("{\"etat\":\"nok\",\"message\":\"La matière <em>$nom</em> n'a pas été ajoutée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
}

//////////////////////////////////////////
// Modification d'un utilisateur unique //
//////////////////////////////////////////
elseif ( ( $action == 'utilisateur' ) && isset($_REQUEST['modif']) && in_array($modif = $_REQUEST['modif'],array('prefs','desactive','active','supprutilisateur','validutilisateur')) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {

  // Vérification que l'identifiant est valide
  // Attention, les valeurs "valide", "demande" et "invitation" sont des chaines de caractères égales à '0' ou '1'.
  $resultat = $mysqli->query("SELECT nom, prenom, login, matieres, mail, (LENGTH(mdp)=40) AS valide, (LEFT(mdp,1)='*') AS demande, (LENGTH(mdp)=1) AS invitation, autorisation, mailexp, mailcopie FROM utilisateurs WHERE id = $id");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant non valide"}');  
  $r = $resultat->fetch_assoc();
  $resultat->free();
  $compte = ( $r['nom'].$r['prenom'] ) ? "de <em>${r['prenom']} ${r['nom']}</em>" : "<em>${r['login']}</em>";
  switch ( $modif )  {

    // Modification des données du compte (venant du formulaire de utilisateurs.php)
    case 'prefs': {
      // Fonction de fabrication de la partie modifiante de la requête
      function fabriqueupdate($requete,$mysqli)  {
        $chaine = '';
        foreach ($requete as $champ=>$val)
          $chaine .= ",$champ = '".$mysqli->real_escape_string($val).'\'';
        return substr($chaine,1);
      }
      // Valeurs à modifier
      $requete = array_diff_assoc( array( 'nom'=>trim($_REQUEST['nom']), 'prenom'=>trim($_REQUEST['prenom']), 'login'=>trim($_REQUEST['login']), 'mail'=>mb_strtolower(trim($_REQUEST['mail1'])), 'mailexp'=>(trim($_REQUEST['mailexp'] ?? '')), 'mailcopie'=>intval(isset($_REQUEST['mailcopie'])) ), $r);
      if ( isset($requete['nom']) && !($nom = mb_convert_case(strip_tags($requete['nom']),MB_CASE_TITLE)) )
        unset($requete['nom']);
      if ( isset($requete['prenom']) && !($prenom = mb_convert_case(strip_tags($requete['prenom']),MB_CASE_TITLE)) )
        unset($requete['prenom']);
      if ( isset($requete['login']) )  {
        if ( !($login = mb_strtolower(str_replace(' ','_',$requete['login']))) )
          unset($requete['login']);
        else  {
          // Vérification que le login n'existe pas déjà
          $resultat = $mysqli->query('SELECT id FROM utilisateurs WHERE login = \''.$mysqli->real_escape_string($login)."' AND id != $id");
          if ( $resultat->num_rows )  {
            $resultat->free();
            unset($requete['login']);
          }
        }
      }
      if ( isset($requete['mail']) )  {
        // Vérification de l'adresse (écriture, confirmation, absence de la base)
        if ( !filter_var($mail = $requete['mail'],FILTER_VALIDATE_EMAIL) || !isset($_REQUEST['mail2']) )
          unset($requete['mail']);
        elseif ( $mail != mb_strtolower(trim($_REQUEST['mail2'])) )
          exit('{"etat":"nok","message":"Les préférences '.$compte.' n\'ont pas été modifiées, les deux adresses électroniques saisies ne sont pas identiques."}');
        elseif ( ( $resultat = $mysqli->query('SELECT id FROM utilisateurs WHERE mail = \''.$mysqli->real_escape_string($mail)."' AND id != $id") ) && $resultat->num_rows )  {
          $resultat->free();
          unset($requete['mail']);
        }
      }
      if ( isset($requete['mailexp']) && !($mailexp = strip_tags($requete['mailexp'])) )
        unset($requete['mailexp']);
      if ( !$requete )
        exit($_SESSION['message'] = '{"etat":"ok","message":"Les valeurs fournies étaient celles déjà enregistrées. Aucune modification n\'a été effectuée."}');
      if( requete('utilisateurs','UPDATE utilisateurs SET '.fabriqueupdate($requete,$mysqli)." WHERE id = $id",$mysqli) )  {
        // Si interface globale activée, mise à jour
        if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
          include("${interfaceglobale}majutilisateurs.php");
          majutilisateurs($id,$requete);
        }
        exit($_SESSION['message'] = '{"etat":"ok","message":"Les préférences '.$compte.' ont été modifiées."}');
      }
      exit('{"etat":"nok","message":"Les préférences '.$compte.' n\'ont pas été modifiées. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Désactivation
    case 'desactive': {
      if ( ( $r['valide'] == 0 ) && ( $r['demande'] == 0 ) && ( $r['invitation'] == 0 ) )
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le compte $compte est déjà actuellement désactivé.\"}");
      if ( ( $r['demande'] == 1 ) || ( $r['invitation'] == 1 ) )
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le compte $compte n'est pas actuellement désactivable.\"}");
      if ( requete('utilisateurs',"UPDATE utilisateurs SET mdp = CONCAT('!',mdp) WHERE id = $id",$mysqli) )  {
        // Si interface globale activée, mise à jour
        if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
          include("${interfaceglobale}majutilisateurs.php");
          majutilisateurs($id,'désactivation');
        }
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Compte $compte désactivé\"}");
      }
      exit("{\"etat\":\"nok\",\"message\":\"La désactivation du compte $compte n'a pas été réalisée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Réactivation
    case 'active': {
      if ( $r['valide'] == 1 )
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le compte $compte est déjà actuellement activé.\"}");
      if ( ( $r['demande'] == 1 ) || ( $r['invitation'] == 1 ) )
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Le compte $compte n'est pas actuellement activable.\"}");
      if ( requete('utilisateurs',"UPDATE utilisateurs SET mdp = SUBSTR(mdp,2) WHERE id = $id",$mysqli) )  {
        // Si interface globale activée, mise à jour
        if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
          include("${interfaceglobale}majutilisateurs.php");
          majutilisateurs($id,'activation');
        }
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Compte $compte réactivé\"}");
      }
      exit("{\"etat\":\"nok\",\"message\":\"La réactivation du compte $compte n'a pas été réalisée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Suppression
    case 'supprutilisateur': {
      if ( requete('utilisateurs',"DELETE FROM utilisateurs WHERE id = $id",$mysqli)
        && requete('groupes',"UPDATE groupes SET utilisateurs = TRIM(BOTH ',' FROM REPLACE(CONCAT(',',utilisateurs,','),',$id,',',')) WHERE FIND_IN_SET($id,utilisateurs)",$mysqli) )  {
        // Recherche des notes : cas des colleurs/profs
        if ( $r['autorisation'] > 2 )  {
          requete('notes',"DELETE FROM notes WHERE colleur = $id",$mysqli);
          requete('heurescolles',"DELETE FROM heurescolles WHERE colleur = $id",$mysqli);
        }
        // Recherche des notes : cas des élèves
        elseif ( $r['autorisation'] == 2 )  {
          $resultat = $mysqli->query("SELECT GROUP_CONCAT(heure) FROM notes WHERE eleve = $id");
          $s = $resultat->fetch_row();
          $resultat->free();
          if ( !is_null($s[0]) )  {
            requete('notes',"DELETE FROM notes WHERE eleve = $id",$mysqli);
            requete('heurescolles',"DELETE FROM heurescolles LEFT JOIN notes ON heurescolles.id=notes.heure
                                    WHERE FIND_IN_SET(heurescolles.id,'${s[0]}') AND notes.id IS NULL",$mysqli);
            requete('heurescolles',"UPDATE heurescolles SET duree = duree-(SELECT dureecolle FROM matieres WHERE matieres.id = heurescolles.matiere) 
                                    WHERE FIND_IN_SET(heurescolles.id,'${s[0]}') AND releve = 0",$mysqli);
          }
        }
        // Si interface globale activée, mise à jour
        if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
          include("${interfaceglobale}majutilisateurs.php");
          majutilisateurs($id,'suppression');
        }
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Compte $compte supprimé\"}");
      }
      exit("{\"etat\":\"nok\",\"message\":\"La suppression du compte $compte n'a pas été réalisée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Validation d'une demande
    case 'validutilisateur': {
      if ( $r['demande'] == 0 )
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"La demande $compte a déjà été validée.\"}");
      if ( requete('utilisateurs',"UPDATE utilisateurs SET mdp = SUBSTR(mdp,2) WHERE id = $id",$mysqli) )  {
        mail($r['mail'],'=?UTF-8?B?'.base64_encode('[Cahier de Prépa] Compte validé').'?=',
"Bonjour

Vous avez rempli une demande de création de compte sur le Cahier de Prépa <https://$domaine$chemin>, correspondant à l'identifiant ${r['login']}.

Cette demande vient de recevoir une réponse favorable de la part de l'équipe pédagogique en charge du site. Vous pouvez donc désormais vous connecter avec votre identifiant et votre mot de passe.

Bonne navigation sur Cahier de Prépa.

Cordialement,
-- 
Cahier de Prépa
",'From: =?UTF-8?B?'.base64_encode('Cahier de Prépa')."?= <$mailadmin>\r\nContent-type: text/plain; charset=UTF-8","-f$mailadmin");
        // Si interface globale activée, mise à jour
        if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
          include("${interfaceglobale}majutilisateurs.php");
          majutilisateurs($id,'activation');
        }
        exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"Demande de compte $compte accordée. L'élève a été prévenu par courriel.\"}");
      }
      exit("{\"etat\":\"nok\",\"message\":\"La validation du compte $compte n'a pas été réalisée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    }
  }
}

//////////////////////////////////////////
// Modification multiple d'utilisateurs // 
//////////////////////////////////////////
elseif ( ( $action == 'utilisateurs' ) && isset($_REQUEST['modif']) && in_array($modif = $_REQUEST['modif'],array('desactive','active','supprutilisateur','validutilisateur')) && isset($_REQUEST['ids']) && strlen($ids = implode(',',array_filter(explode(',',$_REQUEST['ids']),function($id) { return ctype_digit($id); }))) )  {

  // Vérification que les identifiants sont valides
  // Attention, les valeurs "valide", "demande" et "invitation" sont des chaines de caractères égales à '0' ou '1'.
  $resultat = $mysqli->query("SELECT id, nom, prenom, login, mail, (LENGTH(mdp)=40) AS valide, (LEFT(mdp,1)='*') AS demande, (LENGTH(mdp)=1) AS invitation, autorisation, mailexp, mailcopie FROM utilisateurs WHERE FIND_IN_SET(id,'$ids')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiants non valides"}');
  $message = array('ok'=>'','nok'=>'');
  switch ( $modif )  {

    // Désactivation
    case 'desactive': {
      while ( $r = $resultat->fetch_assoc() )  {
        $compte = ( strlen($r['nom'].$r['prenom']) ) ? "de <em>${r['prenom']} ${r['nom']}</em>" : "<em>${r['login']}</em>";
        if ( ( $r['demande'] == 1 ) || ( $r['invitation'] == 1 ) || ( $r['valide'] == 0 ) )
          $message['nok'] .= "Le compte $compte n'a pas été désactivé, car il l'est déjà ou ne peut pas l'être.<br>";
        elseif ( requete('utilisateurs',"UPDATE utilisateurs SET mdp = CONCAT('!',mdp) WHERE id = ${r['id']}",$mysqli) )  {
          // Si interface globale activée, mise à jour
          if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
            include_once("${interfaceglobale}majutilisateurs.php");
            majutilisateurs($r['id'],'désactivation');
          }
          $message['ok'] .= "Le compte $compte a été désactivé.<br>";
        }
        else
          $message['nok'] .= "Le compte $compte n'a pas été désactivé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»<br>';
      }
      break;
    }

    // Réactivation
    case 'active': {
      while ( $r = $resultat->fetch_assoc() )  {
        $compte = ( $r['nom'].$r['prenom'] ) ? "de <em>${r['prenom']} ${r['nom']}</em>" : "<em>${r['login']}</em>";
        if ( ( $r['demande'] == 1 ) || ( $r['invitation'] == 1 ) || ( $r['valide'] == 1 ) )
          $message['nok'] .= "Le compte $compte n'a pas été activé, car il l'est déjà ou ne peut pas l'être.<br>";
        elseif ( requete('utilisateurs',"UPDATE utilisateurs SET mdp = SUBSTR(mdp,2) WHERE id = ${r['id']}",$mysqli) )  {
          // Si interface globale activée, mise à jour
          if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
            include_once("${interfaceglobale}majutilisateurs.php");
            majutilisateurs($r['id'],'activation');
          }
          $message['ok'] .= "Le compte $compte a été réactivé.<br>";
        }
        else
          $message['nok'] .= "Le compte $compte n'a pas été réactivé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»<br>';
      }
      break;
    }

    // Suppression
    case 'supprutilisateur': {
      while ( $r = $resultat->fetch_assoc() )  {
        $compte = ( strlen($r['nom'].$r['prenom']) ) ? "de <em>${r['prenom']} ${r['nom']}</em>" : "<em>${r['login']}</em>";
        if ( requete('utilisateurs',"DELETE FROM utilisateurs WHERE id = ${r['id']}",$mysqli)
          && requete('groupes',"UPDATE groupes SET utilisateurs = TRIM(BOTH ',' FROM REPLACE(CONCAT(',',utilisateurs,','),',${r['id']},',',')) WHERE FIND_IN_SET(${r['id']},utilisateurs)",$mysqli) )  {
          // Si interface globale activée, mise à jour
          if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
            include_once("${interfaceglobale}majutilisateurs.php");
            majutilisateurs($r['id'],'suppression');
          }
          // Recherche des notes : cas des colleurs/profs
          if ( $r['autorisation'] > 2 )  {
            requete('notes',"DELETE FROM notes WHERE colleur = ${r['id']}",$mysqli);
            requete('heurescolles',"DELETE FROM heurescolles WHERE colleur = ${r['id']}",$mysqli);
          }
          // Recherche des notes : cas des élèves
          elseif ( $r['autorisation'] == 2 )  {
            $resultat2 = $mysqli->query("SELECT GROUP_CONCAT(heure) FROM notes WHERE eleve = ${r['id']}");
            $s = $resultat2->fetch_row();
            $resultat2->free();
            if ( !is_null($s[0]) )  {
              requete('notes',"DELETE FROM notes WHERE eleve = ${r['id']}",$mysqli);
              requete('heurescolles',"UPDATE heurescolles SET duree = duree-(SELECT dureecolle FROM matieres WHERE matieres.id = heurescolles.matiere) 
                                      WHERE FIND_IN_SET(heurescolles.id,'${s[0]}') AND releve = 0",$mysqli);
            }
          }
          $message['ok'] .= "Le compte $compte a été supprimé.<br>";
        }
        else
          $message['nok'] .= "Le compte $compte n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»<br>';
      }
      // Nettoyage des groupes et des heures de colles
      requete('heurescolles',"DELETE FROM heurescolles LEFT JOIN notes ON heurescolles.id=notes.heure WHERE notes.id IS NULL",$mysqli);
      requete('groupes',"DELETE FROM groupes WHERE utilisateurs = ''",$mysqli);
      break;
    }

    // Validation d'une demande
    case 'validutilisateur': {
      while ( $r = $resultat->fetch_assoc() )  {
        $compte = ( strlen($r['nom'].$r['prenom']) ) ? "de <em>${r['prenom']} ${r['nom']}</em>" : "<em>${r['login']}</em>";
        if ( $r['demande'] == 0 )
          $message['nok'] .= "La demande $compte a déjà été validée.<br>";
        elseif ( requete('utilisateurs',"UPDATE utilisateurs SET mdp = SUBSTR(mdp,2) WHERE id = ${r['id']}",$mysqli) )  {
          // Si interface globale activée, mise à jour
          if ( ( $r['autorisation'] > 1 ) && $interfaceglobale )  {
            include_once("${interfaceglobale}majutilisateurs.php");
            majutilisateurs($r['id'],'activation');
          }
          // Envoi de courriel de confirmation
          mail($r['mail'],'=?UTF-8?B?'.base64_encode('[Cahier de Prépa] Compte validé').'?=',
"Bonjour

Vous avez rempli une demande de création de compte sur le Cahier de Prépa <https://$domaine$chemin>, correspondant à l'identifiant ${r['login']}.

Cette demande vient de recevoir une réponse favorable de la part de l'équipe pédagogique en charge du site. Vous pouvez donc désormais vous connecter avec votre identifiant et votre mot de passe.

Bonne navigation sur Cahier de Prépa.

Cordialement,
-- 
Cahier de Prépa
",'From: =?UTF-8?B?'.base64_encode('Cahier de Prépa')."?= <$mailadmin>\r\nContent-type: text/plain; charset=UTF-8","-f$mailadmin");
          $message['ok'] .= "La demande de compte $compte a été accordée. L'élève a été prévenu par courriel.";
        }
        else
          $message['nok'] .= "La validation du compte $compte n'a pas été réalisée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'».<br>';
      }
    }

  }
  $resultat->free();
  if ( strlen($message['ok']) )
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"${message['ok']}${message['nok']}\"}");
  exit("{\"etat\":\"nok\",\"message\":\"${message['nok']}\"}");
}

////////////////////////////////////
// Ajout de nouveaux utilisateurs //
////////////////////////////////////
elseif ( ( $action == 'ajout-utilisateurs' ) && isset($_REQUEST['listeutilisateurs']) && isset($_REQUEST['autorisation']) && in_array($autorisation = intval($_REQUEST['autorisation']),array(1,2,3,4,5)) && isset($_REQUEST['saisie']) && isset($_REQUEST['matieres']) && count($matieres = array_filter($_REQUEST['matieres'],function($id) { return ctype_digit($id); })) )  {
  
  // Vérification des matières -- on ne garde que les identifiants existants,
  // et on prend silencieusement par défaut l'ensemble des matières
  $resultat = $mysqli->query('SELECT GROUP_CONCAT(id) AS matieres FROM matieres');
  $r = $resultat->fetch_row();
  $resultat->free();
  $matieres = '0,'.implode(',', array_intersect($_REQUEST['matieres'],explode(',',$r[0])) ?: explode(',',$r[0]) );

  // Récupération des lignes
  $utilisateurs = explode("\n",$_REQUEST['listeutilisateurs']);
  
  // Compteurs : $n nb de comptes ajoutés; $i compteur de ligne traitée
  $n = $i = 0;
  $message = '';
  // Comptes invités : login,mdp
  if ( $autorisation == 1 )
    foreach ( $utilisateurs as $utilisateur)  {
      if ( !strlen(trim($utilisateur)) )
        continue;
      $u = array_map('trim',explode(',',$utilisateur));
      $i = $i+1;
      if ( ( count($u) != 2 ) || !strlen($u[0]) || !strlen($u[1]) )
        $message .= "<br>Ligne $i : mauvais paramètres";
      elseif ( ( $resultat = $mysqli->query('SELECT id FROM utilisateurs WHERE login = \''.($login = $mysqli->real_escape_string(mb_strtolower(str_replace(' ','_',$u[0])))).'\'') ) && $resultat->num_rows ) 
        $message .= "<br>Ligne $i : identifiant <strong>$login</strong> déjà existant";
      elseif ( requete('utilisateurs',"INSERT INTO utilisateurs SET login = '$login', mdp = '".sha1($mdp.$u[1])."', autorisation = 1, matieres = '$matieres', timeout=900",$mysqli) )  {
        $message .= "<br>Ligne $i : ok (identifiant <strong>$login</strong>)";
        $n = $n+1;
      }
      else
        $message .= "<br>Ligne $i : erreur MySQL n°".$mysqli->errno.' «'.$mysqli->error.'»';
    }
  // Autres comptes : nom,prenom,mail ou nom,prenom,mdp
  else
    foreach ( $utilisateurs as $utilisateur)  {
      if ( !strlen(trim($utilisateur)) )
        continue;
      $u = array_map('trim',explode(',',$utilisateur));
      $i = $i+1;
      // Nettoyage des données envoyées
      if ( ( count($u) != 3 ) || !strlen($u[0].$u[1]) || !strlen($u[2]) )
        $message .= "<br>Ligne $i : mauvais paramètres";
      else  {
        $nom = mb_convert_case(strip_tags($mysqli->real_escape_string($u[0])),MB_CASE_TITLE);
        $prenom = mb_convert_case(strip_tags($mysqli->real_escape_string($u[1])),MB_CASE_TITLE);
        $login = mb_strtolower(mb_substr($prenom,0,1).str_replace(' ','_',$nom));
        if ( ( $resultat = $mysqli->query("SELECT id FROM utilisateurs WHERE login = '$login'") ) && $resultat->num_rows )  {
          $resultat->free();
          $message .= "<br>Ligne $i : identifiant <strong>$login</strong> déjà existant";
        }
        // Si nom,prenom,mdp
        elseif ( $_REQUEST['saisie'] == 2 )  {
          if ( requete('utilisateurs',"INSERT INTO utilisateurs SET login = '$login', prenom = '$prenom', nom = '$nom', mdp = '".sha1($mdp.$u[2])."', autorisation = $autorisation, matieres = '$matieres', timeout = 900",$mysqli) )  {
            $message .= "<br>Ligne $i : ok (<strong>$prenom $nom</strong>, identifiant $login)";
            $n = $n+1;
            // Si interface globale activée, mise à jour
            if ( $interfaceglobale )  {
              include_once("${interfaceglobale}majutilisateurs.php");
              majutilisateurs($mysqli->insert_id,"INSERT INTO utilisateurs SET login = '$login', prenom = '$prenom', nom = '$nom', mail = '', mdp = '".sha1($mdp.$u[2])."', autorisation = $autorisation");
            }
          }
          else
            $message .= "<br>Ligne $i : erreur MySQL n°".$mysqli->errno.' «'.$mysqli->error.'»';
        }
        // Si nom,prenom,mail
        else  {
          // Vérification de l'adresse mail (écriture et absence dans la base)
          if ( !$mysqli->real_escape_string(filter_var($mail = mb_strtolower($u[2]),FILTER_VALIDATE_EMAIL)) )
            $message .= "<br>Ligne $i : adresse électronique non valide (<strong>$prenom $nom</strong>)";
          elseif ( ( $resultat = $mysqli->query("SELECT id FROM utilisateurs WHERE mail = '$mail'") ) && $resultat->num_rows )  {
            $resultat->free();
            $message .= "<br>Ligne $i : adresse électronique déjà existante (<strong>$prenom $nom</strong>)";
          }
          elseif ( requete('utilisateurs',"INSERT INTO utilisateurs SET login = '$login', prenom = '$prenom', nom = '$nom', mail = '$mail', mdp = '?', autorisation = $autorisation, matieres = '$matieres', timeout = 900, mailexp = '$prenom $nom'",$mysqli) )  {
            $message .= "<br>Ligne $i : ok (<strong>$prenom $nom</strong>, identifiant $login)";
            $n = $n+1;
            // Si interface globale activée, mise à jour
            if ( $interfaceglobale )  {
              include_once("${interfaceglobale}majutilisateurs.php");
              majutilisateurs($mysqli->insert_id,"INSERT INTO utilisateurs SET login = '$login', prenom = '$prenom', nom = '$nom', mail = '$mail', mdp = '?', autorisation = $autorisation");
            }
            // Récupération de l'adresse électronique du professeur connecté
            $resultat = $mysqli->query("SELECT mail FROM utilisateurs WHERE id = ${_SESSION['id']}");
            $s = $resultat->fetch_row();
            $resultat->free();
            $returnpath = $s[0] ?: $mailadmin;
            $lien = "https://$domaine${chemin}gestioncompte?invitation&mail=".str_replace('@','__',$mail).'&p='.sha1($chemin.$mdp.$mail);
            mail($mail,'=?UTF-8?B?'.base64_encode('[Cahier de Prépa] Invitation').'?=',
"Bonjour

L'équipe pédagogique en charge du Cahier de Prépa <https://$domaine$chemin> vous invite à les rejoindre.

S'il s'agit d'une erreur, merci d'ignorer simplement ce courriel.

Sinon, veuillez cliquer ci-dessous pour vous rendre à la page qui vous permettra d'entrer un mot de passe :
   $lien

Si ce lien ne s'ouvre pas correctement, il a peut-être été coupé lors du clic : dans ce cas, essayez à nouveau en copiant-collant le lien.

Bonne navigation sur Cahier de Prépa.

Cordialement,
-- 
Cahier de Prépa
",'From: =?UTF-8?B?'.base64_encode('Cahier de Prépa')."?= <$mailadmin>\r\nContent-type: text/plain; charset=UTF-8","-f$returnpath");
          }
          else
            $message .= "<br>Ligne $i : erreur MySQL n°".$mysqli->errno.' «'.$mysqli->error.'»';
        }
      }
    }
  // Fabrication du message
  $nouveaucompte = ( $n > 1 ) ? 'nouveaux comptes' : 'nouveau compte';
  if ( $e = $i-$n )
    exit("{\"etat\":\"nok\",\"message\":\"<strong>$n $nouveaucompte et $e erreur".($e>1?'s':'').'</strong>'.stripslashes($message).'"}');
  exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"<strong>$n $nouveaucompte</strong>".stripslashes($message).'"}');
}

///////////////////////////////////////////////////////////////
// Modification d'une association utilisateur-matière unique //
///////////////////////////////////////////////////////////////
elseif ( ( $action == 'utilisateur-matiere' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) && isset($_REQUEST['matiere']) && ctype_digit($mid = $_REQUEST['matiere']) && isset($_REQUEST['val']) )  {

  // Vérification que l'identifiant de la matière est valide
  $resultat = $mysqli->query("SELECT nom FROM matieres WHERE id = $mid");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de matière non valide"}');
  $r = $resultat->fetch_row();
  $resultat->free();
  $matiere = $r[0];
  
  // Vérification que l'identifiant de l'utilisateur est valide
  $resultat = $mysqli->query("SELECT IF(nom>'',CONCAT(prenom,' ',nom),CONCAT('<em>',login,'</em>')) AS nom, FIND_IN_SET('$mid',matieres)>0 AS matiere, LOCATE(',',matieres,3) AS multi FROM utilisateurs WHERE id = $id");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant d\'utilisateur non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  
  // Modification
  $val = intval( $_REQUEST['val'] > 0 );
  // Si rien à faire
  if ( $r['matiere'] == $val )
    exit('{"etat":"nok","message":"Ce réglage était déjà celui en place. Aucune modification n\'a été effectuée."}');
  // Si une seule matière à retirer : impossible
  if ( !$val && ( $r['multi'] == 0 ) )
    $message = "{\"etat\":\"nok\",\"message\":\"L'utilisateur ${r['nom']} n'est associé qu'à une seule matière. Il est impossible de supprimer cette association.\"}";
  // Association
  elseif ( $val && requete('utilisateurs',"UPDATE utilisateurs SET matieres = CONCAT(matieres,',$mid') WHERE id = $id",$mysqli) )
    $message = "{\"etat\":\"ok\",\"message\":\"L'utilisateur ${r['nom']} a été associé à la matière $matiere.\"}";
  // Désassociation
  elseif ( !$val && requete('utilisateurs',"UPDATE utilisateurs SET matieres = TRIM(TRAILING ',' FROM REPLACE(CONCAT(matieres,','),',$mid,',',')) WHERE id = $id",$mysqli) )
    $message = "{\"etat\":\"ok\",\"message\":\"L'association de l'utilisateur ${r['nom']} à la matière $matiere a été supprimée.\"}";
  else
    exit("{\"etat\":\"nok\",\"message\":\"L'association de l'utilisateur ${r['nom']} à la matière $matiere n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  // Mise à jour de $_SESSION['matieres'] si besoin
  if ( $id == $_SESSION['id'] )  {
    $resultat = $mysqli->query("SELECT matieres FROM utilisateurs WHERE id = $id");
    $r = $resultat->fetch_row();
    $resultat->free();
    $_SESSION['matieres'] = $r[0];
  }
  exit($message);

}

////////////////////////////////////////////////////////////////
// Modification multiple d'associations utilisateurs-matières //
////////////////////////////////////////////////////////////////
elseif ( ( $action == 'utilisateurs-matieres' ) && isset($_REQUEST['ids']) && strlen($ids = implode(',',array_filter(explode(',',$_REQUEST['ids']),function($id) { return ctype_digit($id); }))) && isset($_REQUEST['matiere']) && ctype_digit($mid = $_REQUEST['matiere']) && isset($_REQUEST['val']) )  {

  // Vérification que l'identifiant de la matière est valide
  $resultat = $mysqli->query("SELECT nom FROM matieres WHERE id = $mid");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de matière non valide"}');
  $r = $resultat->fetch_row();
  $resultat->free();
  $matiere = $r[0];
  
  // Vérification que les identifiants d'utilisateur sont valides
  $resultat = $mysqli->query("SELECT id, nom, prenom, login, FIND_IN_SET('$mid',matieres)>0 AS matiere, LOCATE(',',matieres,3) AS multi FROM utilisateurs WHERE FIND_IN_SET(id,'$ids')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiants d\'utilisateur non valides"}');  
  $message = array('ok'=>'','nok'=>'');
    
  // Modification
  $val = intval( $_REQUEST['val'] > 0 );
  while ( $r = $resultat->fetch_assoc() )  {
    $compte = ( strlen($r['nom'].$r['prenom']) ) ? "de <em>${r['prenom']} ${r['nom']}</em>" : "<em>${r['login']}</em>";
    // Si rien à faire
    if ( $r['matiere'] == $val )
      $message['nok'] .= "Le compte $compte n'a pas été modifié, car son association à la matière <em>$matiere</em> est déjà celle demandée.<br>";
    // Si une seule matière à retirer : impossible
    elseif ( !$val && ( $r['multi'] == 0 ) )
      $message['nok'] .= "Le compte $compte n'est associé qu'à une seule matière. Il est impossible de supprimer cette association.<br>";
    // Association
    elseif ( $val && requete('utilisateurs',"UPDATE utilisateurs SET matieres = CONCAT(matieres,',$mid') WHERE id = ${r['id']}",$mysqli) )
      $message['ok'] .= "Le compte $compte a été associé à la matière <em>$matiere</em>.<br>";
    // Désassociation
    elseif ( !$val && requete('utilisateurs',"UPDATE utilisateurs SET matieres = TRIM(TRAILING ',' FROM REPLACE(CONCAT(matieres,','),',$mid,',',')) WHERE id = ${r['id']}",$mysqli) )
      $message['ok'] .= "L'association du compte $compte à la matière <em>$matiere</em> a été supprimée.<br>";
    else
      $message['nok'] .= "L'association de l'utilisateur ${r['nom']} à la matière $matiere n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'».<br>';
    // Mise à jour de $_SESSION['matieres'] si besoin
    if ( $r['id'] == $_SESSION['id'] )  {
      $res = $mysqli->query("SELECT matieres FROM utilisateurs WHERE id = ${r['id']}");
      $r = $res->fetch_row();
      $res->free();
      $_SESSION['matieres'] = $r[0];
    }
  }
  $resultat->free();
  if ( strlen($message['ok']) )
    exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"${message['ok']}${message['nok']}\"}");
  exit("{\"etat\":\"nok\",\"message\":\"${message['nok']}\"}");
}

/////////////////////////////////////////////
// Modification des groupes d'utilisateurs //
/////////////////////////////////////////////
elseif ( ( $action == 'groupes' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {

  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT nom, mails, notes, utilisateurs FROM groupes WHERE id = $id");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Suppression
  if ( isset($_REQUEST['supprime']) )  {
    if ( requete('groupes',"DELETE FROM groupes WHERE id = $id",$mysqli) ) 
      exit("{\"etat\":\"ok\",\"message\":\"Le groupe ${r['nom']} a été supprimé.\"}");
    exit("{\"etat\":\"nok\",\"message\":\"Le groupe ${r['nom']} n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Modification
  if ( isset($_REQUEST['champ']) )  {
    switch ( $champ = $_REQUEST['champ'] )  {
      case 'nom':
        if ( !strlen($val = trim($mysqli->real_escape_string($_REQUEST['val']))) )
          exit("{\"etat\":\"nok\",\"message\":\"Le nom du groupe ${r['nom']} n'a pas été modifié&nbsp;: le nom ne peut pas être vide.\"}");
        if ( requete('groupes',"UPDATE groupes SET nom = '$val', nom_nat = '".zpad($val)."' WHERE id = $id",$mysqli) )  {
          $mysqli->query('ALTER TABLE groupes ORDER BY nom_nat');
          exit("{\"etat\":\"ok\",\"message\":\"Le nom du groupe ${r['nom']} a été modifié.\"}");
        }
        exit("{\"etat\":\"nok\",\"message\":\"Le nom du groupe ${r['nom']} n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      case 'mails':
      case 'notes':
        $val = intval( $_REQUEST['val'] > 0 );
        if ( requete('groupes',"UPDATE groupes SET $champ = $val WHERE id = $id",$mysqli) )
          exit("{\"etat\":\"ok\",\"message\":\"Le groupe ${r['nom']} a été modifié.\"}");
        exit("{\"etat\":\"nok\",\"message\":\"Le groupe ${r['nom']} n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
      case 'utilisateurs':
        if ( !isset($_REQUEST['uids']) || !strlen($ids = implode(',',array_filter(explode(',',$_REQUEST['uids']),function($id) { return ctype_digit($id); }))) )
          exit("{\"etat\":\"nok\",\"message\":\"La composition du groupe ${r['nom']} n'a pas été modifiée&nbsp;: le groupe ne peut pas être vide.\"}");
        // Vérification des identifiants d'utilisateurs - tous utilisateurs autorisés
        $resultat = $mysqli->query("SELECT GROUP_CONCAT(id) FROM utilisateurs WHERE FIND_IN_SET(id,'$ids')");
        $s = $resultat->fetch_row();
        $resultat->free();
        if ( !$s[0] )
          exit("{\"etat\":\"nok\",\"message\":\"La composition du groupe ${r['nom']} n'a pas été modifiée&nbsp;: le groupe ne peut pas être vide.\"}");
        if ( requete('groupes',"UPDATE groupes SET utilisateurs = '${s[0]}' WHERE id = $id",$mysqli) )
          exit("{\"etat\":\"ok\",\"message\":\"La composition du groupe ${r['nom']} a été modifié.\"}");
        exit("{\"etat\":\"nok\",\"message\":\"La composition du groupe ${r['nom']} n'a pas été modifié. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
    }
  }
  exit('{"etat":"nok","message":"Champ non valide"}');
}

//////////////////////////////////////
// Ajout d'un groupe d'utilisateurs //
//////////////////////////////////////
elseif ( ( $action == 'ajout-groupe' ) && isset($_REQUEST['nom']) && strlen($nom = trim($mysqli->real_escape_string($_REQUEST['nom']))) && isset($_REQUEST['uids']) && strlen($ids = implode(',',array_filter(explode(',',$_REQUEST['uids']),function($id) { return ctype_digit($id); })))  )  {

  // Vérification des identifiants d'utilisateurs - tous utilisateurs autorisés
  $resultat = $mysqli->query("SELECT GROUP_CONCAT(id) FROM utilisateurs WHERE FIND_IN_SET(id,'$ids')");
  $r = $resultat->fetch_row();
  $resultat->free();
  if ( !$r[0] )
    exit('{"etat":"nok","message":"Un groupe ne peut pas être vide."}');
  // Champs mails et notes
  $mails = intval(isset($_REQUEST['mails']));
  $notes = intval(isset($_REQUEST['notes']));
  // Écriture
  if ( requete('groupes',"INSERT INTO groupes SET nom = '$nom', nom_nat = '".zpad($nom)."', mails = $mails, notes = $notes, utilisateurs = '${r[0]}'",$mysqli) )  {
    $mysqli->query('ALTER TABLE groupes ORDER BY nom_nat');
    exit($_SESSION['message'] = '{"etat":"ok","message":"Le groupe a été ajouté."}');
  }
  exit('{"etat":"nok","message":"Le groupe n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

//////////////////////////////
// Modification du planning //
//////////////////////////////
elseif ( $action == 'planning' )  {

  // Récupérations des donnees envoyées
  $colles = ( isset($_REQUEST['colles']) ) ? $_REQUEST['colles'] : array();
  $vacances = ( isset($_REQUEST['vacances']) ) ? $_REQUEST['vacances'] : array();
  // Valeur maximale du code vacances
  $resultat = $mysqli->query('SELECT MAX(id) FROM vacances');
  $vmax = $resultat->fetch_row()[0];
  $resultat->free();
  // Comparaison et modification
  $modif = array();
  $resultat = $mysqli->query('SELECT id, colle, vacances, DATE_FORMAT(debut,\'%d/%m/%Y\') AS debut FROM semaines');
  while ( $r = $resultat->fetch_assoc() )  {
    $v = intval( ( ctype_digit($v = $vacances[$r['id']]) && ( $v <= $vmax ) ) ? $v : 0 );
    $c = intval( isset($colles[$r['id']]) && !$v );
    if ( ( $c != $r['colle'] ) || ( $v != $r['vacances'] ) )  {
      requete('semaines',"UPDATE semaines SET colle = $c, vacances = $v WHERE id = ${r['id']}",$mysqli);
      $modif[] = "semaine du ${r['debut']}";
    }
  }
  $resultat->free();
  // Message à afficher
  exit( $modif ? '{"etat":"ok","message":"Les modifications ont été réalisées ('.implode(', ',$modif).')."}' : '{"etat":"ok","message":"Aucune modification n\'a été réalisée."}');
}

//////////////////////////////
// Modification de l'agenda //
//////////////////////////////
elseif ( $action == 'agenda-elems' )  {
  
  // Traitement d'une suppression
  if ( isset($_REQUEST['supprime']) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
    $resultat = $mysqli->query("SELECT matiere FROM agenda WHERE id = $id");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Identifiant non valide"}');
    $r = $resultat->fetch_row();
    $resultat->free();
    if ( requete('agenda',"DELETE FROM agenda WHERE id = $id",$mysqli) && recent($mysqli,4,$id) ) 
      exit($_SESSION['message'] = '{"etat":"ok","message":"La suppression a été réalisée."}');
    exit('{"etat":"nok","message":"L\'événement n\'a pas été supprimé. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Traitement d'une modification/d'un ajout d'événement :
  // validation préliminaire
  if ( isset($_REQUEST['type']) && isset($_REQUEST['matiere']) && isset($_REQUEST['debut']) && isset($_REQUEST['fin']) && ctype_digit($tid = $_REQUEST['type']) && ctype_digit($mid = $_REQUEST['matiere']) )  {
    // Validation des dates
    $debut = $_REQUEST['debut'];
    if ( strlen($debut) == 10 )
      $debut .= ' 00h00';
    elseif ( strlen($debut) == 15 )
      $debut = substr($debut,0,11).'0'.substr($debut,11);
    $fin = $_REQUEST['fin'];
    if ( strlen($fin) == 10 )
      $fin .= ' 00h00';
    elseif ( strlen($fin) == 15 )
      $fin = substr($fin,0,11).'0'.substr($fin,11);
    $debut = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$debut);
    $fin = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$fin);
    if ( is_null($debut) || is_null($fin) || ( $debut > $fin ) )
      exit('{"etat":"nok","message":"Les dates/heures choisies ne sont pas valables."}');
    // Validation des dates : l'événement doit se trouver en partie dans
    // l'année scolaire
    $resultat = $mysqli->query("SELECT id FROM semaines WHERE debut <= '$fin' AND debut >= SUBDATE('$debut',80) LIMIT 1");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Les dates de l\'événement le placent hors de l\'année scolaire."}');
    $resultat->free();
    // Pour les informations récentes : jour et mois
    $jour = substr($debut,8,2);
    $mois = substr($debut,5,2);
    $annee = substr($debut,2,2);
    // Validation du type d'événement
    $resultat = $mysqli->query("SELECT nom FROM `agenda-types` WHERE id = $tid");
    if ( !$resultat->num_rows )
      exit('{"etat":"nok","message":"Type d\'événement non valide."}');
    // Besoin du nom pour les informations récentes
    $r = $resultat->fetch_row();
    $resultat->free();
    $type = $mysqli->real_escape_string($r[0]);
    if ( $mid )  {
      // Validation de la matière si non nulle
      $resultat = $mysqli->query("SELECT nom FROM matieres WHERE id = $mid");
      if ( !$resultat->num_rows )
        exit('{"etat":"nok","message":"Matière non valide."}');
      // Besoin du nom pour les informations récentes
      $r = $resultat->fetch_row();
      $resultat->free();
      $matiere = $r[0];
    }
    else
      $matiere = '';
    // Validation du texte
    $texte = $mysqli->real_escape_string($_REQUEST['texte']);
    // Vérification que l'identifiant est valide
    if ( isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
      $resultat = $mysqli->query("SELECT id FROM agenda WHERE id = $id");
      if ( !$resultat->num_rows ) 
        exit('{"etat":"nok","message":"Identifiant non valide"}');
      $resultat->free();
      // Écriture dans la base de données
      if ( requete('agenda',"UPDATE agenda SET matiere = $mid, type = $tid, debut = '$debut', fin = '$fin', texte = '$texte' WHERE id = $id", $mysqli) )  {
        recent($mysqli,4,$id,array('titre'=>"$jour/$mois - $type ".( strlen($matiere) ? $mysqli->real_escape_string("en $matiere ") : ''), 'lien'=>"agenda?mois=$annee$mois", 'texte'=>$texte, 'matiere'=>$mid));
        $mysqli->query('ALTER TABLE agenda ORDER BY fin, debut');
        exit($_SESSION['message'] = '{"etat":"ok","message":"L\'événement a été modifié."}');
      }
      exit('{"etat":"nok","message":"L\'événement n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
    }

    // Nouvel événement
    if ( requete('agenda',"INSERT INTO agenda SET matiere = $mid, type = $tid, debut = '$debut', fin = '$fin', texte = '$texte'", $mysqli) )  {
      recent($mysqli,4,$mysqli->insert_id,array('titre'=>"$jour/$mois - $type ".( strlen($matiere) ? $mysqli->real_escape_string("en $matiere ") : ''), 'lien'=>"agenda?mois=$annee$mois", 'texte'=>$texte, 'matiere'=>$mid, 'protection'=>0));
      $mysqli->query('ALTER TABLE agenda ORDER BY fin, debut');
      exit($_SESSION['message'] = '{"etat":"ok","message":"L\'événement a été ajouté."}');
    }
    exit('{"etat":"nok","message":"L\'événement n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
}

//////////////////////////////////////////////////////////////
// Modification de l'agenda : cas des déplacements de colle //
//////////////////////////////////////////////////////////////
elseif ( $action == 'deplcolle' )  {
  
  // On ne réalise ici que des ajouts d'événements. Pas de suppression, pas de
  // modification. Événements simultanés si deux dates renseignées.

  // Validation préliminaire
  if ( !isset($_REQUEST['matiere']) || !isset($_REQUEST['colleur']) || !isset($_REQUEST['groupe']) || !ctype_digit($matiere = $_REQUEST['matiere']) || !strlen($colleur = $_REQUEST['colleur']) || !strlen($groupe = $_REQUEST['groupe']) )
    exit('{"etat":"nok","message":"La matière, le colleur et le groupe sont obligatoires."}');

  // Validation des dates
  $mois = array('','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre');
  switch ( strlen($_REQUEST['ancien']) )  {
    case 0: $ancien_sql = ''; break;
    case 10:
      $ancien_sql = preg_filter('/(\d{2})\/(\d{2})\/(\d{4})/','$3-$2-$1',$_REQUEST['ancien'].' 00:00');
      $ancien = substr($ancien_sql,8,2);
      $ancien = (( $ancien == '01' ) ? '1er' : ltrim($ancien,'0')).' '.$mois[(int)substr($ancien_sql,5,2)];
      break;
    case 15: 
    case 16:
      $ancien_sql = preg_filter('/^(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})$/','$3-$2-$1 $4:$5',$_REQUEST['ancien']);
      $ancien = substr($ancien_sql,8,2);
      $ancien = (( $ancien == '01' ) ? '1er' : ltrim($ancien,'0')).' '.$mois[(int)substr($ancien_sql,5,2)].' à'.str_replace(':','h',strstr($ancien_sql,' '));
      break;
    default: $ancien_sql = null;
  }
  switch ( strlen($_REQUEST['nouveau']) )  {
    case 0: $nouveau_sql = ''; break;
    case 10:
      $nouveau_sql = preg_filter('/(\d{2})\/(\d{2})\/(\d{4})/','$3-$2-$1',$_REQUEST['nouveau'].' 00:00');
      $nouveau = substr($nouveau_sql,8,2);
      $nouveau = (( $nouveau == '01' ) ? '1er' : ltrim($nouveau,'0')).' '.$mois[(int)substr($nouveau_sql,5,2)];
      break;
    case 15: 
    case 16:
      $nouveau_sql = preg_filter('/^(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})$/','$3-$2-$1 $4:$5',$_REQUEST['nouveau']);
      $nouveau = substr($nouveau_sql,8,2);
      $nouveau = (( $nouveau == '01' ) ? '1er' : ltrim($nouveau,'0')).' '.$mois[(int)substr($nouveau_sql,5,2)].' à'.str_replace(':','h',strstr($nouveau_sql,' '));
      break;
    default: $nouveau_sql = null;
  }
  if ( is_null($ancien_sql) || is_null($nouveau_sql) )
    exit('{"etat":"nok","message":"Les dates/heures choisies ne sont pas valables."}');

  // Validation de la matière
  $resultat = $mysqli->query("SELECT nom FROM matieres WHERE id = $matiere");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Matière non valide."}');
    $r = $resultat->fetch_row();
  $resultat->free();
  // Début du texte des événements
  $texte = 'La colle du groupe '.$mysqli->real_escape_string("$groupe en {$r[0]} avec $colleur");
  // Seulement ancien horaire : annulation de colle
  if ( strlen($ancien_sql) && !strlen($nouveau_sql) )  {
    $validation = " '$ancien_sql' BETWEEN debut AND ADDDATE(debut,60)";
    $texte .= " prévue le $ancien est annulée.";
    $insertion = "($matiere,1,'$ancien_sql','$ancien_sql','<p>$texte</p>')";
  }
  // Seulement nouvel horaire : rattrapage de colle
  elseif ( !strlen($ancien_sql) && strlen($nouveau_sql) )  {
    $validation = " '$nouveau_sql' BETWEEN debut AND ADDDATE(debut,80)";
    $texte .= " est rattrapée le $nouveau".(( strlen($_REQUEST['salle']) ) ? ' en salle '.$mysqli->real_escape_string($_REQUEST['salle']) : '').'.';
    $insertion = "($matiere,2,'$nouveau_sql','$nouveau_sql','<p>$texte</p>')";
  }
  // Ancien et nouvel horaires : déplacement de colle
  else  {
    $validation = " '$ancien_sql' BETWEEN debut AND ADDDATE(debut,80) AND '$nouveau_sql' BETWEEN debut AND ADDDATE(debut,80)";
    $texte .= " est déplacée du $ancien au $nouveau".(( strlen($_REQUEST['salle']) ) ? ' en salle '.$mysqli->real_escape_string($_REQUEST['salle']) : '').'.';
    $insertion = "($matiere,1,'$ancien_sql','$ancien_sql','<p>$texte</p>'),($matiere,2,'$nouveau_sql','$nouveau_sql','<p>$texte</p>')";
  }
  // Validation des dates : chaque horaire doit se trouver dans l'année scolaire
  $resultat = $mysqli->query("SELECT id FROM semaines WHERE $validation LIMIT 1");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Le(s) horaire(s) fourni(s) se trouve(nt) hors de l\'année scolaire."}');
  $resultat->free();
  if ( requete('agenda',"INSERT INTO agenda (matiere,type,debut,fin,texte) VALUES $insertion", $mysqli) )  {
    $mysqli->query('ALTER TABLE agenda ORDER BY fin, debut');
    $debut = ( strlen($ancien_sql) ) ? $ancien_sql : $nouveau_sql;
    recent($mysqli,5,$mysqli->insert_id,array('titre'=>substr($debut,8,2).'/'.substr($debut,5,2)." - Déplacement de colle en ".$mysqli->real_escape_string("${r[0]}, groupe $groupe"), 'lien'=>'agenda?mois='.substr($debut,2,2).substr($debut,5,2), 'texte'=>$texte, 'matiere'=>$matiere, 'protection'=>0));
    exit($_SESSION['message'] = '{"etat":"ok","message":"Déplacement de colle ajouté"}');
  }
  else
    exit('{"etat":"nok","message":"Le déplacement de colle n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

/////////////////////////////////////////////////////
// Modification des types d'événements de l'agenda //
/////////////////////////////////////////////////////
elseif ( ( $action == 'agenda-types' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {

  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT ordre, nom, cle, couleur, (SELECT COUNT(*) FROM `agenda-types`) AS max FROM `agenda-types` WHERE id = $id");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  // Traitement d'une modification
  if ( isset($_REQUEST['nom']) && isset($_REQUEST['cle']) && isset($_REQUEST['couleur']) )  {
    $nom = ucfirst($mysqli->real_escape_string($_REQUEST['nom']));
    $cle = str_replace(' ','_',trim($mysqli->real_escape_string($_REQUEST['cle'])));
    $couleur =  preg_filter('/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/','$1',$_REQUEST['couleur']);
    if ( !strlen($nom) || !strlen($cle) || !$couleur )
      exit('{"etat":"nok","message":"Le type d\'événements n\'a pas été modifié. Le nom, la clé et la couleur doivent être non vides."}');
    if ( $cle != $r['cle'] )  {
      // Vérification que la clé n'existe pas déjà
      $resultat = $mysqli->query("SELECT cle FROM `agenda-types` WHERE id != $id");
      if ( $resultat->num_rows )  {
        while ( $r = $resultat->fetch_assoc() )
          if ( $r['cle'] == $cle )
            exit('{"etat":"nok","message":"Le type d\'événements n\'a pas été modifié. Cette clé existe déjà et doit être unique."}');
        $resultat->free();
      }
    }
    if ( requete('agenda-types',"UPDATE `agenda-types` SET nom = '$nom', cle = '$cle', couleur = '$couleur' WHERE id = $id",$mysqli) )
      exit($_SESSION['message'] = '{"etat":"ok","message":"Le type d\'événements a été modifié."}');
    exit('{"etat":"nok","message":"Le type d\'événements n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Déplacement vers le haut
  if ( isset($_REQUEST['monte']) && ( $r['ordre'] > 1 ) )
    exit( ( requete('agenda-types',"UPDATE `agenda-types` SET ordre = (2*${r['ordre']}-1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}-1) )",$mysqli)
            && $mysqli->query('ALTER TABLE `agenda-types` ORDER BY ordre') )
        ? '{"etat":"ok","message":"Le type d\'événements a été déplacé."}' : '{"etat":"nok","message":"Le type d\'événements n\'a pas été déplacé. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Déplacement vers le bas
  if ( isset($_REQUEST['descend']) && ( $r['ordre'] < $r['max'] ) )
    exit( ( requete('agenda-types',"UPDATE `agenda-types` SET ordre = (2*${r['ordre']}+1-ordre) WHERE ( ordre = ${r['ordre']} OR ordre = (${r['ordre']}+1) )",$mysqli)
            && $mysqli->query('ALTER TABLE `agenda-types` ORDER BY ordre') )
        ? '{"etat":"ok","message":"Le type d\'événements a été déplacé."}' : '{"etat":"nok","message":"Le type d\'événements n\'a pas été déplacé. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');

  // Suppression
  if ( isset($_REQUEST['supprime']) )
    exit( ( requete('agenda',"DELETE FROM agenda WHERE type = $id",$mysqli)
            && requete('agenda-types',"DELETE FROM `agenda-types` WHERE id = $id",$mysqli) 
            && requete('agenda-types',"UPDATE `agenda-types` SET ordre = (ordre-1) WHERE ordre > ${r['ordre']}",$mysqli) )
        ? '{"etat":"ok","message":"La suppression a été réalisée."}' : '{"etat":"nok","message":"Le type d\'événements n\'a pas été supprimé. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

//////////////////////////////////////////////
// Ajout d'un type d'événements de l'agenda //
//////////////////////////////////////////////
elseif ( ( $action == 'ajout-agenda-types' ) && isset($_REQUEST['nom']) && isset($_REQUEST['cle']) && isset($_REQUEST['couleur']) )  {
  $nom = ucfirst($mysqli->real_escape_string($_REQUEST['nom']));
  $cle = str_replace(' ','_',trim($mysqli->real_escape_string($_REQUEST['cle'])));
  $couleur =  preg_filter('/^([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/','$1',$_REQUEST['couleur']);
  if ( !strlen($nom) || !strlen($cle) || !$couleur )
    exit('{"etat":"nok","message":"Le type d\'événements n\'a pas été ajouté. Le nom, la clé et la couleur doivent être non vides."}');
  // Vérification que la clé n'existe pas déjà
  $resultat = $mysqli->query('SELECT cle FROM `agenda-types`');
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )
      if ( $r['cle'] == $cle )
        exit('{"etat":"nok","message":"Le type d\'événements n\'a pas été ajouté. Cette clé existe déjà et doit être unique."}');
    $resultat->free();
  }
  if ( requete('agenda-types',"INSERT INTO `agenda-types` SET nom = '$nom', cle = '$cle', couleur = '$couleur',
                               ordre = (SELECT max(t.ordre)+1 FROM `agenda-types` AS t)",$mysqli) )
    exit($_SESSION['message'] = '{"etat":"ok","message":"Le type d\'événements a été ajouté."}');
  exit('{"etat":"nok","message":"Le type d\'événements n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

//////////////////////////////////////////////////
// Ajout d'un devoir (pour transfert de copies) //
//////////////////////////////////////////////////
elseif ( ( $action == 'ajout-devoir' ) && isset($_REQUEST['description']) && isset($_REQUEST['nom']) && isset($_REQUEST['deadline']) && isset($_REQUEST['matiere']) && isset($_REQUEST['indications']) && in_array($matiere = intval($_REQUEST['matiere']),explode(',',$_SESSION['matieres'])) )  {
  // Validation des données
  $description = trim($mysqli->real_escape_string($_REQUEST['description']));
  $nom = str_replace(array('\\','/'),array('-','-'),trim($mysqli->real_escape_string($_REQUEST['nom'])));
  $indications = trim($mysqli->real_escape_string($_REQUEST['indications']));
  if ( !$description || !$nom )
    exit('{"etat":"nok","message":"Le devoir n\'a pas été ajouté. La description longue et le préfixe court doivent être non vides."}');
  if ( is_null($deadline = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$_REQUEST['deadline'])) )
    exit('{"etat":"nok","message":"La date limite choisie n\'est pas valable."}');
  // Vérification de l'affichage différé et de la date de disponibilité, seulement si dans le futur
  if ( !isset($_REQUEST['affdiff']) || !isset($_REQUEST['dispo']) || !strlen( $dispo = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$_REQUEST['dispo']) ) || ($dispo < date('Y-m-d H:i') ) )
    $dispo = '';
  // Création du répertoire aléatoire d'accueil
  $lien = substr(sha1(mt_rand()),0,9);
  while ( is_dir("documents/devoir$lien") )
    $lien = substr(sha1(mt_rand()),0,9);
  mkdir("documents/devoir$lien");
  if ( requete('devoirs',"INSERT INTO devoirs SET matiere = $matiere, deadline = '$deadline', description = '$description', nom = '$nom', nom_nat = '".zpad($nom)."', lien = 'devoir$lien', indications = '$indications', dispo = '$dispo'",$mysqli) && $mysqli->query("UPDATE matieres SET copies = 1 WHERE id = $matiere") )
    exit($_SESSION['message'] = '{"etat":"ok","message":"Le devoir a été ajouté."}');
  exit('{"etat":"nok","message":"Le devoir n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
}

/////////////////////////////////////////////////////////
// Modification d'un devoir (pour transfert de copies) //
/////////////////////////////////////////////////////////
elseif ( ( $action == 'devoirs' ) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) )  {
  
  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT description, nom, deadline, indications, dispo, lien, matiere FROM devoirs WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();

  $etat = 'nok';
  $message = '';
  
  // Traitement d'une modification
  if ( isset($_REQUEST['description']) && isset($_REQUEST['nom']) && isset($_REQUEST['indications']) && isset($_REQUEST['deadline']) )  {
    $description = trim($mysqli->real_escape_string($_REQUEST['description']));
    $nom = str_replace(array('\\','/'),array('-','-'),trim($mysqli->real_escape_string($_REQUEST['nom'])));
    $indications = trim($mysqli->real_escape_string($_REQUEST['indications']));
    if ( !$description || !$nom )
      exit('{"etat":"nok","message":"Le devoir n\'a pas été modifié. La titre et le préfixe doivent être non vides."}');
    if ( is_null($deadline = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$_REQUEST['deadline'])) )
      exit('{"etat":"nok","message":"Le devoir n\'a pas été modifié. La date limite choisie n\'est pas valable."}');
    if ( ( $description != $r['description'] ) || ( $nom != $r['nom'] ) || ( $indications != $r['indications'] ) || ( $deadline != $r['deadline'] ) )  {
      if ( !requete('devoirs',"UPDATE devoirs SET deadline = '$deadline', description = '$description', nom = '$nom', nom_nat = '".zpad($nom)."', indications = '$indications' WHERE id = $id",$mysqli) )
        exit('{"etat":"nok","message":"Le devoir n\'a pas été modifié. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
      $etat = 'ok';
      $message = "Le devoir <em>${r['nom']}</em> a bien été modifié.<br>";
    }
  
    // Modification de la date de disponibilité
    if ( isset($_REQUEST['affdiff']) && isset($_REQUEST['dispo']) && strlen($dispo = $_REQUEST['dispo']) )  {
      if ( strlen( $dispo = preg_filter('/(\d{2})\/(\d{2})\/(\d{4}) (\d{1,2})h(\d{2})/','$3-$2-$1 $4:$5',$dispo) ) && ( $r['dispo'] != $dispo ) )  {
        // Modification uniquement si dispo est dans le futur
        if ( $dispo < date('Y-m-d H:i') )  {
          $etat = 'ok';
          $message .= "La date de disponiblité du devoir <em>${r['nom']}</em> n'a pas été modifiée car elle ne peut être déplacée dans le passé.<br>";
        }
        elseif ( !requete('devoirs',"UPDATE devoirs SET dispo = '$dispo' WHERE id = $id",$mysqli) )
          exit("{\"etat\":\"nok\",\"message\":\"La date de disponibilité du devoir <em>${r['nom']}</em> n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
        else  {
          $etat = 'ok';
          $message .= "La date de disponiblité du document <em>${r['nom']}</em> a été modifiée.<br>";
        }
      }
    }
    // Suppression de l'affichage différé
    elseif ( $r['dispo'] )  {
      // Modification uniquement si dispo était dans le futur
      if ( $r['dispo'] < date('Y-m-d H:i') )  {
        $etat = 'ok';
        $message .= "La date de disponiblité du devoir <em>${r['nom']}</em> n'a pas été supprimée car elle est déjà passée.<br>";
      }
      else  {
        if ( !requete('devoirs',"UPDATE devoirs SET dispo = '' WHERE id = $id",$mysqli) )
          exit("{\"etat\":\"nok\",\"message\":\"La date de disponibilité du document <em>${r['nom']}</em> n'a pas été supprimée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
        $etat = 'ok';
        $message .= "Le devoir <em>${r['nom']}</em> a été rendu visible dès maintenant.<br>";
      }
    }
  }
  
  // Suppression
  elseif ( isset($_REQUEST['supprime']) )  {
    if ( requete('devoirs',"DELETE FROM devoirs WHERE id = $id",$mysqli) && requete('copies',"DELETE FROM copies WHERE devoir = $id",$mysqli) )  {
      // Suppression physique
      exec("rm -rf documents/${r['lien']}");
      // Mise à jour du champ 'copies' dans la table 'matieres' (pour le menu)
      $mysqli->query("UPDATE matieres SET copies = IF((SELECT id FROM devoirs WHERE matiere = ${r['matiere']} LIMIT 1),1,0) WHERE id = ${r['matiere']}");
      exit("{\"etat\":\"ok\",\"message\":\"Le devoir <em>${r['nom']}</em> a été supprimé.\"}");
    }
    exit("{\"etat\":\"nok\",\"message\":\"Le devoir <em>${r['nom']}</em> n'a pas été supprimé. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'».');
  }
  
  // Message
  if ( $etat == 'nok' )
    exit("{\"etat\":\"nok\",\"message\":\"Le devoir <em>${r['nom']}</em> n'a pas été modifié. Aucune modification demandée.\"}");
  exit($_SESSION['message'] = '{"etat":"ok","message":"'.substr($message,0,-4).'"}');
}

/////////////////////////////////////////////////////////////////////////////////
// Transfert multiple de documents correction/sujet (pour transfert de copies) //
/////////////////////////////////////////////////////////////////////////////////
elseif ( ( $action == 'ajout-copies' ) && isset($_FILES['fichier']) && isset($_REQUEST['id']) && ctype_digit($id = $_REQUEST['id']) && isset($_REQUEST['type']) && ctype_digit($type = $_REQUEST['type']) )  {

  // Vérification du devoir
  $resultat = $mysqli->query("SELECT lien, nom, matiere FROM devoirs WHERE id = $id AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de devoir non valide"}');
  $r = $resultat->fetch_assoc();
  $resultat->free();
  
  // Traitement de chaque fichier envoyé
  $type = ( $type == 2 ) ? 2 : 1;
  $ok = 0;
  $message = '';
  setlocale(LC_CTYPE, "fr_FR.UTF-8");
  for ( $i = 0 ; $i < ( $n = count($_FILES['fichier']['tmp_name']) ) ; $i++ )  {
    if ( !is_uploaded_file($_FILES['fichier']['tmp_name'][$i]) )  {
      $message .= '<br>Le document <em>'.$_FILES['fichier']['tmp_name'][$i].'</em> n\'a pas été ajouté : le fichier a mal été envoyé. Vous devriez en informer l\'administrateur.';
      continue;
    }
    // Vérifications des données envoyées (on fait confiance aux utilisateurs connectés pour ne pas envoyer de scripts malsains)
    // $ext ne doit pas faire plus de 5 caractères sinon fichier plus accessible
    $ext = ( strpos($_FILES['fichier']['name'][$i],'.') ) ? substr(strrchr($_FILES['fichier']['name'][$i],'.'),0,5) : '';
    // Récupération du numéro
    $eid = $_REQUEST['eid'][$i];
    $resultat = $mysqli->query("SELECT id FROM copies WHERE devoir = $id AND eleve = $eid AND ( numero DIV 100 ) = $type");
    if ( ( $n = $resultat->num_rows + 1 ) > 1 )
      $resultat->free();
    // Gestion de la taille
    $taille = ( ( $taille = intval($_FILES['fichier']['size'][$i]/1024) ) < 1024 ) ? "$taille&nbsp;ko" : intval($taille/1024).'&nbsp;Mo';
    // Déplacement du document uploadé au bon endroit
    if ( !move_uploaded_file($_FILES['fichier']['tmp_name'][$i],"documents/${r['lien']}/${eid}_tmp$ext") )  {
      $message .= '<br>Le document <em>'.$_FILES['fichier']['tmp_name'][$i].'</em> n\'a pas été ajouté : problème d\'écriture du fichier. Vous devriez en informer l\'administrateur.';
      continue;
    }
    // Écriture MySQL
    if ( requete('copies',"INSERT INTO copies SET devoir = $id, eleve = $eid, matiere = ${r['matiere']}, numero = ".($type*100+$n).", upload = NOW(), taille = '$taille', ext = '$ext'",$mysqli) )  {
      rename("documents/${r['lien']}/${eid}_tmp$ext","documents/${r['lien']}/${eid}_".$mysqli->insert_id.$ext);
      $ok++;
    }
    else  {
      // Retour en arrière
      unlink("documents/${r['lien']}/${eid}_tmp$ext");
      $message .= '<br>Le document <em>'.$_FILES['fichier']['tmp_name'][$i].'</em> n\'a pas été ajouté. Erreur MySQL n°'.$mysqli->errno.', «&nbsp;'.$mysqli->error.'&nbsp;».';
    }
  }
  // Traitement des échecs 
  if ( !$ok )
    exit("{\"etat\":\"nok\",\"message\":\"Aucun document n'a été envoyé.$message\"}");
  // Réussite : pas de $_SESSION['message'] car pas de rechargement de la page
  if ( $ok < $n )
    exit("{\"etat\":\"ok\",\"message\":\"Seuls $ok documents sur $n ont été ajoutés.$message\"}");
  exit("{\"etat\":\"ok\",\"message\":\"Les $ok documents ont été ajoutés.\"}");
}

/////////////////////////////////////////////////////////////////////////
// Suppression multiple des copies transférées : professeurs seulement //
/////////////////////////////////..//////////////////////////////////////
elseif ( ( $action == 'suppr-copies' ) && isset($_REQUEST['devoir']) && ctype_digit($did = $_REQUEST['devoir']) && isset($_REQUEST['ids']) && count($ids = array_filter(explode(',',$_REQUEST['ids']),function($id) { return ctype_digit($id); })) )  {
  
  // Vérification que l'identifiant est valide
  $resultat = $mysqli->query("SELECT lien FROM devoirs WHERE id = $did AND FIND_IN_SET(matiere,'${_SESSION['matieres']}')");
  if ( !$resultat->num_rows )
    exit('{"etat":"nok","message":"Identifiant de devoir non valide"}');
  $d = $resultat->fetch_row();
  $resultat->free();
  
  // Suppression document par document
  $ok = 0;
  $message = '';
  foreach ( $ids as $id )  {
    $resultat = $mysqli->query("SELECT eleve, numero, ( numero DIV 100 ) AS type, ext FROM copies WHERE devoir = $did AND id = $id");
    if ( !$resultat->num_rows )  {
      $message .= '<br>Identifiant de document non valide.';
      continue;
    }
    $r = $resultat->fetch_assoc();
    $resultat->free();
    // Suppression dans la base
    if( !requete('copies',"DELETE FROM copies WHERE id = $id",$mysqli) ||
        !requete('copies',"UPDATE copies SET numero = numero-1 WHERE devoir = $did AND eleve = ${r['eleve']} AND numero > ${r['numero']} AND ( numero DIV 100 ) = ${r['type']}",$mysqli) )  {
      $message .= '<br>Un document n\'a pas été supprimé. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'».';
      continue;
    }
    // Suppression physique
    unlink("documents/${d[0]}/${r['eleve']}_$id${r['ext']}");
    $ok = $ok + 1;
  }
  // Traitement des échecs 
  if ( !$ok )
    exit("{\"etat\":\"nok\",\"message\":\"Aucun document n'a été supprimé.$message\"}");
  $n = count($ids);
  // Réussite : pas de $_SESSION['message'] car pas de rechargement de la page
  if ( $ok < $n )
    exit("{\"etat\":\"ok\",\"message\":\"Seuls $ok documents sur $n ont été supprimés.$message\"}");
  exit("{\"etat\":\"ok\",\"message\":\"Les $ok documents ont été supprimés.\"}");
}

///////////////////////////////
// Modification des matières //
///////////////////////////////
elseif ( ( $action == 'prefsmatiere' ) && isset($_REQUEST['id']) && in_array($id = intval($_REQUEST['id']),explode(',',$_SESSION['matieres'])) )  {
  
  $resultat = $mysqli->query("SELECT nom, colles_protection, cdt_protection, docs_protection, dureecolle FROM matieres WHERE id = $id");
  $r = $resultat->fetch_assoc();
  $resultat->free();
  
  // Gestion de la durée de colle
  if ( isset($_REQUEST['dureecolle']) && ctype_digit($dureecolle = $_REQUEST['dureecolle']) )  {
    if ( requete('matieres',"UPDATE matieres SET dureecolle = $dureecolle WHERE id = $id",$mysqli) )
      exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"La durée de colle par élève en <em>${r['nom']}</em> a été modifiée.\"}");
    exit("{\"etat\":\"nok\",\"message\":\"La durée de colle par élève en <em>${r['nom']}</em> n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');
  }

  // Génération des valeurs de protection
  foreach ( array('colles','cdt','docs') as $fonction )  {
    if ( isset($_REQUEST[$fonction.'_protection']) )  {
      // Génération de la valeur de protection
      if ( !count($val = array_filter($_REQUEST[$fonction.'_protection'],function($id) { return ctype_digit($id); })) )
        exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été modifiée. La protection d'accès est incorrecte.\"}");
      $protection = ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32);
      if ( $protection == $r[$fonction.'_protection'] )
        exit('{"etat":"nok","message":"Aucune action effectuée : la protection saisie est la même que celle déjà en place."}');
      if ( !requete('matieres',"UPDATE matieres SET ${fonction}_protection = $protection WHERE id = $id",$mysqli) )
        exit("{\"etat\":\"nok\",\"message\":\"La matière <em>${r['nom']}</em> n'a pas été modifiée. Erreur MySQL n°".$mysqli->errno.', «'.$mysqli->error.'»."}');        
      // Mises à jour supplémentaires : répertoire racine, table recents, flux rss
      if ( $fonction == 'colles' )  {
        requete('recents',"UPDATE recents SET protection = $protection WHERE type = 2 AND matiere = $id",$mysqli);
        rss($mysqli, $id, ( $r['colles_protection'] && $protection ) ? $r['colles_protection']^$protection : 0);
      }
      elseif ( $fonction == 'docs' )  {
        requete('reps',"UPDATE reps SET protection = $protection WHERE matiere = $id AND parent = 0",$mysqli);
        requete('recents',"UPDATE recents SET protection = $protection WHERE type = 3 AND matiere = $id",$mysqli);
        rss($mysqli, $id, ( $r['docs_protection'] && $protection ) ? $r['docs_protection']^$protection : 0);
      }
      exit($_SESSION['message'] = "{\"etat\":\"ok\",\"message\":\"La matière <em>${r['nom']}</em> a été modifiée.\"}");
    }
  }
}
  
///////////////////////////////////////////
// Modification des préférences globales //
///////////////////////////////////////////
elseif ( $action == 'prefsglobales' )  {

  // Préférences de l'agenda : protection globale et nombre d'événements sur la page d'accueil
  if ( isset($_REQUEST['protection_agenda']) && isset($_REQUEST['nb_agenda_index']) && ctype_digit($nb_agenda_index = $_REQUEST['nb_agenda_index']) )  {
    // Génération de la valeur de protection
    if ( !count($val = array_filter($_REQUEST['protection_agenda'],function($id) { return ctype_digit($id); })) )
      exit($_SESSION['message'] = '{"etat":"nok","message":"Les préférences d\'accès à l\'agenda n\'ont pas été modifiées."}');
    $protection = ( ( $val[0] == 0 ) || ( $val[0] == 32 ) ) ? $val[0] : array_reduce($val, function($s,$v) { return $s - ( ( $v<6 ) ? 1<<($v-1) : 0 ); }, 32);
    if ( requete('prefs',"UPDATE prefs SET val = $protection WHERE nom='protection_agenda'",$mysqli)
      && requete('prefs',"UPDATE prefs SET val = $nb_agenda_index WHERE nom='nb_agenda_index'",$mysqli) 
      && requete('recents',"UPDATE recents SET protection = $protection WHERE type=4",$mysqli) )
      exit($_SESSION['message'] = '{"etat":"ok","message":"Les préférences de l\'agenda ont été modifiées."}');
    exit('{"etat":"nok","message":"Les préférences globales de l\'agenda n\'ont pas été modifiées. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Préférence d'envoi de mail, venant de utilisateurs-mails.php
  // $depuis : numéro du groupe expéditeur traité
  // $vers : numéro du groupe destinataire traité ou 0 pour les traiter tous
  // $ok : 1 pour autoriser, 0 pour interdire
  elseif ( isset($_REQUEST['mails']) && isset($_REQUEST['depuis']) && isset($_REQUEST['vers']) && isset($_REQUEST['val']) && in_array($depuis = intval($_REQUEST['depuis']), array(2,3,4,5)) && in_array($vers = intval($_REQUEST['vers']), array(0,2,3,4,5)) && ctype_digit($ok = $_REQUEST['val']) )  {
    // Masque : bits à modifier
    $masque = ( $vers ) ? 1 << 4*($depuis-2)+$vers-2 : 15 << 4*($depuis-2);
    // Récupération de la valeur originale
    $resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
    $val_orig = $resultat->fetch_row()[0];
    // Modification
    $val = ( $ok ) ? $val_orig | $masque : $val_orig & ( 65535 - $masque );
    if ( $val == $val_orig )
      exit('{"etat":"nok","message":"Aucune action effectuée : les autorisations d\'envoi demandées sont déjà celles en place."}');
    if ( requete('prefs',"UPDATE prefs SET val = $val WHERE nom='autorisation_mails'",$mysqli) )
      exit($_SESSION['message'] = '{"etat":"ok","message":"Les autorisations d\'envoi de courriels ont été modifiées."}');
    exit('{"etat":"nok","message":"Les autorisations d\'envoi de courriels n\'ont pas été modifiées. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
  
  // Préférence de création de compte, venant de utilisateurs.php
  elseif ( isset($_REQUEST['creation_compte']) )  {
    $val = intval(isset($_REQUEST['autoriser']));
    if ( requete('prefs',"UPDATE prefs SET val = $val WHERE nom='creation_compte'",$mysqli) )
      exit($_SESSION['message'] = '{"etat":"ok","message":"Les créations de compte ont été '.( $val ? 'autorisées' : 'interdites' ).'."}');
    exit('{"etat":"nok","message":"La possibilité de création de comptes n\'a pas été modifiée. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error.'»."}');
  }
}

// Sans action
exit('{"etat":"nok","message":"Aucune action effectuée"}');
?>
