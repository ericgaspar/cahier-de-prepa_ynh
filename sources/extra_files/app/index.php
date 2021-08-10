<?php
// Sécurité
define('OK',1);
// Affichage des erreurs : à commenter en production
//ini_set('display_errors',1); error_reporting(E_ALL); ini_set('display_startup_errors',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

////////////////////////////////////////////
// Validation de la requête : clé de page //
////////////////////////////////////////////

// Recherche de la page concernée, variable $page
// Si $_REQUEST['cle'] existe, on le cherche dans les pages disponibles.
// Si $_REQUEST['cle'] n'est pas trouvée, page d'accueil par défaut.
$mysqli = connectsql();
$resultat = $mysqli->query('SELECT p.id, CONCAT_WS(\'/\',m.cle,p.cle) AS cle,
                                   p.titre, p.bandeau, p.protection, p.mat, p.nom
                            FROM pages AS p LEFT JOIN matieres AS m ON p.mat = m.id
                            ORDER BY p.mat, p.ordre');
if ( !empty($_REQUEST) )  {
  while ( $r = $resultat->fetch_assoc() )
    if ( isset($_REQUEST[$r['cle']]) )  {
      $page = $r;
      break;
    }
}
// Page par défaut : la première
if ( !isset($page) )  {
  // Si pas de page : installation nécessaire
  if ( !$resultat )  {
    include('installation.php');
    exit();
  }
  $resultat->data_seek(0);
  $page = $resultat->fetch_assoc();
}
$resultat->free();

/////////////////////////////
// Vérification de l'accès //
/////////////////////////////
$edition = acces($page['protection'],$page['mat'],$page['titre'],(($page['id'] == 1)?'.':".?${page['cle']}"),$mysqli);

//////////////
//// HTML ////
//////////////
if ( $edition && $page['protection'] )
  $icone = ( $page['protection'] == 32 ) ? '<span class="icon-locktotal"></span>' : '<span class="icon-lock"></span>';
else  $icone = '';
debut($mysqli,$page['titre'].$icone,$message,$autorisation,(($page['id'] == 1)?'.':".?${page['cle']}"),$page['mat']?:false);

// MathJax désactivé par défaut
$mathjax = false;

// Agenda éventuel
if ( $page['id'] == 1 )  {
  $resultat = $mysqli->query('SELECT val FROM prefs WHERE nom=\'nb_agenda_index\' OR nom=\'protection_agenda\' ORDER BY nom');
  $r = $resultat->fetch_row();
  $n = $r[0];
  $r = $resultat->fetch_row();
  $resultat->free();
  if ( $autorisation >= $r[0] )  {
    $resultat = $mysqli->query('SELECT m.nom AS matiere, t.nom AS type, texte,
                              DATE_FORMAT(debut,\'%w%Y%m%e\') AS d, DATE_FORMAT(fin,\'%w%Y%m%e\') AS f,
                              DATE_FORMAT(debut,\'%kh%i\') AS hd, DATE_FORMAT(fin,\'%kh%i\') AS hf
                              FROM agenda AS a LEFT JOIN `agenda-types` AS t ON a.type = t.id LEFT JOIN matieres AS m ON a.matiere = m.id
                              WHERE CURDATE() <= DATE(fin) AND ADDDATE(CURDATE(),7) >= DATE(debut) ORDER BY debut,fin LIMIT '.$n);
    if ( $resultat->num_rows )  {
      echo "\n  <h2><a href=\"agenda\" title=\"Afficher l'agenda du mois\" style=\"text-decoration: none;\"><span class=\"icon-agenda\"></span></a>&nbsp;Prochains événements</h2>\n\n  <article>";
      while ( $r = $resultat->fetch_assoc() )  {
        // Événement sur un seul jour
        if ( ( $d = $r['d'] ) == ( $f = $r['f'] ) )  {
          $date = substr(ucfirst(format_date($d)),0,-5);
          if ( ( $hd = $r['hd'] ) != '0h00' )
            $date .= ( $hd == $r['hf'] ) ? ' à '.str_replace('00','',$hd) : ' de '.str_replace('00','',$hd).' à '.str_replace('00','',$r['hf']);
        }
        // Événement sur plusieurs jours
        else  {
          if ( ( $hd = $r['hd'] ) == '0h00' )
            $date = 'Du '.substr(format_date($d),0,-5).' au '.substr(format_date($f),0,-5);
          else
            $date = 'Du '.substr(format_date($d),0,-5).' à '.str_replace('00','',$r['hd']).' au '.substr(format_date($f),0,-5).' à '.str_replace('00','',$r['hf']);
        }
        $titre = ( strlen($r['matiere']) ) ? "${r['type']} en ${r['matiere']}" : $r['type'];
        echo "\n  <h4>$date&nbsp;: $titre</h4>\n";
        if ( strlen($r['texte']) )
          echo "  ${r['texte']}\n";
      }
      $resultat->free();
      echo "  </article>\n\n";
    }
  }
}

// Affichage sans édition
if ( !$edition )  {
  // Affichage des informations diffusées -- seulement les infos accessibles
  // Fonction requete_protection définie dans fonctions.php
  $resultat = $mysqli->query("SELECT IF(LENGTH(titre),CONCAT('<h3>',titre,'</h3>'),'') AS titre, texte
                              FROM infos WHERE page = ${page['id']} AND cache = 0 AND ( ".requete_protection($autorisation).')');
  if ( $resultat->num_rows )  {
    if ( strlen($page['bandeau']) )
      echo "\n  <h2>${page['bandeau']}</h2>\n";
    while ( $r = $resultat->fetch_assoc() )  {
      $mathjax = $mathjax ?: boolval(strpos($r['texte'],'$')+strpos($r['texte'],'\\'));
      echo <<<FIN

  <article>
    ${r['titre']}
${r['texte']}
  </article>

FIN;
    }
    $resultat->free();
  }
  else
    echo "  <article><h3>Cette page est actuellement vide.</h3></article>\n\n";
}

// Affichage professeur éditeur
else  {
  echo <<<FIN

  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter une nouvelle information"></a>
    <a class="icon-prefs formulaire" title="Modifier les préférences de cette page"></a>
    <a class="icon-aide" title="Aide pour les modifications de cette page"></a>
  </div>

FIN;

  // Affichage des informations diffusées
  $resultat = $mysqli->query("SELECT id, ordre, cache, titre, texte, protection FROM infos WHERE page = ${page['id']}");
  if ( $max = $resultat->num_rows )  {
    if ( strlen($page['bandeau']) )
      echo "  <h2 class=\"edition\">${page['bandeau']}&nbsp;</h2>\n";
    while ( $r = $resultat->fetch_assoc() )  {
      $mathjax = $mathjax ?: boolval(strpos($r['texte'],'$')+strpos($r['texte'],'\\'));
      $lock = $texte = '';
      if ( $r['protection'] != $page['protection'] )  {
        $p = $r['protection'];
        if ( $p == 0 ) 
          $texte = 'Cette information est visible de tous.';
        elseif ( $p == 32 )  {
          $texte = 'Cette information est invisible.';
          $lock = '<span class="icon-locktotal" title="Information invisible"></span>';
        }
        else  {
          $texte = array('invités','élèves','colleurs','lycée','professeurs');
        for ( $a=0; $a<5; $a++ )
          if ( ( ($p-1)>>$a & 1 ) == 1 )
            unset($texte[$a]);
          $texte = 'Cette information est visible des comptes '.implode(', ',$texte).'.';
          $lock = '<span class="icon-lock" title="La protection de cette information est différente de celle de la page."></span>';
        }
        $texte = "\n<p class=\"protection\">$texte</p>";
      }
      // Icônes de modification
      if ( $r['cache'] )  {
        $classe_cache = ' class="cache"';
        $visible = '<a class="icon-montre" title="Afficher l\'information sur la partie publique"></a>
    <a class="icon-cache" style="display:none;" title="Rendre invisible l\'information sur la partie publique"></a>';
      }
      else  {
        $classe_cache = '';
        $visible = '<a class="icon-montre" style="display:none;" title="Afficher l\'information sur la partie publique"></a>
    <a class="icon-cache" title="Rendre invisible l\'information sur la partie publique"></a>';
      }
      $monte = ( $r['ordre'] == 1 ) ? ' style="display:none;"' : '';
      $descend = ( $r['ordre'] == $max ) ? ' style="display:none;"' : '';
      // Affichage
      echo <<<FIN

  <article$classe_cache data-id="infos|${r['id']}">
    $lock<h3 class="edition editable" data-id="infos|titre|${r['id']}" placeholder="Titre de l'information (non obligatoire)">${r['titre']}</h3>
    <a class="icon-aide" title="Aide pour l'édition de cette information"></a>
    <a class="icon-lock" title="Modifier la protection de l'information" data-val="${r['protection']}"></a> 
    $visible
    <a class="icon-monte"$monte title="Déplacer cette information vers le haut"></a>
    <a class="icon-descend"$descend title="Déplacer cette information vers le bas"></a>
    <a class="icon-supprime" title="Supprimer cette information"></a>
    <div class="editable edithtml majpubli" data-id="infos|texte|${r['id']}" placeholder="Texte de l'information (obligatoire)">
${r['texte']}
    </div>$texte
  </article>

FIN;
    }
    $resultat->free();
  }
  else
    echo "  <article><h2>Cette page est actuellement vide.</h2></article>\n\n";

  // Options du select multiple d'accès
  $select_protection = '
          <option value="0">Accès public</option>
          <option value="6">Utilisateurs identifiés</option>
          <option value="1">Invités</option>
          <option value="2">Élèves</option>
          <option value="3">Colleurs</option>
          <option value="4">Lycée</option>
          <option value="5">Professeurs</option>
          <option value="32">Information invisible</option>';
  // Protection des nouvelles informations = protection globale de la page
  $p = $page['protection'];
  if ( ( $p == 0 ) || ( $p == 32 ) )
    $sel_protection = str_replace("\"$p\"","\"$p\" selected",$select_protection);
  else  {
    $sel_protection = str_replace('"6"','"6" selected',$select_protection);
    for ( $a=1; $a<6; $a++ )
      if ( ( ($p-1)>>($a-1) & 1 ) == 0 )
        $sel_protection = str_replace("\"$a\"","\"$a\" selected",$sel_protection);
  }
  // Aide et formulaires d'ajout d'information, de préférences de page
?>

  <form id="form-ajoute" data-action="ajout-info">
    <h3 class="edition">Ajouter une nouvelle information</h3>
    <div>
      <input class="ligne" type="text" name="titre" size=50 placeholder="Titre de l'information (non obligatoire)">
      <textarea name="texte" class="edithtml ligne" rows="10" cols="100" placeholder="Texte de l'information (obligatoire)"></textarea>
      <p class="ligne"><label for="cache">Ne pas diffuser sur la partie publique&nbsp;: </label><input type="checkbox" id="cache" name="cache" value="1"></p>
    </div>
    <p class="ligne"><label for="protection">Accès&nbsp;: </label>
      <select name="protection[]" multiple><?php echo $sel_protection; ?>
      </select>
    </p>
    <input type="hidden" name="page" value="<?php echo $page['id']; ?>">
  </form>

  <form id="form-prefs" data-action="pages">
    <h3 class="edition">Modifier les préférences de la page</h3>
    <p class="ligne"><label for="titre">Titre&nbsp;: </label><input type="text" name="titre" value="<?php echo $page['titre']; ?>" size="80" placeholder="Titre de la page (obligatoire)"></p>
    <p class="ligne"><label for="nom">Nom dans le menu&nbsp;: </label><input type="text" name="nom" value="<?php echo $page['nom']; ?>" size="50" placeholder="Nom de la page dans le menu (obligatoire)"></p>
    <p class="ligne"><label for="cle">Clé dans l'adresse&nbsp;: </label><input type="text" name="cle" value="<?php echo ($p = strpos($page['cle'],'/'))?substr($page['cle'],$p+1):$page['cle']; ?>" size="30" placeholder="Mot-clé sans majuscule, sans accent, sans espace (obligatoire)"></p>
    <p class="ligne"><label for="protection">Accès&nbsp;: </label>
      <select name="protection[]" multiple><?php echo str_replace('Information','Page',$sel_protection); ?>
      </select>
    </p>
    <p class="ligne"><label for="propagation">Propager ce choix d'accès à chaque information de la page&nbsp;: </label><input type="checkbox" id="propagation" name="propagation" value="1"></p>
    <p class="ligne"><label for="bandeau">Texte de début&nbsp;:</label></p>
    <textarea name="bandeau" rows="2" cols="100" placeholder="Texte apparaissant au début de la page (non obligatoire)"><?php echo $page['bandeau']; ?></textarea>
    <input type="hidden" name="id" value="<?php echo $page['id']; ?>">
  </form>
  
  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter une information, de modifier les informations existantes ou de modifier les préférences de la page.</p>
    <p>Pour déplacer cette page d'information, la supprimer, la vider de son contenu ou en créer d'autres, il faut aller à la <a href="pages">gestion des pages</a>.</p>
    <p>Les informations dans chaque zone indiquée par des pointillés sont modifiables individuellement, en cliquant sur les boutons <span class="icon-edite"></span> et en validant avec le bouton <span class="icon-ok"></span> qui apparaît alors.</p>
    <p>Les deux boutons généraux permettent de&nbsp;:</p>
    <ul>
      <li><span class="icon-ajoute"></span>&nbsp;: ouvrir un formulaire pour ajouter une nouvelle information.</li>
      <li><span class="icon-prefs"></span>&nbsp;: ouvrir un formulaire pour modifier les préférences de la page pour modifier le titre, le nom dans le menu, l'accès à la page.</li>
    </ul>
    <h4>Création d'une autre page</h4>
    <p>Il est tout à fait possible d'avoir d'autres pages que celle-ci, y compris une page spécifique à une matière. Vous pouvez pour cela aller à la <a href="pages">gestion des pages</a>.</p>
    <h4>Gestion de l'accès</h4>
    <p>L'accès à chaque page et chaque information peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: page accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: page accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Pour les pages associées à une matière, seuls les utilisateurs associés à cette matière les voient affichées dans le menu et peuvent y accéder. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</li>
      <li><em>Page invisible</em>&nbsp;: page entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont la page peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché dans le titre de la page.</li>
    </ul>
    <p>Le lien de la page dans le menu est visible avant identification. Il disparaît après identification pour les utilisateurs n'ayant pas accès à la page.</p>
    <h4>Protection des informations</h4>
    <p>Chaque information peut être aussi protégée indépendamment de la page (les possibilités sont identiques à celles citées ci-dessus). L'information dont l'accès est différent de celui de la page ne sera simplement pas affichée pour les utilisateurs non autorisés. Elle est alors affichée ici avec un cadenas <span class="icon-lock"></span> devant son titre.</p>
  </div>

  <div id="aide-infos">
    <h3>Aide et explications</h3>
    <p>Le titre et le texte de chaque information sont modifiables séparément, en cliquant sur le bouton <span class="icon-edite"></span> correspondant. Chaque contenu éditable est indiqué par une bordure en pointillés.</p>
    <p>Une fois le texte édité, il est validé en appuyant sur Entrée dans le cas des titres ou sur le bouton <span class="icon-ok"></span>. Au contraire, un clic sur le bouton <span class="icon-annule"></span> annule l'édition.</p>
    <p>Le titre d'une information peut rester vide.</p>
    <p>Le texte doit être formaté en HTML&nbsp;: par exemple, chaque bloc de texte doit être encadré par &lt;p&gt; et &lt;/p&gt;. Il peut donc contenir des liens vers les autres pages du site, vers des documents du site, vers le web... Des boutons sont fournis pour aider au formattage (ainsi qu'une aide).</p>
    <p>D'autres modifications sont possibles à l'aide des boutons disponibles pour chaque information&nbsp;:</p>
    <ul>
      <li><span class="icon-supprime"></span>&nbsp;: supprimer l'information (une confirmation sera demandée)</li>
      <li><span class="icon-monte"></span>&nbsp;: remonter l'information d'un cran</li>
      <li><span class="icon-descend"></span>&nbsp;: descendre l'information d'un cran</li>
      <li><span class="icon-cache"></span>&nbsp;: rendre l'information non visible sur la partie publique. Cela peut être utile pour une information qui n'est plus valable mais que l'on souhaite réutiliser, ou pour une information rédigée en avance dans le but d'une publication ultérieure.</li>
      <li><span class="icon-montre"></span>&nbsp;: afficher l'information sur la partie publique</li>
      <li><span class="icon-lock"></span>&nbsp;: gérer l'accès de l'information</li>
    </ul>
    <h4>Gestion des accès</h4>
    <p>L'accès à chaque information peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: information accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: information accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte.</li>
      <li><em>Information invisible</em>&nbsp;: information entièrement invisible pour les utilisateurs autres que les professeurs.</li>
    </ul>
    <p>Par défaut, l'accès de la page, réglable dans les préférences de la page par le bouton <span class="icon-prefs"></span>, est appliqué.</p>
    <p>Si l'accès choisi pour une information est différent de celui de la page, l'information ne sera simplement pas affichée pour les utilisateurs non autorisés et affichée ici avec un cadenas <span class="icon-lock"></span> devant son titre. Le détail est affiché en gris sous le texte de l'information.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer une nouvelle information. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Le <em>titre</em> sera affiché, un peu plus gros, au-dessus de l'information. Ce doit être un simple texte. Il peut rester vide (il n'y aura alors pas de titre affiché).</p>
    <p>Le <em>texte</em> doit être formaté en HTML. Par exemple, chaque bloc de texte doit être encadré par &lt;p&gt; et &lt;/p&gt; (paragraphe). Il peut donc contenir des liens vers les autres pages du site, vers des documents du site, vers le web... Des boutons sont fournis pour aider au formattage.</p>
    <p>La case à cocher <em>Ne pas diffuser sur la partie publique</em> permet de cacher temporairement cette information, par exemple pour la diffuser ultérieurement. Si la case est cochée, l'information ne sera pas diffusée et pourra être affichée à l'aide du bouton <span class="icon-montre"></span>. Si la case est décochée, l'information sera immédiatement visible et pourra être rendue invisible à l'aide du bouton <span class="icon-cache"></span>.</p>
    <p>L'accès est par défaut celui de la page, mais peut être modifié. Si l'accès choisi pour une information est différent de celui de la page, l'information ne sera simplement pas affichée pour les utilisateurs non autorisés et affichée ici avec un cadenas <span class="icon-lock"></span> devant son titre.</p>
    <p>L'information sera affichée en haut de page, mais pourra être déplacée ultérieurement à l'aide des boutons <span class="icon-descend"></span> et <span class="icon-monte"></span>.</p>
  </div>

  <div id="aide-prefs">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier les préférences de cette page d'informations. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>. On peut y modifier&nbsp;:</p>
    <ul>
      <li>le <em>titre</em> qui sera affiché en haut de page et dans la barre de titre du navigateur. Par exemple, «&nbsp;À propos de l'ADS et du TIPE&nbsp;».</li>
      <li>le <em>nom dans le menu</em> qui est affiché dans le menu en tant que lien vers la page. Il est préférable qu'il rentre sur une ligne, il faut donc le choisir assez court. Par exemple, «&nbsp;Informations ADS/TIPE&nbsp;».</li>
      <li>la <em>clé dans l'adresse</em> qui est un mot-clé qui est utilisé uniquement dans l'adresse de la page. Par convention, il vaut mieux que ce soit un mot unique, court et sans majuscule. Par exemple, «&nbsp;ads-tipe&nbsp;». La clé doit obligatoirement être unique (deux pages ne peuvent pas avoir la même clé).</li>
      <li>le <em>texte de début</em>, qui sera affiché au-dessus des informations de la page. Il s'agit d'une ou deux phrases maximum. Il n'est affiché que si la page contient des informations. Cette case peut être laissée vide.</li>
      <li>l'<em>accès</em> à la page (voir ci-dessous).</li>
    </ul>
    <p>La case à cocher <em>Propager l'accès à chaque information</em> permet de modifier éventuellement l'accès individuel de chaque information. Les informations dont l'accès est différent de celui de la page ne sont simplement pas affichées pour les utilisateurs non autorisés et affichées ici avec un cadenas <span class="icon-lock"></span> devant leur titre.</p>
    <h4>Gestion des accès</h4>
    <p>L'accès à chaque page et chaque information peut être protégé. Trois choix sont possibles&nbsp;:</p>
    <ul>
      <li><em>Accès public</em>&nbsp;: page accessible de tout visiteur, sans identification.</li>
      <li><em>Utilisateurs identifiés</em>&nbsp;: page accessible uniquement par les utilisateurs identifiés, en fonction de leur type de compte. Un cadenas <span class="icon-lock"></span> est alors affiché dans le titre de la page. Pour les pages associées à une matière, seuls les utilisateurs associés à cette matière les voient affichées dans le menu et peuvent y accéder.</li>
      <li><em>Page invisible</em>&nbsp;: page entièrement invisible pour les utilisateurs autres que les professeurs (éventuellement associés à la matière dont la page peut dépendre). Un cadenas <span class="icon-locktotal"></span> est alors affiché dans le titre de la page.</li>
    </ul>
    <p>Le lien de la page dans le menu est visible avant identification. Il disparaît après identification pour les utilisateurs n'ayant pas accès à la page.</p>
    <h4>Autres modifications</h4>
    <p>Il est aussi possible de modifier la matière associée à la page à la <a href="pages">gestion des pages</a>. Une page sans matière apparaît en haut du menu, une page avec matière associée apparaît dans le menu au niveau de la matière. Elle n'est alors visible que par les utilisateurs associés à la matière et modifiable que par les professeurs associés à la matière. Les associations entre utilisateurs et matières sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
  </div>

  <p id="log"></p>
<?php
}

$mysqli->close();
fin($edition,$mathjax);
?>
