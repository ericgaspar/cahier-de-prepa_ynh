<?php
// Script d'installation de Cahier de Prépa
// Aide à l'installation et à la configuration
// Vérifie l'existence de la base de données et des répertoires

// Headers HTML
$header = <<<FIN
<!doctype html>
<html lang="fr">
<head>
  <title>Cahier de Prépa&nbsp;: installation</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
  <link rel="stylesheet" href="css/style.min.css">
  <link rel="stylesheet" href="css/icones.min.css">
  <script type="text/javascript" src="js/jquery.min.js"></script>
  <style>
    p.ligne { padding: 0 2%; }
    .warning { margin: 0 auto; }
    .general { font-size:1.7em; top:-2.8em; }
    .ok, .nok { padding: 1em 2%; }
  </style>
</head>
<body>
<header><h1>Cahier de Prépa&nbsp;: installation</h1></header>
<section>

FIN;
$footer = <<<FIN

</section>
</body>
</html>
FIN;

// Recharge de la page si elle est lancée par un autre script (donc config.php déjà appelé)
if ( defined('OK') )  {
  // Si $chemin est vide : config.php n'a pas été modifié correctement
  if ( !$domaine )
    exit($header.'<h3 class="warning">Le fichier de configuration n\'existe pas, n\'est pas lisible ou est mal rempli.</h3><article><p>Il doit s\'appeler <code>config.php</code>, se trouver au même endroit que l\'ensemble des fichiers constituant le site et être lisible par l\'utilisateur d\'Apache (<code>'.`whoami | tr -d '\n'`.'</code>).</p><p>Vous devez l\'éditer pour y mettre les bonnes valeurs.</p></article>'.$footer);
  header("Location: https://$domaine${chemin}installation");
  exit();
}

///////////////////////////////
// Première vérifications, bloquantes : le fichier de configuration est ok
///////////////////////////////
if ( file_exists('config.php') && is_readable('config.php') )
  include('config.php');
else
  exit($header.'<h3 class="warning">Le fichier de configuration n\'existe pas ou n\'est pas lisible.</h3><article><p>Il doit s\'appeler config.php, se trouver au même endroit que l\'ensemble des fichiers constituant le site et être lisible par l\'utilisateur d\'Apache (<code>'.`whoami | tr -d '\n'`.'</code>).</p></article>'.$footer);
// Vérification des données du fichier de configuration
if ( !isset($domaine) || !isset($chemin) || !isset($serveur) || !isset($base) || !isset($mdp) )
  exit($header.'<h3 class="warning">Le fichier de configuration est incomplet.</h3><article><p>Il manque des données nécessaire au fonctionnement du site dans le fichier <code>config.php</code>. Il faut l\'éditer et le modifier manuellement.</p></article>'.$footer);
if ( strlen($base) > 12 )
  exit($header.'<h3 class="warning">Le nom de la base de données est trop long.</h3><article><p>Le nom de la base de données ne doit pas être supérieur à 12 caractères. Il faut corriger manuellement cela dans le fichier de configuration  <code>config.php</code>.</p></article>'.$footer);

///////////////////////////////
// Connexion obligatoire à partir d'ici, en entrant le mot de passe du fichier de configuration
///////////////////////////////
session_name('CDP_SESSION_INSTALLATION');
session_set_cookie_params(0,$chemin,$domaine,true);
session_start();
if ( isset($_REQUEST['motdepasse']) && ( $_REQUEST['motdepasse'] == $mdp ) )  {
  // Interdiction de garder son identifiant de session
  session_regenerate_id(true);
  $_SESSION = array();
  // Pour vérification aux connexions ultérieures
  $_SESSION['cahier'] = "$domaine$chemin";
  $_SESSION['client'] = $_SERVER['HTTP_USER_AGENT'];
  $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
  $_SESSION['time'] = time()+600;
}
elseif ( !isset($_SESSION['cahier']) || ( $_SESSION['cahier'] != "$domaine$chemin" ) || ( $_SESSION['time'] < time() ) || isset($_REQUEST['deconnexion']) || ( $_SESSION['client'] != $_SERVER['HTTP_USER_AGENT'] ) || ( $_SESSION['ip'] != $_SERVER['REMOTE_ADDR'] ) )  {
  // Suppression du cookie et des données de session
  $_SESSION = array();
  setcookie(session_name(),'',time()-3600,$chemin,$domaine,true);
  session_regenerate_id(true);
  echo <<<FIN
$header
  <article>
    <h3>Bienvenue sur <a href="https://cahier-de-prepa.fr">Cahier de Prépa</a> et merci d'essayer ce gestionnaire de site&nbsp;!</h3>
    <p><a href="https://cahier-de-prepa.fr">Cahier de Prépa</a> est un gestionnaire de sites web pour la communication des professeurs vers leurs élèves de CPGE. Il permet de&nbsp;:</p>
    <ul>
      <li>Donner aux élèves un lien unique regroupant les informations de toutes les matières</li>
      <li>Faire passer des informations de façon rapide (changement d'horaires, erratum de polycopié, petites choses oubliées...)</li>
      <li>Disposer d'informations de façon un peu plus pérenne, sur un nombre de pages sans limite, pages associées à une matière ou non</li>
      <li>Posséder un calendrier propre à la classe</li>
      <li>Annoncer les programmes de colles (pour les élèves et les colleurs)</li>
      <li>Tenir à jour le cahier de texte numérique</li>
      <li>Mettre à disposition des documents, distribués ou non</li>
      <li>Saisir les notes de colles (colleurs), Consulter ses notes de colles (élèves), Consulter la synthèse des notes de colles (professeurs), Relever les notes (administration)</li>
      <li>Envoyer des mails à l'ensemble des élèves/colleurs/professeurs ou à certains seulement, de façon très paramétrable</li>
      <li>Restreindre l'accès de façon sure aux élèves ou aux colleurs, et indépendamment pour chaque ressource et chaque matière</li>
      <li>Obtenir rapidement les nouveaux contenus du site, sur chaque page ou par flux RSS</li>
      <li>Ajouter des formules en LaTeX directement sur le site</li>
    </ul>
  </article>

  <article>
    <a class="icon-ok" onclick="$('form').submit();" title="Envoyer le mot de passe"></a>
    <form action="" method="post">
      <p>Pour commencer l'installation, vous devez saisir ci-dessous <strong>le mot de passe contenu dans le fichier de configuration</strong>&nbsp;:</p>
      <p><input class="ligne" type="password" name="motdepasse"></p>
    </form>
  </article>

FIN;
  exit($footer);
}
// Tout est ok : session valide pendant 10 minutes
else
  $_SESSION['time'] = time()+600;

////////////////////////////
// Fabrication de la base //
////////////////////////////

// Vérification de la connexion. Pas de connexion signifie qu'il faut réaliser l'installation
$mysqli = new mysqli($serveur,$base,$mdp);
if ( $mysqli->connect_errno || !($mysqli->select_db($base)) )  {
  $message = '';
  
  //////////////////////////////
  // Récupération des données //
  //////////////////////////////
  if ( isset($_REQUEST['mdproot']) )  {
    // Vérification du mot de passe root
    $mysqli = new mysqli($serveur,'root',$_REQUEST['mdproot'],'mysql');
    if ( $mysqli->connect_errno )
      $message = '<p class="warning">La connexion à la base de données est impossible. Le mot de passe root est certainement incorrect. Erreur MySQL n°'.$mysqli->connect_errno.', «'.$mysqli->connect_error."».</p>\n";
    else  {
      // Validation des données
      $mysqli->set_charset('utf8');
      $prenom = mb_convert_case(trim($mysqli->real_escape_string($_REQUEST['prenom'])),MB_CASE_TITLE,'UTF-8');
      $nom = mb_convert_case(trim($mysqli->real_escape_string($_REQUEST['nom'])),MB_CASE_TITLE,'UTF-8');
      $mail = strtolower(trim($mysqli->real_escape_string($_REQUEST['mail'])));
      // Login automatiquement généré
      $login = mb_strtolower(mb_substr($prenom,0,1,'UTF-8').str_replace(' ','_',$nom),'UTF-8');
      $nom_matiere = trim($mysqli->real_escape_string($_REQUEST['nom_matiere']));
      $cle_matiere = trim($mysqli->real_escape_string($_REQUEST['cle_matiere']));
      $titre = trim($mysqli->real_escape_string($_REQUEST['titre']));
      if ( !strlen($prenom) || !strlen($nom) || !filter_var($mail,FILTER_VALIDATE_EMAIL) || !strlen($nom_matiere) || !strlen($cle_matiere) || !strlen($titre) )
        $message = "<p class=\"warning\">Toutes les données sont obligatoires. L'adresse mail doit être valide.</p>\n";
      else  {
        // Traitement des données
        // Champs à remplacer : $base, $mdp, $serveur,
        // $login, $nom, $prenom, $mail, $titre, $cle_matiere, $nom_matiere
        include('def_sql.php');
        $mysqli->multi_query($requete);
        if ( $mysqli->errno )  {
          $message = '<p class="warning">Quelque chose n\'a pas fonctionné. Erreur MySQL n°'.$mysqli->errno.', «'.$mysqli->error."».</p>\n";
          $mysqli->close();
        }
        else  {
          $mysqli->close();
          sleep(1);
          $mysqli = new mysqli($serveur,$base,$mdp,$base);
        }
        if ( $mysqli->connect_errno )
          $message = '<p class="warning">La requête semble avoir fonctionné, mais la connexion à la base n\'est pas possible. Erreur MySQL n°'.$mysqli->connect_errno.', «'.$mysqli->connect_error."».</p>\n";
        else  {
          // Envoi du courriel d'invitation 
          if ( !isset($mailadmin) )
            $mailadmin = 'admin@cahier-de-prepa.fr';
          $lien = "https://$domaine${chemin}gestioncompte?invitation&mail=".str_replace('@','__',$mail).'&p='.sha1($chemin.$mdp.$mail);
          mail($mail,'=?UTF-8?B?'.base64_encode('[Cahier de Prépa] Invitation').'?=',
"Bonjour

Le Cahier de Prépa vient d'être créé à l'adresse <https://$domaine$chemin>. Vous devez avant toute chose vous rendre à la page qui vous permettra de saisir un mot de passe :
  $lien

Les mots de passe sont chiffrés avant d'être stockés dans la base de données. Cela signifie que, sauf s'il est trop simple, votre mot de passe sera en complète sécurité : personne ne pourra jamais y avoir accès. Il est quand même dangereux que ce mot de passe soit évident, parce qu'un élève pourrait le deviner en vous voyant de loin le taper par exemple. Un bon mot de passe est un mot de passe d'au moins 8 caractères contenant des lettres, des chiffres et au moins un symbole parmi « ? ; : ! . , - _ ».

Tout ensuite est modifiable. Cahier de Prépa est prévu pour être partagé entre collègues. Seul un professeur déjà connecté peut créer des comptes de professeurs, c'est donc à vous de le faire. Une fois connectés, tous les professeurs ont les mêmes droits. Pour les élèves et colleurs par contre, vous pouvez les inviter à demander une création de compte via l'icône de connexion puis \"Créer un compte\". Il faudra que vous validiez ces demandes dans la page de gestion des utilisateurs.

Par ailleurs, toutes les fonctionnalités sont optionnelles : n'apparaît dans le menu visible des élèves que ce qui n'est pas vide. La matière \"$nom_matiere\" ne sera pas affichée dans ce menu tant qu'il n'y a pas de cahier de texte/programme de colles/documents.

Il y a une aide assez fournie sur chaque page une fois que l'on est connecté en tant que professeur. Bonne navigation sur Cahier de Prépa.

Cordialement,
--
L'installateur automatique de votre Cahier de Prépa
",'From: =?UTF-8?B?'.base64_encode('Cahier de Prépa')."?= <$mailadmin>\r\nContent-type: text/plain; charset=UTF-8","-f$mailadmin");
        }
      }
    }
  }

  ////////////////////////////////////////////
  // Formulaire de récupération des données //
  ////////////////////////////////////////////
  if ( !isset($_REQUEST['mdproot']) || strlen($message) )  {
    echo <<<FIN
$header
  <a class="icon-ok general" onclick="$('form').submit();" title="Envoyer les informations"></a>

  <form action="" method="post">
  $message
    <article>
      <h3>Mot de passe root MySQL</h3>
      <p>La création des tables de la base de données nécessite les droits d'administrateur sur le serveur. Inscrivez ci-dessous le mot de passe root du serveur <code>MySQL</code>. Ce mot de passe n'est noté ni envoyé nulle part (à part au serveur MySQL défini dans le fichier de configuration).</p>
      <p class="ligne"><label for="mdproot">Mot de passe root du serveur MySQL&nbsp;: </label><input type="password" name="mdproot" id="mdproot"></p>
    </article>

    <article>
      <h3>Création d'un compte professeur</h3>
      <p>Entrez ci-dessous les coordonnées d'un des professeurs de la classe. Il va recevoir un courriel d'invitation, lui permettant de créer un mot de passe et de terminer la création de son compte. Il pourra, une fois connecté, créer les autres comptes.</p>
      <p class="ligne"><label for="prenom">Prénom&nbsp;: </label><input type="text" id="prenom" name="prenom" value="" size="50"></p>
      <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text" id="nom" name="nom" value="" size="50"></p>
      <p class="ligne"><label for="mail">Adresse mail&nbsp;: </label><input type="text" id="mail" name="mail" value="" size="50"></p>
    </article>

    <article>
      <h3>Création d'une première matière</h3>
      <p>Afin de faciliter la première rencontre avec l'interface d'administration, nous allons créer une matière qui sera associée au compte défini ci-dessus. D'autres matières pourront être créées ultérieurement.</p>
      <ul>
        <li>Le <em>nom complet</em> s'affichera dans le menu et dans les titres des pages. Mettez une majuscule au début.</li>
        <li>La <em>clé dans l'adresse</em> est un mot-clé utilisé uniquement dans l'adresse des pages associées à la matière. Il vaut mieux que ce soit un mot unique, court et sans majuscule. Par exemple, «&nbsp;maths&nbsp;», «&nbsp;phys&nbsp;»...</li>
      </ul>
      <p class="ligne"><label for="nom_matiere">Nom complet&nbsp;: </label><input type="text" id="nom_matiere" name="nom_matiere" value="" size="50"></p>
      <p class="ligne"><label for="cle_matiere">Clé dans l'adresse&nbsp;: </label><input type="text" id="cle_matiere" name="cle_matiere" value=""" size="30"></p>
    </article>

    <article>
      <h3>Titre du site</h3>
      <p>Il faut enfin renseigner ici le titre du site, qui sera aussi celui de la page d'accueil. Il sera modifiable par les professeurs après la création. Par exemple&nbsp;: &laquo;&nbsp;La [classe] de [lycée]&nbsp;&raquo;.</p>
      <p class="ligne"><label for="titre">Titre du site&nbsp;: </label><input type="text" id="titre" name="titre" value="" size="80"></p>
    </article>

  </form>
$footer
FIN;
  exit();
  }
}

/////////////////////////////////////
// Vérifications de l'installation //
/////////////////////////////////////
function affiche($message,$status)  {
  echo $status ? "\n<p class=\"ok\"><span class=\"icon-ok\"></span>&nbsp;$message</p></div>\n" : "\n<p class=\"nok\"><span class=\"icon-ferme\"></span>&nbsp;$message</p></div>\n";
}

// Si on est arrivé ici, la base est nécessairement créée.
echo $header;

// Récupération éventuelle de l'identifiant que l'on vient de créer
$resultat = $mysqli->query('SELECT login FROM utilisateurs WHERE id = 1 AND LENGTH(mdp) = 1');
if ( $resultat->num_rows )  {
  $r = $resultat->fetch_assoc();
  echo "  <p class=\"warning ok\">L'identifiant du compte professeur créé est ${r['login']} (sans majucule).\n";
  $resultat->free();
}

// Installation
affiche("Ce Cahier de Prépa semble correctement installé. Page d'accueil&nbsp;: <a href=\".\">https://$domaine$chemin</a>",true);

// Connexion en lecture
affiche('Connexion à la base de donnée en lecture',true);

// Connexion en écriture
$mysqli->close();
$mysqli = new mysqli($serveur,"$base-adm",$mdp,$base);
affiche('Connexion à la base de donnée en écriture', !($mysqli->connect_errno));
if ( !($mysqli->connect_errno) )
  $mysqli = new mysqli($serveur,$base,$mdp,$base);

// Tables dans la base de données
$resultat = $mysqli->query('SHOW TABLES');
$tables = array('agenda','agenda-types','cdt','cdt-seances','cdt-types','colles','docs','infos','heurescolles','matieres','notes','pages','prefs','recents','reps','semaines','utilisateurs','groupes');
if ( $resultat->num_rows )  {
  while ( $r = $resultat->fetch_row() )
    $tables = array_diff($tables,$r);
  $resultat->free();
}
affiche('Définition des tables',empty($tables));

// La base de données doit contenir au moins un professeur
$resultat = $mysqli->query('SELECT id FROM utilisateurs WHERE autorisation = 5 LIMIT 1');
affiche('Présence d\'au moins un utilisateur de type professeur',$resultat->num_rows);
$resultat->free();

// La base de données doit contenir la page d'identifiant 1
$resultat = $mysqli->query('SELECT id FROM pages WHERE id = 1');
affiche('Présence de la page d\'accueil',$resultat->num_rows);
$resultat->free();

// La base de données doit contenir la page d'identifiant 1
$resultat = $mysqli->query('SELECT id FROM reps WHERE id = 1');
affiche('Présence du répertoire Général',$resultat->num_rows);
$resultat->free();

// La base de données doit contenir 44 semaines en 2018-19
$resultat = $mysqli->query('SELECT id FROM semaines WHERE debut > \'2019-08-01\' AND debut < \'2020-08-01\'');
affiche('Définition des semaines',$resultat->num_rows == 44);
$resultat->free();
$mysqli->close();

// Vérification des répertoires
$reps = array('documents','sauvegarde','rss');
foreach ( $reps as $rep )  {
  if ( !is_dir('documents') )
    affiche('Le répertoire «&nbsp;documents&nbsp;» n\'existe pas à la racine du site web.',false);
  elseif ( !is_readable('documents') || !is_executable('documents') || !is_writable('documents') )
    affiche("Le répertoire «&nbsp;$rep&nbsp;» n'a pas les bons droits d'accès. Il doit être accessible en lecture et en écriture. Vous pouvez taper en console&nbsp;:<br>
    <code>cd ".dirname($_SERVER['SCRIPT_FILENAME']).' && sudo chgrp '.`id -gn | tr -d '\n'`." $rep && sudo chmod g+w $rep</code>",false);
  else 
    affiche("Répertoire «&nbsp;$rep&nbsp;» accessible en lecture et écriture",1);
}

// Vérification de la quantité de données téléchargeable
if ( '2M' == ini_get('upload_max_filesize') )
  affiche('Les documents envoyés ne pourront excéder 2&nbsp;Mo, ce qui est plutôt faible. Il serait bien d\'augmenter cette limite. Plus d\'informations sur la page <a href="https://cahier-de-prepa.fr/technique">technique</a> de cahier-de-prepa.fr.',false);
else
  affiche('La taille des documents envoyés peut aller jusqu\'à'.ini_get('upload_max_filesize').'o. Cela est modifiable dans la configuration du serveur web. Plus d\'informations sur la page <a href="https://cahier-de-prepa.fr/technique">technique</a> de cahier-de-prepa.fr.',true);

exit($footer);
?>
