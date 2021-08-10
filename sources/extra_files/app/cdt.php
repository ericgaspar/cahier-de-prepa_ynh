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
// cdt=0 : cahier de texte vide, à afficher uniquement pour les profs concernés
// cdt=1 : cahier de texte utilisé, à afficher pour les utilisateurs associés
//  à la matière 
// cdt_protection permet de restreindre l'accès
// cdt_protection=32 : cahier de texte désactivé, pas d'affichage
$mysqli = connectsql();
$resultat = $mysqli->query('SELECT id, cle, nom, cdt_protection AS protection, cdt
                            FROM matieres WHERE ( cdt = 1'.( ( $autorisation == 5 ) ? " OR  FIND_IN_SET(id,'${_SESSION['matieres']}')" : '' ).' ) AND cdt_protection < 32');
if ( $resultat->num_rows )  {
  if ( !empty($_REQUEST) )  {
    while ( $r = $resultat->fetch_assoc() )
      if ( isset($_REQUEST[$r['cle']]) )  {
        $matiere = $r;
        $mid = $matiere['id'];
        $cle = $matiere['cle'];
        break;
      }
  }
  $resultat->free();
  // Si aucune matière trouvée
  if ( !isset($matiere) )  {
    debut($mysqli,'Cahier de texte','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
}
// Si aucune matière présentant son cahier de texte n'est enregistrée
else  {
  debut($mysqli,'Cahier de texte','Cette page ne contient aucune information.',$autorisation,' ');
  $mysqli->close();
  fin();
}

/////////////////////////////
// Vérification de l'accès //
/////////////////////////////
$edition = acces($matiere['protection'],$mid,"Cahier de texte - ${matiere['nom']}","cdt?$cle",$mysqli);

//////////////////////////////////////////////////////
// Validation de la requête : semaine(s) à afficher //
//////////////////////////////////////////////////////

// Récupération des semaines et du nombre de semaines
$resultat = $mysqli->query("SELECT semaines.id, DATE_FORMAT(debut,'%w%Y%m%e') AS debut, DATE_FORMAT(debut,'%y%v') AS semaine, nom AS vacances 
                            FROM semaines LEFT JOIN vacances ON semaines.vacances = vacances.id ORDER BY semaines.id");
$select_semaines = "\n      <option value=\"0\">Toute l'année</option>";
$semaines = $semaines_id = array(0=>'');
while ( $r = $resultat->fetch_assoc() )  {
  $semaines[] = $r;
  $semaines_id[] = $r['semaine'];
  $select_semaines .= "\n      <option value=\"${r['id']}\">".( $r['vacances'] ?: format_date($r['debut']) ).'</option>';
}
$resultat->free();
$nmax = count($semaines);

// Récupération des types de séances
// $select_seances est utilisé dans le bandeau de recherche
// $seances est utilisé pour la validation de recherche de séance
$resultat = $mysqli->query("SELECT cle FROM `cdt-types` WHERE matiere = $mid AND nb");
$select_seances = "\n      <option value=\"tout\">Toutes les séances</option>";
$seances = array();
if ( $resultat->num_rows )  {
  while ( $r = $resultat->fetch_assoc() )  {
    $select_seances .= "\n        <option value=\"${r['cle']}\">Les ${r['cle']}</option>";
    $seances[] = $r['cle'];
  }
  $resultat->free();
}

// Recherche sur du texte
$recherche = '';
if ( isset($_REQUEST['recherche']) && strlen($_REQUEST['recherche']) )  {
  $recherche = htmlspecialchars($_REQUEST['recherche']);
  $requete = 'AND cdt.texte LIKE \'%'.$mysqli->real_escape_string($_REQUEST['recherche']).'%\'';
  $n = 0;
  $nb = $nmax;
}
// Vue de tout le programme de l'année
elseif (  isset($_REQUEST['tout']) )  {
  $requete = '';
  $n = 0;
  $nb = $nmax;
}
// Vue d'une (ou plusieurs) semaine précise
elseif ( isset($_REQUEST['n']) && ctype_digit($n = $_REQUEST['n']) && ( $n >= 0 ) && ( $n <= $nmax ) )  {
  $requete = "AND cdt.semaine >= $n";
  // Nombre d'éléments vus par défaut : 2 en mode édition, 1 sinon
  if ( !isset($_REQUEST['nb']) || !ctype_digit($nb = $_REQUEST['nb']) || ( $nb < 1 ) )
    $nb = 1+$edition;
  // Si $n est nul, "toute l'année" sélectionnée
  if ( !$n )
    $nb = $nmax;
  // Type de séances demandées
  if ( isset($_REQUEST['seances']) && in_array($seance = $_REQUEST['seances'],$seances,true) )  {
    $select_seances = str_replace("\"$seance\"","\"$seance\" selected",$select_seances);
    $requete .= " AND t.cle = '$seance'";
  }
}
// Vue de la semaine en cours à partir du lundi
// Vue de la semaine précédente et de la semaine en cours jusqu'au vendredi
// $n est false si non trouvé (hors année scolaire)
elseif ( ( $n = array_search(date('yW', strtotime('Monday this week',time()-86400)),$semaines_id) ) !== false )  {
  $requete = "AND cdt.semaine >= $n";
  $nb = ( date('N') > 5 ) ? 1 : 2;
}

////////////
/// HTML ///
////////////
$icone = ( $edition && $matiere['protection'] ) ? '<span class="icon-lock"></span>' : '';
debut($mysqli,"Cahier de texte - ${matiere['nom']}$icone",$message,$autorisation,"cdt?$cle",$mid,'datetimepicker');

// MathJax désactivé par défaut
$mathjax = false;

// Formulaire de la demande des semaines à afficher
$select_semaines = str_replace("\"$n\"","\"$n\" selected",$select_semaines);
$boutons = "
  <p id=\"recherchecdt\" class=\"topbarre\">
    <a class=\"icon-precedent\" href=\"?$cle&amp;n=".max(1,$n-1)."\" title=\"Semaine précédente\"></a>
    <a class=\"icon-suivant\" href=\"?$cle&amp;n=".min($n+1,$nmax)."\" title=\"Semaine suivante\"></a>
    <a class=\"icon-voirtout\" href=\"?$cle&amp;tout\" title=\"Voir l'ensemble du cahier de texte\"></a>
    <select id=\"seances\" onchange=\"window.location='?$cle&amp;n='+$(this).next().val()+'&amp;seances='+this.value;\">$select_seances
    </select>
    <select id=\"semaines\" onchange=\"window.location='?$cle&amp;n='+this.value+'&amp;seances='+$(this).prev().val();\">$select_semaines
    </select>
    <span class=\"icon-recherche\" onclick=\"if ( !$(this).prev().is(':visible')) $(this).prev().show(); else window.location='?$cle&amp;recherche='+$(this).prev().val();\"></span>
    <input type=\"text\" value=\"$recherche\" onchange=\"window.location='?$cle&amp;recherche='+this.value;\" title=\"Recherche dans les textes des programmes de colles\">
  </p>
";

// Affichage public sans édition
if ( !$edition )  {
  echo $boutons;
  if ( $n !== false )  {
    // Affichage des éléments du cahier de texte recherchés
    $resultat = $mysqli->query("SELECT cdt.semaine, DATE_FORMAT(cdt.jour,'%w') AS jour, DATE_FORMAT(cdt.jour,'%d/%m/%Y') AS date,
                                TIME_FORMAT(cdt.h_debut,'%kh%i') AS h_debut, TIME_FORMAT(cdt.h_fin,'%kh%i') AS h_fin, DATE_FORMAT(cdt.pour,'%d/%m/%Y') AS pour,
                                cdt.texte, IF(cdt.demigroupe,' (en demi-groupe)','') AS demigroupe, t.titre, t.deb_fin_pour
                                FROM cdt LEFT JOIN `cdt-types` AS t ON t.id = cdt.type
                                WHERE cdt.matiere = $mid AND cdt.cache = 0 $requete");
    if ( $resultat->num_rows )  {
      $compteur = 0;
      $semaine = ( $n > 0 ) ? $n-1 : 0;
      $jours = array('','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi');
      while ( $r = $resultat->fetch_assoc() )  {
        // Nouvelles semaines éventuelles
        while ( $semaine < $r['semaine'] )  {
          // On sort avant de commencer la nouvelle semaine si $compteur était 
          // égal à $nb : on vient de finir la $nb semaine hors vacances.
          if ( $compteur >= $nb )
            break 2;
          $semaine = $semaine+1;
          if ( $semaine == $r['semaine'] )
            $compteur = $compteur+1;
          if ( !$recherche || ( $semaine == $r['semaine'] ) )
            echo "\n  <h3>".( ( $v = $semaines[$semaine]['vacances'] ) ? ucfirst(format_date($semaines[$semaine]['debut']))."&nbsp;: $v" : 'Semaine du '.format_date($semaines[$semaine]['debut']) ).'</h3>';
        }
        // Élément du cahier de texte
        $mathjax = $mathjax ?: boolval(strpos($r['texte'],'$')+strpos($r['texte'],'\\'));
        switch ( $r['deb_fin_pour'] )  {
          case 0: $titre = "${jours[$r['jour']]} ${r['date']} à ${r['h_debut']}&nbsp;: ${r['titre']}${r['demigroupe']}"; break;
          case 1: $titre = "${jours[$r['jour']]} ${r['date']} de ${r['h_debut']} à ${r['h_fin']}&nbsp;: ${r['titre']}${r['demigroupe']}"; break;
          case 2: $titre = "${jours[$r['jour']]} ${r['date']}&nbsp;: ${r['titre']} pour le ${r['pour']}${r['demigroupe']}"; break;
          case 3: $titre = "${jours[$r['jour']]} ${r['date']}&nbsp;: ${r['titre']}${r['demigroupe']}"; break;
          case 4: $titre = "${jours[$r['jour']]} ${r['date']}"; break;
        }
        $titre = ( $r['deb_fin_pour'] < 5 ) ? "\n      <p class=\"titrecdt\">$titre</p>" : '';
        echo <<<FIN

  <article>$titre
${r['texte']}
  </article>

FIN;
      }
      $resultat->free();
    }
    else
      echo "\n  <h2>Aucun résultat n'a été trouvé pour cette recherche.</h2>\n";
  }
  else
    echo "\n  <h2>L'année est terminée... Bonnes vacances&nbsp;!</h2>\n  <p><a href=\"?$cle&amp;tout\">Revoir tout le cahier de texte</a></p>\n";
}

// Affichage professeur éditeur
else  {
  echo <<<FIN
  
  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter un nouvel élément du cahier de texte"></a>
    <a class="icon-prefs formulaire" title="Modifier les réglages du cahier de texte"></a>
    <a class="icon-aide" title="Aide pour les modifications des programmes de colles"></a>
  </div>
$boutons
FIN;
  if ( $n !== false )  {
    
    // Affichage des éléments du cahier de texte recherchés
    $resultat = $mysqli->query("SELECT cdt.id, cdt.semaine, DATE_FORMAT(cdt.jour,'%w') AS jour, DATE_FORMAT(cdt.jour,'%d/%m/%Y') AS date,
                                TIME_FORMAT(cdt.h_debut,'%kh%i') AS h_debut, TIME_FORMAT(cdt.h_fin,'%kh%i') AS h_fin, DATE_FORMAT(cdt.pour,'%d/%m/%Y') AS pour,
                                cdt.texte, IF(cdt.demigroupe,' (en demi-groupe)','') AS demigroupe,
                                cdt.cache, t.id AS tid, t.titre, t.deb_fin_pour
                                FROM cdt LEFT JOIN `cdt-types` AS t ON t.id = cdt.type
                                WHERE cdt.matiere = $mid $requete");
    if ( $resultat->num_rows )  {
      $compteur = 0;
      $semaine = ( $n > 0 ) ? $n-1 : 0;
      $jours = array('','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi');
      while ( $r = $resultat->fetch_assoc() )  {
        // Nouvelles semaines éventuelles
        while ( $semaine < $r['semaine'] )  {
          // On sort avant de commencer la nouvelle semaine si $compteur était 
          // égal à $nb : on vient de finir la $nb semaine hors vacances.
          if ( $compteur >= $nb )
            break 2;
          $semaine = $semaine+1;
          if ( $semaine == $r['semaine'] )
            $compteur = $compteur+1;
          if ( !$recherche || ( $semaine == $r['semaine'] ) )
            echo "\n  <h3>".( ( $v = $semaines[$semaine]['vacances'] ) ? ucfirst(format_date($semaines[$semaine]['debut']))."&nbsp;: $v" : 'Semaine du '.format_date($semaines[$semaine]['debut']) ).'</h3>';
        }
        // Élément du cahier de texte
        $mathjax = $mathjax ?: boolval(strpos($r['texte'],'$')+strpos($r['texte'],'\\'));
        switch ( $r['deb_fin_pour'] )  {
          case 0: $titre = "${jours[$r['jour']]} ${r['date']} à ${r['h_debut']}&nbsp;: ${r['titre']}${r['demigroupe']}"; break;
          case 1: $titre = "${jours[$r['jour']]} ${r['date']} de ${r['h_debut']} à ${r['h_fin']}&nbsp;: ${r['titre']}${r['demigroupe']}"; break;
          case 2: $titre = "${jours[$r['jour']]} ${r['date']}&nbsp;: ${r['titre']} pour le ${r['pour']}${r['demigroupe']}"; break;
          case 3: $titre = "${jours[$r['jour']]} ${r['date']}&nbsp;: ${r['titre']}${r['demigroupe']}"; break;
          case 4: $titre = "${jours[$r['jour']]} ${r['date']}"; break;
          case 5: $titre = '[Entrée hebdomadaire]';
        }
        $classe_cache = ( $r['cache'] ) ? ' class="cache"' : '';
        $bouton_cache = ( $r['cache'] ) ? '<a class="icon-montre" title="Afficher le programme de colles sur la partie publique"></a>' : '<a class="icon-cache" title="Rendre invisible le programme de colles sur la partie publique"></a>';
        $demigroupe = ( strlen($r['demigroupe']) ) ? 1 : 0;
        echo <<<FIN

  <article$classe_cache data-id="cdt-elems|${r['id']}">
    <a class="icon-aide" title="Aide pour les modifications du cahier de texte"></a>
    $bouton_cache
    <a class="icon-supprime" title="Supprimer cet élément du cahier de texte"></a>
    <p class="titrecdt edition" data-donnees='{"tid":${r['tid']},"jour":"${r['date']}","h_debut":"${r['h_debut']}","h_fin":"${r['h_fin']}","pour":"${r['pour']}","demigroupe":$demigroupe}'>$titre</p>
    <div class="editable edithtml" data-id="cdt-elems|texte|${r['id']}" placeholder="Texte de l'élément du cahier de texte">
${r['texte']}
    </div>
  </article>

FIN;
      }
      $resultat->free();
    }
    else
      echo "\n  <article><h2>Aucun résultat n'a été trouvé pour cette recherche.</h2></article>\n\n";
  }
  else
    echo "\n  <article><h2>L'année est terminée... Bonnes vacances&nbsp;!</h2>\n  <p><a href=\"?$cle&amp;tout\">Revoir tout le cahier de texte</a></p></article>\n";

  // Récupération des boutons
  $resultat = $mysqli->query("SELECT id, nom, jour, TIME_FORMAT(h_debut,'%kh%i') AS h_debut, TIME_FORMAT(h_fin,'%kh%i') AS h_fin,
                              type, demigroupe FROM `cdt-seances` WHERE matiere = $mid");
  $select_raccourcis = '';
  $raccourcis = array();
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )  {
      $select_raccourcis .= "<option value=\"${r['id']}\">${r['nom']}</option>";
      $raccourcis[$r['id']] = array('tid'=>$r['type'],'jour'=>$r['jour'],'h_debut'=>$r['h_debut'],'h_fin'=>$r['h_fin'],'demigroupe'=>$r['demigroupe']);
    }
    $resultat->free();
  }
  $select_raccourcis = ( strlen($select_raccourcis) ) ? '<option value="0"></option>'.$select_raccourcis : '<option value="0">Aucun raccourci défini</option>';
    
  // Nouvelle récupération des types de séances, nécessaire car les types
  // "vides" ne sont pas récupérés précédemment
  $resultat = $mysqli->query("SELECT id, titre, deb_fin_pour FROM `cdt-types` WHERE matiere = $mid");
  $mysqli->close();
  $select_seances = '';
  $seances = array();
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )  {
      $select_seances .= "<option value=\"${r['id']}\">${r['titre']}</option>";
      $seances[$r['id']] = $r['deb_fin_pour'];
    }
    $resultat->free();
  }

  // Options du select multiple d'accès
  $select_protection = '
          <option value="0">Accès public</option>
          <option value="6">Utilisateurs identifiés</option>
          <option value="1">Invités</option>
          <option value="2">Élèves</option>
          <option value="3">Colleurs</option>
          <option value="4">Lycée</option>
          <option value="5">Professeurs</option>
          <option value="32">Fonction désactivée</option>';
  $p = $matiere['protection'];
  if ( ( $p == 0 ) || ( $p == 32 ) )
    $sel_protection = str_replace("\"$p\"","\"$p\" selected",$select_protection);
  else  {
    $sel_protection = str_replace('"6"','"6" selected',$select_protection);
    for ( $a=1; $a<6; $a++ )
      if ( ( ($p-1)>>($a-1) & 1 ) == 0 )
        $sel_protection = str_replace("\"$a\"","\"$a\" selected",$sel_protection);
  }
  // Aide et formulaire d'ajout
?>

  <form id="form-cdt">
    <p class="ligne">
      <label for="racourci">Raccourci&nbsp;</label><a class="icon-edite" href="cdt-raccourcis?<?php echo $cle; ?>" title="Éditer les raccourcis de séances"></a>&nbsp;:
      <select name="raccourci"><?php echo $select_raccourcis; ?></select>
    </p>
    <p class="ligne">
      <label for="tid">Séance&nbsp;:</label><a class="icon-edite" href="cdt-seances?<?php echo $cle; ?>"></a>&nbsp;:
      <select name="tid"><?php echo $select_seances; ?></select>
    </p>
    <p class="ligne"><label for="jour">Jour&nbsp;: </label><input type="text" name="jour" value="" size="8"></p>
    <p class="ligne"><label for="h_debut">Heure de début&nbsp;: </label><input type="text" name="h_debut" value="" size="5"></p>
    <p class="ligne"><label for="h_fin">Heure de fin&nbsp;: </label><input type="text" name="h_fin" value="" size="5"></p>
    <p class="ligne"><label for="pour">Pour le&nbsp;: </label><input type="text" name="pour" value="" size="8"></p>
    <p class="ligne"><label for="demigroupe">Séance en demi-groupe&nbsp;: </label>
      <select name="demigroupe"><option value="0">Classe entière</option><option value="1">Demi-groupe</option></select>
    </p>
  </form>
  
  <form id="form-ajoute" data-action="cdt-elems">
    <h3 class="edition">Nouvel élément du cahier de texte</h3>
    <p class="ligne">
      <label for="racourci">Raccourci&nbsp;</label><a class="icon-edite" href="cdt-raccourcis?<?php echo $cle; ?>" title="Éditer les raccourcis de séances"></a>&nbsp;:
      <select name="raccourci"><?php echo $select_raccourcis; ?></select>
    </p>
    <p class="ligne">
      <label for="tid">Séance&nbsp;</label><a class="icon-edite" href="cdt-seances?<?php echo $cle; ?>"></a>&nbsp;:
      <select name="tid"><?php echo $select_seances; ?></select>
    </p>
    <p class="ligne"><label for="jour">Jour&nbsp;: </label><input type="text" name="jour" value="" size="8"></p>
    <p class="ligne"><label for="h_debut">Heure de début&nbsp;: </label><input type="text" name="h_debut" value="" size="5"></p>
    <p class="ligne"><label for="h_fin">Heure de fin&nbsp;: </label><input type="text" name="h_fin" value="" size="5"></p>
    <p class="ligne"><label for="pour">Pour le&nbsp;: </label><input type="text" name="pour" value="" size="8"></p>
    <p class="ligne"><label for="demigroupe">Séance en demi-groupe&nbsp;: </label>
      <select name="demigroupe"><option value="0">Classe entière</option><option value="1">Demi-groupe</option></select>
    </p>
    <textarea name="texte" class="edithtml" rows="10" cols="100" placeholder="Texte de l'élément du cahier de texte (obligatoire)"></textarea>
    <p class="ligne"><label for="cache">Ne pas diffuser sur la partie publique&nbsp;: </label><input type="checkbox" name="cache" value="1"></p>
    <input type="hidden" name="matiere" value="<?php echo $mid; ?>">
  </form>

  <form id="form-prefs" data-action="prefsmatiere">
    <h3 class="edition">Réglages du cahier de texte en <?php echo $matiere['nom']; ?></h3>
    <p class="ligne"><label for="cdt_protection">Accès&nbsp;: </label>
      <select name="cdt_protection[]" multiple><?php echo $sel_protection; ?>
      </select>
    </p>
    <p>Si vous désactivez ici cette fonction, vous ne pourrez plus utiliser cette page. Les éléments du cahier de texte déjà saisis ne seront pas supprimés, le choix est réversible. Vous pourrez réactiver cette fonction à la <a href="matieres">gestion des matières</a>.</p>
    <p>Pour modifier les utilisateurs concernés par cette matière, il faut vous rendre à la <a href="utilisateurs-matieres">gestion des associations utilisateurs-matières</a>.</p>
    <input type="hidden" name="id" value="<?php echo $mid; ?>">
  </form>
  
  <script type="text/javascript">
    seances = <?php echo json_encode($seances); ?>;
    raccourcis = <?php echo json_encode($raccourcis); ?>;
    jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
  </script>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter et de modifier des éléments du cahier de texte. On peut également consulter le cahier de texte, sélectionner une semaine, un type de séance, ou effectuer une recherche de texte.</p>
    <p>Chaque élément du cahier de texte contient une date, éventuellement des horaires, un type de séance, et un texte.</p>
    <p>Les horaires et le texte de chaque élément du cahier de texte, apparaissant dans une zone indiquée par des pointillés, sont modifiables individuellement en cliquant sur les boutons <span class="icon-edite"></span> et en validant avec le bouton <span class="icon-ok"></span> qui apparaît alors.</p>
    <p>Les deux boutons généraux permettent de&nbsp;:</p>
    <ul>
      <li><span class="icon-ajoute"></span>&nbsp;: ouvrir un formulaire pour ajouter un nouvel élément du cahier de texte.</li>
      <li><span class="icon-prefs"></span>&nbsp;: ouvrir un formulaire pour modifier l'accès au cahier de texte.</li>
    </ul>
    <h4>Gestion de l'accès</h4>
    <p>L'accès au cahier de texte peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: cahier accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: cahier accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte et des matières auxquelles ils sont associés. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Fonction désactivée</em>&nbsp;: le cahier de texte pour cette matière est complètement indisponible. La fonction n'apparaît plus dans le menu, y compris pour vous.</li>
    </ul>
    <p>Si vous désactivez ici cette fonction, vous ne pourrez plus utiliser cette page. Les éléments du cahier de texte ne seront pas supprimés, le choix est réversible. Vous pourrez réactiver cette fonction à la <a href="matieres">gestion des matières</a>.</p>
    <p>Le lien de la page dans le menu est visible avant identification. Il disparaît après identification pour les utilisateurs n'ayant pas accès à la page.</p>
    <h4>Suppression de tous les éléments du cahier de texte</h4>
    <p>L'ensemble du cahier de texte est supprimable en un clic à la <a href="matieres">gestion des matières</a>.</p>
    <h4>Impression/récupération</h4>
    <p>Le cahier de texte est très facilement imprimable, avec le bouton <span class="icon-imprime"></span> du menu ou avec la commande d'impression de votre navigateur. Dans les deux cas, tout ce que ne correspond pas au cahier de texte (menu de gauche, marquages en pointillés, icônes de modification) disparaît à l'impression. Le document produit, que vous pourrez conserver en papier ou en pdf, peut constituer un document utilisable pour vous ou pour votre inspection.</p>
    <p>Le cahier de texte n'est pas récupérable facilement en fichier éditable. La commande «&nbsp;enregistrer sous&nbsp;» de votre navigateur ne donnera pas de très bon résultats.</p>
    <h4>Autres réglages</h4>
    <p>Vous pouvez modifier les semaines de période scolaire ou de vacances à la <a href="planning">gestion du planning annuel</a>.</p>
  </div>

  <div id="aide-cdt-elems">
    <h3>Aide et explications</h3>
    <p>Les horaires et le texte de chaque élément du cahier de texte existant sont modifiables séparément, en cliquant sur le bouton <span class="icon-edite"></span>. Chaque contenu éditable est indiqué par une bordure en pointillés.</p>
    <p>Une fois le texte édité, il est validé en appuyant sur le bouton <span class="icon-ok"></span>. Au contraire, un clic sur le bouton <span class="icon-annule"></span> annule l'édition.</p>
    <p>Si la connexion est inactive depuis longtemps, une identification sera à nouveau nécessaire.</p>
    <p>D'autres modifications sont possibles à l'aide des boutons disponibles pour chaque élément du cahier de texte&nbsp;:</p>
    <ul>
      <li><span class="icon-supprime"></span>&nbsp;: supprimer l'élément du cahier de texte (une confirmation sera demandée)</li>
      <li><span class="icon-cache"></span>&nbsp;: rendre l'élément du cahier de texte non visible sur la partie publique. Cela peut être utile pour entrer son cahier de texte à l'avance ou si l'on se rend compte d'une grosse erreur que l'on souhaite corriger à l'abri des regards par exemple.</li>
      <li><span class="icon-montre"></span>&nbsp;: afficher l'élément du cahier de texte sur la partie publique</li>
    </ul>
    <h4>Types de séances</h4>
    <p>Les éléments du cahier de texte peuvent être catégorisées par type de séance. Ces types de séances sont modifiables sur la <a href="cdt-seances?<?php echo $cle; ?>">page correspondante</a>, accessible par le bouton <span class="icon-edite"></span> situé devant le menu de sélection du type de séance. Les types de séances sont indépendants d'une matière à l'autre. Établir ces catégories permet de&nbsp;</p>
    <ul>
      <li>obtenir des titres correspondant correctement à la séance effectuée</li>
      <li>faciliter les recherches dans le cahier de texte</li>
      <li>modifier l'affichage des horaires (horaires de début et de fin, de début seulement, ou pas d'horaire)</li>
    </ul>
    <p>Il est possible de spécifier des types de séances du genre "Entrée quotidienne" ou "Entrée hebdomadaire" si vous préférez saisir votre cahier de texte sans préciser exactement les horaires ou les jours.</p>
    <p>Lorsque vous modifiez le jour/horaire d'un élément du cahier de texte, modifier la séance peut modifier immédiatement l'affichage ou non des champs suivants, selon les réglages effectués à la <a href="cdt-seances?<?php echo $cle; ?>">gestion des types de séances</a>.</p>
    <h4>Raccourcis</h4>
    <p>Il est possible de définir des <em>raccourcis</em> (propres à chaque matière) qui pré-rempliront les champs séances, jour, heures et demi-groupe. On peut par exemple disposer d'un raccourci «&nbsp;Cours du lundi&nbsp;» qui permettra de régler automatiquement le type de séance à Cours, le jour de la semaine au lundi de la semaine en cours, les heures de début et fin à 8h et 10h. Ces raccourcis sont modifiables sur la <a href="cdt-raccourcis?<?php echo $cle; ?>">page correspondante</a>, accessible par le bouton <span class="icon-edite"></span> situé devant le menu de sélection du raccourcis dans le formulaire de modification du type de séance/des horaires.</p>
    <h4>Modification du texte</h4>
    <p>Le texte doit être formaté en HTML&nbsp;: par exemple, chaque bloc de texte doit être encadré par &lt;p&gt; et &lt;/p&gt;. Il peut donc contenir des liens vers les autres pages du site, vers des documents du site, vers le web... Des boutons sont fournis pour aider au formattage (ainsi qu'une aide).</p>
  </div>

  <div id="aide-prefs">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier l'accès au cahier de texte en <?php echo $matiere['nom']; ?>. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: cahier accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: cahier accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte et des matières auxquelles ils sont associés. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Fonction désactivée</em>&nbsp;: le cahier de texte pour cette matière est complètement indisponible. La fonction n'apparaît plus dans le menu, y compris pour vous.</li>
    </ul>
    <p>Si vous désactivez ici cette fonction, vous ne pourrez plus utiliser cette page. Les éléments du cahier de texte ne seront pas supprimés, le choix est réversible. Vous pourrez réactiver cette fonction à la <a href="matieres">gestion des matières</a>.</p>
    <h4>Suppression de tous les éléments du cahier de texte</h4>
    <p>L'ensemble du cahier de texte est supprimable en un clic à la <a href="matieres">gestion des matières</a>.</p>
    <h4>Autres réglages</h4>
    <p>Vous pouvez modifier les semaines de période scolaire ou de vacances à la <a href="planning">gestion du planning annuel</a>.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer un nouvel élément du cahier de texte. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-annule"></span>.</p>
    <p>La <em>séance</em> correspond au type de séance. Modifier la séance peut modifier immédiatement l'affichage ou non des champs suivants, selon les réglages effectués à la <a href="cdt-seances?<?php echo $cle; ?>">gestion des types de séances</a>.</p>
    <p>Le texte doit être formaté en HTML&nbsp;: par exemple, chaque bloc de texte doit être encadré par &lt;p&gt; et &lt;/p&gt;. Il peut donc contenir des liens vers les autres pages du site, vers des documents du site, vers le web... Des boutons sont fournis pour aider au formattage (ainsi qu'une aide).</p>
    <p>La case à cocher <em>Ne pas diffuser sur la partie publique</em> permet de cacher temporairement cet élément du cahier de texte, par exemple pour le diffuser ultérieurement. Si la case est cochée, l'élément du cahier de texte ne sera pas diffusé et pourra être affiché à l'aide du bouton <span class="icon-montre"></span>. Si la case est décochée, l'élément du cahier de texte sera immédiatement visible et pourra être rendu invisible à l'aide du bouton <span class="icon-cache"></span>.</p>
    <h4>Types de séances</h4>
    <p>Les éléments du cahier de texte peuvent être catégorisées par type de séance. Ces types de séances sont modifiables sur la <a href="cdt-seances?<?php echo $cle; ?>">page correspondante</a>, accessible par le bouton <span class="icon-edite"></span> situé devant le menu de sélection du type de séance. Les types de séances sont indépendants d'une matière à l'autre. Établir ces catégories permet de&nbsp;</p>
    <ul>
      <li>obtenir des titres correspondant correctement à la séance effectuée</li>
      <li>faciliter les recherches dans le cahier de texte</li>
      <li>modifier l'affichage des horaires (horaires de début et de fin, de début seulement, ou pas d'horaire)</li>
    </ul>
    <p>Il est possible de spécifier des types de séances du genre "Entrée quotidienne" ou "Entrée hebdomadaire" si vous préférez saisir votre cahier de texte sans préciser exactement les horaires ou les jours.</p>
    <h4>Raccourcis</h4>
    <p>Il est possible de définir des <em>raccourcis</em> (propres à chaque matière) qui pré-rempliront les champs séances, jour, heures et demi-groupe. On peut par exemple disposer d'un raccourci «&nbsp;Cours du lundi&nbsp;» qui permettra de régler automatiquement le type de séance à Cours, le jour de la semaine au lundi de la semaine en cours, les heures de début et fin à 8h et 10h. Ces raccourcis sont modifiables sur la <a href="cdt-raccourcis?<?php echo $cle; ?>">page correspondante</a>, accessible par le bouton <span class="icon-edite"></span> situé devant le menu de sélection du raccourcis dans le formulaire de modification du type de séance/des horaires.</p>
  </div>

  <p id="log"></p>
  
  <script type="text/javascript" src="js/datetimepicker.min.js"></script>
<?php
}
fin($edition,$mathjax);
?>
