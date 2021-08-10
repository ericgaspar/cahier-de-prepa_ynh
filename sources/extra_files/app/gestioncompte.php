<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

// Titre : on récupère celui de la première page
$mysqli = connectsql();
$resultat = $mysqli->query('SELECT titre FROM pages WHERE id = 1');
$mysqli->close();
$r = $resultat->fetch_row();
$resultat->free();
$titre = $r[0];

/////////////////////////////////////
// Récupération du type de demande //
/////////////////////////////////////

// Action
if ( isset($_REQUEST['oublimdp']) )
  // 4 étapes :
  // oublimdp (get) -> affichage du formulaire mail
  // oublimdp&mail (ajax) -> envoi du mail de vérification
  // oublimdp&mail&p (retour mail, get) -> affichage du formulaire des mdp
  // oublimdp&mail&p&mdp1&mdp2 (ajax) -> ok
  // p est le sha1 de $chemin.$mdp.date('Y-m-d-H').$mail
  $action = 'oublimdp';
elseif ( isset($_REQUEST['creation']) ) {
  // 4 étapes :
  // creation (get) -> affichage du formulaire mail
  // creation&mail (ajax) -> envoi du mail de vérification
  // creation&mail&p (retour mail, get) -> affichage du formulaire des mdp
  // creation&mail&p&prenom&nom&mdp1&mdp2&autorisation (ajax) -> ok
  // $creation_compte réglée dans config.php : true si création autorisée
  // p est le sha1 de $chemin.$mdp.date('Y-m-d-H').$mail
  $action = 'creation';
  // Vérification de l'ouverture des créations de compte
  $mysqli = connectsql();
  $resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "creation_compte"');
  $r = $resultat->fetch_row();
  $resultat->free();
  if ( $r[0] == 0 )  {
    debut($mysqli,$titre,'',false);
    echo '<h2>La demande de création de compte n\'est pas autorisée sur ce Cahier de Prépa.</h2><p style="text-align:center;"><a href=".">Retour à la page d\'accueil</a></p>';
    $mysqli->close();
    fin();
  }
  $mysqli->close();
}
elseif ( isset($_REQUEST['invitation']) )
  // 2 étapes :
  // invitation&mail&p (retour mail généré dans ajax.php) -> formulaire
  // invitation&mail&p&mdp1&mdp2 (ajax) -> ok
  // p est le sha1 de $chemin.$mdp.$mail (validité permanente)
  $action = 'invitation';
else  {
  // Aucune action
  $mysqli = connectsql();
  debut($mysqli,$titre,'',false);
  echo '<h2>Aucune action n\'a été effectuée.</h2><p style="text-align:center;"><a href=".">Retour à la page d\'accueil</a></p>';
  $mysqli->close();
  fin();
}

///////////////////////////////////////////////////////////////////
// Réponses successives : seulement si $_REQUEST['mail'] définie //
///////////////////////////////////////////////////////////////////

if ( isset($_REQUEST['mail']) )  {
  
  if ( !($mail = filter_var(str_replace('__','@',strtolower(trim($_REQUEST['mail']))))) )  {
    if ( $_SERVER['REQUEST_METHOD'] == 'GET' )  {
      $mysqli = connectsql();
      debut($mysqli,$titre,'',false);
      $mysqli->close();
      echo '<h2>Les données que vous venez de saisir sont incomplètes ou erronées.</h2><p>Si vous avez cliqué sur un lien depuis un webmail, il est très probable que ce lien ait été coupé. Vous devez copier l\'adresse et la coller dans la barre d\'adresse de votre navigateur.</p><p style="text-align:center;"><a href=".">Retour à la page d\'accueil</a></p>';
      fin();
    }
    exit('{"etat":"nok_","message":"L\'adresse saisie n\'est pas une adresse électronique valide.<br>Vous avez dû faire une faute de frappe."}');
  }
  if ( isset($_REQUEST['p']) )  {
    $p = $_REQUEST['p'];
    // Pour invitation, $p est le sha1 de $chemin.$mdp.$mail 
    // (précédemment avec $domaine.$chemin sans le slash, condition transitoire)
    // Pour oublimdp et creation, $chemin.$mdp.date('Y-m-d-H').$mail
    if ( !( ( $action == 'invitation' ) && ( ( $p == sha1($chemin.$mdp.$mail) ) || ( $p == sha1($domaine.substr($chemin,0,-1).$mdp.$mail) ) ) || ( $p == sha1($chemin.$mdp.date('Y-m-d-H').$mail) ) || ( $p == sha1($chemin.$mdp.date('Y-m-d-H',time()+900).$mail) ) ) )  {
      if ( $_SERVER['REQUEST_METHOD'] == 'GET' )  {
        $mysqli = connectsql();
        debut($mysqli,$titre,'',false);
        $mysqli->close();
        echo '<h2>Les données que vous venez de saisir sont incomplètes ou erronées.</h2><p>Si vous avez cliqué sur un lien depuis un webmail, il est très probable que ce lien ait été coupé. Vous devez copier l\'adresse et la coller dans la barre d\'adresse de votre navigateur.</p><p style="text-align:center;"><a href=".">Retour à la page d\'accueil</a></p>';
        fin();
      }
      exit('{"etat":"nok_","message":"Les paramètres d\'identification ne sont plus corrects, vous avez dû attendre trop longtemps. Veuillez recommencer la procédure."}');
    }
  }
  
  // À partir d'ici, $mail est défini et est valide.
  // Si $p est défini, il est correct. (si non défini, mail à envoyer)
  switch ( $action )  {
    // Oubli d'identifiant
    case 'oublimdp': {

      // Recherche de l'adresse électronique dans la base de données
      // Les comptes non encore validés et suspendus ont un mdp commençant par
      // '*' ou '!', codes ascii inférieurs à '+'.
      $mysqli = connectsql();
      $resultat = $mysqli->query('SELECT id, login, mail FROM utilisateurs WHERE mdp > \'+\' AND mail > \'\'');
      $mysqli->close();
      while ( $r = $resultat->fetch_assoc() )
        if ( $r['mail'] == $mail )  {
          $utilisateur = $r;
          break;
        }
      $resultat->free();
      if ( !isset($utilisateur) )
        exit('{"etat":"nok_","message":"L\'adresse électronique donnée est inconnue. Si vous venez de demander la création de votre compte, le mot de passe n\'est pas modifiable tant que les professeurs de la classe n\'ont pas validé l\'inscription. Vous recevrez un courriel quand cela sera effectif.<p class=\"warning\"><a href=\".\">Retourner au Cahier de Prépa</a></p>"}');

      // Envoi de courriel
      if ( !isset($p) )  {
        // On ajoute 15 minutes au temps utilisé : de xh00 à xh45,
        // on a jusqu'à (x+1)h, de xh45 à (x+1)h on a jusqu'à (x+2)h
        $t = time() + 900;
        $lien = "https://$domaine${chemin}gestioncompte?oublimdp&mail=".str_replace('@','__',$mail).'&p='.sha1($chemin.$mdp.date('Y-m-d-H',$t).$mail);
        mail($mail,'=?UTF-8?B?'.base64_encode('[Cahier de Prépa] Changement de mot de passe').'?=',
"Bonjour

Vous avez rempli une demande de modification de votre mot de passe sur le Cahier de Prépa <https://$domaine$chemin>, correspondant à l'identifiant ${utilisateur['login']}.

Si cette demande ne vient pas de vous ou si vous avez retrouvé votre mot de passe, merci d'ignorer simplement ce courriel.

Sinon, veuillez cliquer ci-dessous pour vous rendre à la page qui vous permettra de modifier votre mot de passe :
    $lien
Ce lien est valable jusqu'à ".date('G\h00',$t+3600).'. Si ce lien ne s\'ouvre pas correctement, il a peut-être été coupé lors du clic : dans ce cas, essayez à nouveau en copiant-collant le lien.

Cordialement,
-- 
Cahier de Prépa
','From: =?UTF-8?B?'.base64_encode('Cahier de Prépa')."?= <$mailadmin>\r\nContent-type: text/plain; charset=UTF-8","-f$mailadmin");
        exit('{"etat":"ok_","message":"Un courriel vient de vous être envoyé à l\'adresse <code>'.$mail.'</code>.<br>Si vous ne voyez rien, pensez à regarder dans les courriels marqués comme spam. Certains serveurs retardent jusqu\'à 10 minutes l\'arrivée des messages, normalement la première fois uniquement.<br>Le courriel qui vous a été envoyé contiendra un lien, valable jusqu\'à '.date('G\h00',$t+3600).', vous permettant de modifier votre mot de passe.<p class=\"warning\"><a href=\".\">Retourner au Cahier de Prépa</a></p>"}');
      }

      // Retour de courriel : utilisation d'un passe
      if ( isset($p) && isset($_REQUEST['mdp1']) && strlen($newmdp = $_REQUEST['mdp1']) )  {
        if ( $newmdp != $_REQUEST['mdp2'] )
          exit('{"etat":"nok_","message":"Les deux mots de passe donnés ne sont pas identiques."}');
        // Token de connexion automatique
        $permconn = '';
        for ( $i = 0; $i < 10; $i++ )
          $permconn .= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0,61)];
        // Écriture du nouveau mot de passe
        $mysqli = connectsql(true);
        if( requete('utilisateurs',"UPDATE utilisateurs SET mdp = '".sha1($mdp.$newmdp)."', permconn = IF(permconn > '','$permconn','') WHERE id = ${utilisateur['id']}",$mysqli) )  {
          // Si interface globale activée, mise à jour
          if ( $interfaceglobale )  {
            include("${interfaceglobale}majutilisateurs.php");
            majutilisateurs($utilisateur['id'],array('mdp'=>sha1($mdp.$newmdp)));
          }
          exit('{"etat":"ok_","message":"Votre mot de passe a bien été modifié.</p><p class=\"warning\"><a href=\".\">Retourner au Cahier de Prépa</a></p>"}');
        }
        exit('{"etat":"nok_","message":"Votre mot de passe n\'a pas pu être modifié suite à une erreur technique."}' );
      }
      break;

    }
    case 'creation': {

      // Impossibilités classiques
      if ( in_array($dom = strstr($mail,'@'),array('@gmail.fr','@laposte.fr')) )
        exit('{"etat":"nok_","message":"L\'adresse saisie ne pourra pas fonctionner&nbsp;: le domaine <code>'.$dom.'</code> ne reçoit pas de courriels."}');
      // Recherche du mail dans la base de données
      $mysqli = connectsql();
      $resultat = $mysqli->query('SELECT id, login, mail FROM utilisateurs');
      $mysqli->close();
      while ( $r = $resultat->fetch_assoc() )
        if ( $r['mail'] == $mail )  {
          $resultat->free();
          exit('{"etat":"nok_","message":"Un compte avec cette adresse électronique existe déjà. Si vous venez de demander la création de votre compte, le compte n\'est pas modifiable tant que les professeurs de la classe n\'ont pas validé l\'inscription. Vous recevrez un courriel quand cela sera effectif. Si ce compte a déjà été validé par les professeurs, vous pouvez <a href=\"?oublimdp\">changer le mot de passe</a>.<p class=\"warning\"><a href=\".\">Retourner au Cahier de Prépa</a></p>"}');
        }
      $resultat->free();

      // Envoi de courriel
      if ( !isset($p) )  {
        // On ajoute 15 minutes au temps utilisé : de xh00 à xh45,
        // on a jusqu'à (x+1)h, de xh45 à (x+1)h on a jusqu'à (x+2)h
        $t = time() + 900;
        $lien = "https://$domaine${chemin}gestioncompte?creation&mail=".str_replace('@','__',$mail).'&p='.sha1($chemin.$mdp.date('Y-m-d-H',$t).$mail);
        mail($mail,'=?UTF-8?B?'.base64_encode('[Cahier de Prépa] Création de compte').'?=',
"Bonjour

Vous venez de donner cette adresse pour une demande de création de compte sur le Cahier de Prépa <https://$domaine$chemin>.

Si cette demande ne vient pas de vous, merci d'ignorer simplement ce courriel.

Sinon, veuillez cliquer ci-dessous pour vous rendre à la page qui vous permettra de terminer votre inscription :
   $lien
Ce lien est valable jusqu'à ".date('G\h00',$t+3600).'. Si ce lien ne s\'ouvre pas correctement, il a peut-être été coupé lors du clic : dans ce cas, essayez à nouveau en copiant-collant le lien. 

Cordialement,
-- 
Cahier de Prépa
','From: =?UTF-8?B?'.base64_encode('Cahier de Prépa')."?= <$mailadmin>\r\nContent-type: text/plain; charset=UTF-8","-f$mailadmin");
        exit('{"etat":"ok_","message":"Un courriel vient de vous être envoyé à l\'adresse <code>'.$mail.'</code>.<br>Si vous ne voyez rien, pensez à regarder dans les courriels marqués comme spam. Vérifiez bien qu\'il n\'y a pas d\'erreur dans cette adresse, car vous ne pourrez pas continuer votre inscription si elle est fausse. Certains serveurs retardent jusqu\'à 10 minutes l\'arrivée des messages, la première fois uniquement.<br>Le courriel qui vous a été envoyé contiendra un lien, valable jusqu\'à '.date('G\h00',$t+3600).', vous permettant de terminer votre inscription.<p class=\"warning\"><a href=\".\">Retourner au Cahier de Prépa</a></p>"}');
      }
      
      // Retour de courriel : utilisation d'un passe
      if ( isset($p) && isset($_REQUEST['nom']) )  {
        // Spécifications pour les manipulations de caractères sur 2 octets (accents)
        mb_internal_encoding('UTF-8');
        if ( !strlen($nom = trim($_REQUEST['nom'])) )
          exit('{"etat":"nok_","message":"Le nom est obligatoire."}');
        if ( !isset($_REQUEST['prenom']) || !strlen($prenom = trim($_REQUEST['prenom'])) )
          exit('{"etat":"nok_","message":"Le prénom est obligatoire."}');
        if ( !isset($_REQUEST['mdp1']) || !strlen($newmdp = $_REQUEST['mdp1']) )
          exit('{"etat":"nok_","message":"Le mot de passe est obligatoire."}');
        if ( $newmdp != $_REQUEST['mdp2'] )
          exit('{"etat":"nok_","message":"Les deux mots de passe donnés ne sont pas identiques."}');
        // Nettoyage des données envoyées
        $mysqli = connectsql();
        $prenom = mb_convert_case(strip_tags($mysqli->real_escape_string($prenom)),MB_CASE_TITLE);
        $nom = mb_convert_case(strip_tags($mysqli->real_escape_string($nom)),MB_CASE_TITLE);
        $mail = $mysqli->real_escape_string($mail);
        $newmdp = sha1($mdp.$newmdp);
        // Login déterminé automatiquement
        $login = mb_strtolower(mb_substr($prenom,0,1).str_replace(' ','_',$nom));
        $resultat = $mysqli->query("SELECT id FROM utilisateurs WHERE login = '$login'");
        $mysqli->close();
        if ( $resultat->num_rows )  {
          $resultat->free();
          exit('{"etat":"nok_","message":"Un compte avec le même identifiant existe déjà. Merci de vous connecter avec l\'adresse électronique correspondante."}');
        }
        // Écriture du nouveau compte
        $mysqli = connectsql(true);
        if( requete('utilisateurs',"INSERT INTO utilisateurs SET login = '$login', prenom = '$prenom', nom = '$nom', mail = '$mail', mdp = '*$newmdp', autorisation = 2, matieres = CONCAT('0,',(SELECT GROUP_CONCAT(id) AS matieres FROM matieres)), timeout = 3600, mailexp = '$prenom $nom', mailcopie = 1, permconn = ''",$mysqli) )  {
          // Si interface globale activée, mise à jour
          if ( $interfaceglobale )  {
            include("${interfaceglobale}majutilisateurs.php");
            majutilisateurs($mysqli->insert_id,"INSERT INTO utilisateurs SET login = '$login', prenom = '$prenom', nom = '$nom', mail = '$mail', mdp = '*$newmdp', autorisation = 2");
          }
          exit('{"etat":"ok_","message":"Votre demande d\'inscription est terminée. Elle est maintenant en attente de validation par les professeurs de la classe. Vous recevrez un courriel lorsque votre inscription sera validée.</p><p class=\"warning\"><a href=\".\">Retourner au Cahier de Prépa</a></p>"}');
        }
        exit('{"etat":"nok_","message":"Votre demande d\'inscription n\'a pas pu être enregistrée suite à une erreur technique."}');
      }
      break;

    }
    case 'invitation': {
      
      // Recherche de l'adresse électronique dans la base de données
      // Les comptes non encore validés ont un mot de passe égal à '?'.
      $mysqli = connectsql();
      $resultat = $mysqli->query('SELECT id, login, mail FROM utilisateurs WHERE mdp = \'?\'');
      $mysqli->close();
      while ( $r = $resultat->fetch_assoc() )
        if ( $r['mail'] == $mail )  {
          $utilisateur = $r;
          break;
        }
      $resultat->free();
      if ( !isset($utilisateur) )  {
        $mysqli = connectsql();
        debut($mysqli,$titre,'L\'adresse électronique donnée est inconnue. Il est probable que vous ayez déjà répondu à l\'invitation envoyée ou que le compte pour lequel vous avez été invité ait été supprimé.',false);
        $mysqli->close();
        fin();
      }
  
      // Modification du mot de passe
      if ( isset($_REQUEST['mdp1']) && strlen($newmdp = $_REQUEST['mdp1']) )  {
        if ( $newmdp != $_REQUEST['mdp2'] )
          exit('{"etat":"nok_","message":"Les deux mots de passe donnés ne sont pas identiques."}');
        // Écriture du nouveau mot de passe
        $mysqli = connectsql(true);
        if( requete('utilisateurs',"UPDATE utilisateurs SET mdp = '".sha1($mdp.$newmdp)."' WHERE id = ${utilisateur['id']}",$mysqli) )  {
          // Si interface globale activée, mise à jour
          if ( $interfaceglobale )  {
            include("${interfaceglobale}majutilisateurs.php");
            majutilisateurs($utilisateur['id'],array('mdp'=>sha1($mdp.$newmdp)));
          }
          exit('{"etat":"ok_","message":"Votre mot de passe a bien été modifié. Votre compte est opérationnel.</p><p class=\"warning\"><a href=\".\">Retourner au Cahier de Prépa</a></p>"}');
        }
        exit('{"etat":"nok_","message":"Votre mot de passe n\'a pas pu être modifié suite à une erreur technique."}');
      }
    }
  }
}

////////////
/// HTML ///
////////////
$mysqli = connectsql();
debut($mysqli,$titre,'',false);
$mysqli->close();
switch ( $action )  {
  case 'oublimdp': {
    if ( !isset($mail) )
      echo <<<FIN

  <article>
    <a class="icon-ferme" onclick="history.back()" title="Revenir à la page précédente"></a>
    <a class="icon-ok" title="Valider"></a>
    <h3>Mot de passe oublié</h3>
    <form onsubmit="return false;">
      <p>Si vous avez oublié votre mot de passe, vous pouvez le régénérer en saisissant votre adresse électronique ci-dessous. Vous recevrez un courriel contenant un lien temporaire permettant de modifier votre mot de passe.</p>
      <input class="ligne" type="email" name="mail" autofocus placeholder="Adresse électronique">
    </form>
  </article>

FIN;
    else
      echo <<<FIN

  <article>
    <a class="icon-ok" title="Valider"></a>
    <h3>Mot de passe oublié</h3>
    <form>
      <p>Veuillez saisir deux fois votre nouveau mot de passe&nbsp;:</p>
      <input class="ligne" type="password" name="mdp1" autofocus placeholder="Mot de passe">
      <input class="ligne" type="password" name="mdp2" placeholder="Confirmation">
      <input type="hidden" name="p" value="$p">
      <input type="hidden" name="mail" value="$mail">
    </form>
  </article>

FIN;
    break;
  }
  case 'creation': {
    if ( !isset($mail) )
      echo <<<FIN

  <article>
    <a class="icon-ferme" onclick="history.back()" title="Revenir à la page précédente"></a>
    <a class="icon-ok" title="Valider"></a>
    <h3>Création de compte</h3>
    <form onsubmit="return false;">
      <p>Vous pouvez demander ici une création de compte sur ce Cahier de Prépa, si vous êtes élève dans cette classe. Vous devez tout d'abord fournir une adresse électronique valide. Vous recevrez un courriel contenant un lien temporaire permettant de continuer votre inscription.</p>
      <p>Il est conseillé de fournir une adresse électronique régulièrement consultée&nbsp;: elle pourra servir aux professeurs et aux colleurs pour vous contacter.</p>
      <p>À la fin de votre inscription, la demande sera mise en attente de validation par les professeurs de la classe.</p>
      <p>Aucune donnée personnelle n'est enregistrée à cette étape.</p>
      <input class="ligne" type="email" name="mail" autofocus placeholder="Adresse électronique">
    </form>
  </article>

FIN;
    else
      echo <<<FIN

  <article>
    <a class="icon-ok" title="Valider"></a>
    <h3>Création de compte</h3>
    <form>
      <p>Vous pouvez demander ici une création de compte sur ce Cahier de Prépa, si vous êtes élève de cette classe. Une fois votre demande remplie, l'inscription sera mise en attente de validation par les professeurs de la classe. Vous recevrez un courriel dès que votre inscription sera validée.</p>
      <p>Le mot de passe vous est complètement personnel et ne sera divulgué à personne. Il est chiffré avant son stockage dans la base de données. La bonne pratique est de ne pas écrire un simple mot du dictionnaire mais une suite de lettres, de chiffres et/ou de signes de ponctuation qui n'ont de sens que pour vous. Tous les caractères de votre clavier, y compris l'espace, sont autorisés.</p>
      <p>Seules les données permettant le fonctionnement de ce site sont stockées&nbsp;: nom, prénom, adresse électronique. Aucune de ces données ne sera partagée avec une autre entité. En demandant la création de votre compte, vous autorisez l'administrateur du site à stocker ces informations. Vous pourrez supprimer votre compte à tout moment.</p>
      <input class="ligne" type="text" name="prenom" size="50" autofocus placeholder="Prénom">
      <input class="ligne" type="text" name="nom" size="50"" placeholder="Nom">
      <input class="ligne" type="password" name="mdp1" placeholder="Mot de passe">
      <input class="ligne" type="password" name="mdp2" placeholder="Confirmation">
      <input type="hidden" name="p" value="$p">
      <input type="hidden" name="mail" value="$mail">
    </form>
  </article>

FIN;
    break;
  }
  case 'invitation': {
    if ( isset($utilisateur['login']) )
      echo <<<FIN

  <article>
    <a class="icon-ok" title="Valider"></a>
    <h3>Création de compte</h3>
    <form>
      <p>L'équipe pédagogique vous a créé un compte ici. L'identifiant associé à ce compte est</p>
      <p class="warning">${utilisateur['login']}</p>
      <p>Il ne vous reste plus qu'à définir votre mot de passe. Le mot de passe vous est complètement personnel et ne sera divulgué à personne. Il est chiffré avant son stockage dans la base de données. La bonne pratique est de ne pas écrire un simple mot du dictionnaire mais une suite de lettres, de chiffres et de signes de ponctuation qui n'ont de sens que pour vous. Tous les caractères de votre clavier, y compris l'espace, sont autorisés.</p>
      <p>Seules les données permettant le fonctionnement de ce site sont stockées&nbsp;: nom, prénom, adresse électronique. Aucune de ces données ne sera partagée avec une autre entité. En demandant la création de votre compte, vous autorisez l'administrateur du site à stocker ces informations. Vous pourrez supprimer votre compte à tout moment.</p>
      <input class="ligne" type="password" name="mdp1" autofocus placeholder="Mot de passe">
      <input class="ligne" type="password" name="mdp2" placeholder="Confirmation">
      <input type="hidden" name="p" value="$p">
      <input type="hidden" name="mail" value="$mail">
    </form>
  </article>

FIN;
    else
      echo '<h2>Les paramètres d\'accès à cette page ne sont pas corrects.</h2>';
    break;
  }
  default:
    echo '<h2>Aucune action n\'a été effectuée.</h2>';
}
?>
  
  <script type="text/javascript">
$( function() {
  // Envoi
  $('a.icon-ok').on('click',function () {
    $.ajax({url: 'gestioncompte.php', method: "post", data: '<?php echo $action;?>=&'+$('form').serialize(), dataType: 'json'})
    .done( function(data) {
      if ( data['etat'] == 'ok_' ) {
        $('form').html('<p>'+data['message']+'</p>');
        $('a.icon-ok, input').remove();
      }
      else if ( data['etat'] == 'nok_' )  {
        $('p:first').html(data['message']).addClass('warning');
        $('p').not(':first').not('.ligne').remove();
      }
    });
  });
  // Envoi par appui sur Entrée
  $('input,select').on('keypress',function (e) {
    if ( e.which == 13 ) {
      $('a.icon-ok').click();
      return false;
    }
  });
});
  </script>
<?php
fin();
?>
