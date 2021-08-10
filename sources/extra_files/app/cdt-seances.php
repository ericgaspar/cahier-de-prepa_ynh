<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

// Note : l'affichage des heures de début/fin est conditionné par le champ
// deb_fin_pour de la table cdt-types (donc du type de séances choisi) :
// 0 : Début seulement (jour date à debut : type (demigroupe))
// 1 : Début et fin (jour date de debut à fin : type (demigroupe))
// 2 : Pas d'horaire mais date d'échéance (jour date : type pour (demigroupe))
// 3 : Pas d'horaire ni date d'échéance (jour date : type (demigroupe))
// 4 : Entrée journalière (jour date)
// 5 : Entrée hebdomadaire (pas de titre)

////////////////////////////////////////
// Validation de la requête : matière //
////////////////////////////////////////

// Recherche de la matière concernée, variable $matiere
// Si $_REQUEST['cle'] existe, on la cherche dans les matières disponibles.
// colles_protection=32 : cahier de texte désactivé, pas d'affichage
// Accès aux professeurs connectés uniquement
$mysqli = connectsql();
if ( $autorisation == 5 )  {
  $resultat = $mysqli->query("SELECT id, cle, nom FROM matieres WHERE FIND_IN_SET(id,'${_SESSION['matieres']}') AND cdt_protection < 32");
  if ( $resultat->num_rows )  {
    if ( !empty($_REQUEST) )  {
      while ( $r = $resultat->fetch_assoc() )
        if ( isset($_REQUEST[$r['cle']]) )  {
          $matiere = $r;
          break;
        }
    }
    $resultat->free();
  }
  // Si aucune matière trouvée
  if ( !isset($matiere) )  {
    debut($mysqli,'Types de séances du cahier de texte','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
}
elseif ( $autorisation )  {
  debut($mysqli,'Types de séances du cahier de texte','Vous n\'avez pas accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}
else  {
  $titre = 'Types de séances du cahier de texte';
  $actuel = false;
  include('login.php');
}

//////////////
//// HTML ////
//////////////
debut($mysqli,"Cahier de texte - ${matiere['nom']}",$message,5,"cdt-seances?${matiere['cle']}");
echo <<<FIN

  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter un nouveau type de séances"></a>
    <a class="icon-annule" onclick="history.back()" title="Retour au cahier de texte"></a>
    <a class="icon-aide" title="Aide pour les modifications des types de séances"></a>
  </div>

  <h2 class="edition">Modifier les types de séances</h2>

FIN;
$deb_fin_pour = '
          <option value="0">Début seulement</option>
          <option value="1">Début et fin</option>
          <option value="2">Pas d\'horaire mais date d\'échéance</option>
          <option value="3">Pas d\'horaire ni date d\'échéance</option>
          <option value="4">Entrée journalière</option>
          <option value="5">Entrée hebdomadaire</option>
';
// Récupération
$resultat = $mysqli->query("SELECT id, ordre, titre, cle, deb_fin_pour, nb FROM `cdt-types` WHERE matiere = ${matiere['id']}");
$mysqli->close();
$max = $resultat->num_rows;
while ( $r = $resultat->fetch_assoc() )  {
  $id = $r['id'];
  $monte = ( $r['ordre'] == 1 ) ? ' style="display:none;"' : '';
  $descend = ( $r['ordre'] == $max ) ? ' style="display:none;"' : '';
  $s = ( $r['nb'] > 1 ) ? 's' : '';
  $dfp = str_replace("\"${r['deb_fin_pour']}\"","\"${r['deb_fin_pour']}\" selected",$deb_fin_pour);
  echo <<<FIN

  <article data-id="cdt-types|$id">
    <a class="icon-aide" title="Aide pour l'édition de ce type de séances"></a>
    <a class="icon-ok" title="Valider les modifications"></a>
    <a class="icon-monte"$monte title="Déplacer ce type de séances vers le haut"></a>
    <a class="icon-descend"$descend title="Déplacer ce type de séances vers le bas"></a>
    <a class="icon-supprime" title="Supprimer ce type de séances"></a>
    <form>
      <h3 class="edition">${r['titre']}</h3>
      <p>Ce type de séances correspond à ${r['nb']} élément$s du cahier de texte.</p>
      <p class="ligne"><label for="titre$id">Titre&nbsp;: </label><input type="text" id="titre$id" name="titre" value="${r['titre']}" size="50"></p>
      <p class="ligne"><label for="cle$id">Clé&nbsp;: </label><input type="text" id="cle$id" name="cle" value="${r['cle']}" size="50"></p>
      <p class="ligne"><label for="deb_fin_pour$id">Affichage d'horaires&nbsp;:</label>
        <select id="deb_fin_pour$id" name="deb_fin_pour">$dfp        </select>
      </p>
    </form>
  </article>

FIN;
}
$resultat->free();

// Aide et formulaire d'ajout
?>

  <form id="form-ajoute" data-action="ajout-cdt-types">
    <h3 class="edition">Ajouter un nouveau type de séances</h3>
    <div>
      <input type="text" class="ligne" name="titre" value="" size="50" placeholder="Titre pour l'affichage (Commence par majuscule, singulier)">
      <input type="text" class="ligne" name="cle" value="" size="50" placeholder="Clé pour les adresses web (Un seul mot, pluriel)">
      <p class="ligne"><label for="deb_fin_pour">Affichage d'horaires&nbsp;:</label>
        <select name="deb_fin_pour">
          <option value="0">Début seulement</option>
          <option value="1">Début et fin</option>
          <option value="2">Pas d'horaire mais date d'échéance</option>
          <option value="3">Pas d'horaire ni date d'échéance</option>
          <option value="4">Entrée journalière</option>
          <option value="5">Entrée hebdomadaire</option>
        </select>
      </p>
    </div>
    <input type="hidden" name="matiere" value="<?php echo $matiere['id']; ?>">
  </form>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter ou de modifier les types de séances du cahier de texte. Les modifications effectuées ici ne concernent que la matière <em><?php echo $matiere['nom']; ?></em>.</p>
    <p>Le <em>titre</em> sera affiché au début de chaque élément du cahier de texte. Il doit s'agit d'un nom singulier et commençant par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Séance de travaux pratiques&nbsp;», «&nbsp;Interrogation de cours&nbsp;»</p>
    <p>La <em>clé</em> sera affichée dans le menu déroulant de recherche, précédé de «&nbsp;les&nbsp», ainsi que dans l'adresse des pages qui affichent ce type de séances&nbsp;: il faut donc que ce soit un pluriel, court, en un mot, sans majucule au début (sauf s'il le faut). Par exemple, «&nbsp;TP&nbsp;», «&nbsp;interros&nbsp;».</p>
    <p>Vous pouvez choisir les valeurs affichées pour chaque type de séances grâce à l'<em>affichage d'horaires</em>&nbsp;:</p>
    <ul>
      <li><em>Début seulement</em> conduit à ce qu'il n'y ait qu'une heure de début. Utile pour les interrogations par exemple.</li>
      <li><em>Début et fin</em> conduit à ce qu'il y ait une heure de début et une heure de fin. C'est le cas le plus général.</li>
      <li><em>Pas d'horaire mais date d'échéance</em> conduit à n'afficher qu'une date (&nbsp;pour le&nbsp;) et aucun horaire. Utile typiquement pour les devoirs maison.</li>
      <li><em>Pas d'horaire ni date d'échéance</em> vous convient si vous souhaitez arrêter de saisir les heures. Par exemple, pour les devoirs surveillés du samedi matin, il suffit d'avoir la date. Le titre affiché pour une séance de ce type sur la partie publique contiendra la date et le <em>titre</em> du type (par exemple &laquo;&nbsp;Samedi XX/XX/XXXX : Devoir surveillé&nbsp;&raquo;).</li>
      <li><em>Entrée journalière</em> vous convient si vous souhaitez ne saisir qu'un élément par jour dans le cahier de texte. La différence avec la possibilité précédente et que l'on n'affichera pas le <em>titre</em> du type sur la partie publique. Cela est donc utile uniquement si vous souhaitez regrouper vos séances par jour. Le titre affiché pour un élément du cahier de texte de ce type sur la partie publique ne contiendra que la date. Le meilleur <em>titre</em> sera alors certainement &laquo;&nbsp;Cours&nbsp;&raquo; (ne s'affichera que dans le formulaire de recherche en haut de page).</li>
      <li><em>Entrée hebdomadaire</em> vous convient si vous souhaitez ne saisir qu'un élément par semaine dans le cahier de texte. Cela est donc utile uniquement si vous souhaitez regrouper vos séances par semaine. Il n'y aura pas de titre pour un élément du cahier de texte de ce type sur la partie publique, car la date de chaque semaine est déjà écrite systématiquement.</li>
    </ul>
    <h4>Exemples de différentes possibilités</h4>
    <p>La première possibilité, celle de base, correspond à tout renseigner&nbsp; chaque séance (cours, TD, TP) a une heure de début et une heure de fin. On obtient&nbsp;</p>
    <ul>
      <li>[ Cours, cours, Début et fin ]</li>
      <li>[ Séance de travaux dirigés, TD, Début et fin ]</li>
      <li>[ Séance de travaux pratiques, TP, Début et fin ]</li>
      <li>[ Devoir surveillé, DS, Début et fin ]</li>
      <li>[ Interrogation de cours, interros, Début seulement ]</li>
      <li>[ Distribution de document, distributions, Début seulement ]</li>
      <li>[ Devoir maison, DM, Pas d'horaire mais date d'échéance ]</li>
    </ul>
    <p>Une deuxième possibilité est de simplifier les séances qui ont un horaire à peu près fixe ou qui n'arrivent qu'une fois par semaine. Cela peut être le cas des TP, des devoirs... Si on imagine le cas d'un collègue qui ne voit pas l'intérêt du type &laquo;&nbsp;Distribution de document&nbsp;&raquo;, cela donne&nbsp;:</p>
    <ul>
      <li>[ Cours, cours, Début et fin ]</li>
      <li>[ Séance de travaux dirigés, TD, Début et fin ]</li>
      <li>[ Séance de travaux pratiques, TP, Pas d'horaire ni date d'échéance ]</li>
      <li>[ Devoir surveillé, DS, Pas d'horaire ni date d'échéance ]</li>
      <li>[ Interrogation de cours, interros, Début seulement ]</li>
      <li>[ Devoir maison, DM, Pas d'horaire mais date d'échéance ]</li>
    </ul>
    <p>Une troisième possibilité peut être de n'écrire qu'un seul élément du cahier de texte pour les cours, TD. Mais de garder les TP, sans horaires (ou avec), de garder les DS... C'est-à-dire&nbsp;:</p>
    <ul>
      <li>[ Cours, cours, Entrée hebdomadaire ]</li>
      <li>[ Séance de travaux pratiques, TP, Pas d'horaire ni date d'échéance ]</li>
      <li>[ Devoir surveillé, DS, Pas d'horaire ni date d'échéance ]</li>
    </ul>
    <p>Quatrième idée, on peut choisir de regrouper les séances par jour. Mais vouloir être capable de n'afficher que les DM, que les DS, ou tout le reste. Il faut donc avoir&nbsp;:</p>
    <ul>
      <li>[ Cours, cours, Entrée journalière ]</li>
      <li>[ Devoir surveillé, DS, Pas d'horaire ni date d'échéance ]</li>
      <li>[ Devoir maison, DM, Pas d'horaire mais date d'échéance ]</li>
    </ul>
    <p>Il est possible d'imaginer beaucoup plus de combinaisons. Il ne tient qu'à vous de trouver celle qui vous semble la meilleure.</p>
    <p>Seuls les types qui correspondent effectivement à des séances apparaissent dans le menu déroulant de recherche de la partie publique. Ce n'est donc pas un problème d'avoir des types qui ne servent finalement à rien, il n'apparaîtront pas sur la partie publique.</p>
  </div>

  <div id="aide-cdt-types">
    <h3>Aide et explications</h3>
    <p>Le <em>titre</em> sera affiché au début de chaque élément du cahier de texte. Il doit s'agit d'un nom singulier et commençant par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Séance de travaux pratiques&nbsp;», «&nbsp;Interrogation de cours&nbsp;»</p>
    <p>La <em>clé</em> sera affichée dans le menu déroulant de recherche, précédé de «&nbsp;les&nbsp;», ainsi que dans l'adresse des pages qui affichent ce type de séances&nbsp;: il faut donc que ce soit un pluriel, court, en un mot, sans majucule au début (sauf s'il le faut). Par exemple, «&nbsp;TP&nbsp;», «&nbsp;interros&nbsp;». La clé doit obligatoirement être unique (deux types de séances ne peuvent pas avoir la même clé).</p>
    <p>Vous pouvez choisir les valeurs affichées pour chaque type de séances grâce à l'<em>affichage d'horaires</em>&nbsp;:</p>
    <ul>
      <li><em>Début seulement</em> conduit à ce qu'il n'y ait qu'une heure de début. Utile pour les interrogations par exemple.</li>
      <li><em>Début et fin</em> conduit à ce qu'il y ait une heure de début et une heure de fin. C'est le cas le plus général.</li>
      <li><em>Pas d'horaire mais date d'échéance</em> conduit à n'afficher qu'une date (&nbsp;pour le&nbsp;) et aucun horaire. Utile typiquement pour les devoirs maison.</li>
      <li><em>Pas d'horaire ni date d'échéance</em> vous convient si vous souhaitez arrêter de saisir les heures. Par exemple, pour les devoirs surveillés du samedi matin, il suffit d'avoir la date. Le titre affiché pour une séance de ce type sur la partie publique contiendra la date et le <em>titre</em> du type (par exemple &laquo;&nbsp;Samedi XX/XX/XXXX : Devoir surveillé&nbsp;&raquo;).</li>
      <li><em>Entrée journalière</em> vous convient si vous souhaitez ne saisir qu'un élément par jour dans le cahier de texte. La différence avec la possibilité précédente et que l'on n'affichera pas le <em>titre</em> du type sur la partie publique. Cela est donc utile uniquement si vous souhaitez regrouper vos séances par jour. Le titre affiché pour un élément du cahier de texte de ce type sur la partie publique ne contiendra que la date. Le meilleur <em>titre</em> sera alors certainement &laquo;&nbsp;Cours&nbsp;&raquo; (ne s'affichera que dans le formulaire de recherche en haut de page).</li>
      <li><em>Entrée hebdomadaire</em> vous convient si vous souhaitez ne saisir qu'un élément par semaine dans le cahier de texte. Cela est donc utile uniquement si vous souhaitez regrouper vos séances par semaine. Il n'y aura pas de titre pour un élément du cahier de texte de ce type sur la partie publique, car la date de chaque semaine est déjà écrite systématiquement.</li>
    </ul>
    <p>Une fois les modifications faites, il faut les valider en cliquant sur le bouton <span class="icon-ok"></span>.</p>
    <p>D'autres modifications sont possibles à l'aide des boutons disponibles pour chaque type de séances&nbsp;:</p>
    <ul>
      <li><span class="icon-supprime"></span>&nbsp;: supprimer le type de séances (une confirmation sera demandée)</li>
      <li><span class="icon-monte"></span>&nbsp;: remonter le type de séances d'un cran</li>
      <li><span class="icon-descend"></span>&nbsp;: descendre le type de séances d'un cran</li>
    </ul>
    <p>Attention&nbsp;: supprimer un type de séances supprime aussi automatiquement tous les éléments du cahier de texte correspondant à ce type. Cela ne peut impacter que la matière <em><?php echo $matiere['nom']; ?></em>. Le nombre d'éléments du cahier de texte correspondant à un type est donné pour chaque type de séances.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer un nouveau type de séances. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Un type de séances créé ici ne concernera que la matière <em><?php echo $matiere['nom']; ?></em>.</p>
    <p>Le <em>titre</em> sera affiché au début de chaque élément du cahier de texte. Il doit s'agit d'un nom singulier et commençant par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Séance de travaux pratiques&nbsp;», «&nbsp;Interrogation de cours&nbsp;»</p>
    <p>La <em>clé</em> sera affichée dans le menu déroulant de recherche, précédé de «&nbsp;les&nbsp;», ainsi que dans l'adresse des pages qui affichent ce type de séances&nbsp;: il faut donc que ce soit un pluriel, court, en un mot, sans majucule au début (sauf s'il le faut). Par exemple, «&nbsp;TP&nbsp;», «&nbsp;interros&nbsp;». La clé doit obligatoirement être unique (deux types de séances ne peuvent pas avoir la même clé).</p>
    <p>Vous pouvez choisir les valeurs affichées pour chaque type de séances grâce à l'<em>affichage d'horaires</em>&nbsp;:</p>
    <ul>
      <li><em>Début seulement</em> conduit à ce qu'il n'y ait qu'une heure de début. Utile pour les interrogations par exemple.</li>
      <li><em>Début et fin</em> conduit à ce qu'il y ait une heure de début et une heure de fin. C'est le cas le plus général.</li>
      <li><em>Pas d'horaire mais date d'échéance</em> conduit à n'afficher qu'une date (&nbsp;pour le&nbsp;) et aucun horaire. Utile typiquement pour les devoirs maison.</li>
      <li><em>Pas d'horaire ni date d'échéance</em> vous convient si vous souhaitez arrêter de saisir les heures. Par exemple, pour les devoirs surveillés du samedi matin, il suffit d'avoir la date. Le titre affiché pour une séance de ce type sur la partie publique contiendra la date et le <em>titre</em> du type (par exemple &laquo;&nbsp;Samedi XX/XX/XXXX : Devoir surveillé&nbsp;&raquo;).</li>
      <li><em>Entrée journalière</em> vous convient si vous souhaitez ne saisir qu'un élément par jour dans le cahier de texte. La différence avec la possibilité précédente et que l'on n'affichera pas le <em>titre</em> du type sur la partie publique. Cela est donc utile uniquement si vous souhaitez regrouper vos séances par jour. Le titre affiché pour un élément du cahier de texte de ce type sur la partie publique ne contiendra que la date. Le meilleur <em>titre</em> sera alors certainement &laquo;&nbsp;Cours&nbsp;&raquo; (ne s'affichera que dans le formulaire de recherche en haut de page).</li>
      <li><em>Entrée hebdomadaire</em> vous convient si vous souhaitez ne saisir qu'un élément par semaine dans le cahier de texte. Cela est donc utile uniquement si vous souhaitez regrouper vos séances par semaine. Il n'y aura pas de titre pour un élément du cahier de texte de ce type sur la partie publique, car la date de chaque semaine est déjà écrite systématiquement.</li>
    </ul>
  </div>

  <p id="log"></p>
  
  <script type="text/javascript">
$( function() {
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
