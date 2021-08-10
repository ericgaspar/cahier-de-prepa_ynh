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

// Accès aux professeurs connectés uniquement. Redirection pour les autres.
if ( $autorisation < 5 )  {
  header("Location: https://$domaine$chemin");
  exit();
}
$mysqli = connectsql();
// Si connexion light : on doit répéter son mot de passe pour aller plus loin
// login.php contient fin()
if ( $_SESSION['light'] )  {
  $titre = 'Les courriels';
  $actuel = 'utilisateurs-mails';
  include('login.php');
}

////////////////////////////////////////////////////////////////////////////////
// Préférence d'autorisation d'envoi de courriels 
// Stockée dans la table prefs, nom = autorisation_mails
//
// $aut_envoi est une valeur numérique contenant l'ensembles des accès entre
// les quatres groupes P (professeurs), L (lycée), C (colleurs) et E (élèves).
// C'est la représentation décimale de la valeur binaire
//    PP PL PC PE LP LL LC LE CP CL CC CE EP EL EC EE
// où XY correspond à l'autorisation de X à envoyer un courriel à Y (1=oui).
// Pour accéder aux autorisations du groupe numéro $n (2->E,3->C,4->L,5->P),
// il faut décaler $autorisation de 4*$n bits et garder les 4 bits faibles, soit
//    ( $aut_envoi >> 4*($n-2) ) & 15
// Pour accéder à l'autorisation du groupe numéro $n vers le groupe numéro $m, 
//    ( $aut_envoi >> 4*($n-2)+$m-2 ) & 1
////////////////////////////////////////////////////////////////////////////////

//////////////
//// HTML ////
//////////////
debut($mysqli,'Modification des réglages de courriels',$message,5,'utilisateurs-mails');

// Récupération des autorisations d'envoi
$resultat = $mysqli->query('SELECT val FROM prefs WHERE nom = "autorisation_mails"');
$aut_envoi = $resultat->fetch_row()[0];
$autorisations = array(5=>'les professeurs',4=>'le lycée',3=>'les colleurs',2=>'les élèves');
?>

  <div id="icones">
    <a class="icon-download" href="utilisateurs?xls" title="Télécharger la liste des utilisateurs en xls"></a>
    <a class="icon-aide" title="Aide pour les modifications des courriels"></a>
  </div>

  <article>
    <h3>Possibilités d'envoi de courriels</h3>
    <table id="envoimails" class="utilisateurs">
      <tbody>
        <tr>
          <th colspan="2"></th>
          <th class="vertical"><span>Vers les professeurs</span></th>
          <th class="vertical"><span>Vers le lycée</span></th>
          <th class="vertical"><span>Vers les colleurs</span></th>
          <th class="vertical"><span>Vers les éleves</span></th>
        </tr>
<?php
foreach ( $autorisations as $a => $auto )  {
  $envoi = ( $aut_envoi >> 4*($a-2) ) & 15;
  echo <<<FIN
        <tr data-id="$a">
          <th>Par $auto</th><th class="icones"><span class="icon-ok" title="Établir l'autorisation d'envoi générale par ${autorisations[$a]}"></span>&nbsp;<span class="icon-nok" title="Supprimer l'autorisation d'envoi par ${autorisations[$a]}"></span></th>
          
FIN;
  for ( $i=5; $i>=2; $i-- )  {
    $ok = ( $envoi >> $i-2 ) & 1;
    echo "<td class=\"icone\">$i|$ok</td>";
  }
  echo "        </tr>\n";
}
?>
      </tbody>
    </table>
  </article>

  <article>
    <h3>Liste des utilisateurs</h3>
    <table id="umails" class="utilisateurs">
      <tbody>
<?php
// Tableau des utilisateurs (comptes validés uniquement)
$autorisations = array(5=>'Professeur',4=>'Lycée',3=>'Colleur',2=>'Élève');
foreach ( $autorisations as $a => $auto )  {
  $resultat = $mysqli->query("SELECT id, nom, prenom, IF(LENGTH(mail),mail,\"Pas d'adresse\") AS mail, mailexp FROM utilisateurs WHERE mdp > '0' AND autorisation=$a ORDER BY nom, prenom, login");
  $s = ( $a == 4 ) ? '' : 's';
  if ( $n = $resultat->num_rows )  {
    echo <<<FIN
        <tr class="categorie"><th colspan="4">{$auto}$s ($n)</th></tr>
        <tr><th>Identité</th><th>Nom affiché</th><th>Adresse électronique</th><th></th></tr>

FIN;
    while ( $r = $resultat->fetch_assoc() )
      echo <<<FIN
        <tr data-id="${r['id']}">
          <td>${r['nom']} ${r['prenom']}</td>
          <td>${r['mailexp']}</td>
          <td>${r['mail']}</td>
          <td class="icones"><a class="icon-edite" title="Éditer ce compte"></a></td>
        </tr>

FIN;
    $resultat->free();
  }
}

?>
      </tbody>
    </table>
  </article>

<?php

// Aide et formulaire de modification
?>

  <div id="form-edite">
    <a class="icon-ok" title="Valider ces modifications"></a>
    <h3 class="edition">Modifier un utilisateur</h3>
    <form>
      <p id="compteactif">Vous pouvez ici modifier le compte XXX, de type YYY. Ce compte est actif. L'utilisateur du compte ne sera pas automatiquement prévenu de vos modifications.</p>
      <p id="comptedesactive">Vous pouvez ici modifier le compte XXX, de type YYY. Ce compte a été désactivé&nbsp;: la connexion à ce Cahier de Prépa par ce compte n'est pas possible.</p>
      <p id="demande">Vous pouvez ici modifier la demande XXX, de type YYY. Cette demande n'a pas encore été validée, vous pourrez la valider après modification.</p>
      <p id="invitation">Vous pouvez ici modifier l'invitation XXX, de type YYY. L'utilisateur de ce compte ne sera pas automatiquement prévenu de vos modifications. Attention, la modification de l'identifiant ou de l'adresse électronique rendra impossible la validation de l'invitation par l'utilisateur concerné.</p>
      <p>Seules les valeurs modifiées seront prises en compte. Pour modifier l'adresse électronique, il est nécessaire de la saisir deux fois.</p>
      <p class="ligne"><label for="prenom">Prénom&nbsp;: </label><input type="text" name="prenom" value="" size="50"></p>
      <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text" name="nom" value="" size="50"></p>
      <p class="ligne"><label for="login">Identifiant&nbsp;: </label><input type="text" name="login" value="" size="50"></p>
      <p class="ligne"><label for="mail1">Adresse électronique&nbsp;: </label><input type="email" name="mail1" value="" size="50"></p>
      <p class="ligne"><label for="mail2">Confirmation (si modification)&nbsp;: </label><input type="email" name="mail2" value="" size="50"></p>
      <p class="ligne"><label for="mailexp">Nom affiché comme expéditateur/destinataire de courriel&nbsp;: </label><input type="text" name="mailexp" value="" size="50"></p>
      <p class="ligne"><label for="mailcopie">Recevoir une copie des courriels envoyés&nbsp;: </label><input type="checkbox" name="mailcopie" value="1"></p>
    </form>
  </div>
  
  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici de régler les possibilités d'envoi de courriels, de visualiser l'ensemble des adresses électronique et de modifier les données des utilisateurs pouvant se connecter à ce Cahier de Prépa.</p>
    <p>Les associations entre les utilisateurs et les matières sont à régler à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
    <p>L'ajout, la suppression et la désactivation des comptes utilisateurs est possibles à la <a href="utilisateurs">gestion des utilisateurs</a>.</p>
    <p>Le seul bouton général <span class="icon-download"></span> permet de récupérer l'ensemble des noms et adresses électroniques de tous les utilisateurs en fichier de type <code>xls</code>, éditable par un logiciel tableur (Excel, LibreOffice Calc...).</p>
    <h4>Possibilités d'envoi de courriels</h4>
    <p>Le tableau est une double correspondance où chaque échange peut être autorisé (<span class="icon-ok"></span>) ou interdit (<span class="icon-nok"></span>). Un clic sur une de ces icônes commute entre autorisation et interdiction.</p>
    <p>Les boutons <span class="icon-ok"></span> et <span class="icon-nok"></span> en début de ligne permettent de modifier d'un seul clic toutes les possibilités d'envoi de la ligne.</p>
    <p>L'action est immédiate et s'applique instantanément à tous les utilisateurs, même connectés.</p>
    <h4>Tableau récapitulatif</h4>
    <p>Le tableau général présente tous les utilisateurs existants, ordonnés par type puis par ordre alphabétique.</p>
    <p>Les données particulières des utilisateurs (nom, prénom, identifiant, adresse électronique, nom affiché comme expéditeur) sont modifiables en cliquant sur le bouton <span class="icon-edite"></span> qui ouvrira un formulaire.</p>
    <p>Les identifiants sont modifiables ici. Ces identifiants ne servent qu'à la connexion&nbsp;: n'oubliez pas alors de prévenir l'utilisateur. Il pourra néanmoins se connecter à l'aide de son adresse électronique.</p>
    <h4>Types d'utilisateurs</h4>
    <p>Il existe cinq types d'utilisateurs&nbsp;:</p>
    <ul>
      <li>Les <em>professeurs</em> peuvent modifier tout ce qui est réglable dans ce Cahier de Prépa&nbsp;: pages d'informations et informations générales, utilisateurs, groupes d'élèves, matières, planning annuel. Tous les professeurs ont les mêmes droits sur ces catégories (il n'y a pas d'&laquo;&nbsp;administrateur&nbsp;&raquo;). Ils peuvent être associés ou non à une ou plusieurs matières, et pouvoir alors modifier ce qui concerne spécifiquement ces matières&nbsp;: programmes de colles, cahier de texte, documents, notes de colles. Ils peuvent voir l'ensemble des notes de colles mises dans les matières associées, et les récupérer sous forme de fichier xls. </li>
      <li>Les utilisateurs liés à l'administration du <em>lycée</em> peuvent relever les notes de colles via une interface spécifique. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>colleurs</em> peuvent être associés ou non à une ou plusieurs matières (ils ne peuvent pas modifier leur liste des matières associées). Ils peuvent voir les contenus associés à ces matières et les contenus généraux, si l'accès de ces contenus est autorisé. Ils peuvent mettre des notes dans ces matières et voir leurs notes uniquement. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>élèves</em> peuvent être associés ou non à une ou plusieurs matières (ils ne peuvent pas modifier leur liste des matières associées). Ils peuvent voir les contenus associés à ces matières et les contenus généraux,  si l'accès de ces contenus est autorisé. Ils peuvent voir leurs notes de colles. Ils peuvent modifier leur identité et leur mot de passe. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>invités</em> sont des comptes prévus pour être éventuellement partagés entre plusieurs personnes. Une fois connecté, il est impossible de changer les paramètres du compte (identifiant, mot de passe, matières associées). Les invités peuvent voir les contenus associés à leurs matières et les contenus généraux, si l'accès de ces contenus est autorisé.</li>
    </ul>
    <p>Il n'est pas possible de changer le type d'un utilisateur (transformer un élève en colleur, etc.).</p>
  </div>

  <p id="log"></p>
<?php
fin(true);
?>
