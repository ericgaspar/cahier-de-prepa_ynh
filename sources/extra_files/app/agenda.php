<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

//////////////////////////////////////////////
// Validation de la requête : jour et année //
//////////////////////////////////////////////
if ( isset($_REQUEST['mois']) && is_numeric($mois = $_REQUEST['mois']) && $mois > 1000 && $mois != date('ym') )  {
  $debutmois = mktime(0,0,0,$mois%100,1,(int)($mois/100));
  $auj = 0;
}
else  {
  $debutmois = mktime(0,0,0,idate('n'),1);
  $auj = idate('d');
}
$deb = date('Y-m-d',$debutmois);
// Nombres de jour du mois demandé et du précédent
$nbj = idate('t',$debutmois);
$nbj_prec = idate('t',$debutmois-1);
// Numéro dans la semaine du 1er du mois (0->lundi, 6->dimanche)
$j1 = (idate('w',$debutmois)+6)%7;
// Nombre de semaines à afficher
$nbs = ceil(($j1+$nbj)/7);
//$debutcal = $debutmois-86400*(idate('w',$debutmois)-1);

/////////////////////////////
// Vérification de l'accès //
/////////////////////////////
$mysqli = connectsql();
$resultat = $mysqli->query('SELECT val FROM prefs WHERE nom=\'protection_agenda\'');
$r = $resultat->fetch_row();
$protection = $r[0];
$resultat->free();
$edition = acces($protection,0,'Agenda',"agenda",$mysqli);

////////////
/// HTML ///
////////////
$mois = array('','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre');
$icone = ( $edition && $protection ) ? '<span class="icon-lock"></span>' : '';
debut($mysqli,'Agenda - '.$mois[idate('m',$debutmois)].' '.date('Y',$debutmois).$icone,$message,$autorisation,'agenda',false,'datetimepicker');

// Contrôles généraux, seulement en mode édition
if ( $edition )  {
?>
  <div id="icones">
    <a class="icon-ajout-colle formulaire" title="Ajouter un nouveau déplacement de colle"></a>
    <a class="icon-ajoute formulaire" title="Ajouter un nouvel événement à l'agenda"></a>
    <a class="icon-prefs formulaire" title="Modifier les préférences de l'agenda"></a>
    <a class="icon-aide" title="Aide pour l'édition de l'agenda"></a>
  </div>

<?php
}

// MathJax désactivé par défaut
$mathjax = false;

// Stockage des identifiants des événements chaque jour
// Le 1er est noté 1, le dernier jour du mois précédent est 0, le jour
// précédent est -1... Il y a $nbs*7 jours à considérer.
$ej = array_fill_keys(range(1-$j1,$nbs*7-$j1),[]);

// Récupération des événements
$resultat = $mysqli->query("SELECT a.id, m.nom AS matiere, t.nom AS type, IFNULL(m.id,0) as mid, t.id as tid, t.couleur, texte,
                            DATE_FORMAT(debut,'%w%Y%m%e') AS d, DATE_FORMAT(fin,'%w%Y%m%e') AS f,
                            DATE_FORMAT(debut,'%d/%m/%Y') AS jd, DATE_FORMAT(fin,'%d/%m/%Y') AS jf,
                            DATE_FORMAT(debut,'%kh%i') AS hd, DATE_FORMAT(fin,'%kh%i') AS hf,
                            DATEDIFF(debut,'$deb')+1 AS njd, DATEDIFF(fin,'$deb')+1 AS njf
                            FROM agenda AS a LEFT JOIN `agenda-types` AS t ON a.type = t.id LEFT JOIN matieres AS m ON a.matiere = m.id
                            WHERE debut < '$deb' + INTERVAL ".($j1+7*$nbs)." DAY AND fin >= '$deb' - INTERVAL $j1 DAY
                            ORDER BY fin,debut");
if ( $resultat->num_rows )  {
  $evenements = array();
  $couleurs = array();
  while ( $r = $resultat->fetch_assoc() )  {
    $id = $r['id'];
    // Titres
    if ( strlen($r['matiere']) )  {
      $titre = "${r['matiere']} - ${r['type']}";
      $titrebis = "${r['type']} en ${r['matiere']}";
    }
    else
      $titre = $titrebis = $r['type'];
    // Événement sur un seul jour
    if ( ( $d = $r['d'] ) == ( $f = $r['f'] ) )  {
      // Enregistrement sur le jour concerné
      $ej[$r['njd']][] = $id;
      // Date à afficher
      if ( ( $hd = $r['hd'] ) == '0h00' )
        $date = 'Le '.format_date($d);
      else  {
        $date = ( $hd == $r['hf'] ) ? 'Le '.format_date($d).' à '.str_replace('00','',$hd) : 'Le '.format_date($d).' de '.str_replace('00','',$hd).' à '.str_replace('00','',$r['hf']);
        $titre = str_replace('00','',$hd)." : $titre";
      }
    }
    // Événement sur plusieurs jours
    else  {
      // Enregistrement pour les jours concernés si événement sur plusieurs jours
      foreach ( range($njd = $r['njd'], $njf = $r['njf']) as $i )
        $ej[$i][] = ( ($i>$njd)?'_':'' ).$id.( ($i<$njf)?'_':'' );
      // Date à afficher
      if ( ( $hd = $r['hd'] ) == '0h00' )
        $date = 'Du '.format_date($d).' au '.format_date($f);
      else  {
        $date = 'Du '.format_date($d).' à '.str_replace('00','',$r['hd']).' au '.format_date($f).' à '.str_replace('00','',$r['hf']);
        $titre = str_replace('00','',$hd)." : $titre";
      }
    }
    // Enregistrement, différent suivant le mode d'affichage (édition ou non)
    $evenements[$id] = ( $edition ) ? array('date'=>$date,'titre'=>$titre,'texte'=>$r['texte'],'type'=>$r['tid'],'matiere'=>$r['mid'],'debut'=>"{$r['jd']} $hd",'fin'=>"{$r['jf']} {$r['hf']}",'je'=>( $hd == '0h00' ))
                                    : array('date'=>$date,'titre'=>$titre,'titrebis'=>(( $r['matiere'] ) ? $r['type'].' en '.$r['matiere'] : $r['type']),'texte'=>$r['texte'],'type'=>$r['tid']);
    // Couleurs
    $couleurs[$r['tid']] = $r['couleur'];
    // MathJax
    $mathjax = ( $mathjax ) ? true : strpos($r['texte'],'$')+strpos($r['texte'],'\\');
  }
  $resultat->free();
  // Écriture des données (événements, couleurs) en JavaScript
  echo "<script>\n  \$( function() {\n";
  foreach ( $couleurs as $tid => $couleur )
    echo "    \$('.evnmt$tid').css('background-color','#$couleur');\n";
  echo '    evenements = '.json_encode($evenements).";\n  });\n</script>\n";
}

// Calendrier
?>

  <p id="rechercheagenda" class="topbarre">
    <a class="icon-precedent" href="?mois=<?php echo date('ym',$debutmois-1); ?>" title="Mois précédent"></a>
    <a class="icon-suivant" href="?mois=<?php echo date('ym',$debutmois+2764800); ?>" title="Mois suivant"></a>
  </p>

  <div id="calendrier">
    <table id="semaine">
      <thead>
        <tr>
          <th>Lundi</th>
          <th>Mardi</th>
          <th>Mercredi</th>
          <th>Jeudi</th>
          <th>Vendredi</th>
          <th>Samedi</th>
          <th>Dimanche</th>
        </tr>
      </thead>
    </table>
<?php
// Une ligne par semaine
for ( $s = 0; $s < $nbs ; $s++ )  {
  // Identifiant du jour de début de ligne
  $d = 1-$j1+$s*7;
  // Nombre maximal d'événements sur un jour de la semaine concernée
  $nmax = max(count($ej[$d]),count($ej[$d+1]),count($ej[$d+2]),count($ej[$d+3]),count($ej[$d+4]),count($ej[$d+5]),count($ej[$d+6]));
  $height = 2.5+max($nmax,3)*1.25;
  // Fond, obligatoire pour les lignes de séparation des jours
  echo <<<FIN
    <div style="height: ${height}em">
      <table class="semaine-bg" style="height: ${height}em">
        <tbody>
          <tr>

FIN;
  for ( $i = $d ; $i < $d+7 ; $i++ )
    echo ( ( $i <= 0 ) || ( $i > $nbj ) ) ? "            <td class=\"autremois\"></td>\n" : "            <td></td>\n";
  echo <<<FIN
          </tr>
        </tbody>
      </table>
      <table class="evenements">
        <thead>
          <tr>

FIN;
  // Numéros de jour
  for ( $i = $d ; $i < $d+7 ; $i++ )
    if ( $i <= 0 )
      echo '            <th class="autremois">'.($nbj_prec+$i)."</th>\n";
    elseif ( $i > $nbj )
      echo '            <th class="autremois">'.($i-$nbj)."</th>\n" ;
    elseif ( $i == $auj )
      echo "            <th id=\"aujourdhui\">$i</th>\n" ;
    else
      echo "            <th>$i</th>\n" ;
  echo <<<FIN
          </tr>
        </thead>
        <tbody>

FIN;
  // Écriture de $nmax lignes d'événements
  for ( $j = 0 ; $j < $nmax ; $j++ )  {
    echo "          <tr>\n";
    $evenement_deja_commence = false;
    for ( $i = $d ; $i < $d+7 ; $i++ )  {
      // Si pas d'événement, on passe au jour suivant
      if ( empty($ej[$i]) )  {
        echo "            <td></td>\n";
        continue;
      }
      // Si on n'a pas affiché la veille un événement sur plusieurs jours
      // (sauf en début de semaine)
      if ( !$evenement_deja_commence )  {
        $classe = '';
        // Cas début de semaine
        if ( $i == $d )  {
          $id = array_shift($ej[$i]);
          // Si événement commencé la semaine précédente, on continue
          if ( $id[0] == '_' )  {
            $classe = ' evnmt_suite';
            $id = substr($id,1);
          }
        }
        // Hors début de semaine : on cherche un événement non déjà commencé
        else  {
          foreach ( $ej[$i] as $k=>$id )
            if ( $id[0] != '_' )  {
              unset($ej[$i][$k]);
              break;
            }
          // Si les seuls événements possibles sont déjà commencés, il ne
          // faut rien afficher
          if ( $id[0] == '_' )  {
            echo "            <td></td>\n";
            continue;
          }
        }
        // Si l'id se termine par '_', événement sur plusieurs jours
        if ( $id[strlen($id)-1] == '_' )  {
          $evenement_deja_commence = true;
          $classe .= ' evnmt_suivi';
          $id = substr($id,0,-1);
        }
      }
      // Si événément déjà commencé au moins la veille, qui termine ce jour
      elseif ( ( $pos = array_search("_$id",$ej[$i]) ) !== false )  {
        $evenement_deja_commence = false;
        unset($ej[$i][$pos]);
        $classe = ' evnmt_suite';
      }
      // Si événément déjà commencé au moins la veille, qui continue
      else  {
        unset($ej[$i][array_search("_${id}_",$ej[$i])]);
        $classe = ' evnmt_suite evnmt_suivi';
      }
      // Affichage
      echo "            <td><p id=\"e$id\" class=\"modifevnmt evnmt{$evenements[$id]['type']}$classe\">{$evenements[$id]['titre']}</p></td>\n";
    }
    echo "          </tr>\n";
  }
  echo <<<FIN
        </tbody>
      </table>
    </div>

FIN;
}
echo "  </div>\n";

if ( $edition ) {
  // Récupération des types d'événement
  $resultat = $mysqli->query('SELECT id, nom FROM `agenda-types`');
  $select_types = '';
  //$types = array();
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )
      $select_types .= "<option value=\"${r['id']}\">${r['nom']}</option>";
    $resultat->free();
  }
  // Récupération des matières
  $resultat = $mysqli->query('SELECT id, nom FROM matieres');
  $select_matieres = '';
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )
      $select_matieres .= "<option value=\"${r['id']}\">${r['nom']}</option>";
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
          <option value="32">Agenda désactivé</option>';
  $p = $protection;
  if ( ( $p == 0 ) || ( $p == 32 ) )
    $sel_protection = str_replace("\"$p\"","\"$p\" selected",$select_protection);
  else  {
    $sel_protection = str_replace('"6"','"6" selected',$select_protection);
    for ( $a=1; $a<6; $a++ )
      if ( ( ($p-1)>>($a-1) & 1 ) == 0 )
        $sel_protection = str_replace("\"$a\"","\"$a\" selected",$sel_protection);
  }
  
  // Récupération du nombre d'événements affichés sur la page d'accueil
  $resultat = $mysqli->query('SELECT val FROM prefs WHERE nom=\'nb_agenda_index\'');
  $mysqli->close();
  $r = $resultat->fetch_row();
  $n = $r[0];
  $resultat->free();
?>

  <form id="form-evnmt" data-action="agenda-elems">
    <h3 class="edition">Modifier un événement</h3>
    <p class="ligne"><label for="type">Type&nbsp;:</label>
      <select name="type"><?php echo $select_types; ?></select>
      <a class="icon-edite" href="agenda-types">&nbsp;</a>
    </p>
    <p class="ligne"><label for="matiere">Matière&nbsp;:</label>
      <select name="matiere"><option value="0">Pas de matière</option><?php echo $select_matieres; ?></select>
    </p>
    <p class="ligne"><label for="debut">Début&nbsp;: </label><input type="text" name="debut" value="" size="15"></p>
    <p class="ligne"><label for="fin">Fin&nbsp;: </label><input type="text" name="fin" value="" size="15"></p>
    <p class="ligne"><label for="jours">Date(s) seulement&nbsp;: </label><input type="checkbox" name="jours" value="1"></p>
    <textarea name="texte" class="edithtml" rows="10" cols="100" placeholder="Texte associé à l'événement (non obligatoire)"></textarea>
    <input type="hidden" name="id" value="">
  </form>
   
  <form id="form-ajoute" data-action="agenda-elems">
    <h3 class="edition">Ajouter un événement</h3>
    <p class="ligne"><label for="type">Type&nbsp;:</label>
      <select name="type"><?php echo $select_types; ?></select>
      <a class="icon-edite" href="agenda-types">&nbsp;</a>
    </p>
    <p class="ligne"><label for="matiere">Matière&nbsp;:</label>
      <select name="matiere"><option value="0">Pas de matière</option><?php echo $select_matieres; ?></select>
    </p>
    <p class="ligne"><label for="debut">Début&nbsp;: </label><input type="text" name="debut" value="" size="15"></p>
    <p class="ligne"><label for="fin">Fin&nbsp;: </label><input type="text" name="fin" value="" size="15"></p>
    <p class="ligne"><label for="jours">Date(s) seulement&nbsp;: </label><input type="checkbox" name="jours" value="1"></p>
    <textarea name="texte" class="edithtml" rows="10" cols="100" placeholder="Texte associé à l'événement (non obligatoire)"></textarea>
  </form>
  
  <form id="form-ajout-colle" data-action="deplcolle">
    <h3 class="edition">Nouveau déplacement de colle</h3>
    <p>Ce formulaire spécial donne la possibilité de créer une annulation (si l'<i>ancien horaire</i> est le seul renseigné), un rattrapage (si le <i>nouvel horaire</i> est le seul renseigné) ou un déplacement (si les deux horaires sont renseignés) de colle de façon automatique. Dans le cas d'un déplacement, deux événements sont créés. La salle est facultative. Le(s) événement(s) créé(s) sont ensuite modifiables en éditant le texte. Pour chaque horaire, régler l'heure à «&nbsp;0h00&nbsp;» permet de n'afficher que la date.</p>
    <p class="ligne"><label for="matiere">Matière&nbsp;:</label>
      <select name="matiere"><?php echo $select_matieres; ?></select>
    </p>
    <p class="ligne"><label for="colleur">Colleur&nbsp;: </label><input type="text" placeholder="(obligatoire)" name="colleur" value="" size="20"></p>
    <p class="ligne"><label for="groupe">Groupe&nbsp;: </label><input type="text" placeholder="(obligatoire)" name="groupe" value="" size="10"></p>
    <p class="ligne"><label for="ancien">Ancien horaire&nbsp;: </label><input type="text" placeholder="(non obligatoire)" name="ancien" value="" size="15"></p>
    <p class="ligne"><label for="ancien">Nouvel horaire&nbsp;: </label><input type="text" placeholder="(non obligatoire)" name="nouveau" value="" size="15"></p>
    <p class="ligne"><label for="salle">Salle de rattrapage&nbsp;: </label><input type="text" placeholder="(non obligatoire)" name="salle" value="" size="10"></p>
  </form>

  <form id="form-prefs" data-action="prefsglobales">
    <h3 class="edition">Modifier les préférences de l'agenda</h3>
    <p class="ligne"><label for="protection_agenda">Accès&nbsp;: </label>
      <select name="protection_agenda[]" multiple><?php echo $sel_protection; ?>
      </select>
    </p>
    <p>Pour modifier les semaines de l'année, il faut vous rendre à la <a href="planning">gestion du planning annuel</a>.</p>
    <p class="ligne"><label for="nb_agenda_index">Nombre d'événements affichés sur la page d'accueil&nbsp;: </label></p>
    <p class="ligne">&nbsp;
      <input type="text" name="nb_agenda_index" value="<?php echo $n; ?>" size="3">
    </p>
    <p>Les différents types d'événements sont modifiables sur une <a href="agenda-types">page spécifique</a>.</p>
  </form>
  
  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter un événement dans l'agenda, de modifier les événements existants ou de modifier les préférences de l'agenda.</p>
    <p>Pour modifier un événement existant, il faut cliquer dessus. Un formulaire de modification apparaîtra au-dessus du calendrier.</p>
    <p>Les trois boutons généraux permettent de&nbsp;:</p>
    <ul>
      <li><span class="icon-ajout-colle"></span>&nbsp;: ouvrir un formulaire pour ajouter un nouveau déplacement de colle.</li>
      <li><span class="icon-ajoute"></span>&nbsp;: ouvrir un formulaire pour ajouter un nouvel événement.</li>
      <li><span class="icon-prefs"></span>&nbsp;: ouvrir un formulaire pour modifier les préférences globales de l'agenda.</li>
    </ul>
    <h4>Déplacement de colle</h4>
    <p>L'ajout de déplacement de colle est en réalité un simple raccourci pratique qui conduit à l'ajout d'un ou deux événements, en fonction des dates données. Le texte sera généré automatiquement à partir des informations saisies. Le ou les deux événements seront ensuite modifiable comme tous les autres événements.</p>
    <h4>Préférences globales de l'agenda</h4>
    <p>Vous pouvez modifier&nbsp;:</p>
    <ul>
      <li>l'<em>accès</em> à l'agenda.</li>
      <li>le <em>nombre d'événements affichés sur la page d'accueil</em></li>
    </ul>
    <p>Des détails sont donnés dans l'aide du formulaire de modification.</p>
    <h4>Types d'événements</h4>
    <p>Les types d'événements ne sont pas modifiables sur cette page, mais sur une <a href="agenda-types">page spécifique</a>.</p>
    <h4>Visibilité des événements</h4>
    <p>Attention, contrairement aux informations, aux documents et aux programmes de colles, les événements sont obligatoirement visibles par tous ceux qui ont accès à l'agenda. Il n'est pas possible de cacher un événement pour le faire apparaître plus tard. Cette fonctionnalité est prévue pour une prochaine version.</p>
    <h4>Affichage sur la page d'accueil</h4>
    <p>Si des événements sont disponibles dans les 7 jours qui viennent, ils sont automatiquement affichés en haut de la page d'accueil, avant toute information. Cette fonctionnalité est désactivable en réglant le <em>nombre d'événements affichés sur la page d'accueil</em> à zéro dans les préférences de l'agenda.</p>
    <h4>Matières et droits</h4>
    <p>Seuls les professeurs peuvent modifier l'agenda. Afin de faciliter les modifications, en particulier lors des changements d'emploi du temps concernant plusieurs matières, il n'y a pas d'impossibilité d'ajouter/modifier un événement concernant une autre matière. Par ailleurs, toute matière concernée doit avoir été créée à la page de gestion des <a href="matieres">matières</a>.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet d'ajouter ou de modifier un événement de l'agenda. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <h4>Type, matière</h4>
    <p>Le <em>type</em> d'événement est associé à un titre (utilisé dans le calendrier et dans les informations récentes) et une couleur (utilisée dans le calendrier). Les types d'événements sont modifiables sur la page de modification des <a href="agenda-types">types d'événements</a>. Il est possible d'ajouter des types d'événements à ceux déjà définis.</p>
    <p>La <em>matière</em> n'est pas obligatoire, mais permet de l'afficher dans le titre. Il est possible de créer une matière à la page de gestion des <a href="matieres">matières</a>, même si celle-ci ne sert à rien par ailleurs ou si le professeur de la matière ne participe pas&nbsp;: elle n'apparaîtra pas dans le menu tant qu'elle reste «&nbsp;vide&nbsp;».</p>
    <h4>Horaires</h4>
    <p>Le <em>début</em> et la <em>fin</em> sont les deux horaires définissant l'événement. Plusieurs cas sont possibles&nbsp;:</p>
    <ul>
      <li>si <em>début</em> et <em>fin</em> sont identiques, alors l'événement sera simplement caractérisé par une date unique.</li>
      <li>si <em>début</em> et <em>fin</em> sont le même jour, alors l'événement sera caractérisé par une date et deux horaires, de début et de fin.</li>
      <li>si <em>début</em> et <em>fin</em> sont sur deux jours différents, alors l'événement apparaîtra sur le calendrier sur l'ensemble de la durée définie.</li>
    </ul>
    <p>La case à cocher <em>Date(s) seulement</em> permet de supprimer les heures. Cela peut permettre de définir un événement sur toute une journée (si <em>début</em> et <em>fin</em> sont identiques), voire sur plusieurs jours (s'ils sont différents&nbsp;; <em>fin</em> est inclus dans l'intervalle).</p>
    <h4>Texte optionnel</h4>
    <p>Enfin, une case de saisie de texte est fournie pour ajouter une description plus longue à l'événement. Cette description n'est pas obligatoire mais sera utile la plupart du temps. En dehors du mode d'édition dans lequel vous êtes actuellement, ce texte sera affiché lors d'un clic sur l'événement, dans le calendrier.</p>
    <p>Le texte doit être formaté en HTML&nbsp;: par exemple, chaque bloc de texte doit être encadré par &lt;p&gt; et &lt;/p&gt;. Il peut donc contenir des liens vers les autres pages du site, vers des documents du site, vers le web... Des boutons sont fournis pour aider au formattage (ainsi qu'une aide).</p>
  </div>

  <div id="aide-ajout-colle">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet d'ajouter un déplacement de colle dans l'agenda. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>L'ajout de déplacement de colle est en réalité un simple raccourci pratique qui conduit à l'ajout d'un ou deux événements, en fonction des dates données. Le texte sera généré automatiquement à partir des informations saisies. Le ou les deux événements seront ensuite modifiable comme tous les autres événements.</p>
    <h4>Matière, colleur, groupe</h4>
    <p>La <em>matière</em> est obligatoire. Il est possible de créer une matière à la page de gestion des <a href="matieres">matières</a>, même si celle-ci ne sert à rien par ailleurs ou si le professeur de la matière ne participe pas&nbsp;: elle n'apparaîtra pas dans le menu tant qu'elle reste «&nbsp;vide&nbsp;».</p>
    <p>Le <em>colleur</em> et le <em>groupe</em> sont obligatoire. Ces données seront utilisées pour générer automatiquement le texte du déplacement de colle. Il convient d'ajouter la civilité du <em>colleur</em> (M., Mme). Le <em>groupe</em> est à renseigner uniquement avec le numéro, par exemple «&nbsp;8&nbsp;» pour le groupe 8 (le terme «&nbsp;groupe&nbsp;» sera ajouté).</p>
    <h4>Horaires et salle</h4>
    <p>L'<em>ancien horaire</em> et le <em>nouvel horaire</em> ne sont pas obligatoire tous les deux, mais au moins l'un des deux doit bien sûr être renseigné. Trois cas sont possibles&nbsp;:</p>
    <ul>
      <li><em>ancien horaire</em> seul&nbsp;: un seul événement créé, la colle est dite «&nbsp;annulée&nbsp;»</li>
      <li><em>nouvel horaire</em> seul&nbsp;: un seul événement créé, la colle est dite «&nbsp;rattrapée&nbsp;»</li>
      <li><em>ancien horaire</em> et <em>nouvel horaire</em>&nbsp;: deux événements créés, la colle est dite «&nbsp;déplacée&nbsp;».</li>
    </ul>
    <p>L'heure n'est pas obligatoire. Si un horaire est saisi sous la forme «&nbsp;XX/XX/XXXX 0h00&nbsp;», seule la date sera retenue. Il est par exemple autorisé de saisir l'heure de l'<em>ancien horaire</em>, mais pas celui du <em>nouvel horaire</em>, dans le cas où il n'est pas encore connu au moment de la saisie.</p>
    <p>L'icône <span class="icon-ferme"></span> présente devant chaque horaire permet de le supprimer.</p>
    <p>La <em>salle de rattrapage</em> n'est utilisée que si le <em>nouvel horaire</em> est saisi. Il faut renseigner uniquement le numéro/nom de la salle, par exemple «&nbsp;111&nbsp;» pour la salle 111 (le terme «&nbsp;salle&nbsp;» sera ajouté).</p>
    <h4>Modification ultérieure</h4>
    <p>Si deux événements sont créés, ils le sont avec le même texte, généré automatiquement à l'aide des données saisies. Ils deviennent automatiquement indépendants&nbsp;: modifier l'un (horaire ou texte) ne modifie pas l'autre. Attention à la modification d'horaire de rattrapage notamment&nbsp;: il faut bien penser à la faire sur le deuxième événement en priorité, et éventuellement sur le premier.</p>
  </div>

  <div id="aide-prefs">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier les préférences globales de l'agenda. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>. On peut y modifier&nbsp;:</p>
    <ul>
      <li>l'<em>accès</em> à l'agenda (voir ci-dessous).</li>
      <li>le <em>nombre d'événements affichés sur la page d'accueil</em>&nbsp;: les événements qui se situent dans les 7 jours prochains s'affichent automatiquement en haut de la page d'accueil du Cahier de Prépa, dans la limite du nombre égal à cette valeur. En particulier, mettre zéro supprime cet affichage automatique. La valeur par défaut est 10.</li>
    </ul>
    <p>L'accès à l'agenda peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: agenda accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: agenda accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page.</li>
      <li><em>Agenda invisible</em>&nbsp;: agenda entièrement invisible pour les utilisateurs autres que les professeurs. Un cadenas <span class="icon-locktotal"></span> est alors affiché dans le titre de la page.</li>
    </ul>
  </div>

  <p id="log"></p>

  <script type="text/javascript" src="js/datetimepicker.min.js"></script>

<?php
}
fin($edition,$mathjax);
?>
