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
  $titre = 'Modification du planning';
  $actuel = 'planning';
  include('login.php');
}

//////////////
//// HTML ////
//////////////
debut($mysqli,'Modification des pages',$message,5,'pages');
echo <<<FIN

  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter une nouvelle page"></a>
    <a class="icon-aide" title="Aide pour les modifications des pages"></a>
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
          <option value="32">Page invisible</option>';


// Récupération des matières
$select_matieres = '<option value="0">Pas de matière associée</option>';
$resultat = $mysqli->query("SELECT id, nom FROM matieres WHERE FIND_IN_SET(id,'${_SESSION['matieres']}') ORDER BY ordre");
while ( $r = $resultat->fetch_assoc() )
  $select_matieres .= "<option value=\"${r['id']}\">${r['nom']}</option>";
$resultat->free();

// Récupération des pages
$resultat = $mysqli->query("SELECT IFNULL(m.id,0) AS id, m.nom, MAX(p.ordre) AS max
                            FROM pages AS p LEFT JOIN matieres AS m ON p.mat = m.id
                            WHERE FIND_IN_SET(p.mat,'${_SESSION['matieres']}') GROUP BY m.id ORDER BY m.ordre");
while ( $m = $resultat->fetch_assoc() )  {
  if ( $m['id'] )
    echo "\n  <h3>${m['nom']}</h3>\n";
  $resultat1 = $mysqli->query("SELECT p.id, p.ordre, p.cle, p.nom, p.mat, p.titre, p.bandeau, p.protection, COUNT(i.id) AS n
                              FROM pages AS p LEFT JOIN infos AS i ON i.page = p.id WHERE p.mat = ${m['id']} GROUP BY p.id ORDER BY p.ordre");
  while ( $r = $resultat1->fetch_assoc() )  {
    $id = $r['id'];
    $monte = ( ( $r['ordre'] == 1 ) || ( !$m['id'] && ( $r['ordre'] == 2 ) ) ) ? ' style="display:none;"' : '';
    $descend = ( ( $r['ordre'] == $m['max'] ) || ( $r['id'] == 1 ) ) ? ' style="display:none;"' : '';
    $sel_matiere = str_replace("\"${m['id']}\"","\"${m['id']}\" selected",$select_matieres);
    $suppr = ( $r['id'] > 1 ) ? "\n    <a class=\"icon-supprime\" title=\"Supprimer cette page\"></a>" : '';
    $nom = ( $m['id'] ) ? "${m['nom']}/${r['nom']}" : $r['nom'];
    // Protection
    $p = $r['protection'];
    if ( ( $p == 0 ) || ( $p == 32 ) )
      $sel_protection = str_replace("\"$p\"","\"$p\" selected",$select_protection);
    else  {
      $sel_protection = str_replace('"6"','"6" selected',$select_protection);
      for ( $a=1; $a<6; $a++ )
        if ( ( ($p-1)>>($a-1) & 1 ) == 0 )
          $sel_protection = str_replace("\"$a\"","\"$a\" selected",$sel_protection);
    }
    // Différence entre page d'accueil et les autres
    if ( $r['id'] > 1 )  {
      $suppr = "\n    <a class=\"icon-supprime\" title=\"Supprimer cette page\"></a>";
      str_replace("\"${m['id']}\"","\"${m['id']}\" selected",$select_matieres);
      $sel_matiere = <<<FIN

      <p class="ligne"><label for="matiere$id">Matière&nbsp;: </label>
        <select id="matiere$id" name="matiere">$sel_matiere</select>
      </p>
FIN;
      $span = '';
    }
    else  {
      $suppr = $sel_matiere = '';
      // Pour obtenir le bon comportement du script js lors après les montées/descentes
      $span = '<span></span>';
    }
    $supprinfos = ( $r['n'] ) ? "\n      <input type=\"button\" class=\"ligne supprmultiple\" data-id=\"pages|$id|infos\" value=\"Supprimer les ${r['n']} informations de la page\">" : '';
    $propagationdisabled = ( $r['n'] ) ? '' : ' disabled';
    echo <<<FIN

  <article data-id="pages|$id">
    <a class="icon-aide" title="Aide pour l'édition de cette page"></a>
    <a class="icon-ok" title="Valider les modifications"></a>$suppr
    <a class="icon-descend"$descend title="Déplacer cette page vers le bas"></a>
    <a class="icon-monte"$monte title="Déplacer cette page vers le haut"></a>
    <form>
      <h3 class="edition">$nom</h3>
      <p class="ligne"><label for="titre$id">Titre&nbsp;: </label><input type="text" id="titre$id" name="titre" value="${r['titre']}" size="50" placeholder="Ex: «&nbsp;Informations en [matière]&nbsp;», «&nbsp;À propos du TIPE&nbsp;»"></p>
      <p class="ligne"><label for="nom$id">Nom dans le menu&nbsp;: </label><input type="text" id="nom$id" name="nom" value="${r['nom']}" size="50" placeholder="Pas trop long. Ex: «&nbsp;Informations&nbsp;», «&nbsp;Informations TIPE&nbsp;»"></p>
      <p class="ligne"><label for="cle$id">Clé dans l'adresse&nbsp;: </label><input type="text" id="cle$id" name="cle" value="${r['cle']}" size="30" placeholder="En minuscules et sans espace. Ex: «&nbsp;infos&nbsp;», «&nbsp;tipe&nbsp;»"></p>$sel_matiere
      <p class="ligne"><label for="protection$id">Accès&nbsp;: </label>
        <select name="protection[]" multiple>$sel_protection
        </select>
      </p>
      <p class="ligne"><label for="propagation$id">Propager ce choix d'accès à chaque information de la page&nbsp;: </label><input type="checkbox" id="propagation$id" name="propagation" value="1"$propagationdisabled></p>
      <p class="ligne"><label for="bandeau$id">Texte de début&nbsp;:</label></p>
      <textarea id="bandeau$id" name="bandeau" rows="2" cols="100" placeholder="Texte qui s'affichera au début de la page">${r['bandeau']}</textarea>$supprinfos
    </form>
  </article>$span

FIN;
  }
  $resultat1->free();
}
$resultat->free();
$mysqli->close();

// Aide et formulaire d'ajout
?>

  <form id="form-ajoute" data-action="ajout-page">
    <h3 class="edition">Ajouter une nouvelle page</h3>
    <input type="text" class="ligne" name="nom" value="" size="50" placeholder="Nom pour le menu. Pas trop long. Ex: «&nbsp;Informations&nbsp;», «&nbsp;Informations TIPE&nbsp;»">
    <input type="text" class="ligne" name="cle" value="" size="30" placeholder="Clé pour l'adresse web. En minuscules et sans espace. Ex: «&nbsp;infos&nbsp;», «&nbsp;tipe&nbsp;»">
    <input type="text" class="ligne" name="titre" value="" size="50" placeholder="Titre. Ex: «&nbsp;Informations en [matière]&nbsp;», «&nbsp;À propos du TIPE&nbsp;»">
    <p class="ligne"><label for="matiere$id">Matière&nbsp;: </label>
      <select name="matiere">
        <?php echo $select_matieres; ?>
      </select>
    </p>
    <p class="ligne"><label for="protection">Accès&nbsp;: </label>
      <select name="protection[]" multiple><?php echo $select_protection; ?>
      </select>
    </p>
    <p class="ligne"><label for="bandeau">Texte de début&nbsp;:</label></p>
    <textarea name="bandeau" rows="2" cols="100" placeholder="Texte qui s'affichera au début de la page"></textarea>
  </form>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter avec le bouton <span class="icon-ajoute"></span>, de modifier et de supprimer les pages d'informations.</p>
    <p>Chaque page peut être associée à une matière ou non. Sans matière associée, elle sera affichée tout en haut du menu, directement sous les icônes. Avec une matière associée, elle sera affichée dans le menu sous le titre de la matière. Une page apparaît toujours dans le menu des utilisateurs de type professeur, pour pouvoir être éditée. Pour les visiteurs non identifiés, elle n'apparaît que si elle est contient au moins une information. Pour les utilisateurs identifiés, il faut en plus que l'accès à la page leur soit autorisé.</p>
    <p>La première page sans matière associée est la page d'accueil du Cahier de Prépa&nbsp;: il est donc impossible de la supprimer ou de la déplacer.</p>
    <p>Pour toutes les autres pages, il est possible de les supprimer en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée). Si cela est possible, les pages peuvent être déplacées les unes par rapport aux autres dans le menu, à l'aide des boutons <span class="icon-monte"></span> et <span class="icon-descend"></span>.</p>
    <p>Seules les pages sans matière associée ou dont la matière est aussi associée à votre compte sont modifiables. Vous pouvez modifier les associations des matières à votre compte à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
    <p>Le titre de la première page a un statut spécial&nbsp;: c'est le titre du Cahier de Prépa. Il est donc repris à plusieurs endroits (titre dans la barre de titre du navigateur, titre dans le flux RSS). Le <em>nom dans le menu</em> de cette page est par contre peu important car affiché uniquement lorsque la souris survole l'icône <span class="icon-accueil"></span>.</p>
  </div>
  
  <div id="aide-pages">
    <h3>Aide et explications</h3>
    <p>Pour chaque page, vous pouvez modifier&nbsp;:</p>
    <ul>
      <li>le <em>titre</em> qui sera affiché en haut de page et dans la barre de titre du navigateur. Par exemple, «&nbsp;À propos du TIPE&nbsp;».</li>
      <li>le <em>nom dans le menu</em> qui est affiché dans le menu en tant que lien vers la page. Il est préférable qu'il rentre sur une ligne, il faut donc le choisir assez court. Par exemple, «&nbsp;Informations TIPE&nbsp;».</li>
      <li>la <em>clé dans l'adresse</em> qui est un mot-clé utilisé uniquement dans l'adresse de la page. Par convention, il vaut mieux que ce soit un mot unique, court et sans majuscule. Par exemple, «&nbsp;tipe&nbsp;». La clé doit obligatoirement être unique (deux pages ne peuvent pas avoir la même clé).</li>
      <li>la <em>matière</em> associée qui conditionne la place du lien dans le menu général.</li>
      <li>l'<em>accès</em> à la page (voir ci-dessous).</li>
      <li>le <em>texte de début</em> qui sera affiché au-dessus des informations de la page. Il s'agit d'une ou deux phrases maximum. Il n'est affiché que si la page contient des informations. Cette case peut être laissée vide.</li>
    </ul>
    <p>Une fois modifié, le formulaire est à validé par un clic sur le bouton <span class="icon-ok"></span>.</p>
    <h4>Gestion des accès</h4>
    <p>L'accès à chaque page et chaque information peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: page accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: page accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Pour les pages associées à une matière, seuls les utilisateurs associés à cette matière les voient affichées dans le menu et peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Page invisible</em>&nbsp;: page entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont la page peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché dans le titre de la page.</li>
    </ul>
    <p>Le lien de la page dans le menu est visible avant identification. Il disparaît après identification pour les utilisateurs n'ayant pas accès à la page.</p>
    <h4>Protection des informations</h4>
    <p>Chaque information sur une page peut être aussi protégée indépendamment de la page qui la contient (les possibilités sont identiques à celles citées ci-dessus). L'information dont l'accès est différent de celui de la page ne sera simplement pas affichée pour les utilisateurs non autorisés.</p>
    <h4>Suppression de la page ou de ses informations</h4>
    <p>La suppression d'une page entraîne automatiquement la suppression de toutes les informations qui y étaient inscrites.</p>
    <p>Il est aussi possible de supprimer toutes les informations d'une page (sans supprimer la page elle-même) en cliquant sur le bouton correspondant. Celui-ci n'apparaît pas pour les pages vides.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer une nouvelle page d'informations. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Il est possible de définir pour cette page&nbsp;:</p>
    <ul>
      <li>le <em>titre</em> qui sera affiché en haut de page et dans la barre de titre du navigateur. Par exemple, «&nbsp;À propos du TIPE&nbsp;».</li>
      <li>le <em>nom dans le menu</em> qui est affiché dans le menu en tant que lien vers la page. Il est préférable qu'il rentre sur une ligne, il faut donc le choisir assez court. Par exemple, «&nbsp;Informations TIPE&nbsp;».</li>
      <li>la <em>clé dans l'adresse</em> qui est un mot-clé utilisé uniquement dans l'adresse de la page. Par convention, il vaut mieux que ce soit un mot unique, court et sans majuscule. Par exemple, «&nbsp;tipe&nbsp;». La clé doit obligatoirement être unique (deux pages ne peuvent pas avoir la même clé).</li>
      <li>la <em>matière</em> associée qui conditionne la place du lien dans le menu général.</li>
      <li>l'<em>accès</em> à la page (voir ci-dessous).</li>
      <li>le <em>texte de début</em> qui sera affiché au-dessus des informations de la page. Il s'agit d'une ou deux phrases maximum. Il n'est affiché que si la page contient des informations. Cette case peut être laissée vide.</li>
    </ul>
    <h4>Gestion des accès</h4>
    <p>L'accès à chaque page et chaque information peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: page accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: page accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Pour les pages associées à une matière, seuls les utilisateurs associés à cette matière les voient affichées dans le menu et peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Page invisible</em>&nbsp;: page entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont la page peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché dans le titre de la page.</li>
    </ul>
    <p>Le lien de la page dans le menu est visible avant identification. Il disparaît après identification pour les utilisateurs n'ayant pas accès à la page.</p>
    <p>La page sera automatiquement positionnée en dernière place, éventuellement au sein de la matière choisie. Il sera ensuite possible de la déplacer parmi les autres pages à l'aide des boutons <span class="icon-monte"></span> et <span class="icon-descend"></span>.</p>   
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
