<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

// Note : l'affichage des heures de début/fin est conditionné par le champ
// deb_fin_pour de la table cdt-types (donc du type de séance choisi) :
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
    debut($mysqli,'Raccourcis de séances du cahier de texte','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
}
elseif ( $autorisation )  {
  debut($mysqli,'Raccourcis de séances du cahier de texte','Vous n\'avez pas accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}
else  {
  $titre = 'Raccourcis de séances du cahier de texte';
  $actuel = false;
  include('login.php');
}

// Récupération des types de séances
$resultat = $mysqli->query("SELECT id, titre, deb_fin_pour FROM `cdt-types` WHERE matiere = ${matiere['id']}");
$select_seances = '';
$seances = array();
if ( $resultat->num_rows )  {
  while ( $r = $resultat->fetch_assoc() )  {
    $select_seances .= "<option value=\"${r['id']}\">${r['titre']}</option>";
    $seances[$r['id']] = $r['deb_fin_pour'];
  }
  $resultat->free();
}
// Select sur les jours de la semaine et pour les demigroupes
$select_jours = '<option value="1">Lundi</option><option value="2">Mardi</option><option value="3">Mercredi</option><option value="4">Jeudi</option><option value="5">Vendredi</option><option value="6">Samedi</option><option value="0">Dimanche</option>';
$select_dg = '<option value="0">Classe entière</option><option value="1">Demi-groupe</option>';

//////////////
//// HTML ////
//////////////
debut($mysqli,"Cahier de texte - ${matiere['nom']}",$message,5,"cdt-raccourcis?${matiere['cle']}",false,'datetimepicker');
echo <<<FIN

  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter un nouveau raccourci de séance"></a>
    <a class="icon-annule" onclick="history.back()" title="Retour au cahier de texte"></a>
    <a class="icon-aide" title="Aide pour les modifications des raccourcis de séance"></a>
  </div>

  <h2 class="edition">Modifier les raccourcis de séance</h2>

FIN;

// Récupération
$resultat = $mysqli->query("SELECT id, ordre, nom, jour, type, demigroupe, TIME_FORMAT(h_debut,'%kh%i') AS h_debut, TIME_FORMAT(h_fin,'%kh%i') AS h_fin
                            FROM `cdt-seances` WHERE matiere = ${matiere['id']}");
$mysqli->close();
if ( $max = $resultat->num_rows )  {
  while ( $r = $resultat->fetch_assoc() )  {
    $id = $r['id'];
    $monte = ( $r['ordre'] == 1 ) ? ' style="display:none;"' : '';
    $descend = ( $r['ordre'] == $max ) ? ' style="display:none;"' : '';
    $sel_jours = str_replace("\"${r['jour']}\"","\"${r['jour']}\" selected",$select_jours);
    $sel_seances = str_replace("\"${r['type']}\"","\"${r['type']}\" selected",$select_seances);
    $sel_dg = str_replace("\"${r['demigroupe']}\"","\"${r['demigroupe']}\" selected",$select_dg);
    
    echo <<<FIN

  <article data-id="cdt-raccourcis|$id">
    <a class="icon-aide" data-id="raccourci" title="Aide pour l'édition de ce raccourci de séance"></a>
    <a class="icon-ok" title="Valider les modifications"></a>
    <a class="icon-monte"$monte title="Déplacer ce type de séance vers le haut"></a>
    <a class="icon-descend"$descend title="Déplacer ce type de séance vers le bas"></a>
    <a class="icon-supprime" title="Supprimer ce type de séance"></a>
    <form class="cdt-raccourcis">
      <h3 class="edition">${r['nom']}</h3>
      <p class="ligne"><label for="nom0">Nom&nbsp;: </label><input type="text" id="nom0" name="nom" value="${r['nom']}" size="50"></p>
      <p class="ligne"><label for="type0">Séance&nbsp;:</label>
        <select id="type0" name="type">$sel_seances</select>
      </p>
      <p class="ligne"><label for="jour0">Jour&nbsp;:</label>
        <select id="jour0" name="jour">$sel_jours</select>
      </p>
      <p class="ligne"><label for="h_debut$id">Heure de début&nbsp;: </label><input type="text" id="h_debut$id" name="h_debut" value="${r['h_debut']}" size="5"></p>
      <p class="ligne"><label for="h_fin$id">Heure de fin&nbsp;: </label><input type="text" id="h_fin$id" name="h_fin" value="${r['h_fin']}" size="5"></p>
      <p class="ligne"><label for="demigroupe$id">Séance en demi-groupe&nbsp;: </label>
        <select id="demigroupe$id" name="demigroupe">$sel_dg</select>
      </p>
    </form>
  </article>

FIN;
  }
  $resultat->free();
}
else
  echo "\n  <article>\n    <h2>Aucun raccourci de séances n'existe encore pour cette matière.</h2>\n    <p>Cliquez sur le bouton <span class=\"icon-ajoute\"></span> en haut de cette page pour en ajouter.</p>\n  </article>\n";

// Aide et formulaire d'ajout
?>

  <form id="form-ajoute" data-action="ajout-cdt-raccourci">
    <h3 class="edition">Ajouter un nouveau raccourci de séances</h3>
    <div>
      <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text" name="nom" value="" size="50"></p>
      <p class="ligne"><label for="type">Séance&nbsp;:</label>
        <select name="type"><?php echo $select_seances; ?></select>
      </p>
      <p class="ligne"><label for="jour">Jour&nbsp;:</label>
        <select name="jour"><?php echo $select_jours; ?></select>
      </p>
      <p class="ligne"><label for="h_debut">Heure de début&nbsp;: </label><input type="text" name="h_debut" value="" size="5"></p>
      <p class="ligne"><label for="h_fin">Heure de fin&nbsp;: </label><input type="text" name="h_fin" value="" size="5"></p>
      <p class="ligne"><label for="demigroupe">Séance en demi-groupe&nbsp;: </label>
        <select name="demigroupe"><?php echo $select_dg; ?></select>
      </p>
    </div>
    <input type="hidden" name="matiere" value="<?php echo $matiere['id']; ?>">
  </form>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter ou de modifier des <em>raccourcis</em> pour le cahier de texte. Ces raccourcis de séance, propres à chaque matière, formeront un menu déroulant disponible lors de l'édition des horaires d'un élément du cahier de texte. Sélectionner un raccourci de séance dans ce menu déroulant pré-remplit les champs séances, jour, heures et demi-groupe. Cela permet donc d'aller plus vite lors du remplissage du cahier de texte. Les raccourcis n'apparaissent qu'en mode édition (pour vous), non devant les élèves ou autres visiteurs.</p>
    <p>On peut par exemple disposer d'un raccourci &laquo;&nbsp;Cours du lundi&nbsp;&raquo; qui permettra de régler automatiquement le type de séance à Cours, le jour de la semaine au lundi de la semaine en cours, les heures de début et fin à 8h et 10h.</p>
    <p>Le <em>nom</em> est ce qui sera affiché dans le menu d'accès aux raccourcis, visible lors de l'édition d'un élément du cahier de texte. On peut mettre ce que l'on veut.</p>
    <p>La <em>séance</em> est le type de séance qui sera automatiquement sélectionné. Les types de séances sont modifiables, indépendamment pour chaque matière, sur la page de <a href="cdt-seances?<?php echo $matiere['cle']; ?>">modification des types de séances</a>.</p>
    <p>Pour ajouter un raccourci, il faut cliquer sur le bouton <span class="icon-ajoute"></span>.</p>
    <p>Les raccourcis existants sont directement modifiables. Les modifications sont prises en compte après validation avec le bouton <span class="icon-ok"></span>.</p>
  </div>

  <div id="aide-cdt-raccourcis">
    <h3>Aide et explications</h3>
    <p>Le <em>nom</em> est ce qui sera affiché dans le menu d'accès aux raccourcis, visible lors de l'édition d'un élément du cahier de texte. On peut mettre ce que l'on veut.</p>
    <p>La <em>séance</em> est le type de séance qui sera automatiquement sélectionné. Les types de séances sont modifiables, indépendamment pour chaque matière, à la <a href="cdt-seances?<?php echo $matiere['cle']; ?>">gestion de types de séances</a>.</p>
    <p>Une fois les modifications faites, il faut les valider en cliquant sur le bouton <span class="icon-ok"></span>.</p>
    <p>D'autres modifications sont possibles à l'aide des boutons disponibles pour chaque raccourci de séance&nbsp;:</p>
    <ul>
      <li><span class="icon-supprime"></span>&nbsp;: supprimer le raccourci de séance (une confirmation sera demandée)</li>
      <li><span class="icon-monte"></span>&nbsp;: remonter le raccourci de séance d'un cran</li>
      <li><span class="icon-descend"></span>&nbsp;: descendre le raccourci de séance d'un cran</li>
    </ul>
    <p>Supprimer un raccourci de séance n'a strictement aucun impact sur les éléments du cahier de texte.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer un nouveau raccourci de séance. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Chaque raccourci de séance ne concerne qu'une matière à la fois.</p>
    <p>Le <em>nom</em> est ce qui sera affiché dans le menu d'accès aux raccourcis, visible lors de l'édition d'un élément du cahier de texte. On peut mettre ce que l'on veut.</p>
    <p>La <em>séance</em> est le type de séance qui sera automatiquement sélectionné. Les types de séances sont modifiables, indépendamment pour chaque matière, sur la page de <a href="cdt-seances?<?php echo $matiere['cle']; ?>">modification des types de séances</a>.</p>
  </div>

  <p id="log"></p>
  
  <script type="text/javascript">
    seances = <?php echo json_encode($seances); ?>;
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
  <script type="text/javascript" src="js/datetimepicker.min.js"></script>
  
<?php
fin(true);
?>
