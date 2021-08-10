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
  $titre = 'Modification des groupes';
  $actuel = 'groupes';
  include('login.php');
}

// Récupération des utilisateurs et fabrication du formulaire de modification des utilisateurs
$resultat = $mysqli->query('SELECT id, autorisation, IF(nom > \'\',CONCAT(nom,\' \',prenom),CONCAT(\'<em>\',login,\'</em>\')) AS nomcomplet,
                            (mail=\'\') AS pasmail, (LEFT(mdp,1)=\'!\') AS desactive, (LEFT(mdp,1)=\'*\') AS demande, (mdp=\'?\') AS invitation
                            FROM utilisateurs WHERE autorisation > 1 ORDER BY autorisation DESC, nom, prenom, login');
$a = 0;
$utilisateurs = array();
$table = '';
while ( $r = $resultat->fetch_assoc() )  {
  $utilisateurs[$r['id']] = $r['nomcomplet'];
  if ( $a != $r['autorisation'] )  {
    $a = $r['autorisation'];
    switch ( $a )  {
      case 2 : $t = 'Élèves'; break;
      case 3 : $t = 'Colleurs'; break;
      case 4 : $t = 'Lycée'; break;
      case 5 : $t = 'Professeurs'; break;
    }
    $table .= <<<FIN
        <tr class="categorie">
          <th>$t</th>
          <th class="icone"><a class="icon-cocher"></a></th>
        </tr>

FIN;
  }
  if ( $r['pasmail'] == 1 )         $r['nomcomplet'] .= ' (pas d\'adresse électronique)';
  elseif ( $r['desactive'] == 1 )   $r['nomcomplet'] .= ' (compte désactivé)';
  elseif ( $r['demande'] == 1 )     $r['nomcomplet'] .= ' (demande non répondue)';
  elseif ( $r['invitation'] == 1 )  $r['nomcomplet'] .= ' (invitation non répondue)';
  $table .= "        <tr><td>${r['nomcomplet']}</td><td class=\"icone\"><input type=\"checkbox\" id=\"u${r['id']}\"></td></tr>\n";
}
$resultat->free();


//////////////
//// HTML ////
//////////////
debut($mysqli,'Modication des groupes d\'utilisateurs',$message,5,'groupes');
echo <<<FIN

  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter un groupe d'utilisateurs"></a>
    <a class="icon-aide" title="Aide pour les modifications des groupes"></a>
  </div>

FIN;

// Récupération et affichage
$resultat = $mysqli->query('SELECT id, nom, IF(mails,\' checked\',\'\') AS mails, IF(notes,\' checked\',\'\') AS notes, utilisateurs FROM groupes');
if ( $resultat->num_rows )  {
  while ( $r = $resultat->fetch_assoc() )  {
    $u =  implode(', ',array_intersect_key($utilisateurs,array_flip(explode(',',$r['utilisateurs']))));
    echo <<<FIN

  <article data-id="groupes|${r['id']}">
    <h3 class="edition">Groupe <span class="editable" data-id="groupes|nom|${r['id']}" data-placeholder="Nom du groupe (Ex: 1, A, LV2 Espagnol...)">${r['nom']}</span></h3>
    <a class="icon-aide" title="Aide pour l'édition de ce groupe"></a>
    <a class="icon-supprime" title="Supprimer ce groupe"></a>
    <p class="usergrp"><strong>Utilisateurs&nbsp;:</strong> <span data-uids="${r['utilisateurs']}">$u</span></p>
    <p class="ligne"><label for="mails${r['id']}">Groupe visible lors de l'envoi de courriel</label><input type="checkbox" id="mails${r['id']}"${r['mails']}></p>
    <p class="ligne"><label for="notes${r['id']}">Groupe visible lors de la saisie des notes de colles</label><input type="checkbox" id="notes${r['id']}"${r['notes']}></p>
  </article>

FIN;
  }
  $resultat->free();
}
else
  echo "\n  <article>\n    <h2>Aucun groupe n'est enregistré.</h2>\n  </article>\n";

// Aide et formulaire d'ajout
?>

  <div id="form-utilisateurs">
    <a class="icon-ok" title="Valider ces utilisateurs"></a>
    <h3>Choix des utilisateurs du groupe </h3>
    <form>
    <table class="utilisateurs">
      <tbody>
<?php echo $table; ?>
      </tbody>
    </table>
    </form>
  </div>
  
  <form id="form-ajoute" data-action="ajout-groupe">
    <h3 class="edition">Ajouter un nouveau groupe</h3>
    <div>
      <input type="text" class="ligne" name="nom" value="" size="50" placeholder="Nom du groupe (Ex: 1, A, LV2 Espagnol...)">
      <p class="usergrp"><strong>Utilisateurs&nbsp;:</strong> <span data-uids="">[Personne]</span></p>
      <p class="ligne"><label for="mails">Groupe visible lors de l'envoi de courriel</label><input type="checkbox" name="mails" value="1"></p>
      <p class="ligne"><label for="notes">Groupe visible lors de la saisie des notes de colles</label><input type="checkbox" name="notes" value="1"></p>
      <input type="hidden" name="uids" value="">
    </div>
  </form>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter et de modifier des groupes d'utilisateurs. Ces groupes peuvent être utilisés pour deux fonctionnalités&nbsp;:</p>
    <ul>
      <li>l'envoi de courriels&nbsp;: ils sont listés à la suite des expéditeurs possibles, pour les sélectionner plus facilement. Ils peuvent regrouper des utilisateurs de tout type.</li>
      <li>la saisie de notes de colles&nbsp;: ils permettent une sélection rapide des élèves à noter. Ils sont logiquement censés regrouper des élèves uniquement.</li>
    </ul>
    <p>Les groupes pour l'envoi de courriel ne sont en réalité affichés que pour les utilisateurs colleur/lycée/professeur. Si les élèves peuvent envoyer des courriels (réglage sur la page de <a href="utilisateurs-mails">gestion des courriels</a>), ils ne voient pas les groupes. Il est donc possible de faire des groupes «&nbsp;Colleurs de [matière]&nbsp;» ou «&nbsp;Équipe pédagogique&nbsp;» par exemple.</p>
    <p>Les groupes d'utilisateurs ne se limitent pas aux groupes de colles, il peut être notamment intéressant de créer les deux demi-groupes de la classe ou les groupes d'option par exemple, pour envoyer des courriels aux seuls élèves concernés le cas échéant.</p>
    <p>Les notes de colles peuvent être mises à cheval sur plusieurs groupes. Ce n'est pas grave si parfois un élève ne colle pas avec le reste de son groupe marqué ici.</p>
    <p>Chaque utilisateur peut participer à plusieurs groupes. Chaque groupe peut contenir jusqu'à l'ensemble des utilisateurs, sans limite de type de compte ou de matière associée.</p>
    <p>Un clic sur l'icône <span class="icon-ajoute"></span> permet d'ouvrir le formulaire permettant de créer un nouveau groupe.</p>
    <h4>Modification des groupes</h4>
    <p>Chaque groupe existant peut être supprimé en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée). Cela ne supprime pas les comptes des utilisateurs du groupe.</p>
    <p>Le nom et la liste des utilisateurs de chaque groupe existant sont indiqués par des zones en pointillés et peuvent être modifiés en cliquant sur le bouton <span class="icon-edite"></span> et en validant avec le bouton <span class="icon-ok"></span> qui apparaît alors.</p>
    <p>Les cases à cocher pour définir l'utilisation des groupes sur les courriels ou les notes agissent immédiatement&nbsp;: cocher ou décocher active ou désactive l'utilisation, sans validation supplémentaire.</p>
    <h4>Ordre des groupes</h4>
    <p>Les groupes sont automatiquement classés par ordre alphanumérique.</p>
  </div>
  
  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer un nouveau groupe d'utilisateurs. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Les groupes d'utilisateurs peuvent être utilisés pour deux fonctionnalités&nbsp;:</p>
    <ul>
      <li>l'envoi de courriels&nbsp;: ils sont listés à la suite des expéditeurs possibles, pour les sélectionner plus facilement. Ils peuvent regrouper des utilisateurs de tout type.</li>
      <li>la saisie de notes de colles&nbsp;: ils permettent une sélection rapide des élèves à noter. Ils sont logiquement censés regrouper des élèves uniquement.</li>
    </ul>
    <p>Les groupes pour l'envoi de courriel ne sont en réalité affichés que pour les utilisateurs colleur/lycée/professeur. Si les élèves peuvent envoyer des courriels (réglage sur la page de <a href="utilisateurs-mails">gestion des courriels</a>), ils ne voient pas les groupes. Il est donc possible de faire des groupes «&nbsp;Colleurs de [matière]&nbsp;» ou «&nbsp;Équipe pédagogique&nbsp;» par exemple.</p>
    <p>Les groupes d'utilisateurs ne se limitent pas aux groupes de colles, il peut être notamment intéressant de créer les deux demi-groupes de la classe ou les groupes d'option par exemple, pour envoyer des courriels aux seuls élèves concernés le cas échéant.</p>
    <p>Les notes de colles peuvent être mises à cheval sur plusieurs groupes. Ce n'est pas grave si parfois un élève ne colle pas avec le reste de son groupe marqué ici.</p>
    <p>Les groupes sont automatiquement classés par ordre alphanumérique.</p>
    <h4>Préférence du groupe</h4>
    <p>Le <em>nom du groupe</em> est ce qui apparaîtra derrière la mention &laquo;&nbsp;Groupe&nbsp;&raquo;. Il peut s'agir d'un simple numéro (1,2,3...) pour des groupes de colles, d'une lettre ou d'un mot pour des demi-groupes par exemple (A et B, impairs et pairs...), ou encore d'un nom plus long (&laquo;&nbsp;Colleurs de Mathématiques&nbsp;&raquo;...).</p>
    <p>La liste des <em>utilisateurs</em> est à définir en cliquant sur le bouton <span class="icon-edite"></span> à côté. Une nouvelle fenêtre permet alors de cocher ou décocher les utilisateurs, en cliquant sur les cases ou sur les noms des utilisateurs. L'icône <span class="icon-cocher"></span> permet de cocher tous les utilisateurs d'un même type. Un utilisateur au minimum est obligatoire.</p>
    <p>Les deux cases à cocher <em>Groupe visible lors de l'envoi de courriel</em> et <em>Groupe visible lors de la saisie des notes de colles</em> permettent de choisir l'utilisation du groupe.</p>
  </div>
  
  <div id="aide-groupes">
    <h3>Aide et explications</h3>
    <p>Le <em>nom du groupe</em> et la liste des <em>utilisateurs</em> sont modifiables séparément, en cliquant sur le bouton <span class="icon-edite"></span> correspondant. Chaque contenu éditable est indiqué par une bordure en pointillés.</p>
    <p>Le <em>nom du groupe</em> est ce qui apparaîtra derrière la mention &laquo;&nbsp;Groupe&nbsp;&raquo;. Il peut s'agir d'un simple numéro (1,2,3...) pour des groupes de colles, d'une lettre ou d'un mot pour des demi-groupes par exemple (A et B, impairs et pairs...), ou encore d'un nom plus long (&laquo;&nbsp;Colleurs de Mathématiques&nbsp;&raquo;...).</p>
    <p>La liste des <em>utilisateurs</em> est à définir en cliquant sur le bouton <span class="icon-edite"></span> à côté. Une nouvelle fenêtre permet alors de cocher ou décocher les utilisateurs, en cliquant sur les cases ou sur les noms des utilisateurs. L'icône <span class="icon-cocher"></span> permet de cocher tous les utilisateurs d'un même type. Un utilisateur au minimum est obligatoire.</p>
    <p>Chaque groupe existant peut être supprimé en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée). Cela ne supprime pas les comptes des utilisateurs du groupe.</p>
    <h4>Utilisation du groupe</h4>
    <p>Chaque groupe d'utilisateurs peut être utilisé pour deux fonctionnalités&nbsp;:</p>
    <ul>
      <li>l'envoi de courriels&nbsp;: ils sont listés à la suite des expéditeurs possibles, pour les sélectionner plus facilement. Ils peuvent regrouper des utilisateurs de tout type.</li>
      <li>la saisie de notes de colles&nbsp;: ils permettent une sélection rapide des élèves à noter. Ils sont logiquement censés regrouper des élèves uniquement.</li>
    </ul>
    <p>Les groupes pour l'envoi de courriel ne sont en réalité affichés que pour les utilisateurs colleur/lycée/professeur. Si les élèves peuvent envoyer des courriels (réglage sur la page de <a href="utilisateurs-mails">gestion des courriels</a>), ils ne voient pas les groupes. Il est donc possible de faire des groupes «&nbsp;Colleurs de [matière]&nbsp;» ou «&nbsp;Équipe pédagogique&nbsp;» par exemple.</p>
    <p>Les groupes d'utilisateurs ne se limitent pas aux groupes de colles, il peut être notamment intéressant de créer les deux demi-groupes de la classe ou les groupes d'option par exemple, pour envoyer des courriels aux seuls élèves concernés le cas échéant.</p>
    <p>Les notes de colles peuvent être mises à cheval sur plusieurs groupes. Ce n'est pas grave si parfois un élève ne colle pas avec le reste de son groupe marqué ici.</p>
    <p>Les cases à cocher pour définir l'utilisation des groupes sur les courriels ou les notes agissent immédiatement&nbsp;: cocher ou décocher active ou désactive l'utilisation, sans validation supplémentaire.</p>
    <h4>Ordre des groupes</h4>
    <p>Les groupes sont automatiquement classés par ordre alphanumérique.</p>
  </div>
  
  <p id="log"></p>
<?php

$mysqli->close();
fin(true);
?>
