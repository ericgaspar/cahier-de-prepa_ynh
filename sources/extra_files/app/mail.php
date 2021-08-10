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

// Accès défini selon le champ mailenvoi dans la table utilisateurs
// Possibilité d'afficher cette page seulement si mailenvoi vaut 1.
$mysqli = connectsql();
if ( !$autorisation )  {
  $titre = 'Envoi de courriel';
  $actuel = false;
  include('login.php');
}
// Récupération de l'autorisation d'envoi de courriels
$resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
$aut_envoi = $resultat->fetch_row()[0] >> 4*($autorisation-2) & 15;
$resultat->free();
if ( !$aut_envoi )  {
  debut($mysqli,'Envoi de courriel','Vous n\'avez pas accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}
$aut_dest = array_reverse(array_keys(str_split('00'.strrev(decbin($aut_envoi))),1));
    
//////////////
//// HTML ////
//////////////
debut($mysqli,'Envoi de courriel',$message,$autorisation,'mail');

// Récupération des préférences d'envoi (mail, mailexp, mailcopie) de l'utilisateur
$resultat = $mysqli->query("SELECT mail, mailexp, IF(mailcopie,' checked','') AS mailcopie
                            FROM utilisateurs WHERE id = ${_SESSION['id']}");
$u = $resultat->fetch_assoc();
$resultat->free();
?>

  <div id="icones">
    <a class="icon-aide" data-id="page" title="Aide pour l'envoi de courriel"></a>
  </div>

  <article>
    <a class="icon-mailenvoi" title="Envoyer le courriel"></a>
    <p><strong>Nom de l'expéditeur&nbsp;: </strong>&nbsp;<?php echo $u['mailexp']; ?></p>
    <p><strong>Adresse électronique&nbsp;: </strong>&nbsp;<?php echo $u['mail']; ?></p>
    <p><strong>Destinataires&nbsp;: </strong>&nbsp;<span id="maildest">[Personne]</span>&nbsp;<a class="icon-edite"></a></p>
    <p><strong>Sujet&nbsp;:</strong></p>
    <form id="mail">
      <input class="ligne" type="text" name="sujet">
      <p><strong>Texte du message&nbsp;:</strong></p>
      <textarea name="texte" rows="20" cols="100"><?php echo "\n\n\n\n-- \n${u['mailexp']}\nMail envoyé depuis <https://$domaine$chemin"; ?>></textarea>
      <p class="ligne"><label for="copie">Recevoir le courriel en copie&nbsp;: </label>
        <input type="checkbox" id="copie" name="copie" value="1"<?php echo $u['mailcopie']; ?>>
      </p>
      <input type="hidden" name="id-copie" value="">
      <input type="hidden" name="id-bcc" value="">
      <input type="hidden" name="action" value="courriel">
    </form>
    <p>Le nom d'expéditeur, l'adresse électronique et le réglage par défaut de la mise en copie des courriels sont modifiables sur la page de <a href="prefs">vos préférences personnelles</a> (attention avant de cliquer sur ce lien, il n'y a pas d'enregistrement des brouillons).</p>
  </article>

  <p id="log"></p>

  <div id="form-destinataires">
    <a class="icon-ok" title="Valider ces destinataires"></a>
    <h3>Choix des destinataires</h3>
    <form>
    <table class="utilisateurs">
      <thead>
        <tr><th></th><th class="icone">Copie</th><th class="icone">Copie cachée</th></tr>
      </thead>
      <tbody>

<?php

// Récupération de tous les utilisateurs pouvant être destinataires, en fonction du réglage global
foreach ( $aut_dest as $a )  {
  $resultat = $mysqli->query("SELECT id, mailexp FROM utilisateurs WHERE autorisation = $a AND mdp > '0' AND LENGTH(mail) AND id != ${_SESSION['id']} ORDER BY nom, prenom, login");
  if ( $resultat->num_rows )  {
    switch ( $a )  {
      case 2 : $t = 'Élèves'; break;
      case 3 : $t = 'Colleurs'; break;
      case 4 : $t = 'Lycée'; break;
      case 5 : $t = 'Professeurs'; break;
    }
    echo <<<FIN
        <tr class="categorie">
          <th>$t</th>
          <th class="icone"><a class="icon-cocher dest" title="Cocher tous les $t en copie"></a></th>
          <th class="icone"><a class="icon-cocher bcc" title="Cocher tous les $t en copie cachée"></a></th>
        </tr>

FIN;
  }
  while ( $r = $resultat->fetch_assoc() )
    echo "        <tr><td>${r['mailexp']}</td><td class=\"icone\"><input type=\"checkbox\" class=\"dest\" value=\"${r['id']}\"></td><td class=\"icone\"><input type=\"checkbox\" class=\"bcc\" value=\"${r['id']}\"></td></tr>\n";
  $resultat->free();
}

// Récupération des groupes d'utilisateurs, pour les comptes non élèves seulement
if ( $autorisation > 2 )  {
  $resultat = $mysqli->query('SELECT g.id, g.nom, g.utilisateurs AS uid,
                              GROUP_CONCAT( u.mailexp ORDER BY u.nom SEPARATOR \', \') AS noms
                              FROM groupes AS g JOIN utilisateurs AS u ON FIND_IN_SET(u.id,g.utilisateurs)
                              WHERE g.mails AND u.mdp > \'0\' AND LENGTH(u.mail) AND FIND_IN_SET(u.autorisation,\''.implode(',',$aut_dest).'\')
                              GROUP BY g.id ORDER BY g.nom_nat');
  if ( $resultat->num_rows )  {
    echo "        <tr class=\"categorie\"><th>Groupes d'élèves</th><th></th><th></th></tr>\n";
    while ( $r = $resultat->fetch_assoc() )
      echo "        <tr class=\"gr\"><td>Groupe ${r['nom']}&nbsp;: ${r['noms']}</td><td class=\"icone\"><input type=\"checkbox\" class=\"dest\" value=\"${r['uid']}\"></td><td class=\"icone\"><input type=\"checkbox\" class=\"bcc\" value=\"${r['uid']}\"></td></tr>\n";
    $resultat->free();
  }
}
$mysqli->close();

// Aide et formulaire d'ajout
?>

      </tbody>
    </table>
    </form>
  </div>
  
<?php
// Aide spécifique aux élèves
if ( $autorisation == 2 )  {
?>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'envoyer un courriel à certains utilisateurs, selon un choix défini par les professeurs. L'envoi du courriel est réalisé lors du clic sur le bouton <span class="icon-mailenvoi" style="font-size: 1em;"></span>.</p>
    <h4>Expéditeur</h4>
    <p>Le <em>nom d'expéditeur</em>, l'<em>adresse électronique</em> et le réglage par défaut de la <em>mise en copie des courriels</em> sont modifiables sur la page de <a href="prefs">vos préférences personnelles</a>.</p>
    <p>Le courriel sera envoyé en votre nom mais avec une adresse électronique différente de la vôtre pour éviter d'être considéré comme du spam. Le retour du mail sera positionné sur votre adresse électronique&nbsp;: si vos correspondants répondent à ce mail, ils devraient automatiquement vous répondre.</p>
    <h4>Destinataires</h4>
    <p>La liste des destinataires est éditable à tout moment en cliquant sur le bouton <span class="icon-edite"></span>. Cela ouvre une fenêtre dans laquelle on peut cocher les destinataires. Le bouton <span class="icon-ok"></span> de cette fenêtre valide la liste des destinataires uniquement, n'envoie pas le courriel.</p>
    <p>Les cases à cocher <em>Copie</em> permettent d'ajouter les utilisateurs cochés dans la liste des destinataires visibles. Il est obligatoire qu'au moins un utilisateur soit destinataire.</p>
    <p>Les cases à cocher <em>Copie cachée</em> permettent d'ajouter les utilisateurs cochés dans la liste des destinataires en copie cachée&nbsp;: les autres destinataires ne verront pas que ce courriel a aussi été envoyé à ceux en copie cachée.</p>
    <p>Un utilisateur ne peut pas être à la fois en copie et en copie cachée.</p>
    <p>Un clic sur le nom d'un utilisateur est équivalent à un clic sur la case <em>Copie</em> associée.</p>
    <h4>Sujet et contenu du courriel</h4>
    <p>Le <em>sujet</em> est le titre du courriel. Tous les caractères sont autorisés. Vous devez envoyer vos courriels avec un sujet correspondant explicitement au contenu...</p>
    <p>Le <em>contenu</em> est le corps du courriel, en texte brut. Le courriel envoyé ne sera pas formaté en HTML&nbsp;: il n'est pas possible de réaliser un formattage particulier (changer une taille d'écriture, une police, mettre de la couleur...). Par convention classique,</p>
    <ul>
      <li>écrire un mot entre astérisques (*) signifie le mettre en gras et appuyer sur ce mot.</li>
      <li>écrire un mot entre slashes (/) signifie le mettre en italique pour indiquer qu'il faut y faire attention.</li>
      <li>écrire en majuscules signifie que l'on en train de crier. :-)</li>
    </ul>
    <h4>Réception en copie</h4>
    <p>Il est aussi possible de recevoir en copie ce mail en cochant la case située en bas de ce formulaire. Si la case est cochée, votre adresse électronique sera placée dans les destinataires en copie cachée.</p>
    <p>Les messages d'erreurs (de type &laquo;&nbsp;destinataire non valide&nbsp;&raquo;, &laquo;&nbsp;boîte pleine&nbsp;&raquo;...) seront envoyés automatiquement sur votre adresse électronique.</p>
  </div>

<?php
}
// Pour les autres utilisateurs
else  {
?>  

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'envoyer un courriel aux utilisateurs ayant renseigné leur adresse électronique, selon les réglages modifiables sur la page de <a href="utilisateurs-mails">gestion des courriels</a>. L'envoi du courriel est réalisé lors du clic sur le bouton <span class="icon-mailenvoi" style="font-size: 1em;"></span>.</p>
    <h4>Expéditeur</h4>
    <p>Le <em>nom d'expéditeur</em>, l'<em>adresse électronique</em> et le réglage par défaut de la <em>mise en copie des courriels</em> sont modifiables sur la page de <a href="prefs">vos préférences personnelles</a>.</p>
    <p>Le courriel sera envoyé en votre nom mais avec une adresse électronique différente de la vôtre pour éviter d'être considéré comme du spam. Le retour du mail sera positionné sur votre adresse électronique&nbsp;: si vos correspondants répondent à ce mail, ils devraient automatiquement vous répondre.</p>
    <h4>Destinataires</h4>
    <p>La liste des destinataires est éditable à tout moment en cliquant sur le bouton <span class="icon-edite"></span>. Cela ouvre une fenêtre dans laquelle on peut cocher les destinataires. Le bouton <span class="icon-ok"></span> de cette fenêtre valide la liste des destinataires uniquement, n'envoie pas le courriel.</p>
    <p>Un utilisateur ne peut pas être à la fois en copie et en copie cachée.</p>
    <p>Les cases à cocher <em>Copie</em> permettent d'ajouter les utilisateurs cochés dans la liste des destinataires. Il est obligatoire qu'au moins un utilisateur soit destinataire.</p>
    <p>Les cases à cocher <em>Copie cachée</em> permettent d'ajouter les utilisateurs cochés dans la liste des destinataires en copie cachée&nbsp;: les autres destinataires ne verront pas que ce courriel a aussi été envoyé à ceux en copie cachée.</p>
    <p>Un clic sur le nom d'un utilisateur est équivalent à un clic sur la case <em>Copie</em> associée.</p>
    <p>Les boutons <span class="icon-cocher"></span> permettent de cocher l'ensemble des utilisateurs du type correspondant.</p>
    <h4>Groupes d'utilisateurs</h4>
    <p>Si des groupes d'utilisateurs ont été établis, ils apparaissent en bas de la liste des destinataires. Cliquer sur les cases correspondantes ou le nom d'un groupe permet de sélectionner automatiquement les utilisateurs concernés.</p><?php if ( $autorisation == 5 ) echo '<p>Les groupes d\'utilisateurs peuvent être définis ou modifiés sur la page de <a href="groupes">gestion des groupes</a>.</p>'; ?>
    <h4>Sujet et contenu du courriel</h4>
    <p>Le <em>sujet</em> est le titre du courriel. Tous les caractères sont autorisés. Pensez à envoyer des courriels avec un sujet correspondant explicitement au contenu...</p>
    <p>Le <em>contenu</em> est le corps du courriel, en texte brut. Le courriel envoyé ne sera pas formaté en HTML&nbsp;: il n'est pas possible de réaliser un formattage particulier (changer une taille d'écriture, une police, mettre de la couleur...). Par convention classique,</p>
    <ul>
      <li>écrire un mot entre astérisques (*) signifie le mettre en gras et appuyer sur ce mot.</li>
      <li>écrire un mot entre slashes (/) signifie le mettre en italique pour indiquer qu'il faut y faire attention.</li>
      <li>écrire en majuscules signifie que l'on en train d'hurler. :-)</li>
    </ul>
    <h4>Réception en copie</h4>
    <p>Il est aussi possible de recevoir en copie ce mail en cochant la case située en bas de ce formulaire. Si la case est cochée, votre adresse électronique sera placée dans les destinataires en copie cachée.</p>
    <p>Les messages d'erreurs (de type &laquo;&nbsp;destinataire non valide&nbsp;&raquo;, &laquo;&nbsp;boîte pleine&nbsp;&raquo;...) seront envoyés automatiquement sur votre adresse électronique.</p>
  </div>

<?php
}
?>  

  <script type="text/javascript">
$( function() {
  // Envoi par appui sur Entrée dans le sujet
  $('input[name="sujet"]').on('keypress',function (e) {
    if ( e.which == 13 ) {
      $('a.general.icon-ok').click();
      return false;
    }
  });
});
  </script>
<?php
fin(true);
?>
