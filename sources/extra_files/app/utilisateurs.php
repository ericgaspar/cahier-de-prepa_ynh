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
  $titre = 'Modification des utilisateurs';
  $actuel = 'utilisateurs';
  include('login.php');
}

//////////////////////////////////
// Exportation des notes en xls //
//////////////////////////////////
// Exportation uniquement si aucun header déjà envoyé
if ( isset($_REQUEST['xls']) && !headers_sent() )  {
  // Recherche des utilisateurs concernés : comptes validés hors comptes invités
  // et hors comptes sans nom et sans mail (identifiant seul)
  $resultat = $mysqli->query('SELECT u.nom, prenom, IF(LENGTH(mail),mail,"Pas d\'adresse") AS mail, autorisation,
                              GROUP_CONCAT(m.nom ORDER BY m.ordre SEPARATOR \', \') AS mats 
                              FROM utilisateurs AS u JOIN matieres AS m ON FIND_IN_SET(m.id,u.matieres)
                              WHERE mdp > \'0\' AND autorisation > 1 AND u.nom > \'\' OR prenom > \'\' OR mail > \'\'
                              GROUP BY u.id ORDER BY autorisation DESC, IF(LENGTH(u.nom),CONCAT(u.nom,prenom),login)');
  $mysqli->close();
  if ( $resultat->num_rows )  {
    // Fonction de saisie
    function saisie_chaine($l, $c, $v)  {
      echo pack("ssssss", 0x204, 8 + strlen($v), $l, $c, 0, strlen($v)).$v;
      return;
    }
    // Correspondance autorisation-type de compte
    $categories = array(2=>'Élève',3=>'Colleur',4=>'Lycée',5=>'Professeur');
    // Envoi des headers
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=utilisateurs.xls");
    header("Content-Transfer-Encoding: binary");
    // Début du fichier xls
    echo pack("sssss", 0x809, 6, 0, 0x10, 0);
    // Remplissage
    saisie_chaine(0, 0, 'Nom');
    saisie_chaine(0, 1, utf8_decode('Prénom'));
    saisie_chaine(0, 2, utf8_decode('Adresse électronique'));
    saisie_chaine(0, 3, utf8_decode('Catégorie'));
    saisie_chaine(0, 4, utf8_decode('Matières'));
    $i = 0;
    while ( $r = $resultat->fetch_assoc() )  {
      saisie_chaine(++$i, 0, utf8_decode($r['nom']));
      saisie_chaine($i, 1, utf8_decode($r['prenom']));
      saisie_chaine($i, 2, utf8_decode($r['mail']));
      saisie_chaine($i, 3, utf8_decode($categories[$r['autorisation']]));
      saisie_chaine($i, 4, utf8_decode($r['mats']));
    }
    // Fin du fichier xls
    echo pack("ss", 0x0A, 0x00);
    $resultat->free();
  }
  exit();
}

//////////////
//// HTML ////
//////////////
debut($mysqli,'Modification des utilisateurs',$message,5,'utilisateurs');
?>

  <div id="icones">
    <a class="icon-prefs formulaire" title="Modifier les réglages de la gestion des comptes"></a>
    <a class="icon-ajoute formulaire" title="Ajouter de nouveaux utilisateurs"></a>
    <a class="icon-download" href="?xls" title="Télécharger la liste des utilisateurs en xls"></a>
    <a class="icon-aide" title="Aide pour les modifications des utilisateurs"></a>
  </div>

  <article>
    <h3>Liste des utilisateurs</h3>
    <table id="u" class="utilisateurs">
      <tbody>
<?php
// Variables utilisées pour tout le tableau
$autorisations = array(5=>'Professeur',4=>'Lycée',3=>'Colleur',2=>'Élève',1=>'Invité');
$requete = 'SELECT id, nom, prenom, login, autorisation FROM utilisateurs WHERE XXX ORDER BY autorisation DESC, nom, prenom, login';

// Fonction d'affichage des lignes du tableau
// $r : les données utilisateurs
// $type : entier correspondant au type de compte : 1->demandes à valider,
// 2->invitations non répondues, 3->comptes classiques, 4->comptes désactivés
function ligne($r,$type)  {
  $autorisations = $GLOBALS['autorisations'];
  switch ($type)  {
    case 1:
      $login_autorisation = "<td>${r['login']}</td>\n          <td>${autorisations[$r['autorisation']]}</td>";
      $deuxiemeicone = '<a class="icon-validutilisateur" title="Valider cette demande"></a>';
      $texte = 'cette demande';
      break;
    case 2:
      $login_autorisation = "<td>${r['login']}</td>\n          <td>${autorisations[$r['autorisation']]}</td>";
      $deuxiemeicone = '<a class="icon-desactive" title="Désactiver cette invitation"></a>';
      $texte = 'cette invitation';
      break;
    case 3:
      $login_autorisation = "<td colspan=\"2\">${r['login']}</td>";
      $deuxiemeicone = '<a class="icon-desactive" title="Désactiver ce compte"></a>';
      $texte = 'ce compte';
      break;
    case 4:
      $login_autorisation = "<td>${r['login']}</td>\n          <td>${autorisations[$r['autorisation']]}</td>";
      $deuxiemeicone = '<a class="icon-active" title="Réactiver ce compte"></a>';
      $texte = 'ce compte';
  }
  echo <<<FIN
        <tr data-id="${r['id']}">
          <td>${r['nom']}</td>
          <td>${r['prenom']}</td>
          $login_autorisation
          <td class="icones">
            <a class="icon-edite" title="Éditer $texte"></a>
            $deuxiemeicone
            <a class="icon-supprutilisateur" title="Supprimer $texte"></a>
          </td>
          <td class="icones"><input type="checkbox"></td>
        </tr>

FIN;
}

// Récupération des demandes à valider
$resultat = $mysqli->query(str_replace('XXX','mdp LIKE \'*%\'',$requete));
if ( $n = $resultat->num_rows )  {
  echo  <<<FIN
        <tr class="categorie"><th colspan="6">Demandes en attente de validation ($n)</th></tr>
        <tr>
          <th>Nom</th><th>Prénom</th><th>Identifiant</th><th>Type</th>
          <th class="icones">
            <a class="icon-validutilisateur" title="Valider l'ensemble des demandes cochées"></a>
            <a class="icon-supprutilisateur" title="Supprimer l'ensemble des demandes cochées"></a>
          </th>
          <th class="icones"><a class="icon-cocher" title="Tout cocher"></a></th>
        </tr>

FIN;
  while ( $r = $resultat->fetch_assoc() )
    ligne($r,1);
  $resultat->free();
}

// Récupération des invitations non répondues
$resultat = $mysqli->query(str_replace('XXX','mdp = \'?\'',$requete));
if ( $n = $resultat->num_rows )  {
  echo <<<FIN
        <tr class="categorie"><th colspan="6">Invitations envoyées en attente de réponse ($n)</th></tr>
        <tr>
          <th>Nom</th><th>Prénom</th><th>Identifiant</th><th>Type</th>
          <th class="icones">
            <a class="icon-desactive" title="Désactiver tous les invitations cochées"></a>
            <a class="icon-supprutilisateur" title="Supprimer l'ensemble des invitations cochées"></a>
          </th>
          <th class="icones"><a class="icon-cocher" title="Tout cocher"></a></th>
        </tr>

FIN;
  while ( $r = $resultat->fetch_assoc() )
    ligne($r,2);
  $resultat->free();
}

// Décompte total des utilisateurs (comptes validés et non désactivés) en fonction de leur type
foreach ( $autorisations as $a => $auto)  {
  $resultat = $mysqli->query(str_replace('XXX',"mdp > '0' AND autorisation=$a",$requete));
  $s = ( $a == 4 ) ? '' : 's';
  if ( $n = $resultat->num_rows )  {
    echo <<<FIN
        <tr class="categorie"><th colspan="6">{$auto}$s ($n)</th></tr>
        <tr>
          <th>Nom</th><th>Prénom</th><th colspan="2">Identifiant</th>
          <th class="icones">
            <a class="icon-desactive" title="Désactiver les comptes cochés"></a>
            <a class="icon-supprutilisateur" title="Supprimer l'ensemble des comptes cochés"></a>
          </th>
          <th class="icones"><a class="icon-cocher" title="Tout cocher"></a></th>
        </tr>

FIN;
    while ( $r = $resultat->fetch_assoc() )
      ligne($r,3);
    $resultat->free();
  }
}

// Récupération des comptes désactivés
$resultat = $mysqli->query(str_replace('XXX','mdp LIKE \'!%\'',$requete));
if ( $n = $resultat->num_rows )  {
  echo <<<FIN
        <tr class="categorie"><th colspan="6">Comptes désactivés ($n)</th></tr>
        <tr>
          <th>Nom</th><th>Prénom</th><th>Identifiant</th><th>Type</th>
          <th class="icones">
            <a class="icon-active" title="Activer tous les comptes cochés"></a>
            <a class="icon-supprutilisateur" title="Supprimer toutes les comptes cochés"></a>
          </th>
          <th class="icones"><a class="icon-cocher" title="Tout cocher"></a></th>
        </tr>

FIN;
  while ( $r = $resultat->fetch_assoc() )
    ligne($r,4);
  $resultat->free();
}
?>
      </tbody>
    </table>
  </article>

<?php
// Récupération des matières
$resultat = $mysqli->query('SELECT id, nom FROM matieres ORDER BY ordre');
$select_matieres = '';
while ( $r = $resultat->fetch_assoc() )
  $select_matieres .= "          <option value=\"${r['id']}\">${r['nom']}</option>\n";
$resultat->free();

// Récupération de la préférence de création de compte
$resultat = $mysqli->query('SELECT IF(val,\' checked\',\'\') FROM prefs WHERE nom = "creation_compte"');
$creation_compte = $resultat->fetch_row()[0];
$resultat->free();
$mysqli->close();

// Aide et formulaire d'ajout
?>
  <form id="form-ajoute" data-action="ajout-utilisateurs">
    <h3 class="edition">Ajouter de nouveaux utilisateurs</h3>
    <p class="ligne"><label for="autorisation">Type de comptes&nbsp;:</label>
      <select name="autorisation">
        <option selected hidden value="0">Choisir ...</option>
        <option value="1">Invités</option>
        <option value="2">Élèves</option>
        <option value="3">Colleurs</option>
        <option value="4">Lycée</option>
        <option value="5">Professeurs</option>
      </select>
    </p>
    <p class="ligne"><label for="matieres">Matières&nbsp;:</label>
      <select multiple name="matieres[]">
<?php echo $select_matieres; ?>
      </select>
    </p>
    <p class="ligne"><label for="saisie">Type de saisie&nbsp;:</label>
      <select name="saisie">
        <option selected value="1">Invitation électronique</option>
        <option value="2">Saisie du mot de passe</option>
      </select>
    </p>
    <p class="ligne"><strong>Comptes à créer&nbsp;:</strong></p>
    <p>Écrire ci-dessous les nouveaux utilisateurs&nbsp;:</p>
    <ul>
      <li>Un utilisateur par ligne</li>
      <li>Uniquement des utilisateurs de même type associés aux mêmes matières</li>
    </ul>
    <p class="affichesiinvitation">Sur chaque ligne, vous devez écrire nom, prénom, adresse électronique (séparés par des virgules). Un courriel sera envoyé à chaque utilisateur avec un lien à validité permanente pour saisir le mot de passe. Ces comptes apparaîtront dans la catégorie «&nbsp;invitation&nbsp;» tant qu'ils n'auront pas finalisé leur inscription.</p>
    <p class="affichesimotdepasse">Sur chaque ligne, vous devez écrire nom, prénom et mot de passe (séparés par des virgules). Les utilisateurs ne seront pas prévenus automatiquement de cette création de compte, ce sera à vous de le faire. Ils pourront modifier leur mot de passe s'ils le souhaitent. Ils ne pourront envoyer des courriels que s'ils saisissent une adresse électronique.</p>
    <p class="affichesiinvite">Sur chaque ligne, vous devez écrire l'identifiant du compte et le mot de passe, séparés par une virgule. Vous pourrez ensuite communiquer ces coordonnées aux personnes concernées. Elles ne pourront pas modifier le mot de passe que vous avez choisi.</p>
    <textarea name="listeutilisateurs" rows="10" cols="100"></textarea>
  </form>

  <form id="form-prefs" data-action="prefsglobales">
    <h3 class="edition">Réglages de la gestion des comptes</h3>
    <p class="ligne"><label for="autoriser">Autoriser les demandes de création de comptes&nbsp;: </label>
      <input type="checkbox" id="autoriser" name="autoriser"<?php echo $creation_compte; ?>>
    </p>
    <input type="hidden" name="creation_compte" value="1">
    <p class="ligne">Pour modifier les associations entre utilisateurs et matières, il faut vous rendre sur la page de <a href="utilisateurs-matieres">gestion des associations utilisateurs-matières</a>.</p>
    <p class="ligne">Pour voir les adresses électroniques et modifier les réglages d'envoi de courriels, il faut vous rendre sur la page de <a href="utilisateurs-mails">gestion des courriels</a>.</p>
  </form>

  <div id="form-edite">
    <a class="icon-ok" title="Valider ces modifications"></a>
    <h3 class="edition">Modifier un utilisateur</h3>
    <form>
      <p id="compteactif">Vous pouvez ici modifier le compte XXX, de type YYY. Ce compte est actif. L'utilisateur du compte ne sera pas automatiquement prévenu de vos modifications.</p>
      <p id="comptedesactive">Vous pouvez ici modifier le compte XXX, de type YYY. Ce compte a été désactivé&nbsp;: la connexion à ce Cahier de Prépa par ce compte n'est pas possible.</p>
      <p id="demande">Vous pouvez ici modifier la demande XXX, de type YYY. Cette demande n'a pas encore été validée, vous pourrez la valider après modification.</p>
      <p id="invitation">Vous pouvez ici modifier l'invitation XXX, de type YYY. L'utilisateur de ce compte ne sera pas automatiquement prévenu de vos modifications. Attention, la modification de l'identifiant ou de l'adresse électronique rendra impossible la validation de l'invitation par l'utilisateur concerné.</p>
      <p>Seules les valeurs modifiées seront prises en compte. Pour modifier l'adresse électronique, il est nécessaire de la saisir deux fois.</p>
      <p class="ligne"><label for="prenom">Prénom&nbsp;: </label><input type="text" name="prenom" value="" size="50"></p>
      <p class="ligne"><label for="nom">Nom&nbsp;: </label><input type="text" name="nom" value="" size="50"></p>
      <p class="ligne"><label for="login">Identifiant&nbsp;: </label><input type="text" name="login" value="" size="50"></p>
      <p class="ligne"><label for="mail1">Adresse électronique&nbsp;: </label><input type="email" name="mail1" value="" size="50"></p>
      <p class="ligne"><label for="mail2">Confirmation (si modification)&nbsp;: </label><input type="email" name="mail2" value="" size="50"></p>
      <p class="ligne"><label for="mailexp">Nom affiché comme expéditateur/destinataire de courriel&nbsp;: </label><input type="text" name="mailexp" value="" size="50"></p>
      <p class="ligne"><label for="mailcopie">Recevoir une copie des courriels envoyés&nbsp;: </label><input type="checkbox" name="mailcopie" value="1"></p>
    </form>
  </div>
  
  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici d'ajouter, de modifier et de supprimer des utilisateurs pouvant se connecter à ce Cahier de Prépa.</p>
    <p>Les associations entre les utilisateurs et les matières sont à régler à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
    <p class="ligne">La modification des adresses électroniques est possible ici pour chaque utilisateur, mais il est préférable de vous rendre sur la page de <a href="utilisateurs-mails">gestion des courriels</a> si vous souhaitez les voir globalement. Vous pourrez aussi y modifier les réglages d'envoi de courriels.</p>
    <p>Les trois boutons généraux permettent de&nbsp;:</p>
    <ul>
      <li><span class="icon-ajoute"></span>&nbsp;: ouvrir un formulaire pour ajouter de nouveaux utilisateurs.</li>
      <li><span class="icon-download"></span>&nbsp;: récupérer l'ensemble des noms et adresses électroniques de tous les utilisateurs en fichier de type <code>xls</code>, éditable par un logiciel tableur (Excel, LibreOffice Calc...).</li>
      <li><span class="icon-prefs"></span>&nbsp;: ouvrir un formulaire pour autoriser ou interdire les demandes de création de compte.</li>
    </ul>
    <h4>Tableau récapitulatif</h4>
    <p>Le tableau général présente tous les utilisateurs existants, ordonnés par type puis par ordre alphabétique.</p>
    <p>Les données particulières des utilisateurs (nom, prénom, identifiant, adresse électronique, nom affiché comme expéditeur) sont modifiables en cliquant sur le bouton <span class="icon-edite"></span> qui ouvrira un formulaire.</p>
    <p>Les autres boutons du tableau permettent une action directe&nbsp;: désactiver un compte, supprimer un compte, valider une demande.</p>
    <p>Les identifiants sont modifiables ici. Ces identifiants ne servent qu'à la connexion&nbsp;: n'oubliez pas alors de prévenir l'utilisateur. Il pourra néanmoins se connecter à l'aide de son adresse électronique.</p>
    <h4>Modifications multiples</h4>
    <p>Il est possible de réaliser une modification identique sur un certain nombre de comptes en cochant les cases en bout de ligne et en cliquant sur les boutons d'action situés sur les lignes d'entêtes. Seuls des comptes de même type peuvent être traités simultanément. Les boutons <span class="icon-cocher"></span> permettent de cocher l'ensemble des comptes d'un type donné.</p>
    <h4>Suppression et désactivation</h4>
    <p>Chaque utilisateur peut être supprimé à l'aide du bouton <span class="icon-supprime"></span> (une confirmation sera demandée). Attention, lors de la suppression d'un utilisateur (élève, colleur, professeur), les colles le concernant sont automatiquement supprimées. Ne supprimez surtout pas un compte pour le recréer, modifiez-le directement.</p>
    <p>La désactivation d'un compte permet de supprimer la possibilité de l'utilisateur de se connecter tout en conservant ses données comme son adresse électronique et les colles réalisées. C'est donc l'opération à réaliser pour un élève parti en cours d'année, dont on veut conserver les notes de colles jusqu'en fin d'année.</p>
    <h4>Types d'utilisateurs</h4>
    <p>Il existe cinq types d'utilisateurs&nbsp;:</p>
    <ul>
      <li>Les <em>professeurs</em> peuvent modifier tout ce qui est réglable dans ce Cahier de Prépa&nbsp;: pages d'informations et informations générales, utilisateurs, groupes d'élèves, matières, planning annuel. Tous les professeurs ont les mêmes droits sur ces catégories (il n'y a pas d'&laquo;&nbsp;administrateur&nbsp;&raquo;). Ils peuvent être associés ou non à une ou plusieurs matières, et pouvoir alors modifier ce qui concerne spécifiquement ces matières&nbsp;: programmes de colles, cahier de texte, documents, notes de colles. Ils peuvent voir l'ensemble des notes de colles mises dans les matières associées, et les récupérer sous forme de fichier xls. </li>
      <li>Les utilisateurs liés à l'administration du <em>lycée</em> peuvent relever les notes de colles via une interface spécifique. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>colleurs</em> peuvent être associés ou non à une ou plusieurs matières (ils ne peuvent pas modifier leur liste des matières associées). Ils peuvent voir les contenus associés à ces matières et les contenus généraux, si l'accès de ces contenus est autorisé. Ils peuvent mettre des notes dans ces matières et voir leurs notes uniquement. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>élèves</em> peuvent être associés ou non à une ou plusieurs matières (ils ne peuvent pas modifier leur liste des matières associées). Ils peuvent voir les contenus associés à ces matières et les contenus généraux,  si l'accès de ces contenus est autorisé. Ils peuvent voir leurs notes de colles. Ils peuvent modifier leur identité et leur mot de passe. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>invités</em> sont des comptes prévus pour être éventuellement partagés entre plusieurs personnes. Une fois connecté, il est impossible de changer les paramètres du compte (identifiant, mot de passe, matières associées). Les invités peuvent voir les contenus associés à leurs matières et les contenus généraux, si l'accès de ces contenus est autorisé.</li>
    </ul>
    <p>Il n'est pas possible de changer le type d'un utilisateur (transformer un élève en colleur, etc.).</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de créer de nouveaux comptes utilisateurs. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Il est possible de créer simultanément autant de comptes que l'on le souhaite, mais tous les comptes créés simultanément doivent correspondre à un même type d'utilisateurs (invités, élèves, colleurs, administratifs, professeurs).</p>
    <h4>Matières</h4>
    <p>Il est nécessaire de spécifier à quelle matières seront associés les utilisateurs (les mêmes pour les comptes créés en même temps). Toutes les matières peuvent être sélectionnées. Les ressources associées aux matières non associées seront interdites sans autre condition à ces nouveaux utilisateurs. Pour les colleurs et professeurs, les actions possibles seront restreintes aux matières associées. Pour les administratifs, la relève des colles est indépendante et se fait simultanément sur toutes les matières.</p>
    <p>L'association à une matière n'est pas définitive, il est possible de modifier (ajouter ou supprimer) les associations entre matières et utilisateurs à tout moment à la <a href="utilisateurs-matieres">gestion utilisateurs-matières</a>.</p>
    <p>Il est plus facile de <a href="matieres">créer les matières</a> avant de créer les comptes utilisateurs.</p>
    <h4>Invitation ou saisie du mot de passe</h4>
    <p>Deux méthodes d'inscription sont possibles&nbsp;:</p>
    <ul>
      <li>l'envoi d'invitation&nbsp;: il faut pour cela renseigner sur chaque ligne de la case de saisie nom, prénom et adresse électronique. Les utilisateurs recevront chacun un courriel avec un lien à usage unique et validité illimitée permettant de finaliser l'inscription en saisissant un mot de passe.</li>
      <li>la saisie directe du mot de passe&nbsp;: il faut pour cela renseigner sur chaque ligne de la case de saisie nom, prénom et mot de passe. Vous devrez alors contacter et donner le mot de passe choisi à chaque nouvel utilisateur. Cette méthode est moins bonne sur le plan de la sécurité.</li>
    </ul>
    <p>Les données à saisir doivent séparées par des virgules, sans espaces, et à un compte par ligne.</p>
    <p>Les élèves peuvent aussi s'inscrire seule en faisant une demande, disponible au niveau de l'identification.</p>
    <h4>Types d'utilisateurs</h4>
    <p>Il existe cinq types d'utilisateurs&nbsp;:</p>
    <ul>
      <li>Les <em>professeurs</em> peuvent modifier tout ce qui est réglable dans ce Cahier de Prépa&nbsp;: pages d'informations et informations générales, utilisateurs, groupes d'élèves, matières, planning annuel. Tous les professeurs ont les mêmes droits sur ces catégories (il n'y a pas d'&laquo;&nbsp;administrateur&nbsp;&raquo;). Ils peuvent être associés ou non à une ou plusieurs matières, et pouvoir alors modifier ce qui concerne spécifiquement ces matières&nbsp;: programmes de colles, cahier de texte, documents, notes de colles. Ils peuvent voir l'ensemble des notes de colles mises dans les matières associées, et les récupérer sous forme de fichier xls. </li>
      <li>Les utilisateurs liés à l'administration du <em>lycée</em> peuvent relever les notes de colles via une interface spécifique. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>colleurs</em> peuvent être associés ou non à une ou plusieurs matières (ils ne peuvent pas modifier leur liste des matières associées). Ils peuvent voir les contenus associés à ces matières et les contenus généraux, si l'accès de ces contenus est autorisé. Ils peuvent mettre des notes dans ces matières et voir leurs notes uniquement. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>élèves</em> peuvent être associés ou non à une ou plusieurs matières (ils ne peuvent pas modifier leur liste des matières associées). Ils peuvent voir les contenus associés à ces matières et les contenus généraux,  si l'accès de ces contenus est autorisé. Ils peuvent voir leurs notes de colles. Ils peuvent modifier leur identité et leur mot de passe. Ils peuvent envoyer des courriels si les professeurs le décident.</li>
      <li>Les <em>invités</em> sont des comptes prévus pour être éventuellement partagés entre plusieurs personnes. Une fois connecté, il est impossible de changer les paramètres du compte (identifiant, mot de passe, matières associées). Les invités peuvent voir les contenus associés à leurs matières et les contenus généraux, si l'accès de ces contenus est autorisé.</li>
    </ul>
  </div>
  
  <div id="aide-prefs">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet d'autoriser ou d'interdire les demandes de création de compte. Ces demandes peuvent être réalisées par des utilisateurs non connectés, ayant cliqué sur le bouton <span class="icon-connexion"></span>, puis &laquo;&nbsp;Créer un compte&nbsp;&raquo;. Les demandes existantes sont placées en attente de validation sur cette page. Si tous les élèves et colleurs attendus ont leur compte, il peut être utile de décocher cette case pour éviter les demandes imprévues.</p>
    <p>Ce formulaire sera validé par un clic sur <span class="icon-ok"></span>, et abandonné par un clic sur <span class="icon-ferme"></span>.</p>
  </div>

  <p id="log"></p>
<?php
fin(true);
?>
