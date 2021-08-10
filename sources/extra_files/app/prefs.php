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

// Accès aux professeurs, colleurs, élèves connectés uniquement
$mysqli = connectsql();
if ( !$autorisation )  {
  $titre = 'Préférences';
  $actuel = false;
  include('login.php');
}
elseif ( $autorisation < 2 )  {
  debut($mysqli,'Préférences','Vous n\'avez pas accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}
// Si connexion light : on doit répéter son mot de passe pour aller plus loin
// login.php contient fin()
if ( $_SESSION['light'] )  {
  $titre = 'Mon compte';
  $actuel = 'prefs';
  include('login.php');
}

//////////////
//// HTML ////
//////////////
debut($mysqli,'Mon compte',$message,$autorisation,'prefs');

// Récupération des données de l'utilisateur
$resultat = $mysqli->query("SELECT nom, prenom, mail, timeout, mailexp,
                            IF(mailcopie,' checked','') AS mailcopie,
                            IF(permconn > '',' checked','') AS permconn
                            FROM utilisateurs WHERE id = ${_SESSION['id']}");
$r = $resultat->fetch_assoc();
$resultat->free();
// Autorisation d'envoi de courriel
$resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
$aut_envoi = $resultat->fetch_row()[0] >> 4*($autorisation-2) & 15;
$mysqli->close();
?>

  <div id="icones">
    <a class="icon-aide" title="Aide pour les modifications de vos préférences"></a>
  </div>

  <article data-id="prefsperso|1">
    <h3 class="edition">Mon identité</h3>
    <a class="icon-ok noreload" title="Valider les modifications"></a>
    <form>
      <p class="ligne"><label for="prenom">Prénom&nbsp;: </label><input type="text" id="prenom" name="prenom" value="<?php echo $r['prenom']; ?>" size="50"></p>
      <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text" id="nom" name="nom" value="<?php echo $r['nom']; ?>" size="50"></p>
      <p class="ligne"><label for="mdp_1">Mot de passe&nbsp;: </label><input type="password" id="mdp_1" name="mdp" value=""></p>
      <p>Le mot de passe actuel doit être obligatoirement fourni pour toute modification.</p>
    </form>
  </article>

  <article data-id="prefsperso|2">
    <h3 class="edition">Mon mot de passe</h3>
    <a class="icon-ok" title="Valider les modifications"></a>
    <form>
      <p class="ligne"><label for="mdp_2">Mot de passe actuel&nbsp;: </label><input type="password" id="mdp_2" name="mdp" value=""></p>
      <p class="ligne"><label for="mdp1">Nouveau mot de passe&nbsp;: </label><input type="password" id="mdp1" name="mdp1" value=""></p>
      <p class="ligne"><label for="mdp2">Confirmation&nbsp;: </label><input type="password" id="mdp2" name="mdp2" value=""></p>
    </form>
  </article>

  <article data-id="prefsperso|3">
    <h3 class="edition">Mon adresse électronique</h3>
    <a class="icon-ok" title="Valider les modifications"></a>
    <p>Un code de confirmation va être envoyé par courriel à la nouvelle adresse.</p>
    <form>
      <p class="ligne"><label for="mail">Adresse électronique&nbsp;: </label><input type="email" id="mail" name="mail" value="<?php echo $r['mail']; ?>" size="50"></p>
      <p class="ligne" style="display: none;"><label for="confirmation">Code de confirmation&nbsp;: </label><input type="text" id="confirmation" name="confirmation" value="" size="50" disabled></p>
      <p class="ligne"><label for="mdp_3">Mot de passe&nbsp;: </label><input type="password" id="mdp_3" name="mdp" value=""></p>
      <p>Le mot de passe actuel doit être obligatoirement fourni pour toute modification.</p>
    </form>
  </article>

  <article data-id="prefsperso|4">
    <h3 class="edition">Ma connexion</h3>
    <a class="icon-ok noreload" title="Valider les modifications"></a>
    <form>
      <p class="ligne"><label for="login">Identifiant&nbsp;: </label><input type="text" id="login" name="login" value="<?php echo $_SESSION['login']; ?>" size="50"></p>
      <p class="ligne"><label for="permconn">Conserver ma connexion sur cette machine&nbsp;: </label><input type="checkbox" id="permconn" name="permconn" value="1"<?php echo $r['permconn']; ?>></p>
      <p class="ligne"><label for="timeout">Durée avant déconnexion&nbsp;: </label><input type="text" id="timeout" name="timeout" value="<?php echo $r['timeout']; ?>" size="3"></p>
      <p class="ligne"><label for="mdp_4">Mot de passe actuel&nbsp;: </label><input type="password" id="mdp_4" name="mdp" value=""></p>
      <p>Le mot de passe actuel doit être obligatoirement fourni pour toute modification.</p>
    </form>
  </article>
<?php
// Préférences d'envoi des mails seulement si autorisé globalement

if ( $aut_envoi )  {
?>

  <article data-id="prefsperso|5">
    <h3 class="edition">Mes envois de courriel</h3>
    <a class="icon-ok noreload" title="Valider les modifications"></a>
    <form>
      <p class="ligne"><label for="mailexp">Nom affiché comme expéditateur/destinataire de courriel&nbsp;: </label><input type="text" id="mailexp" name="mailexp" value="<?php echo $r['mailexp']; ?>" size="50"></p>
      <p class="ligne"><label for="mailcopie">Recevoir une copie des courriels envoyés&nbsp;: </label><input type="checkbox" id="mailcopie" name="mailcopie" value="1"<?php echo $r['mailcopie']; ?>></p>
      <p class="ligne"><label for="mdp_5">Mot de passe actuel&nbsp;: </label><input type="password" id="mdp_5" name="mdp" value=""></p>
      <p>Le mot de passe actuel doit être obligatoirement fourni pour toute modification.</p>
    </form>
  </article>
<?php
}
?>

  <article id="rgpd">
    <h2>Données personnelles et compatibilité RGPD</h2>
    <p>Les informations recueillies par chaque Cahier de Prépa font l'objet d'un traitement informatique destiné à assurer le fonctionnement du Cahier (envoi de mail, affichage des coordonnées pour les professeurs, inscription des notes de colle). Seules sont stockées les informations strictement nécessaires au bon fonctionnement du service : nom, prénom et adresse électronique. Toutes ces données sont visibles et modifiables ci-dessus.</p>
    <p>Vos données, à l'exception de votre mot de passe, sont aussi accessibles et modifiables par l'ensemble des professeurs de la classe et l'administration de votre lycée, pour permettre le bon fonctionnement du Cahier. Par votre inscription sur ce Cahier, vous autorisez cela ainsi que le stockage de ces informations par l'administrateur du site. Les éventuelles notes de colles des élèves ne sont consultables que par les personnes concernées (élève, colleur, professeur de la matière, administration du lycée), sur les pages dédiées.</p>
    <p>Votre mot de passe vous est complètement personnel. Il est chiffré avant son stockage dans la base de données et ne peut donc techniquement être divulgué à personne.</p>
    <p>La suppression de votre compte doit passer par les professeurs de la classe. Le fonctionnement de la classe peut néanmoins nécessiter la conservation de votre compte au moins jusqu'à la fin de l'année scolaire.</p>
    <p>Les adresses IP ne sont pas conservées dans la base de données, mais chaque action de modification de la base conduit à l'écriture de l'adresse IP utilisée dans un journal.</p>
    <p>Aucun cookie n'est utilisé pour stocker des données. Seul un cookie de session (identifiant permettant de conserver l'identification d'une page à l'autre) est utilisé lorsque vous vous connectez.</p>
    <p>Conformément à la <a href="https://www.cnil.fr/fr/loi-78-17-du-6-janvier-1978-modifiee">loi «&nbsp;informatique et libertés&nbsp;» du 6 janvier 1978 modifiée</a>, vous disposez d'un <a href="https://www.cnil.fr/fr/le-droit-dacces">droit d'accès</a> et d'un <a href="https://www.cnil.fr/fr/le-droit-de-rectification">droit de rectification</a> des informations qui vous concernent. Vous pouvez accèder aux informations vous concernant en vous connectant sur votre Cahier de Prépa ou en vous adressant à <a href="mailto:contact@cahier-de-prepa.fr">contact@cahier-de-prepa.fr</a>. Vous pouvez également, pour des motifs légitimes, <a href="https://www.cnil.fr/fr/le-droit-dopposition">vous opposer au traitement des données vous concernant</a>.</p>
    <p>Aucune de ces données ne sera communiquée à une autre organisation. Cahier de Prépa est un service gratuit offert par un professeur de CPGE bénévole, sans publicité et sans vente de données.</p>
    <p>Le traitement des données réalisé par Cahier de Prépa est compatible avec le Réglement Général sur la Protection des Données.</p>
  </article>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici de modifier vos préférences. Une fois modifié, un formulaire est à validé par un clic sur le bouton <span class="icon-ok"></span> correspondant.</p>
    <p>Afin d'éviter les modifications de compte frauduleuses, il est nécessaire de taper son mot de passe pour toute modification.</p>
    <h4>Modification de l'identité&nbsp;: nom, prénom</h4>
    <p>Il est possible de modifier votre <em>nom</em> ou votre <em>prénom</em>. Ils sont utilisés pour tous les affichages nécessitant de vous identifier&nbsp;: tableaux d'administration du Cahier de Prépa et tableaux de notes de colles.</p>
    <h4>Modification du mot de passe</h4>
    <p>Votre <em>mot de passe</em> est stocké chiffré dans la base de données du Cahier de Prépa. Il n'est jamais manipulé sans être préalablement chiffré. Vous avez intérêt à utiliser un mot de passe qui vous est personnel, et à éviter les mots de passe faciles à deviner tels que «&nbsp;arnaud75&nbsp;» ou «&nbsp;goldorak&nbsp;» (reprenant des mots entiers et une passion, une identité, une adresse...). Vous avez intérêt aussi à utiliser le même mot de passe sur tous les Cahiers de Prépa où vous pourriez vous connecter.</p>
    <h4>Modification de l'adresse électronique</h4>
    <p>Pour modifier votre <em>adresse électronique</em>, vous devrez saisir cette nouvelle adresse et récupérer un <em>code de confirmation</em> de 8 caractères envoyé par courriel à cette adresse. Ce code est valable entre 15 et 75 minutes. La demande s'annule automatiquement si vous ne donnez pas suite. Vous pouvez demander à recevoir ce courriel autant de fois que vous le souhaitez.</p>
    <p>Il n'est pas possible d'affecter la même adresse électronique à deux comptes différents sur un même Cahier de Prépa.</p>
    <p>L'adresse électronique sert à envoyer ou recevoir des courriels, en fonction du réglage choisi par l'équipe pédagogique.</p>
    <h4>Modification des paramètres de connexion</h4>
    <p>Votre <em>identifiant</em> est initialement de la forme &laquo;&nbsp;jdupont&nbsp;&raquo;. Vous pouvez le modifier, à condition de ne pas demander un identifiant déjà existant dans la base.</p>
    <p>La <em>durée avant connexion</em> est la durée en secondes au bout de laquelle votre session sera effacée sur le serveur, et une reconnexion sera nécessaire. C'est une sécurité si vous oubliez de vous déconnecter alors que vous êtes sur un ordinateur ouvert au public. La valeur par défaut est 900s, soit 15 minutes.</p>
    <p>La case à cocher <em>Conserver ma connexion sur cette machine</em> est au contraire utile lorsque vous êtes sur une machine personnelle comme votre téléphone, votre ordinateur personnel ou un ordinateur en réseau si vous faites bien attention à ne jamais laisser votre session ouverte sans vous ! Cocher cette case permet de ne pas avoir besoin de se connecter systématiquement. Le mot de passe ne sera demandé que pour l'affichage de données nomminatives ou de modifications importantes.</p>
    <h4>Modification des paramètres d'envoi de courriel</h4>
    <p>Ce réglage n'est disponible que si vous avez la possibilité d'envoyer des courriels via ce Cahier de Prépa. Vous pouvez alors régler votre <em>nom affiché comme expéditeur</em>, qui s'affiche notamment devant le destinataire lorsqu'il reçoit et lit votre courriel.</p>
    <p>La case à cocher <em>Recevoir une copie des courriels envoyés</em> n'est qu'une valeur par défaut. Une case à cocher se trouve sur la page de <a href="mail">rédaction des courriels</a>, vous permettant de modifier au cas par cas ce réglage. Il permet de recevoir sur son adresse électronique une copie des courriels envoyés, afin de l'archiver par exemple.</p>
  </div>

  <p id="log"></p>
  
  <script type="text/javascript">
$( function() {
  // Envoi par appui sur Entrée
  $('input').on('keypress',function (e) {
    if ( e.which == 13 ) {
      $(this).parents('article').children('a.icon-ok').click();
      return false;
    }
  });
  $('.icon-ferme').on('click',function() {
    $(this).parent().remove();
  });
});
  </script>
<?php
fin(true);
?>
