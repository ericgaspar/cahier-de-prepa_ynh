<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

////////////////////////////////////////
// Validation de la requête : matière //
////////////////////////////////////////

// Recherche de la matière concernée, variable $matiere
// Si $_REQUEST['cle'] existe, on la cherche dans les matières disponibles.
// colles=0 : cahier de texte vide, à afficher uniquement pour les profs concernés
// colles=1 : cahier de texte utilisé, à afficher pour les utilisateurs associés
//  à la matière 
// colles_protection permet de restreindre l'accès
// colles_protection=32 : cahier de texte désactivé, pas d'affichage
$mysqli = connectsql();
$resultat = $mysqli->query('SELECT id, cle, nom, colles_protection AS protection
                            FROM matieres WHERE ( colles = 1'.( ( $autorisation == 5 ) ? " OR  FIND_IN_SET(id,'${_SESSION['matieres']}')" : '' ).' ) AND colles_protection < 32');
if ( $resultat->num_rows )  {
  if ( !empty($_REQUEST) )  {
    while ( $r = $resultat->fetch_assoc() )
      if ( isset($_REQUEST[$r['cle']]) )  {
        $matiere = $r;
        $mid = $matiere['id'];
        break;
      }
  }
  $resultat->free();
  // Si aucune matière trouvée
  if ( !isset($matiere) )  {
    debut($mysqli,'Programme de colles','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
}
// Si aucune matière présentant son programme de colle n'est enregistrée
else  {
  debut($mysqli,'Programme de colles','Cette page ne contient aucune information.',$autorisation,' ');
  $mysqli->close();
  fin();
}

/////////////////////////////
// Vérification de l'accès //
/////////////////////////////
$edition = acces($matiere['protection'],$mid,"Programme de colles - ${matiere['nom']}","colles?${matiere['cle']}",$mysqli);

//////////////////////////////////////////////////////////////////
// Validation de la requête : semaine(s) ou éléments à afficher //
//////////////////////////////////////////////////////////////////

// Récupération des semaines et du nombre de semaines
$resultat = $mysqli->query("SELECT semaines.id, DATE_FORMAT(debut,'%w%Y%m%e') AS debut, DATE_FORMAT(debut,'%y%v') AS semaine, nom AS vacances 
                            FROM semaines LEFT JOIN vacances ON semaines.vacances = vacances.id ORDER BY semaines.id");
$select_semaines = "\n      <option value=\"0\">Toute l'année</option>";
$semaines = array(0=>'');
while ( $r = $resultat->fetch_assoc() )  {
  $semaines[] = $r['semaine'];
  $select_semaines .= "\n      <option value=\"${r['id']}\">".( $r['vacances'] ?: format_date($r['debut']) ).'</option>';
}
$resultat->free();
$nmax = count($semaines);

// Recherche sur du texte
$recherche = '';
if ( isset($_REQUEST['recherche']) && strlen($_REQUEST['recherche']) )  {
  $recherche = htmlspecialchars($_REQUEST['recherche']);
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
elseif ( isset($_REQUEST['n']) && ctype_digit($n = $_REQUEST['n']) && ( $n > 0 ) && ( $n <= $nmax ) )  {
  $requete = "WHERE s.id >= $n";
  // Nombre d'éléments vus par défaut : 2 en mode édition, 1 sinon
  if ( !isset($_REQUEST['nb']) || !ctype_digit($nb = $_REQUEST['nb']) || ( $nb < 1 ) )
    $nb = 1+$edition;
}
// Vue de la semaine en cours, prochaine semaine à partir du vendredi 19h
// $n est false si non trouvé (hors année scolaire)
elseif ( ( $n = array_search(date('yW', strtotime('Monday this week',time()+104400)),$semaines) ) !== false )  {
  $requete = "WHERE s.id >= $n";
  $nb = 1+$edition;
}

////////////
/// HTML ///
////////////
$icone = ( $edition && $matiere['protection'] ) ? '<span class="icon-lock"></span>' : '';
debut($mysqli,"Programme de colles - ${matiere['nom']}$icone",$message,$autorisation,"colles?${matiere['cle']}",$mid);

// MathJax désactivé par défaut
$mathjax = false;

// Formulaire de la demande des semaines à afficher
$select_semaines = str_replace("\"$n\"","\"$n\" selected",$select_semaines);
$boutons = "
  <p id=\"recherchecolle\" class=\"topbarre\">
    <a class=\"icon-precedent\" href=\"?${matiere['cle']}&amp;n=".max(1,$n-1)."\" title=\"Semaine précédente\"></a>
    <a class=\"icon-suivant\" href=\"?${matiere['cle']}&amp;n=".min($n+1,$nmax)."\" title=\"Semaine suivante\"></a>
    <a class=\"icon-voirtout\" href=\"?${matiere['cle']}&amp;tout\" title=\"Voir l'ensemble du programme de colles\"></a>
    <select id=\"semaines\" onchange=\"window.location='?${matiere['cle']}&amp;'+((this.value>0)?'n='+this.value:'tout');\">$select_semaines
    </select>
    <span class=\"icon-recherche\" onclick=\"if( $(this).prev().is(':visible') && $(this).prev().val().length ) window.location='?${matiere['cle']}&amp;recherche='+$(this).prev().val(); else $(this).prev().show();\"></span>
    <input type=\"text\" value=\"$recherche\" onchange=\"window.location='?${matiere['cle']}&amp;recherche='+this.value;\" title=\"Recherche dans les textes des programmes de colles\">
  </p>
";

// Affichage du titre de chaque semaine
function generetitre($date,$vacances)  {
  switch ( $vacances )  {
    case 0:
      return'Semaine du '.format_date($date);
    case 1:
      return ucfirst(format_date($date))."&nbsp;: Vacances de Toussaint";
    case 2:
      return ucfirst(format_date($date))."&nbsp;: Vacances de Noël";
    case 3:
      return ucfirst(format_date($date))."&nbsp;: Vacances d'hiver";
    case 4:
      return ucfirst(format_date($date))."&nbsp;: Vacances de Pâques";
  }
}

// Affichage public sans édition
if ( !$edition )  {
  echo $boutons;
  if ( $n !== false )  {
    // Affichage des programmes de colles diffusés
    if ( $recherche )
      $resultat = $mysqli->query("SELECT DATE_FORMAT(s.debut,'%w%Y%m%e') AS debut, s.colle, nom AS vacances, c.texte
                                  FROM colles AS c LEFT JOIN semaines AS s ON c.semaine=s.id LEFT JOIN vacances ON s.vacances = vacances.id
                                  WHERE c.matiere = $mid AND c.cache = 0 AND c.texte LIKE '%".$mysqli->real_escape_string($recherche).'%\' ORDER BY c.semaine');
    else
      $resultat = $mysqli->query("SELECT DATE_FORMAT(s.debut,'%w%Y%m%e') AS debut, s.colle, nom AS vacances, c.texte
                                  FROM semaines AS s LEFT JOIN vacances ON s.vacances = vacances.id
                                  LEFT JOIN (SELECT texte, semaine FROM colles WHERE matiere = $mid AND cache = 0) AS c ON c.semaine=s.id
                                  $requete ORDER BY s.id" );
    $mysqli->close();
    if ( $resultat->num_rows > 0 )  {
      $compteur = 0;
      while ( ( $compteur < $nb ) && ( $r = $resultat->fetch_assoc() ) )  {
        $mathjax = $mathjax ?: boolval(strpos($r['texte'],'$')+strpos($r['texte'],'\\'));
        $titre = ( $r['vacances'] ) ? ucfirst(format_date($r['debut']))."&nbsp;: ${r['vacances']}" : 'Semaine du '.format_date($r['debut']);
        if ( $r['colle'] )  {
          $compteur = $compteur+1;
          $texte = $r['texte'] ?: '    <p>Le programme de colles de cette semaine n\'est pas défini.</p>';
        }
        else
          $texte = ( $r['vacances'] ) ? '' : '    <p>Il n\'y a pas de colles cette semaine.</p>';
        echo <<<FIN
      
  <article>
    <h3>$titre</h3>
$texte
  </article>

FIN;
      }
      $resultat->free();
    }
    else
      echo "\n  <h2>Aucun résultat n'a été trouvé pour cette recherche.</h2>\n";
  }
  else
    echo "\n  <h2>L'année est terminée... Bonnes vacances&nbsp;!</h2>\n  <p><a href=\"?${matiere['cle']}&amp;tout\">Revoir tout le programme de l'année</a></p>\n";
}

// Affichage professeur éditeur
else  {
  echo <<<FIN

  <div id="icones">
    <a class="icon-prefs formulaire" title="Modifier les réglages des programmes de colles"></a>
    <a class="icon-aide" title="Aide pour les modifications des programmes de colles"></a>
  </div>
$boutons
FIN;
  if ( $n !== false )  {
    // Affichage des semaines concernées
    // Le programme est identifié par le couple semaine-matière plutôt que son
    // identifiant propre pour assurer une cohérence entre les programmes saisis
    // et ceux non saisis/supprimés.
    if ( strlen($recherche) )
      $resultat = $mysqli->query("SELECT DATE_FORMAT(s.debut,'%w%Y%m%e') AS debut, s.colle, nom AS vacances, c.texte, s.id, c.cache 
                                  FROM colles AS c LEFT JOIN semaines AS s ON c.semaine=s.id LEFT JOIN vacances ON s.vacances = vacances.id
                                  WHERE c.matiere = $mid AND c.texte LIKE '%".$mysqli->real_escape_string($recherche).'%\' ORDER BY c.semaine');
    else
      $resultat = $mysqli->query("SELECT DATE_FORMAT(s.debut,'%w%Y%m%e') AS debut, s.colle, nom AS vacances, c.texte, s.id, c.cache 
                                  FROM semaines AS s LEFT JOIN vacances ON s.vacances = vacances.id
                                  LEFT JOIN (SELECT texte, semaine, cache FROM colles WHERE matiere = $mid) AS c ON c.semaine=s.id
                                  $requete ORDER BY s.id");
    $mysqli->close();
    if ( $resultat->num_rows )  {
      $compteur = 0;
      while ( ( $compteur < $nb ) && ( $r = $resultat->fetch_assoc() ) )  {
        $mathjax = $mathjax ?: boolval(strpos($r['texte'],'$')+strpos($r['texte'],'\\'));
        $titre = ( $r['vacances'] ) ? ucfirst(format_date($r['debut']))."&nbsp;: ${r['vacances']}" : 'Semaine du '.format_date($r['debut']);
        if ( $r['colle'] )  {
          $compteur = $compteur+1;
          if ( is_null($r['texte']) )
            echo <<<FIN

  <article data-id="ajoutecolle|${r['id']}-$mid">
    <h3 class="edition">$titre</h3>
    <a class="icon-aide" title="Aide pour la saisie d'un nouveau programme de colles"></a>
    <a class="icon-ajoutecolle" title="Saisir ce programme de colles"></a>
    <p>Le programme de colles de cette semaine n'est pas encore défini.</p>
  </article>

FIN;
          else  {
            $classe_cache = ( $r['cache'] ) ? ' class="cache"' : '';
            $bouton_cache = ( $r['cache'] ) ? '<a class="icon-montre" title="Afficher le programme de colles sur la partie publique"></a>' : '<a class="icon-cache" title="Rendre invisible le programme de colles sur la partie publique"></a>';
            echo <<<FIN

  <article$classe_cache data-id="colles|${r['id']}-$mid">
    <h3 class="edition">$titre</h3>
    <a class="icon-aide" title="Aide pour l'édition de ce programme de colles"></a>
    $bouton_cache
    <a class="icon-supprime" title="Supprimer ce programme de colles"></a>
    <div class="editable edithtml majpubli" data-id="colles|texte|${r['id']}-$mid" placeholder="Texte du programme de colles">
${r['texte']}
    </div>
  </article>

FIN;
          }
        }
        else  {
          $texte = ( $r['vacances'] ) ? '' : '    <p>Il n\'y a normalement pas de colles cette semaine-là.</p><p>S\'il s\'agit d\'une erreur, cela est modifiable <a href="planning">sur la page de gestion du planning annuel</a>.</p>';
          echo <<<FIN

  <article>
    <h3>$titre</h3>
$texte
  </article>

FIN;
        }
      }
      $resultat->free();
    }
    else
      echo "\n  <article><h2>Aucun résultat n'a été trouvé pour cette recherche.</h2></article>\n";
  }
  else
    echo "\n  <article><h2>L'année est terminée.</h2>\n  <p><a href=\"?${matiere['cle']}&amp;tout\">Revoir tout le programme de l'année</a></p></article>\n";

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

  <form id="form-ajoutecolle" data-action="ajout-colle">
    <textarea name="texte" class="edithtml" rows="10" cols="100" placeholder="Texte du programme de colles (obligatoire)"></textarea>
    <p class="ligne"><label for="cache">Ne pas diffuser sur la partie publique&nbsp;: </label><input type="checkbox" name="cache" value="1"></p>
  </form>
  
  <form id="form-prefs" data-action="prefsmatiere">
    <h3 class="edition">Réglages des programmes de colles en <?php echo $matiere['nom']; ?></h3>
    <p class="ligne"><label for="colles_protection">Accès&nbsp;: </label>
      <select name="colles_protection[]" multiple><?php echo $sel_protection; ?>
      </select>
    </p>
    <p>Si vous désactivez ici cette fonction, vous ne pourrez plus utiliser cette page. Les programmes de colles déjà saisis ne seront pas supprimés, le choix est réversible. Vous pourrez réactiver cette fonction à la <a href="matieres">gestion des matières</a>.</p>
    <p>Pour modifier les semaines correspondant à un programme de colle, il faut vous rendre à la <a href="planning">gestion du planning annuel</a>.</p>
    <p>Pour modifier les utilisateurs concernés par cette matière, il faut vous rendre à la <a href="utilisateurs-matieres">gestion des associations utilisateurs-matières</a>.</p>
    <input type="hidden" name="id" value="<?php echo $mid; ?>">
  </form>
  
  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter et de modifier des programmes de colles. On peut également les consulter, sélectionner une semaine ou effectuer une recherche de texte.</p>
    <p>Chaque programme de colles est associé à une semaine du planning annuel. Il est impossible de déplacer un programme de colles. Pour modifier les semaines correspondant à un programme de colle, il faut vous rendre à la <a href="planning">gestion du planning annuel</a>.</p>
    <p>Si une semaine de colles ne contient pas de programme, il est possible d'en rajouter un en cliquant sur le bouton <span class="icon-ajoute"></span>.</p>
    <p>Les textes modifiables sont dans les zones indiquées par des pointillés. Ils sont modifiables en cliquant sur les boutons <span class="icon-edite"></span> et en validant avec le bouton <span class="icon-ok"></span> qui apparaît alors.</p>
    <h4>Gestion des accès</h4>
    <p>L'accès aux programmes de colles peut être protégé en cliquant sur le bouton des préférences <span class="icon-prefs"></span>. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: programme accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: programme accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte et des matières auxquelles ils sont associés. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Fonction désactivée</em>&nbsp;: les programmes de colles pour cette matière sont complètement indisponibles. La fonction n'apparaît plus dans le menu, y compris pour vous.</li>
    </ul>
    <p>Si vous désactivez ici cette fonction, vous ne pourrez plus utiliser cette page. Les programmes de colles ne seront pas supprimés, le choix est réversible. Vous pourrez réactiver cette fonction à la <a href="matieres">gestion des matières</a>.</p>
    <p>Le lien de la page dans le menu est visible avant identification. Il disparaît après identification pour les utilisateurs n'ayant pas accès à la page.</p>
    <h4>Suppression de tous les programmes de colles</h4>
    <p>L'ensemble des programmes de colles est supprimable en un clic à la <a href="matieres">gestion des matières</a>.</p>
  </div>

  <div id="aide-prefs">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier l'accès aux programmes de colles en <?php echo $matiere['nom']; ?>. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: programme accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: programme accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte et des matières auxquelles ils sont associés. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Fonction désactivée</em>&nbsp;: les programmes de colles pour cette matière sont complètement indisponibles. La fonction n'apparaît plus dans le menu, y compris pour vous.</li>
    </ul>
    <p>Si vous désactivez ici cette fonction, vous ne pourrez plus utiliser cette page. Les programmes de colles ne seront pas supprimés, le choix est réversible. Vous pourrez réactiver cette fonction à la <a href="matieres">gestion des matières</a>.</p>
    <h4>Suppression de tous les programmes de colles</h4>
    <p>L'ensemble des programmes de colles n'est pas supprimable ici, mais en un clic à la <a href="matieres">gestion des matières</a>.</p>
  </div>

  <div id="aide-colles">
    <h3>Aide et explications</h3>
    <p>Le texte de chaque programme de colle existant est modifiable, en cliquant sur le bouton <span class="icon-edite"></span>. Si aucun programme de colle n'existe pour la semaine concerné, il est possible d'en créer un en cliquant sur le bouton <span class="icon-ajoute"></span>. Chaque contenu éditable est indiqué par une bordure en pointillés.</p>
    <p>Une fois le texte édité, il est validé en appuyant sur le bouton <span class="icon-ok"></span>. Au contraire, un clic sur le bouton <span class="icon-annule"></span> annule l'édition.</p>
    <p>Si la connexion est inactive depuis longtemps, une identification sera à nouveau nécessaire.</p>
    <p>D'autres modifications sont possibles à l'aide des boutons disponibles pour chaque programme de colles&nbsp;:</p>
    <ul>
      <li><span class="icon-supprime"></span>&nbsp;: supprimer le programme de colles (une confirmation sera demandée)</li>
      <li><span class="icon-cache"></span>&nbsp;: rendre le programme de colles non visible sur la partie publique. Cela peut être utile pour entrer ses programmes de colles à l'avance ou si l'on se rend compte d'une grosse erreur que l'on souhaite corriger à l'abri des regards (le programme est toujours modifiable).</li>
      <li><span class="icon-montre"></span>&nbsp;: afficher le programme de colles sur la partie publique</li>
    </ul>
    <p>Le texte doit être formaté en HTML&nbsp;: par exemple, chaque bloc de texte doit être encadré par &lt;p&gt; et &lt;/p&gt;. Il peut donc contenir des liens vers les autres pages du site, vers des documents du site, vers le web... Des boutons sont fournis pour aider au formattage (ainsi qu'une aide).</p>
  </div>

  <div id="aide-ajoutecolle">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer un nouveau programme de colles. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-annule"></span>.</p>
    <p>Le texte doit être formaté en HTML&nbsp;: par exemple, chaque bloc de texte doit être encadré par &lt;p&gt; et &lt;/p&gt;. Il peut donc contenir des liens vers les autres pages du site, vers des documents du site, vers le web... Des boutons sont fournis pour aider au formattage (ainsi qu'une aide).</p>
    <p>La case à cocher <em>Ne pas diffuser sur la partie publique</em> permet de cacher temporairement ce programme de colles, par exemple pour le diffuser ultérieurement.</p>
    <p>La case à cocher <em>Ne pas diffuser sur la partie publique</em> permet de cacher temporairement ce programme de colles, par exemple pour le diffuser ultérieurement. Si la case est cochée, le programme de colles ne sera pas diffusé et pourra être affiché à l'aide du bouton <span class="icon-montre"></span>. Si la case est décochée, le programme de colles sera immédiatement visible et pourra être rendu invisible à l'aide du bouton <span class="icon-cache"></span>.</p>
  </div>
  
  <p id="log"></p>
<?php
}
fin($edition,$mathjax);
?>
