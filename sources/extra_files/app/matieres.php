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
  $titre = 'Modification des matières';
  $actuel = 'matieres';
  include('login.php');
}

//////////////
//// HTML ////
//////////////
debut($mysqli,'Modification des matières',$message,5,'matieres');
echo <<<FIN

  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter une nouvelle matière"></a>
    <a class="icon-aide" title="Aide pour les modifications des matières"></a>
  </div>

FIN;
$select_protection = '
          <option value="0">Accès public</option>
          <option value="6">Utilisateurs identifiés</option>
          <option value="1">Invités</option>
          <option value="2">Élèves</option>
          <option value="3">Colleurs</option>
          <option value="4">Lycée</option>
          <option value="5">Professeurs</option>
          <option value="32">Fonction désactivée</option>';

// Récupération
$resultat = $mysqli->query("SELECT m.id, ordre, cle, m.nom, colles, cdt, docs, notes, copies, dureecolle, colles_protection, cdt_protection, docs_protection, COUNT(u.id) AS nbeleves
                            FROM matieres AS m LEFT JOIN utilisateurs AS u ON FIND_IN_SET(m.id,u.matieres) AND autorisation = 2 AND mdp > '0' GROUP BY m.id ORDER BY ordre");
$mysqli->close();
$max = $resultat->num_rows;
while ( $r = $resultat->fetch_assoc() )  {
  $id = $r['id'];
  $monte = ( $r['ordre'] == 1 ) ? ' style="display:none;"' : '';
  $descend = ( $r['ordre'] == $max ) ? ' style="display:none;"' : '';
  $selects_protection = array();
  foreach ( array('colles','cdt','docs') as $fonction )  {
    $p = $r[$fonction.'_protection'];
    if ( ( $p == 0 ) || ( $p == 32 ) )
      $selects_protection[$fonction] = str_replace("\"$p\"","\"$p\" selected",$select_protection);
    else  {
      $selects_protection[$fonction] = str_replace('"6"','"6" selected',$select_protection);
      for ( $a=1; $a<6; $a++ )
        if ( ( ($p-1)>>($a-1) & 1 ) == 0 )
          $selects_protection[$fonction] = str_replace("\"$a\"","\"$a\" selected",$selects_protection[$fonction]);
    }
  }
  $notes = str_replace( ($r['notes']<2)?'"1"':'"2"', ($r['notes']<2)?'"1" selected':'"2" selected', "\n          <option value=\"1\">Fonction activée</option>\n          <option value=\"2\">Fonction désactivée</option>");
  $copies = str_replace( ($r['copies']<2)?'"1"':'"2"', ($r['copies']<2)?'"1" selected':'"2" selected', "\n          <option value=\"1\">Fonction activée</option>\n          <option value=\"2\">Fonction désactivée</option>");
  $boutons = '';
  if ( $r['colles'] )      $boutons .= "\n    <input type=\"button\" class=\"ligne supprmultiple\" data-id=\"matieres|$id|colles\" value=\"Supprimer tous les programmes de colles\">";
  if ( $r['cdt'] )         $boutons .= "\n    <input type=\"button\" class=\"ligne supprmultiple\" data-id=\"matieres|$id|cdt\" value=\"Supprimer tout le cahier de texte\">";
  if ( $r['docs'] )        $boutons .= "\n    <input type=\"button\" class=\"ligne supprmultiple\" data-id=\"matieres|$id|docs\" value=\"Supprimer tous les répertoires et documents\">";
  if ( $r['notes'] ==1 )   $boutons .= "\n    <input type=\"button\" class=\"ligne supprmultiple\" data-id=\"matieres|$id|notes\" value=\"Supprimer toutes les notes\">";
  if ( $r['copies'] == 1 ) $boutons .= "\n    <input type=\"button\" class=\"ligne supprmultiple\" data-id=\"matieres|$id|copies\" value=\"Supprimer toutes les copies\">";
  $suppr = ( $max > 1 ) ? "\n    <a class=\"icon-supprime\" title=\"Supprimer cette matière\"></a>" : '';
  $associationeleves = 'Cette matière '.( $r['nbeleves']>0 ? "concerne ${r['nbeleves']} élèves" : 'ne concerne aucun élève').'. Ceci est modifiable sur la page de <a href="utilisateurs-matieres">gestion des associations utilisateurs-matières</a>.';
  echo <<<FIN

  <article data-id="matieres|$id">
    <a class="icon-aide" title="Aide pour l'édition de cette matière"></a>
    <a class="icon-ok noreload" title="Valider les modifications"></a>
    <a class="icon-descend"$descend title="Déplacer cette matière vers le bas"></a>
    <a class="icon-monte"$monte title="Déplacer cette matière vers le haut"></a>$suppr
    <form>
      <h3 class="edition">${r['nom']}</h3>
      <p class="ligne"><label for="nom$id">Nom complet&nbsp;: </label><input type="text" id="nom$id" name="nom" placeholder="Commence par une majuscule : Mathématiques, Physique..." value="${r['nom']}" size="50"></p>
      <p class="ligne"><label for="cle$id">Clé dans l'adresse&nbsp;: </label><input type="text" id="cle$id" name="cle" placeholder="Diminutif en minuscules : maths, phys..." value="${r['cle']}" size="30"></p>
      <p class="ligne"><label for="colles_protection$id">Accès aux programmes de colles&nbsp;: </label>
        <select name="colles_protection[]" multiple>${selects_protection['colles']}
        </select>
      </p>
      <p class="ligne"><label for="cdt_protection$id">Accès au cahier de texte&nbsp;: </label>
        <select name="cdt_protection[]" multiple>${selects_protection['cdt']}
        </select>
      </p>
      <p class="ligne"><label for="docs_protection$id">Accès aux documents&nbsp;: </label>
        <select name="docs_protection[]" multiple>${selects_protection['docs']}
        </select>
      </p>
      <p class="ligne"><label for="notes$id">Notes de colles&nbsp;: </label>
        <select name="notes">$notes
        </select>
      </p>
      <p class="ligne"><label for="dureecolle$id">Durée des colles en minutes par élève&nbsp;: </label><input type="text" id="dureecolle$id" name="dureecolle" placeholder="Valeur par défaut. Typiquement 20 ou 30." value="${r['dureecolle']}" size="3"></p>
      <p class="ligne"><label for="notes$id">Récupération de copies&nbsp;: </label>
        <select name="copies">$copies
        </select>
      </p>
      <p>$associationeleves</p>
    </form>$boutons
  </article>

FIN;
}
$resultat->free();


// Aide et formulaire d'ajout
?>

  <form id="form-ajoute" data-action="ajout-matiere">
    <h3 class="edition">Ajouter une nouvelle matière</h3>
    <div>
      <input type="input" class="ligne" name="nom" value="" size="50" placeholder="Nom pour l'affichage (Commence par une majuscule : Mathématiques, Physique...)">
      <input type="input" class="ligne" name="cle" value="" size="50" placeholder="Clé pour les adresses web (Diminutif en minuscules : maths, phys...)">
      <p class="ligne"><label for="colles_protection">Accès aux programmes de colles&nbsp;: </label>
        <select name="colles_protection[]" multiple><?php echo $select_protection; ?>

        </select>
      </p>
      <p class="ligne"><label for="cdt_protection">Accès au cahier de texte&nbsp;: </label>
        <select name="cdt_protection[]" multiple><?php echo $select_protection; ?>

        </select>
      </p>
      <p class="ligne"><label for="docs_protection">Accès aux documents&nbsp;: </label>
        <select name="docs_protection[]" multiple><?php echo $select_protection; ?>

        </select>
      </p>
      <p class="ligne"><label for="notes">Notes de colles&nbsp;: </label>
        <select name="notes">
          <option value="1">Fonction activée</option>
          <option value="2">Fonction désactivée</option>
        </select>
      </p>
      <input class="ligne" type="text" name="dureecolle" value="" size="3" placeholder="Durée des colles en minutes par élève. Typiquement 20 ou 30.">
      <p class="ligne"><label for="copies">Récupération des copies&nbsp;: </label>
        <select name="copies">
          <option value="1">Fonction activée</option>
          <option value="2">Fonction désactivée</option>
        </select>
      </p>
    </div>
  </form>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter ou de modifier les matières enregistrées.</p>
    <p>Seules les matières qui proposent un contenu (cahier de texte, programmes de colles, documents, notes) sont visibles dans le menu général. Cela est automatique.</p>
    <p>L'ordre d'apparition des matières dans le menu général est modifiable grâce aux boutons <span class="icon-monte"></span> et <span class="icon-descend"></span>.</p>
    <p>Excepté l'ordre des matières, vous ne pouvez modifier que les matières associées à votre compte. Ces associations sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
    <h4>Propriétés de chaque matière</h4>
    <p>Pour chaque matière associée à votre compte, vous pouvez modifier&nbsp;:</p>
    <ul>
      <li>le <em>nom complet</em> qui s'affiche dans le menu général et dans les titres des pages propres à la matières (cahier de texte, programmes de colles, documents, notes).</li>
      <li>la <em>clé dans l'adresse</em> qui est un mot-clé utilisé uniquement dans l'adresse des pages associées à la matière. Il vaut mieux que ce soit un mot unique, court (possiblement abrégé) et sans majuscule. Par exemple, «&nbsp;maths&nbsp;», «&nbsp;phys&nbsp;»...</li>
      <li>la <em>durée des colles</em>, qui permet de précalculer automatiquement la durée des colles déclarées. Le colleur peut systématiquement modifier la durée calculée avant de valider sa colle.</li>
    </ul>
    <h4>Gestion des accès</h4>
    <p>L'<em>accès au programmes de colles</em>, l'<em>accès au cahier de texte</em> et l'<em>accès aux documents</em> peuvent être choisis parmi trois possibilités&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: ressource accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: ressource accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte et des matières auxquelles ils sont associés (seuls les utilisateurs du type autorisé et associés à la matière peuvent accéder à la ressource). Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Fonction désactivée</em>&nbsp;: ressource complètement indisponible pour cette matière. La fonction n'apparaît plus dans le menu, y compris pour vous.</li>
    </ul>
    <p>Vous pouvez aussi activer ou désactiver les <em>notes de colles</em>.</p>
    <p>Lorsque vous désactivez une fonction, cela ne supprime pas les éléments qui auraient été déjà saisis&nbsp;: le choix est réversible.</p>
    <p>Les liens dans le menu n'existent que lorsque du contenu est présent. Ils sont visibles de tout visiteur avant identification. Ils disparaissent après identification pour les utilisateurs n'ayant pas accès aux contenus.</p>
    <h4>Suppressions massives</h4>
    <p>Pour les matières qui vous sont associées, il est possible de supprimer en un seul coup les programmes de colles, le cahier de texte, les documents ou les notes, en cliquant sur les boutons associés. Une confirmation sera demandée. Les boutons ne s'affichent pas s'il n'y a rien à supprimer.</p>
    <p>Il est possible de complètement supprimer une matière en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée). Cela entraîne&nbsp;:</p>
    <ul>
      <li>la suppression définitive des programmes de colles de cette matière</li>
      <li>la suppression définitive du cahier de texte de cette matière</li>
      <li>la suppression définitive des notes de cette matière</li>
      <li>le déplacement des pages d'informations éventuelles de cette matière vers les pages «&nbsp;générales&nbsp;», non associées à une matière</li>
      <li>le déplacement virtuel du répertoire racine de la matière et de tous les documents qu'il contient au sein du répertoire Général, non associé à une matière.</li>
    </ul>
    <h4>Associations utilisateurs-matières</h4>
    <p>Les associations utilisateurs-matières se trouvent sur une <a href="utilisateurs-matieres">page séparée</a>.</p>
  </div>

  <div id="aide-matieres">
    <h3>Aide et explications</h3>
    <h4>Édition des propriétés</h4>
    <p>Excepté l'ordre des matières, vous ne pouvez modifier que les matières associées à votre compte. Ces associations sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
    <p>Le <em>nom complet</em> est affiché dans le menu général et dans les titres des pages propres à la matières (cahier de texte, programmes de colles, documents, notes). Il doit commencer par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Mathématiques&nbsp;», «&nbsp;Sciences Physiques&nbsp;»... Il est aussi utilisé pour le répertoire situé à la racine des documents et contenant tous les documents de la matière.</p>
    <p>La <em>clé dans l'adresse</em> n'est lisible que dans les liens pointant vers les ressources associées à la matière. Par convention, on préfère un mot tout en minuscules et court. Par exemple : «&nbsp;maths&nbsp;», «&nbsp;phys&nbsp;», «&nbsp;ph-ch&nbsp;», «&nbsp;frc&nbsp;»... La clé doit obligatoirement être unique (deux matières ne peuvent pas avoir la même clé).</p>
    <p>La <em>durée des colles</em> est une valeur indicative qui permet de précalculer automatiquement la durée des colles déclarées. Le colleur peut systématiquement modifier la durée calculée avant de valider sa colle.</li>
    <h4>Gestion des accès</h4>
    <p>L'<em>accès au programmes de colles</em>, l'<em>accès au cahier de texte</em> et l'<em>accès aux documents</em> peuvent être choisis parmi trois possibilités&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: ressource accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: ressource accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte et des matières auxquelles ils sont associés (seuls les utilisateurs du type autorisé et associés à la matière peuvent accéder à la ressource). Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Fonction désactivée</em>&nbsp;: ressource complètement indisponible pour cette matière. La fonction n'apparaît plus dans le menu, y compris pour vous.</li>
    </ul>
    <p>Vous pouvez aussi activer ou désactiver les <em>notes de colles</em>.</p>
    <p>Lorsque vous désactivez une fonction, cela ne supprime pas les éléments qui auraient été déjà saisis&nbsp;: le choix est réversible.</p>
    <p>Les liens dans le menu n'existent que lorsque du contenu est présent. Ils sont visibles de tout visiteur avant identification. Ils disparaissent après identification pour les utilisateurs n'ayant pas accès aux contenus.</p>
    <h4>Suppressions massives</h4>
    <p>Pour les matières qui vous sont associées, il est possible de supprimer en un seul coup les programmes de colles, le cahier de texte, les documents ou les notes, en cliquant sur les boutons associés. Une confirmation sera demandée. Les boutons ne s'affichent pas s'il n'y a rien à supprimer.</p>
    <p>Il est possible de complètement supprimer une matière en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée). Cela entraîne&nbsp;:</p>
    <ul>
      <li>la suppression définitive des programmes de colles de cette matière</li>
      <li>la suppression définitive du cahier de texte de cette matière</li>
      <li>la suppression définitive des notes de cette matière</li>
      <li>le déplacement des pages d'informations éventuelles de cette matière vers les pages «&nbsp;générales&nbsp;», non associées à une matière</li>
      <li>le déplacement virtuel du répertoire racine de la matière et de tous les documents qu'il contient au sein du répertoire Général, non associé à une matière.</li>
    </ul>
    <h4>Associations utilisateurs-matières</h4>
    <p>Les associations utilisateurs-matières se trouvent sur une <a href="utilisateurs-matieres">page séparée</a>.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer une nouvelle matière. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Une nouvelle matière, ainsi que chaque rubrique associée, n'apparaît que si du contenu peut être affiché. Cet affichage est automatique.</p>
    <p>Le <em>nom pour l'affichage</em> sera affiché dans le menu général et dans les titres des pages propres à la matières (cahier de texte, programmes de colles, documents, notes). Il doit commencer par une majuscule. Il peut être relativement long. Par exemple&nbsp;: «&nbsp;Mathématiques&nbsp;», «&nbsp;Sciences Physiques&nbsp;»... Il est aussi utilisé pour le répertoire situé à la racine des documents et contenant tous les documents de la matière.</p>
    <p>La <em>clé pour les adresses web</em> ne sera lisible que dans les liens pointant vers les ressources associées à la matière. Par convention, on préfère un mot tout en minuscules et court. Par exemple : «&nbsp;maths&nbsp;», «&nbsp;phys&nbsp;», «&nbsp;ph-ch&nbsp;», «&nbsp;frc&nbsp;»... La clé doit obligatoirement être unique (deux matières ne peuvent pas avoir la même clé).</p>
    <p>La <em>durée des colles</em> est une valeur indicative qui permet de précalculer automatiquement la durée des colles déclarées. Le colleur peut systématiquement modifier la durée calculée avant de valider sa colle.</li>
    <h4>Gestion des accès</h4>
    <p>L'<em>accès au programmes de colles</em>, l'<em>accès au cahier de texte</em> et l'<em>accès aux documents</em> peuvent être choisis parmi trois possibilités&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: ressource accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: ressource accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte et des matières auxquelles ils sont associés (seuls les utilisateurs du type autorisé et associés à la matière peuvent accéder à la ressource). Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Fonction désactivée</em>&nbsp;: ressource complètement indisponible pour cette matière. La fonction n'apparaît plus dans le menu, y compris pour vous.</li>
    </ul>
    <p>Les liens dans le menu n'existeront que lorsque du contenu sera présent. Ils seront visibles de tout visiteur avant identification. Ils disparaitront après identification pour les utilisateurs n'ayant pas accès aux contenus.</p>
    <h4>Association aux utilisateurs</h4>
    <p>La nouvelle matière sera automatiquement associée à tous les comptes utilisateurs qui peuvent déjà avoir accès à toutes les matières (en général, les élèves). Après création, les associations avec les utilisateurs pourront être modifiées à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
  </div>

  <p id="log"></p>
  
  <script type="text/javascript">
$( function() {
  // Envoi par appui sur Entrée
  $('input,select').on('keypress',function (e) {
    if ( e.which == 13 ) {
      $(this).parent().parent().siblings('a.icon-ok').click();
      return false;
    }
  });
});
  </script>
<?php
fin(true);
?>
