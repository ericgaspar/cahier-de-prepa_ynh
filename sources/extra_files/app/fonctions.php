<?php
// Sécurité : ne doit pas être exécuté s'il n'est pas lancé par un script autorisé
if ( !defined('OK') )  exit();

////////////////////////////////////////////////////////////////////////////////
//////////////////////////// Gestion de la session /////////////////////////////
////////////////////////////////////////////////////////////////////////////////

// Fonction de connexion à la base MySQL
// $interfaceglobale permet d'écrire dans la base de données globales, pour les
// mises à jour d'adresse électronique/mot de passe, si configuré dans config.php.
function connectsql($ecriture=false,$interfaceglobale=false)  {
  if ( $interfaceglobale )  {
    // Le include est dans une fonction pour éviter la réécriture des variables
    include("${interfaceglobale}config.php");
    $mysqli = new mysqli($serveur,( $ecriture ) ? $base.'-adm' : $base, $mdp, $base);
  }
  else
    $mysqli = new mysqli($GLOBALS['serveur'],( $ecriture ) ? $GLOBALS['base'].'-adm' : $GLOBALS['base'], $GLOBALS['mdp'], $GLOBALS['base']);
  $mysqli->set_charset('utf8');
  return $mysqli;
}

// Fonction d'écriture des connexions
// $connexion = 1 pour connexion normale, 2 pour connexion light par cookie,
//              3 pour reconnexion normale, 4 pour reconnexion light, 5 pour déconnexion
function logconnect($connexion)  {
  if ( is_dir('sauvegarde') && is_executable('sauvegarde') && is_writable('sauvegarde') )  {
    if ( !file_exists($fichier = 'sauvegarde/connexion.'.date('Y-m').'.php') )  {
      $f = fopen($fichier,'wb');
      fwrite($f, "<?php exit(); ?>\n\n");
    }
    else
      $f = fopen($fichier,'ab');
    switch ( $connexion )  {
      case 1: fwrite($f, 'Le '.date('d/m/Y à H:i:s').", connexion de ${_SESSION['login']}\n"); break;
      case 2: fwrite($f, 'Le '.date('d/m/Y à H:i:s').", connexion light par cookie de ${_SESSION['login']}\n"); break;
      case 3: fwrite($f, 'Le '.date('d/m/Y à H:i:s').", reconnexion normale de ${_SESSION['login']}\n"); break;
      case 4: fwrite($f, 'Le '.date('d/m/Y à H:i:s').", reconnexion light de ${_SESSION['login']}\n"); break;
      case 5: fwrite($f, 'Le '.date('d/m/Y à H:i:s').", déconnexion de ${_SESSION['login']}\n"); break;
    }
    fclose($f);
  }
}

// Fonction d'enregistrement de session : remplissage de la variable $_SESSION et log
// $r contient les données de l'utilisateur pour une nouvelle session, ou false
// pour une simple mise à jour de $_SESSION['light'], $_SESSION['timeout'] et $_SESSION['time']
// $light : true si la connexion est obtenue par cookie et ne permet que la lecture
// $timeout : utile dans le cas du passage de connexion normale à connexion light uniquement
// Cette fonction est utilisée uniquement dans ajax.php et fonctions.php
function enregistre_session($r,$light,$timeout=0)  {
  // Nouvelle session
  if ( $r )  {
    // Interdiction de garder son identifiant de session
    session_regenerate_id(true);
    $_SESSION = array();
    // Interdiction de pouvoir se connecter aux autres site sur le même serveur
    $_SESSION['chemin'] = $GLOBALS['chemin'];
    // Pour vérification aux connexions ultérieures
    $_SESSION['client'] = ( $_SERVER['HTTP_USER_AGENT'] ?: '' );
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['login'] = $r['login'];
    $_SESSION['id'] = $r['id'];
    $_SESSION['permconn'] = $r['permconn'];
    $_SESSION['lastconn'] = $r['lastconn'];
    // Mise à jour de dernière connexion
    $mysqli = connectsql(true);
    $mysqli->query("UPDATE utilisateurs SET lastconn = NOW() WHERE id = ${r['id']}");
    $mysqli->close();
    // Autorisations
    $_SESSION['light'] = $light;
    $_SESSION['autorisation'] = $r['autorisation'];
    $_SESSION['matieres'] = $r['matieres'];
    // Temps de session : depuis la base si connexion normale, 1 jour si light
    $_SESSION['timeout'] = ( $light ) ? 86400 : ( $r['timeout']?:900 );
    $_SESSION['time'] = time() + $_SESSION['timeout'];
    // Pour sécurisation des requêtes AJAX
    $_SESSION['csrf-token'] = $_REQUEST['csrf-token'] ?? bin2hex(random_bytes(32));
    // Si interface globale, vérification
    $_SESSION['compteglobal'] = false;
    if ( ( $r['autorisation'] > 1 ) && $GLOBALS['interfaceglobale'] )  {
      $mysqli = connectsql(false,$GLOBALS['interfaceglobale']);
      // Identifiant global de l'utilisateur = 1000*idCahier+idUtilisateur
      // On cherche uniquement un compte correspondant à au moins deux connexions
      $resultat = $mysqli->query("SELECT id FROM comptes 
                                  WHERE FIND_IN_SET( (SELECT id FROM cahiers WHERE rep = TRIM(BOTH '/' FROM '${GLOBALS['chemin']}'))*1000+${r['id']}, connexions) AND LOCATE(',',connexions)");
      if ( $resultat->num_rows )  {
        $_SESSION['compteglobal'] = $resultat->fetch_row()[0];
        $resultat->free();
      }
      $mysqli->close();
    }
  }
  // Mise à jour de session
  else  {
    $_SESSION['light'] = $light;
    $_SESSION['timeout'] = ( $light ) ? 86400 : ( $timeout?:900 );
    $_SESSION['time'] = time() + $_SESSION['timeout'];
  }
  // Écriture de la connexion dans le fichier de log (voir commentaires de logconnect)
  logconnect(1+$light+2*!$r);
}

// Fonction de suppression de session
// Cette fonction est utilisée uniquement dans ajax.php et fonctions.php
function suppression_session()  {
  // Écriture de la déconnexion dans le fichier de log, sauf si la session a
  // été perdue parce qu'effacée du serveur
  if ( isset($_SESSION['login']) )  {
    logconnect(5);
    // Suppression des données de session et de l'éventuel cookie de connexion permanente
    $_SESSION = array();
    setcookie('CDP_SESSION_PERM','',time()-3600,$GLOBALS['chemin'],$GLOBALS['domaine'],true);
  }
  // Suppression du cookie de session et régénération interne de l'identifiant de session
  setcookie('CDP_SESSION','',time()-3600,$GLOBALS['chemin'],$GLOBALS['domaine'],true);
  session_regenerate_id(true);
}

// Fonction de vérification de la qualité light ou non de la connexion
// Renvoie true si la connexion est complète, false si elle est light.
// Les connexions permanentes le sont par connexion par cookie, sans taper
// son mot de passe systématiquement. Pour les modifications, il faut se
// connecter à nouveau. Cette fonction n'est utilisée que dans ajax.php.
function connexionlight()  {
  if ( $_SESSION['light'] )
    exit('{"etat":"mdp"}');
  return true;
}

// Création de session
session_name('CDP_SESSION');
session_set_cookie_params(0,$chemin,$domaine,true);
session_start();
// Niveau d'autorisation
//  * 0 = non connecté
//  * 1 = compte invité
//  * 2 = élève
//  * 3 = colleur
//  * 4 = lycée
//  * 5 = professeur
// Gestion des protections : voir la fonction acces ci-dessous
$message = '';
// Gestion des utilisateurs connectés
if ( isset($_SESSION['chemin']) && ( $_SESSION['chemin'] == $chemin ) )  {
  // Passage en connexion light si timeout mais connexion permanente
  if ( ( $_SESSION['time'] < time() ) && !$_SESSION['light'] && $_SESSION['permconn'] )
    enregistre_session(false,true);
  // Passage en connexion normale si reconnexion sur une connexion light
  elseif ( $_SESSION['light'] && isset($_REQUEST['motdepasse']) )  {
    $mysqli = connectsql();
    // Récupération du compte dans la base de données
    $resultat = $mysqli->query('SELECT timeout FROM utilisateurs WHERE mdp = \''.sha1($mdp.$_REQUEST['motdepasse'])."' AND id = ${_SESSION['id']}");
    $mysqli->close();
    if ( $resultat->num_rows )  {
      $r = $resultat->fetch_row();
      $resultat->free();
      enregistre_session(false,false,$r[0]);
      // Si paramètre "connexion", reconnexion sans autre demande (par login.php),
      // suivie d'un rechargement immédiat : terminaison et $_SESSION['message']
      if ( isset($_REQUEST['connexion']) )  {
        // Modification du timeout pour autoriser un envoi sur une durée de 1h
        $_SESSION['time'] = max($_SESSION['time'],time()+3600);
        exit($_SESSION['message'] = '{"etat":"ok","message":"Connexion réussie"}');
      }
    }
    else
      exit('{"etat":"'.(isset($_REQUEST['connexion'])?'':'mdp').'nok","message":"Mauvais mot de passe"}');
  }
  // Déconnexion automatique si Timeout ou changement de UserAgent ou d'IP
  // Changement d'IP: on ne regarde pas le dernier élément, certains lycées
  // on des adresses dynamiques à ce niveau
  elseif ( ( $_SESSION['time'] < time() ) || ( $_SESSION['client'] != ( $_SERVER['HTTP_USER_AGENT'] ?: '' ) ) || ( substr($_SESSION['ip'],0,-3) != substr($_SERVER['REMOTE_ADDR'],0,-3) ) )  {
    suppression_session();
    // Pour la suite du script
    $message = 'Vous devez vous connecter à nouveau, suite à une longue durée d\'inactivité.';
    $_SESSION['autorisation'] = 0;
  }
  // Tout est ok : session valide pendant timeout
  else
    $_SESSION['time'] = time() + $_SESSION['timeout'];
}
// Connexion complète (login et mdp, script ajax.php demandé)
elseif ( isset($_REQUEST['motdepasse']) && isset($_REQUEST['login']) && strlen($login = trim($_REQUEST['login'])) )  {
  // Pas de connexion a priori
  $_SESSION['autorisation'] = 0;
  // Récupération du compte dans la base de données
  $mysqli = connectsql();
  $resultat = $mysqli->query('SELECT * FROM utilisateurs WHERE mdp = \''.sha1($mdp.$_REQUEST['motdepasse']).'\'');
  while ( $r = $resultat->fetch_assoc() )
    if ( ( $r['login'] == $_REQUEST['login'] ) || ( $r['mail'] == $_REQUEST['login'] ) )  {
      // Pas de connexion permanente si ce n'est pas coché (mais on ne modifie pas la
      // base : on ne supprime pas celles qui pourraient exister sur d'autres appareils)
      if ( !isset($_REQUEST['permconn']) )
        $r['permconn'] = '';
      // Génération du token de connexion automatique si demandé et s'il n'existe pas déjà
      elseif ( !strlen($r['permconn']) )  {
        $permconn = '';
        for ( $i = 0; $i < 10; $i++ )
          $permconn .= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0,61)];
        $mysqli2 = connectsql(true);
        $mysqli2->query("UPDATE utilisateurs SET permconn = '$permconn' WHERE id = ${r['id']}");
        $mysqli2->close();
        $r['permconn'] = $permconn;
      }
      // Enregistrement de la session et écriture du cookie pour connexion light
      enregistre_session($r,false);
      if ( $r['permconn'] )
        setcookie('CDP_SESSION_PERM',$r['permconn'],time()+31536000,$chemin,$domaine,true);
      // Nombre d'éléments récents à afficher
      $resultat = $mysqli->query("SELECT COUNT(id) FROM recents WHERE publi > '${r['lastconn']}' OR maj > '${r['lastconn']}'");
      $_SESSION['recents'] = $resultat->fetch_row()[0];
      break;
    }
  $resultat->free();
  $mysqli->close();
  if ( $_SESSION['autorisation'] == 0 )
    exit('{"etat":"nok","message":"Mauvais couple identifiant/mot de passe"}');
  // Si paramètre "connexion", connexion initiale (bouton "connexion" ou login.php),
  // suivie d'un rechargement immédiat : terminaison et $_SESSION['message']
  if ( isset($_REQUEST['connexion']) )
    exit($_SESSION['message'] = '{"etat":"ok","message":"Connexion réussie"}');
}
// Connexion light automatique par cookie
elseif ( isset($_COOKIE['CDP_SESSION_PERM']) && preg_match('/^\w{10}$/',$_COOKIE['CDP_SESSION_PERM']) )  {
  $mysqli = connectsql();
  // Récupération du compte dans la base de données
  $resultat = $mysqli->query("SELECT * FROM utilisateurs WHERE mdp > '0' AND permconn = '${_COOKIE['CDP_SESSION_PERM']}'");
  $mysqli->close();
  if ( $resultat->num_rows )  {
    enregistre_session($resultat->fetch_assoc(),true);
    $resultat->free();
  }
  // Suppression du cookie s'il ne correspond pas à un compte
  else 
    setcookie('CDP_SESSION_PERM','',time()-3600,$chemin,$domaine,true);
}
$autorisation = $_SESSION['autorisation'] ?? 0;
// Destruction du cookie de session si non connecté
if ( !$autorisation )
  setcookie('CDP_SESSION','',time()-3600,$chemin,$domaine,true);

////////////////////////////////////////////////////////////////////////////////
////////////////////// Mise à jour de la base de données ///////////////////////
////////////////////////////////////////////////////////////////////////////////

// Fonction d'envoi de requêtes MySQL et d'enregistrement
//  * sauvegarde une fois par mois la table complète
//  * enregistre la requête
//  * exécute la requête
//  * renvoie le résultat de l'exécution
function requete($table,$requete,$mysqli)  {
  if ( is_dir($rep = 'sauvegarde') && is_executable('sauvegarde') && is_writable('sauvegarde') )  {
    // Sauvegarde de la table complète une seule fois par mois
    $mois = date('Y-m');
    $heure = date('d/m/Y à H:i:s');
    if ( !file_exists("$rep/$table.$mois.php") )  {
      $s = <<<FIN
  <?php exit(); ?>
-- Sauvegarde complète de la table $table le $heure
TRUNCATE `$table`; 
FIN;
      $resultat = $mysqli->query("SHOW COLUMNS FROM `$table`");
      $s1 = "INSERT INTO $table (";
      while ( $r = $resultat->fetch_row() )
        $s1 .= "`${r[0]}`,";
      $s1 = substr($s1,0,-1).') VALUES';
      $resultat->free();
      // Récupération des données
      $resultat = $mysqli->query("SELECT * FROM `$table`");
      if ( $resultat->num_rows )  {
        while ( $r = $resultat->fetch_row() )
          $s1 .= "\n  ('".  str_replace('SEPARATEUR','\',\'',addslashes(implode('SEPARATEUR',$r)))  .'\'),';
        $s1 = substr($s1,0,-1).';';
        $resultat->free();
      }
      else
        $s1 = '-- Table vide !';
      $fichier = fopen("$rep/$table.$mois.php",'wb');
      fwrite($fichier, "$s\n$s1\n");
    }
    else
      $fichier = fopen("$rep/$table.$mois.php",'ab');
    // Éxécution de la requête
    $resultat = $mysqli->query($requete);
    // Sauvegarde systématique de la requête
    $insert = ( $mysqli->insert_id ) ? ' (identifiant '.$mysqli->insert_id.')' : '';
    if ( isset($_SESSION['login'])  )
      fwrite($fichier, "\n-- Requête de ${_SESSION['login']} (${_SESSION['ip']}) le $heure\n$requete; -- ".$mysqli->affected_rows." ligne(s) affectée(s)$insert\n");
    else  {
      $login = ( isset($GLOBALS['utilisateur']) ) ? $GLOBALS['utilisateur']['login'] : $GLOBALS['login'];
      fwrite($fichier, "\n-- Requête de $login (${_SERVER['REMOTE_ADDR']}) le $heure\n$requete; -- ".$mysqli->affected_rows." ligne(s) affectée(s)$insert\n");
    }
    fclose($fichier);
  }
  return $resultat;
}

// Fonction de mise à jour des informations récentes
// $type : 1->informations, 2->programmes de colles, 3->documents, 4->agenda, 5->devoir
// $id : celui de l'information/le programme de colles/le document/l'événement/le devoir
// $prop : tableau qui contient matiere (ou 0), titre, lien, texte, protection, publi
//         complet si ajout dans la base, incomplet si mise à jour
//         les chaines titre, lien et texte doivent être échappées
//         la valeur publi permet de positionner des infos dans le futur
// $publi : true si publication de la date, pour les modifications (champ maj)
function recent($mysqli,$type,$id,$prop=array(),$publi=true)  {

  // Ajout dans la base de données
  if ( count($prop) >= 5 )
    requete('recents',"INSERT INTO recents SET id=$id, type=$type, publi = ".( ( isset($prop['publi']) && $prop['publi'] ) ? "'${prop['publi']}'" : 'NOW()' ).", matiere = '${prop['matiere']}', titre = '${prop['titre']}', lien = '${prop['lien']}', texte = '${prop['texte']}', protection = '${prop['protection']}'",$mysqli);
  // Suppression de la base
  elseif ( !$prop )  {
    // Récupération des anciennes propriétés ; rien à faire si n'existe pas
    $resultat = $mysqli->query("SELECT matiere, protection FROM recents WHERE id = $id AND type = $type");
    if ( !$resultat->num_rows )
      return true;
    $prop = $resultat->fetch_assoc();
    $resultat->free();
    requete('recents',"DELETE FROM recents WHERE id = $id AND type = $type",$mysqli);
  }
  // Modification
  else  {
    // Récupération des anciennes propriétés
    $resultat = $mysqli->query("SELECT matiere, titre, lien, texte, protection FROM recents WHERE id = $id AND type = $type");
    if ( $resultat->num_rows )  {
      $anciennesprop = $resultat->fetch_assoc();
      $resultat->free();
      // Construction de la requête et exécution
      $requete = ( $publi || ( isset($prop['protection']) && ( $prop['protection'] == 32 ) ) ) ? ', maj = NOW()' : '';
      foreach ($prop as $champ=>$val)
        $requete .= ", $champ = '$val'";
      $requete = substr($requete,1);
      requete('recents',"UPDATE recents SET $requete WHERE id = $id AND type = $type",$mysqli);
    }
    // Si l'élément n'est pas dans la base, il faut le reconstruire
    // Ne devrait pas servir, à moins d'une erreur dans la base
    else  {
      if ( $type == 1 )
        $resultat = $mysqli->query("SELECT mat AS matiere, CONCAT('.?',p.cle) AS lien, texte, i.protection,
                                           CONCAT( IF(LENGTH(i.titre),i.titre,'Information'), IF(p.id>1,CONCAT(' [',IF(mat=0,'',CONCAT(m.nom,'/')),p.nom,']'),'') ) AS titre
                                    FROM infos AS i LEFT JOIN pages AS p ON page=p.id LEFT JOIN matieres AS m ON mat=m.id WHERE i.id = $id");
      elseif ( $type == 2 )
        $resultat = $mysqli->query("SELECT matiere, CONCAT('Colles du ',DATE_FORMAT(debut,'%e/%m'),' en ',nom) AS titre,
                                           CONCAT('colles?',cle,'&amp;n=',semaine) AS lien, texte, colles_protection AS protection
                                    FROM colles LEFT JOIN matieres ON matiere=matieres.id LEFT JOIN semaines ON semaine=semaines.id
                                    WHERE colles.id = $id");
      elseif ( $type == 3 )
        $resultat = $mysqli->query("SELECT d.matiere, d.nom AS titre, 'download?id=$id' AS lien, d.protection, 
                                           CONCAT(ext,'|',taille,'|',d.parent,'|',GROUP_CONCAT( r.nom ORDER BY FIND_IN_SET(r.id,d.parents) SEPARATOR '/' )) AS texte
                                    FROM docs AS d LEFT JOIN reps AS r ON FIND_IN_SET(r.id,d.parents) WHERE d.id = $id");
      elseif ( $type == 4 )
        $resultat = $mysqli->query("SELECT matiere, CONCAT(SUBSTRING(debut,9,2),'/',SUBSTRING(debut,6,2),' - ',t.nom,IF(matiere>0, CONCAT(' en ',m.nom),'')) AS titre,
                                           CONCAT('agenda?mois=',SUBSTRING(debut,3,2),SUBSTRING(debut,6,2)) AS lien, texte, 0 AS protection
                                    FROM agenda LEFT JOIN matieres AS m ON matiere = m.id LEFT JOIN `agenda-types` AS t ON type = t.id WHERE agenda.id = $id");
      $prop = array_map(array($mysqli,'real_escape_string'),$resultat->fetch_assoc());
      $resultat->free();
      requete('recents',"INSERT INTO recents SET id=$id, type=$type, publi = NOW(), matiere = ${prop['matiere']}, titre = '${prop['titre']}', lien = '${prop['lien']}', texte = '${prop['texte']}', protection = ${prop['protection']}",$mysqli);
    }
  }
  // Mise dans l'ordre, les plus récents en premier
  $mysqli->query('ALTER TABLE recents ORDER BY publi DESC');
  // Sélection des matières concernées pour la régénération des flux
  $matieres = ( !isset($prop['matiere']) || !isset($anciennesprop['matiere']) ) ? ( $prop['matiere'] ?? $anciennesprop['matiere'] ) : array( $prop['matiere'], $anciennesprop['matiere'] );
  // Sélection des autorisations concernées pour la régénération des flux
  // Si $prop['protection'] seule existe, c'est un ajout ou la modification d'une autre propriété
  // Si $ancienne['protection'] seule existe, c'est une suppression
  // Dans les deux cas, on modifie les flux correspondant, aucun si 32.
  if ( !isset($prop['protection']) || !isset($anciennesprop['protection']) )  {
    // Si ajout/suppression avec protection=32, rien à faire pour les flux RSS
    if ( ( $protection = $prop['protection'] ?? $anciennesprop['protection'] ) == 32 )
      return true;
  }
  // Si c'est une modification de protection, les deux existent
  else
    $protection = ( $prop['protection'] && $anciennesprop['protection'] ) ? $prop['protection']^$anciennesprop['protection'] : 0;
  // Regénération des flux
  rss($mysqli,$matieres,$protection);
  return true;
}

// Fonction de mise à jour des flux RSS
// $matieres doit être soit zéro (toutes matières) ou un identifiant de matière
// $protection correspond à la protection de la nouveauté/mise à jour
// Si $protection est nul, il faut remettre à jour tous les flux
function rss($mysqli,$matieres,$protection)  {
  if ( !is_array($matieres) )
    $requete = " OR FIND_IN_SET($matieres,matieres)";
  else  {
    $requete = '';
    foreach ( $matieres as $matiere ) 
      $requete .= " OR FIND_IN_SET($matiere,matieres)";
  }
  if ( $protection )  {
    // $protection est définie comme expliqué avant la fonction acces, ci-dessous.
    // 32-$protection donne les autorisations, mais il faut retourner la chaîne
    // et la décaler d'un cran, avant de récupérer les indices des 1 présents.
    $autorisations = array_keys(str_split('0'.strrev(decbin(32-$protection))),1);
    foreach ( $autorisations as $autorisation ) 
      $requete .= " OR autorisation = $autorisation";
    $combinaisons = array();
  }
  else 
    $combinaisons = array('0|toutes');
  // Récupération de toutes les combinaisons autorisation-matières possibles
  $resultat = $mysqli->query('SELECT CONCAT(autorisation,\'|\',matieres) FROM utilisateurs WHERE '.substr($requete,4));
  while ( $r = $resultat->fetch_row() )
    $combinaisons[] = $r[0];
  $resultat->free();
  $combinaisons = array_unique($combinaisons);

  // Préambule du flux RSS - Titre du flux : titre du site
  $resultat = $mysqli->query('SELECT titre FROM pages WHERE id = 1');
  $r = $resultat->fetch_row();
  $resultat->free();
  $titre = $r[0];
  $d = date(DATE_RSS);
  $site = "https://${GLOBALS['domaine']}${GLOBALS['chemin']}";
  $preambule = <<<FIN
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>$titre</title>
    <atom:link href="${site}REP/rss.xml" rel="self" type="application/rss+xml" />
    <link>$site</link>
    <description>$titre - Flux RSS</description>
    <lastBuildDate>$d</lastBuildDate>
    <language>fr-FR</language>


FIN;

  // Log
  $mois = date('Y-m');
  $heure = date('d/m/Y à H:i:s');
  if ( file_exists("rss/log.$mois.php") )
    $fichierlog = fopen("rss/log.$mois.php",'ab');
  else  {
    $fichierlog = fopen("rss/log.$mois.php",'wb');
    fwrite($fichierlog,'<?php exit(); ?>');
  }
  fwrite($fichierlog, "\n-- Génération le $heure -- ".json_encode($matieres)."\n");

  // Génération pour les différentes combinaisons autorisation-matières
  foreach ( $combinaisons as $c )  {
    list($autorisation,$matieres) = explode('|',$c);
    $requete = ( $autorisation ) ? "FIND_IN_SET(matiere,'$matieres') AND (".requete_protection($autorisation).')' : 'protection = 0';
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
    // Vérification des éventuels affichages différés
    $debut = '';
    $resultat = $mysqli->query("SELECT UNIX_TIMESTAMP(publi) AS publi FROM recents WHERE $requete AND publi > NOW() ORDER BY publi LIMIT 1");
    if ( $resultat->num_rows )  {
      $debut = '<?php if ( time() > '.($resultat->fetch_row()[0])." )  { define('OK',1); \$c='$c'; include('../../genere_rss.php'); exit(); } ?>\n";
      $resultat->free();
    }
    // Mise à jour du flux RSS
    $rep = 'rss/'.substr(sha1("?!${GLOBALS['mdp']}|$c"),0,20);
    if ( !is_dir($rep) )
      mkdir($rep,0777,true);
    $fichier = fopen($rep.'/rss.xml','wb');
    fwrite($fichier, $debut.str_replace('REP',$rep,$preambule).$rss."  </channel>\n</rss>\n");
    fclose($fichier);

    // Log
    fwrite($fichierlog, "  $c $rep\n");
  }
  fclose($fichierlog);
  return true;
}

// Fonction PHP pour le stockage dans la base MySQL de l'ordre "naturel" (1,2,10,11 et non 1,10,11,2)
// Remplace tout nombre par un nombre égal mais écrit sur 10 chiffres, complété par des zéros à gauche
// Utilisé pour les documents et les groupes
function zpad($s) {
  return preg_replace_callback('/(\d+)/', function($m){
    return(str_pad($m[1],10,'0',STR_PAD_LEFT)); }
  , $s);
}

////////////////////////////////////////////////////////////////////////////////
////////////////////////// Gestion des autorisations ///////////////////////////
////////////////////////////////////////////////////////////////////////////////

// Fonction d'accès en lecture ou en écriture
// Retourne true si accès en mode édition (professeur de la matière)
// Retourne faux si accès en mode lecture uniquement (autre utilisateur autorisé)
// Affiche une page de connexion si besoin (et arrête l'exécution)
// Affiche une page d'interdiction et arrête l'exécution sinon
// $protection est une valeur numérique.
// * si $protection = 0, accès autorisé sans connexion identifiée.
// * si 0 < $protection < 33, $protection est la représentation décimale de la
// valeur binaire PLCEI (types d'utilisateurs : professeurs, lycée, colleurs,
// élèves, invités) à laquelle on a ajouté 1.
// Chaque 0 correspond aux accès autorisés, chaque 1 correspond aux protections
// (accès interdit pour ce type de compte).
// Exemple : 10 -> PLCEI=9=01001 -> autorisé pour P,C,E et interdit pour L et I.
// Le code 32 (interdit pour tous) correspond aux docs/reps/... non visibles.
// * si $protection = 32, page non affichée dans le menu, non visible sauf pour
// les professeurs associés à la matière
// $matiere est la matière associée à la page à afficher
// $titre est le titre de la page affichée si accès refusé ou connexion demandée
// $actuel est le lien du menu si accès refusé ou connexion demandée
function acces($protection,$matiere,$titre,$actuel,$mysqli)  {
  $autorisation = $_SESSION['autorisation'] ?? 0;
  // Mode édition
  if ( ( $autorisation == 5 ) && in_array($matiere,explode(',',$_SESSION['matieres'])) )
    return !isset($_REQUEST['mode_lecture']);
  if ( $protection == 0 )
    return false; // accès autorisé sans condition
  // Si page protégée et utilisateur non connecté : connexion demandée
  // login.php contient $mysqli->close() et fin()
  if ( $autorisation == 0 )
    include('login.php');
  // À partir d'ici, on a un utilisateur connecté
  // On affiche seulement si :
  // * pour les professeurs, tout contenu autorisé d'une autre matière
  // * pour les autres, uniquement les contenus à la fois autorisés et de
  // matière associée
  if ( ( $autorisation == 5 ) && ( $protection < 17 ) || in_array($matiere,explode(',',$_SESSION['matieres'])) && ( ( ($protection-1)>>($autorisation-1) & 1 ) == 0 ) )
    return false;
  // Accès non autorisé
  debut($mysqli,$titre,'Vous n\'avez pas accès à cette page.',$autorisation,$actuel);
  $mysqli->close();
  fin();
}
// Version simplifiée pour le test d'éléments seuls, matière déjà vérifiée
// Retourne true si accès autorisé, false si non
function accestest($protection,$autorisation)  {
  if ( ( $protection == 0 ) || ( $autorisation == 5 ) )  return true;
  if ( $autorisation == 0 )  return false;
  if ( ( ($protection-1)>>($autorisation-1) & 1 ) == 0 )  return true;
  return false;
}
// Définition de la chaîne de protection mysql pour récupérer les éléments,
// matière déjà vérifiée, sans mode édition. 
// Pour index.php, agenda.php, cdt.php, docs.php
function requete_protection($autorisation)  {
  return $autorisation ? "( protection = 0 ) OR ( ( (protection-1)>>($autorisation-1) & 1 ) = 0 )" : 'protection = 0';
}

////////////////////////////////////////////////////////////////////////////////
//////////////////////////////// Affichage HTML ////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

// En-têtes HTML et début de page
function debut($mysqli,$titre,$message,$autorisation,$actuel=false,$matiere=false,$css=false)  {
  // Menu seulement si $actuel non vide
  if ( $actuel )  {
    
    // Requête partielles de récupération
    // Non connecté : on voit tout ce qui est non vide et non désactivé
    // Invité/Élèves/Colleurs/Lycée : on voit ce qui est non vide et
    // autorisé (matière associée et protection correcte)
    // Prof : on voit tout ce qui est non vide et autorisé pour les matières non
    // associées, toutes les pages et reps pour les matières associées, tout ce
    // qui n'est pas désactivé (colles,cdt,docs) pour les matières associées
    switch ( $autorisation )  {
      case 5:
        $requete_matieres = '';
        $requete_pages = "protection < 17 OR FIND_IN_SET(mat,'${_SESSION['matieres']}')";
        $requete_reps = "protection < 17 OR FIND_IN_SET(matiere,'${_SESSION['matieres']}')";
        $requete_collesdocscdt = "X_protection < 17 OR X_protection < 32 AND FIND_IN_SET(m.id,'${_SESSION['matieres']}')";
        $requete_notes = "notes < 2 AND FIND_IN_SET(m.id,'${_SESSION['matieres']}')";
        $requete_copies = "copies < 2 AND FIND_IN_SET(m.id,'${_SESSION['matieres']}')";
        $requete_rss = "protection < 17 OR FIND_IN_SET(matiere,'${_SESSION['matieres']}')";
        break;
      case 0:
        $requete_matieres = '';
        $requete_pages = $requete_reps = "protection < 32";
        $requete_collesdocscdt = "X_protection < 32";
        $requete_notes = $requete_copies = '0';
        $requete_rss = 'protection = 0';
        break;
      default:
        $requete_matieres = "WHERE FIND_IN_SET(m.id,'${_SESSION['matieres']}')";
        $requete_pages = $requete_reps = "protection = 0 OR ( (protection-1)>>($autorisation-1) & 1 ) = 0";
        $requete_collesdocscdt = "( X_protection = 0 OR ( (X_protection-1)>>($autorisation-1) & 1 ) = 0 )";
        $requete_notes = ( ( $autorisation == 4 || $autorisation == 1 ) ? '0' : 'notes < 2' );
        $requete_copies = ( ( $autorisation == 2 ) ? 'copies = 1' : '0' );
        $requete_rss = "FIND_IN_SET(matiere,'${_SESSION['matieres']}') AND ( protection = 0 OR ( (protection-1)>>($autorisation-1) & 1 ) = 0 )";
    }
    
    // Icônes principales (accueil, agenda, impression, rss)
    $recents = $_SESSION['recents'] ?? '';
    $icones = <<<FIN
    <a class="icon-menu" title="Afficher le menu"></a>
    <a class="icon-accueil" href="." title="Revenir à la page d'accueil"></a>
    <a class="icon-recent" href="recent" title="Voir les $recents nouveaux contenus">$recents</a>
    <a class="icon-agenda" href="agenda" title="Agenda"></a>
    <a class="icon-imprime" title="Imprimer cette page" onclick="window.print();return false;"></a>

FIN;
    // Notes (colleurs et profs)
    if ( ( $autorisation == 3 ) || ( $autorisation == 5 ) )  {
      $resultat = $mysqli->query("SELECT cle FROM matieres WHERE FIND_IN_SET(id,'${_SESSION['matieres']}') AND notes < 2");
      if ( $resultat->num_rows == 1 )  {
        $icones .= '    <a class="icon-notes" href="notes?'. $resultat->fetch_row()[0] ."\" title=\"Ajouter des notes de colles\"></a>\n";
        $resultat->free();
      }
    }
    // Préférences et mails (tous types de comptes sauf invité)
    if ( $autorisation > 1 )  {
      $icones .= "    <a class=\"icon-prefs\" href=\"prefs\" title=\"Gérer ses préférences\"></a>\n";
      // Autorisation d'envoi de courriel
      $resultat2 = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
      if ( $resultat2->fetch_row()[0] >> 4*($autorisation-2) & 15 )
        $icones .= "    <a class=\"icon-mail\" href=\"mail\" title=\"Envoyer un courriel\"></a>\n";
      $resultat2->free();
      if ( $_SESSION['compteglobal'] )
        $icones .= "    <a class=\"icon-echange\" title=\"Changer de Cahier\"></a>\n";
    }
    // Connexion/Déconnexion
    $icones .= ( ( $autorisation ) ? '    <a class="icon-deconnexion" title="Se déconnecter"></a>' : '    <a class="icon-connexion" title="Se connecter"></a>' );
    
    ////////////////////////////
    // Menu : pages générales //
    ////////////////////////////
    
    // Pages d'information générales
    $resultat = $mysqli->query("SELECT cle, nom FROM pages WHERE mat = 0 AND id > 1 AND ( $requete_pages ) ORDER BY ordre");
    $menu = "    <hr>\n";
    if ( $resultat->num_rows )  {
      while ( $r = $resultat->fetch_assoc() )
        $menu .= "    <a href=\".?${r['cle']}\">${r['nom']}</a>\n";
    }
    $resultat->free();
    // Page de téléchargement
    $menu .= "    <a href=\"docs\"><span class=\"icon-rep\"></span>&nbsp;Documents à télécharger</a>\n";
    $resultat = $mysqli->query("SELECT id, nom FROM reps WHERE matiere = 0 AND menu = 1 AND ( $requete_reps )");
    if ( $resultat->num_rows )  {
      while ( $r = $resultat->fetch_assoc() )
        $menu .= "    <a class=\"menurep\" href=\"docs?rep=${r['id']}\">${r['nom']}</a>\n";
      $resultat->free();
    }

    /////////////////////
    // Menu : matières //
    /////////////////////
    
    // Récupération et affichage des matières
    $resultat = $mysqli->query('SELECT * FROM (
                                  SELECT m.id, m.cle, m.nom,
                                  ( colles = 1 AND '.str_replace('X','colles',$requete_collesdocscdt).' ) AS colles,
                                  (   docs = 1 AND '.str_replace('X',  'docs',$requete_collesdocscdt).' ) AS docs,
                                  (    cdt = 1 AND '.str_replace('X',   'cdt',$requete_collesdocscdt).' ) AS cdt,
                                  ( '.$requete_notes.' ) AS notes,
                                  ( '.$requete_copies.' ) AS copies,
                                  GROUP_CONCAT(CONCAT(m.cle,"/",p.cle) SEPARATOR "//") AS pcle, GROUP_CONCAT(p.nom SEPARATOR "//") AS pnom
                                  FROM matieres AS m LEFT JOIN (
                                    SELECT * FROM pages WHERE '.$requete_pages.'
                                  ) AS p ON p.mat = m.id
                                  '.$requete_matieres.'
                                  GROUP BY m.id ORDER BY m.ordre, p.ordre
                                ) AS t WHERE colles + docs + cdt + notes > 0 OR pnom IS NOT NULL');
    if ( $resultat->num_rows )  {
      while ( $r = $resultat->fetch_assoc() )  {
        $edition = ( $autorisation == 5 ) && ( in_array($r['id'],explode(',',$_SESSION['matieres'])) );
        $menu .= "    <h3>${r['nom']}</h3>\n";
        if ( !is_null($r['pcle']) )  {
          $pcle = explode('//',$r['pcle']);
          $pnom = explode('//',$r['pnom']);
          $nom = $pnom[0];
          foreach ( $pcle as $cle )  {
            $menu .= "    <a href=\".?$cle\">$nom</a>\n";
            $nom = next($pnom);
          }
        }
        if ( $r['colles'] )
          $menu .= "    <a href=\"colles?${r['cle']}\"><span class=\"icon-colles\"></span>&nbsp;Programme de colles</a>\n";
        if ( $r['docs'] )  {
          $menu .= "    <a href=\"docs?${r['cle']}\"><span class=\"icon-rep\"></span>&nbsp;Documents à télécharger</a>\n";
          $resultat_doc = $mysqli->query("SELECT id, nom FROM reps WHERE matiere = ${r['id']} AND menu = 1 AND ( $requete_reps )");
          if ( $resultat_doc->num_rows )  {
            while ( $d = $resultat_doc->fetch_assoc() )
              $menu .= "    <a class=\"menurep\" href=\"docs?rep=${d['id']}\">${d['nom']}</a>\n";        
            $resultat_doc->free();
          }
        }
        if ( $r['cdt'] )  {
          $menu .= "    <a href=\"cdt?${r['cle']}\"><span class=\"icon-cdt\"></span>&nbsp;Cahier de texte</a>\n";
          if ( $edition && ( substr($actuel,0,3) == 'cdt' ) && ( substr($actuel,-strlen($r['cle'])-1) == "?${r['cle']}" ) )
            $menu .= "    <a class=\"menurep\" href=\"cdt-seances?${r['cle']}\">Types de séances</a>\n    <a class=\"menurep\" href=\"cdt-raccourcis?${r['cle']}\">Raccourcis de séances</a>\n";
        }
        if ( $r['notes'] )
          $menu .= "    <a href=\"notes?${r['cle']}\"><span class=\"icon-notes\"></span>&nbsp;Notes de colles</a>\n";
        if ( $r['copies'] )
          $menu .= "    <a href=\"copies?${r['cle']}\"><span class=\"icon-copies\"></span>&nbsp;Transfert de copies</a>\n";
      }
      $resultat->free();
    }

    ////////////////////
    // Administration //
    ////////////////////

    // Relève des colles, compte lycée uniquement
    if ( $autorisation == 4 )
      $menu .= "    <hr>\n    <a href=\"relevenotes\">Relève des notes</a>\n";
    // Liens d'édition, professeurs seulement
    if ( $autorisation == 5 )
      $menu .= <<<FIN
    <h3>Gestion du site</h3>
    <a href="pages">Les pages</a>
    <a href="matieres">Les matières</a>
    <a href="utilisateurs">Les utilisateurs</a>
    <a href="utilisateurs-matieres">Les associations utilisateurs-matières</a>
    <a href="utilisateurs-mails">Les courriels</a>
    <a href="groupes">Les groupes</a>
    <a href="planning">Le planning annuel</a>

FIN;
    // Menu final
    $menu = <<<FIN
<nav>
  <div id="iconesmenu">
$icones
  </div>
  <div id="menu">
$menu  </div>
</nav>
FIN;
    $menu = str_replace("href=\"$actuel\"","id=\"actuel\" href=\"$actuel\"",$menu);
  }
  else
    $menu = '';
  
  //////////
  // HTML //
  //////////
  
  // Message si non vide
  if ( strlen($message) )
    $message = "  <div class=\"warning\">$message</div>\n";
  elseif ( ( $autorisation == 5 ) && ( basename($_SERVER['PHP_SELF']) == 'index.php' ) )  {
    $resultat = $mysqli->query('SELECT id FROM utilisateurs WHERE mdp LIKE "*%"');
    if ( $n = $resultat->num_rows )  {
      $resultat->free();
      $n = ( $n > 1 ) ? "$n comptes" : "1 compte";
      $message = '  <div class="warning">Il y a actuellement '.(( $n > 1 ) ? "$n comptes" : "1 compte").' en attente de validation de votre part. C\'est assez urgent... Il faut aller sur la page de gestion des <a href="utilisateurs">utilisateurs</a> pour les valider.</div>';
    }
  }
  // Flux RSS
  $rss = substr(sha1( ( $autorisation ) ? "?!${GLOBALS['mdp']}|$autorisation|${_SESSION['matieres']}" : "?!${GLOBALS['mdp']}|0|toutes" ),0,20);
  // Token CSRF
  $token = ( isset($_SESSION['csrf-token']) ) ? " data-csrf-token=\"${_SESSION['csrf-token']}\"" : '';
  // Matière
  $matiere = ( $matiere ) ? " data-matiere=\"$matiere\"" : '';
  // Titre dans le head : <span> pouvant indiquer la protection en mode édition
  $head = strtok($titre,'<');
  // Fichiers CSS supplémentaires
  $css = ( $css ) ? "\n  <link rel=\"stylesheet\" href=\"css/$css.min.css\">" : '';
  // Affichage
  echo <<<FIN
<!doctype html>
<html lang="fr">
<head>
  <title>$head</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
  <link rel="stylesheet" href="css/style.min.css?v=920">
  <link rel="stylesheet" href="css/icones.min.css?v=920">$css
  <script type="text/javascript" src="js/jquery.min.js"></script>
  <link rel="alternate" type="application/rss+xml" title="Flux RSS" href="rss/$rss/rss.xml">
</head>
<body$token$matiere>

<header><h1>$titre</h1></header>

$menu

<section>
$message
FIN;
}

// Bas de page
function fin($edition = false, $mathjax = false)  {
  // MathJax chargé seulement si besoin
  $mathjax = ( $mathjax ) ? '
<script type="text/javascript" src="/MathJax/MathJax.js?config=TeX-AMS-MML_HTMLorMML"></script>
<script type="text/x-mathjax-config">MathJax.Hub.Config({tex2jax:{inlineMath:[["$","$"],["\\\\(","\\\\)"]]}});</script>' : '';
  // Édition possible si $edition est true
  $js = ( $edition ) ? '<script type="text/javascript" src="js/edition.min.js?v=920"></script>' : '<script type="text/javascript" src="js/fonctions.min.js?v=920"></script>';
  // Affichage de message si $_SESSION['message']
  if ( isset($_SESSION['message']) )  {
    $m = json_decode($_SESSION['message'],true);
    $m = "\n<script type=\"text/javascript\">$( function() { affiche(\"${m['message']}\",'${m['etat']}'); });</script>";
    unset($_SESSION['message']);
  }
  else
    $m = '';
  echo <<<FIN

</section>

<footer>Ce site est réalisé par le logiciel <a href="http://cahier-de-prepa.fr">Cahier de prépa</a>, publié sous <a href="Licence_CeCILL_V2-fr.html">licence libre</a>.
</footer>
<div id="load"><img src="js/ajax-loader.gif"></div>

$js$mathjax$m

</body>
</html>
FIN;
  exit();
}

// Affichage des semaines
function format_date($date)  {
  $semaine = array('dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi');
  $mois = array('','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre');
  return $semaine[substr($date,0,1)].' '.substr($date,7).( ( substr($date,7) == '1' ) ? 'er' : '' ).' '.$mois[intval(substr($date,5,2))].' '.substr($date,1,4);
}

?>
