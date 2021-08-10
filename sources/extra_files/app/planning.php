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
debut($mysqli,'Modication du planning',$message,5,'planning');
echo <<<FIN

  <div id="icones">
    <a class="icon-ok noreload" title="Valider les modifications du planning"></a>
    <a class="icon-aide" title="Aide pour les modifications du planning"></a>
  </div>

  <article>
    <h3>Liste des semaines</h3>
    <form>
      <table id="planning">
        <thead>
          <tr>
            <th>Début de semaine</th>
            <th>Colle ou non</th>
            <th>Vacances</th>
          </tr>
        </thead>
        <tbody>

FIN;

// Récupération des vacances
$resultat = $mysqli->query('SELECT id, nom FROM vacances WHERE id > 0 ORDER BY id');
$select_vacances = '<option value="0">Période scolaire</option>';
while ( $r = $resultat->fetch_row() )
  $select_vacances .= "<option value=\"${r[0]}\">${r[1]}</option>";
$resultat->free();
// Récupération et affichage des matières
$semaine = array('Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi');
$resultat = $mysqli->query('SELECT id, DATE_FORMAT(debut,\'%w\') AS jour, DATE_FORMAT(debut,\'%d/%m/%Y\') AS debut, IF(colle=1,\' checked\',\'\') AS colle, vacances FROM semaines');
$mysqli->close();
while ( $r = $resultat->fetch_assoc() )  {
  $select = str_replace("\"${r['vacances']}\"","\"${r['vacances']}\" selected",$select_vacances);
  $r['jour'] = $semaine[$r['jour']];
  echo <<<FIN
          <tr>
            <td>${r['jour']} ${r['debut']}</td>
            <td><input type="checkbox" name="colles[${r['id']}]" value="1"${r['colle']}></td>
            <td><select name="vacances[${r['id']}]">$select</select></td>
          </tr>

FIN;
}
$resultat->free();

// Fin du formulaire
?>
        </tbody>
      </table>
    </form>
  </article>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici de modifier le planning annuel, c'est-à-dire pour chaque semaine de l'année, préciser s'il s'agit&nbsp;:
    <ul>
      <li>d'une semaine de colle (case <em>Colle ou non</em> cochée), qui pourra recevoir des programmes de colles et des notes de colles.</li>
      <li>d'une semaine sans colle (case <em>Colle ou non</em> décochée), qui ne pourra recevoir ni programmes de colles, ni notes de colles.</li>
      <li>d'une semaine de vacances (colonne <em>Vacances</em>) qui ne pourra recevoir ni cahier de texte, ni programmes de colles, ni notes de colles.</li>
    </ul>
    <p>Les vacances de deux semaines sont donc à marquer deux fois, une fois sur chaque semaine.</p>
    <p>Il est préférable de décocher la case <em>Colle ou non</em> lorsque l'on sait qu'il n'y aura pas de colle, comme souvent en début ou en fin d'année&nbsp;: cela modifie l'affichage des programmes de colles (&laquo;&nbsp;Il n'y a pas de colle cette semaine&nbsp;&raquo; au lieu de &laquo;&nbsp;Le programme de colles de cette semaine n'est pas défini.&nbsp;&raquo;), et évite les erreurs d'écriture des programmes de colles ou de saisie des notes.</p>
    <p>La validation n'est pas faite à chaque modification, mais une seule fois globalement après unn clic sur le bouton <span class="icon-ok"></span>.</p>
  </div>

  <p id="log"></p>
<?php
fin(true);
?>
