<?php
// Sécurité
define('OK',1);
// Configuration
include('config.php');
// Fonctions
include('fonctions.php');

// Comptes en attente de validation de l'équipe pédagogique :
// * en début de mdp (donc 41 caractères)
// Comptes en attente de réponse de l'utilisateur :
// ? en début de mdp (non défini, donc 1 caractère)
// Comptes désactivés :
// ! en début de mdp (donc 41 caractères)
// Comptes actifs :
// mdp valant un sha1 (donc "mdp > '0'" dans les where)

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
  $titre = 'Modification des associations utilisateurs-matières';
  $actuel = 'utilisateurs-matieres';
  include('login.php');
}

//////////////
//// HTML ////
//////////////
debut($mysqli,'Modification des associations utilisateurs-matières',$message,5,'utilisateurs-matieres');
?>

  <div id="icones">
    <a class="icon-aide" title="Aide pour les modifications des associations utilisateurs-matières"></a>
  </div>

  <article>
    <h3>Liste des utilisateurs</h3>
    <table id="umats" class="utilisateurs">
      <thead>
        <tr>
          <th></th>
<?php
// Récupération des matières
$resultat = $mysqli->query('SELECT id, nom FROM matieres ORDER BY ordre');
$matieres = array();
$iconesmultiples = '';
while ( $r = $resultat->fetch_assoc() )  {
  $matieres[$r['id']] = 0;
  echo "          <th class=\"vertical\"><span id=\"m${r['id']}\">${r['nom']}</span></th>\n";
  $iconesmultiples .= "\n          <th class=\"icone\"><span class=\"icon-ok\" data-id=\"${r['id']}\"></span></th>";
}
$resultat->free();
echo "          <th></th>\n        </tr>\n      </thead>\n      <tbody>\n";
$iconesmultiples .= "\n          <th class=\"icone\"><span class=\"icon-cocher\"></span></th>";

// Nombre de colonnes du tableau
$nc = count($matieres)+2;

// Variables utilisées pour tout le tableau
$autorisations = array(5=>'Professeur',4=>'Lycée',3=>'Colleur',2=>'Élève',1=>'Invité');
$requete = 'SELECT id, IF(LENGTH(nom),CONCAT(nom,\' \',prenom),CONCAT(\'<em>\',login,\'</em>\')) AS nomprenom, autorisation, SUBSTR(matieres,3) AS matieres FROM utilisateurs WHERE XXX ORDER BY autorisation DESC, nom, prenom, login';

// Fonction de remplissage des lignes
function ligne($r,$matieres,$autorisations = false)  {
  if ( $autorisations )
    $r['nomprenom'] .= " (${autorisations[$r['autorisation']]})";
  echo "        <tr data-id=\"${r['id']}\">\n          <td>${r['nomprenom']}</td>\n          ";
  foreach ( explode(',',$r['matieres']) as $mid )
    $matieres[$mid] = 1;
  foreach ( $matieres as $mid => $ok )
    echo "<td class=\"icone\">$mid|$ok</td>";
  echo "<td class=\"icone\"><input type=\"checkbox\"></td>\n        </tr>\n";
}

// Récupération des demandes à valider
$resultat = $mysqli->query(str_replace('XXX','mdp LIKE \'*%\'',$requete));
if ( $n = $resultat->num_rows )  {
  echo "        <tr class=\"categorie\">\n          <th>Demandes en attente de validation ($n)</th>$iconesmultiples\n        </tr>\n";
  while ( $r = $resultat->fetch_assoc() )
    ligne($r,$matieres,$autorisations);
  $resultat->free();
}

// Récupération des invitations non répondues
$resultat = $mysqli->query(str_replace('XXX','mdp = \'?\'',$requete));
if ( $n = $resultat->num_rows )  {
  echo "        <tr class=\"categorie\">\n          <th>Invitations envoyées en attente de réponse ($n)</th>$iconesmultiples\n        </tr>\n";
  while ( $r = $resultat->fetch_assoc() )
    ligne($r,$matieres,$autorisations);
  $resultat->free();
}

// Décompte total des utilisateurs (comptes validés et non désactivés) en fonction de leur type
foreach ( $autorisations as $a => $auto)  {
  $resultat = $mysqli->query(str_replace('XXX',"mdp > '0' AND autorisation=$a",$requete));
  $s = ( $a == 4 ) ? '' : 's';
  if ( $n = $resultat->num_rows )  {
    echo "        <tr class=\"categorie\">\n          <th>{$auto}$s ($n)</th>$iconesmultiples\n        </tr>\n";
    while ( $r = $resultat->fetch_assoc() )
      ligne($r,$matieres);
    $resultat->free();
  }
}

// Récupération des comptes désactivés
$resultat = $mysqli->query(str_replace('XXX','mdp LIKE \'!%\'',$requete));

if ( $n = $resultat->num_rows )  {
  echo "        <tr class=\"categorie\">\n          <th>Comptes désactivés ($n)</th>$iconesmultiples\n        </tr>\n";
  while ( $r = $resultat->fetch_assoc() )
    ligne($r,$matieres,$autorisations);
  $resultat->free();
}
?>
      </tbody>
    </table>
  </article>

<?php


// Aide et formulaire d'ajout
?>
  
  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici de modifier les associations entre utilisateurs et matières.</p>
    <p>Chaque bouton <span class="icon-ok"></span> ou <span class="icon-nok"></span> à l'intersection d'un utilisateur et d'une matière permet de modifier immédiatement en cliquant l'association concernée.</p>
    <p>Attention, cette modification est immédiate&nbsp;: si vous supprimez une association, entre un élève et une matière, les notes qu'il pourrait avoir eues. Ajouter à nouveau cette association (en cliquant à nouveau dans la même case) ne permet pas de récupérer des notes perdues.</p>
    <p>Il est possible de traiter simultanément plusieurs utilisateurs en cochant les cases en bout de ligne et en cliquant sur les boutons d'action qui apparaissent alors sur les lignes d'entêtes. Seuls des comptes de même types peuvent être traités simultanément. Les boutons <span class="icon-cocher"></span> permettent de cocher l'ensemble des comptes d'un type donné.</p>
  </div>

  <p id="log"></p>
<?php
fin(true);
?>
