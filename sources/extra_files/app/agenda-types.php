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
  $titre = 'Types d\'événements de l\'agenda';
  $actuel = 'agenda';
  include('login.php');
}

//////////////
//// HTML ////
//////////////
debut($mysqli,"Agenda - Types d'événements",$message,5,'agenda',false,'colpick');
echo <<<FIN

  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter un nouveau type d'événements"></a>
    <a class="icon-annule" onclick="history.back()" title="Retour à l'agenda"></a>
    <a class="icon-aide" title="Aide pour les modifications des types d'événements"></a>
  </div>

  <h2 class="edition">Modifier les types d'événements</h2>

FIN;

// Récupération
$resultat = $mysqli->query('SELECT t.id, ordre, nom, cle, couleur, COUNT(a.id) AS nb FROM `agenda-types` AS t LEFT JOIN agenda AS a ON type = t.id GROUP BY t.id ORDER BY ordre');
$mysqli->close();
$max = $resultat->num_rows;
while ( $r = $resultat->fetch_assoc() )  {
  $id = $r['id'];
  $s = ( $r['nb'] > 1 ) ? 's' : '';
  if ( $id < 3 )  {
    $suppr = '';
    $indication = ' Il n\'est pas supprimable car il est utilisé pour l\'ajout automatique des déplacements de colles.';
  }
  else  {
    $suppr = "\n    <a class=\"icon-supprime\" title=\"Supprimer ce type d'événements\"></a>";
    $indication = '';
  }
  $monte = ( $r['ordre'] == 1 ) ? ' style="display:none;"' : '';
  $descend = ( $r['ordre'] == $max ) ? ' style="display:none;"' : '';
  echo <<<FIN

  <article data-id="agenda-types|$id">
    <a class="icon-ok" title="Valider les modifications"></a>
    <a class="icon-aide" title="Aide pour l'édition de ce type d'événements"></a>
    <a class="icon-monte"$monte title="Déplacer ce type d'événements vers le haut"></a>
    <a class="icon-descend"$descend title="Déplacer ce type d'événements vers le bas"></a>$suppr
    <h3 class="edition">${r['nom']}</h3>
    <form>
      <p>Ce type d'événements correspond à ${r['nb']} événement$s dans l'agenda.$indication</p>
      <p class="ligne"><label for="nom$id">Nom&nbsp;: </label><input type="text" id="nom$id" name="nom" value="${r['nom']}" size="50"></p>
      <p class="ligne"><label for="cle$id">Clé&nbsp;: </label><input type="text" id="cle$id" name="cle" value="${r['cle']}" size="50"></p>
      <p class="ligne"><label for="couleur$id">Couleur&nbsp;: </label><input type="text" id="couleur$id" name="couleur" value="${r['couleur']}" size="6"></p>
    </form>
  </article>

FIN;
}
$resultat->free();

// Aide et formulaire d'ajout
?>

  <form id="form-ajoute" data-action="ajout-agenda-types">
    <h3 class="edition">Ajouter un nouveau type d'événements</h3>
    <div>
      <input type="text" class="ligne" name="nom" value="" size="50" placeholder="Nom pour l'affichage (Commence par majuscule, singulier)">
      <input type="text" class="ligne" name="cle" value="" size="50" placeholder="Clé pour les adresses web (Un seul mot, minuscules ou sigle, singulier)">
      <input type="text" class="ligne" name="couleur" value="" size="6" placeholder="Couleur des événements (code RRGGBB)">
    </div>
  </form>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter ou de modifier les types d'événements de l'agenda. Ces modifications sont propres à chaque matière.</p>
    <p>Le <em>nom</em> sera affiché au début de chaque événement, dans le calendrier et dans les informations récentes. Il doit s'agit d'un nom singulier et commençant par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Annulation de cours&nbsp;», «&nbsp;Interrogation de cours&nbsp;» (si vous souhaitez les annoncer :-) )</p>
    <p>La <em>clé</em> sera affichée dans le menu déroulant de recherche, précédé de «&nbsp;les&nbsp», ainsi que dans l'adresse des pages qui affichent ce type d'événements&nbsp;: il faut donc que ce soit un pluriel, pas trop long, en un mot, sans majucule au début (sauf s'il le faut). Par exemple, «&nbsp;annulations&nbsp;», «&nbsp;interros&nbsp;».</p>
    <p>Remarque&nbsp;: les clés ne sont pas encore utilisées actuellement, mais le seront certainement dans la prochaine version, qui devrait arriver en cours d'année.</p>
    <p>La <em>couleur</em> est celle qui sera affiché pour tous les événement du type concerné, dans le calendrier. Elle est codée sous la forme <code>RRGGBB</code>, un sélecteur de couleur apparaît au clic sur la case colorée.</p>
    <h4>Suppression et gestion de l'ordre d'affichage</h4>
    <p>Il est possible de supprimer la plupart des types d'événements en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée).</p>
    <p>Tous les types d'événements peuvent être déplacés les uns par rapport aux autres, à l'aide des boutons <span class="icon-monte"></span> et <span class="icon-descend"></span>. Cela modifie leur ordre d'affichage dans les menus de sélection, pour la création et la modification d'événements.</p>
    <h4>Déplacement et rattrapage de colle</h4>
    <p>Les types <em>Déplacement de colle</em> et <em>Rattrapage de colle</em> sont utilisé par la possibilité d'ajout automatique d'un déplacement de colle dans l'agenda. Il est donc impossible de les supprimer, mais l'on peut tout à fait les déplacer voire les renommer (ce qui n'est pas une bonne idée), et bien sûr modifier la couleur.</p>
  </div>

  <div id="aide-agenda-types">
    <h3>Aide et explications</h3>
    <p>Le <em>nom</em> sera affiché au début de chaque événement, dans le calendrier et dans les informations récentes. Il doit s'agit d'un nom singulier et commençant par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Annulation de cours&nbsp;», «&nbsp;Interrogation de cours&nbsp;» (si vous souhaitez les annoncer :-) )</p>
    <p>La <em>clé</em> sera affichée dans le menu déroulant de recherche, précédé de «&nbsp;les&nbsp», ainsi que dans l'adresse des pages qui affichent ce type d'événements&nbsp;: il faut donc que ce soit un pluriel, pas trop long, en un mot, sans majucule au début (sauf s'il le faut). Par exemple, «&nbsp;annulations&nbsp;», «&nbsp;interros&nbsp;».</p>
    <p>Remarque&nbsp;: les clés ne sont pas encore utilisées actuellement, mais le seront certainement dans la prochaine version, qui devrait arriver en cours d'année.</p>
    <p>La <em>couleur</em> est celle qui sera affiché pour tous les événement du type concerné, dans le calendrier. Elle est codée sous la forme <code>RRGGBB</code>, un sélecteur de couleur apparaît au clic sur la case colorée.</p>
    <p>Une fois les modifications faites, il faut les valider en cliquant sur le bouton <span class="icon-ok"></span>.</p>
    <p>D'autres modifications sont possibles à l'aide des boutons disponibles pour chaque type d'événements&nbsp;:</p>
    <ul>
      <li><span class="icon-supprime"></span>&nbsp;: supprimer le type d'événements (une confirmation sera demandée)</li>
      <li><span class="icon-monte"></span>&nbsp;: remonter le type d'événements d'un cran</li>
      <li><span class="icon-descend"></span>&nbsp;: descendre le type d'événements d'un cran</li>
    </ul>
    <p>Attention&nbsp;: supprimer un type d'événements supprime aussi automatiquement tous les événements correspondant à ce type. Le nombre d'événements correspondant à un type est donné pour chaque type de séances.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer un nouveau type d'événements. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Le <em>nom</em> sera affiché au début de chaque événement, dans le calendrier et dans les informations récentes. Il doit s'agit d'un nom singulier et commençant par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Annulation de cours&nbsp;», «&nbsp;Interrogation de cours&nbsp;» (si vous souhaitez les annoncer :-) )</p>
    <p>La <em>clé</em> sera affichée dans le menu déroulant de recherche, précédé de «&nbsp;les&nbsp», ainsi que dans l'adresse des pages qui affichent ce type d'événements&nbsp;: il faut donc que ce soit un pluriel, pas trop long, en un mot, sans majucule au début (sauf s'il le faut). Par exemple, «&nbsp;annulations&nbsp;», «&nbsp;interros&nbsp;». La clé doit obligatoirement être unique (deux types d'événements ne peuvent pas avoir la même clé).</p>
    <p>Remarque&nbsp;: les clés ne sont pas encore utilisées actuellement, mais le seront certainement dans la prochaine version, qui devrait arriver en cours d'année.</p>
    <p>La <em>couleur</em> est celle qui sera affiché pour tous les événement du type concerné, dans le calendrier. Elle est codée sous la forme <code>RRGGBB</code>, un sélecteur de couleur apparaît au clic sur la case colorée.</p>
  </div>

  <p id="log"></p>

  <script type="text/javascript" src="js/colpick.min.js"></script>
  <script type="text/javascript">
$( function() {
  // Sélecteurs de couleurs pour les formulaire affichés au chargement
  $('[name="couleur"]').colpick();
  // Envoi par appui sur Entrée
  $('input,select').on('keypress',function (e) {
    if ( e.which == 13 ) {
      $(this).parent().parent().children('a.icon-ok').click();
      return false;
    }
  });
});
  </script>

<?php
fin(true);
?>
