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

// Accès aux comptes administratifs connectés uniquement. Redirection pour les autres.
if ( $autorisation != 4 )  {
  header("Location: https://$domaine$chemin");
  exit();
}
$mysqli = connectsql();
// Si connexion light : on doit répéter son mot de passe pour aller plus loin
// login.php contient fin()
if ( $_SESSION['light'] )  {
  $titre = 'Relève des notes de colle';
  $actuel = 'relevenotes';
  include('login.php');
}

// Fonction pour l'affichage des durées en heures/minutes. Argument en minutes.
function format_duree($duree)  {
  if ( $duree == 0 )
    return '-';
  if ( $duree >= 60 )
    return intdiv($duree,60).'h'.( $duree%60 ?: '');
  return ($duree%60).'m';
}

///////////////////////////////////////////
// Exportation en xls ou pour impression //
///////////////////////////////////////////
// Trois types d'exportation possible :
// * decompte -> liste des colleurs/heures déclarées, en xls
// * notes -> liste des notes, en xls
// * impression -> affichage des deux listes en html, pour l'impression
// Exportation uniquement si aucun header déjà envoyé
if ( isset($_REQUEST['datereleve']) && ctype_digit($date = $_REQUEST['datereleve']) && !headers_sent() )  {
  // Récupération du titre du Cahier
  $resultat = $mysqli->query('SELECT titre FROM pages WHERE id = 1');
  $r = $resultat->fetch_row();
  $resultat->free();
  $titre = $r[0];
  // Récupération de la bonne date
  $date = preg_replace('/(\d{2})(\d{2})(\d{2})/','20$1-$2-$3',$date);
  // Requêtes possibles
  $requete_decompte = "SELECT IF(LENGTH(c.nom),CONCAT(c.nom,' ',c.prenom),c.login) AS colleur, m.nom AS matiere,
                              SUM(nb) AS nb, SUM(duree*(description>'')) AS duree_td, SUM(duree) AS duree
                      FROM heurescolles AS h LEFT JOIN utilisateurs AS c ON colleur = c.id LEFT JOIN matieres AS m ON matiere = m.id
                      LEFT JOIN (SELECT COUNT(*) AS nb, heure FROM notes GROUP BY heure) AS n ON h.id = n.heure
                      WHERE h.releve = '$date' GROUP BY h.colleur ORDER BY c.nom, m.ordre";
  $requete_notes =    "SELECT h.id, IF(LENGTH(c.nom),CONCAT(LEFT(prenom,1),'. ',c.nom),login) AS colleur, eleve,
                              m.nom AS matiere, DATE_FORMAT(jour,'%d/%m/%y') AS jour, TIME_FORMAT(h.heure,'%kh%i') AS heure, duree,
                              note, description, duree
                      FROM heurescolles AS h JOIN utilisateurs AS c ON colleur = c.id JOIN matieres AS m ON matiere = m.id
                      LEFT JOIN ( SELECT heure,note,IF(LENGTH(nom),CONCAT(nom,' ',prenom),login) AS eleve 
                                  FROM notes JOIN utilisateurs AS e ON eleve = e.id ) AS n ON n.heure = h.id
                      WHERE releve = '$date' ORDER BY c.nom, h.jour, h.heure, eleve";                      
  // Fonctions de saisie xls
  function saisie_nombre($l, $c, $v)  {
    echo pack("sssss", 0x203, 14, $l, $c, 0).pack("d", $v);
  }
  function saisie_chaine($l, $c, $v)  {
    echo pack("ssssss", 0x204, 8 + strlen($v), $l, $c, 0, strlen($v)).$v;
  }
  // Exportation
  switch ( $_REQUEST['export'] )  {
    case 'decompte':  {
      $resultat = $mysqli->query($requete_decompte);
      $mysqli->close();
      if ( $resultat->num_rows )  {
        // Envoi des headers
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=decompte-$date.xls");
        header("Content-Transfer-Encoding: binary");
        // Début du fichier xls
        echo pack("sssss", 0x809, 6, 0, 0x10, 0);
        // Remplissage
        saisie_chaine(0, 0, utf8_decode('Relevé des heures de colles du '.preg_replace('/(\d{4})-(\d{2})-(\d{2})/','$3/$2/$1',$date).' - '.$titre));
        saisie_chaine(2, 0, 'Colleur');
        saisie_chaine(2, 1, utf8_decode('Matière'));
        saisie_chaine(2, 2, utf8_decode('Nombre d\'élèves'));
        saisie_chaine(2, 3, utf8_decode('Séances sans note'));
        saisie_chaine(2, 4, utf8_decode('Durée totale déclarée'));
        $i = 2;
        $total_n = $total_duree = $total_duree_td = 0;
        while ( $r = $resultat->fetch_assoc() )  {
          saisie_chaine(++$i, 0, utf8_decode($r['colleur']));
          saisie_chaine($i, 1, utf8_decode($r['matiere']));
          saisie_nombre($i, 2, $r['nb']);
          saisie_chaine($i, 3, format_duree($r['duree_td']));
          saisie_chaine($i, 4, format_duree($r['duree']));
          $total_n += $r['nb'];
          $total_duree_td += $r['duree_td'];
          $total_duree += $r['duree'];
        }
        // Totaux
        saisie_chaine($i = $i+2, 0, 'Total');
        saisie_nombre($i, 2, $total_n);
        saisie_chaine($i, 3, format_duree($total_duree_td));
        saisie_chaine($i, 4, format_duree($total_duree));
        // Fin du fichier xls
        echo pack("ss", 0x0A, 0x00);
        $resultat->free();
      }
      exit();
    }
    case 'notes':  {
      $resultat = $mysqli->query($requete_notes);
      $mysqli->close();
      if ( $resultat->num_rows )  {
        // Envoi des headers
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=decompte-$date.xls");
        header("Content-Transfer-Encoding: binary");
        // Début du fichier xls
        echo pack("sssss", 0x809, 6, 0, 0x10, 0);
        // Remplissage
        saisie_chaine(0, 0, utf8_decode('Détail des notes de colles du '.preg_replace('/(\d{4})-(\d{2})-(\d{2})/','$3/$2/$1',$date).' - '.$titre));
        saisie_chaine(2, 0, 'Colleur');
        saisie_chaine(2, 1, utf8_decode('Matière'));
        saisie_chaine(2, 2, utf8_decode('Élève/Description'));
        saisie_chaine(2, 3, utf8_decode('Note'));
        saisie_chaine(2, 4, utf8_decode('Date'));
        saisie_chaine(2, 5, utf8_decode('Heure'));
        saisie_chaine(2, 6, utf8_decode('Nb d\'élèves'));
        saisie_chaine(2, 7, utf8_decode('Durée déclarée'));
        $i = 2;
        $n = $hid = 0;
        while ( $r = $resultat->fetch_assoc() )  {
          saisie_chaine(++$i, 0, utf8_decode($r['colleur']));
          saisie_chaine($i, 1, utf8_decode($r['matiere']));
          if ( strlen($r['eleve']) )  {
            saisie_chaine($i, 2, utf8_decode($r['eleve']));
            saisie_chaine($i, 3, utf8_decode($r['note']));
          }
          else 
            saisie_chaine($i, 2, utf8_decode($r['description']));
          if ( $hid != $r['id'] )  {
            saisie_chaine($i, 4, utf8_decode($r['jour']));
            saisie_chaine($i, 5, ( $r['heure'] == '0h00' ) ? '-' : utf8_decode($r['heure']));
            saisie_chaine($i, 7, format_duree($r['duree']));
            if ( $n )
              saisie_nombre($i-$n, 6, $n);
            $hid = $r['id'];
            $n = 0;
          }
          $n = $n+empty($r['description']);
        }
        if ( $n )
          saisie_nombre($i-$n+1, 6, $n);
        // Fin du fichier xls
        echo pack("ss", 0x0A, 0x00);
        $resultat->free();
      }
      exit();
    }
    case 'impression':  {
      $date = preg_replace('/(\d{4})-(\d{2})-(\d{2})/','$3/$2/$1',$date);
      echo <<<FIN
<!doctype html>
<html lang="fr">
<head>
  <title>Impression</title>
  <meta charset="utf-8">
  <link rel="stylesheet" href="css/style1810.min.css">
</head>
<body>
<header>
  <h1>Relevé des colles - $date - $titre</h1>
</header>

FIN;
      // Décompte des heures
      $resultat = $mysqli->query($requete_decompte);
      if ( $resultat->num_rows )  {
        echo "\n  <article>\n    <h3>Relevé des heures, par colleur</h3>\n";
        echo <<<FIN
    <table>
      <tbody>
        <tr><th>Colleur</th><th>Matière</th><th>Nombre d'élèves</th><th>Séances sans note</th><th>Durée totale déclarée</th></tr>
FIN;
        // Remplissage
        $total_n = $total_duree = $total_duree_td = 0;
        while ( $r = $resultat->fetch_assoc() )  {
          echo "\n        <tr><th>${r['colleur']}</th><td>${r['matiere']}</td><td>${r['nb']}</td><td>".format_duree($r['duree_td']).'</td><td>'.format_duree($r['duree']).'</td></tr>';
          $total_n += $r['nb'];
          $total_duree_td += $r['duree_td'];
          $total_duree += $r['duree'];
        }
        $resultat->free();
        echo "\n        <tr><th>Total</th><td></td><td>$total_n</td><td>".format_duree($total_duree_td).'</td><td>'.format_duree($total_duree)."</td></tr>\n      </tbody>\n    </table>\n  </article>\n\n";
      }
      
      // Détail des notes
      $resultat = $mysqli->query($requete_notes);
      if ( $resultat->num_rows )  {
        echo "\n  <article>\n    <h3>Détail des notes</h3>\n";
        echo <<<FIN
    <table>
      <tbody>
        <tr><th>Colleur</th><th>Matière</th><th colspan="2">Élève et note / Description</th><th>Date</th><th>Heure</th><th>Durée déclarée pour l'heure</th></tr>
FIN;
        // Remplissage
        while ( $r = $resultat->fetch_assoc() )  {
          $description = ( strlen($r['description']) ? "<td colspan=\"2\">${r['description']}</td>" : "<td>${r['eleve']}</td><td>${r['note']}</td>" );
          echo "\n        <tr><th>${r['colleur']}</th><td>${r['matiere']}</td>$description<td>${r['jour']}</td><td>".( ( $r['heure'] == '0h00' ) ? '-' : $r['heure'] ).'</td><td>'.format_duree($r['duree']).'</td></tr>';
        }
        $resultat->free();
        echo "\n      </tbody>\n    </table>\n  </article>\n\n";
      }
      
      exit("</body>\n</html>\n");
    }
  }
}

//////////////
//// HTML ////
//////////////
debut($mysqli,'Relevé des notes de colles',$message,4,'relevenotes');
echo <<<FIN

  <div id="icones">
    <a class="icon-voirtout" href="?tableau" title="Voir le tableau récapitulatif pour tous les colleurs"></a>
    <a class="icon-aide" title="Aide pour les relèves de notes de colles"></a>
  </div>

FIN;

// Si tableau récapitulatif total demandé
if ( isset($_REQUEST['tableau']) )  {
  $resultat = $mysqli->query('SELECT IF(LENGTH(c.nom),CONCAT(c.nom,\' \',c.prenom),c.login) AS colleur, m.nom AS matiere, 
                                     SUM(nb*(releve>0)) AS nb_rel, SUM(duree*(releve>0)) AS total_rel, SUM(duree*(releve>0)*(description>\'\')) AS td_rel,
                                     SUM(nb*(releve=0)) AS nb_nrel, SUM(duree*(releve=0)) AS total_nrel, SUM(duree*(releve=0)*(description>\'\')) AS td_nrel
                              FROM heurescolles AS h LEFT JOIN utilisateurs AS c ON colleur = c.id LEFT JOIN matieres AS m ON matiere = m.id
                              LEFT JOIN (SELECT COUNT(*) AS nb, heure FROM notes GROUP BY heure) AS n ON h.id = n.heure
                              GROUP BY colleur ORDER BY ordre, c.nom');
  echo <<<FIN
  <article>
    <a class="icon-ferme" href="relevenotes" title="Fermer ce récapitulatif"></a>
    <h3>Récapitulatif annuel des heures de colles réalisées</h3>

FIN;
  if ( $resultat->num_rows )  {
    echo <<<FIN
    <table class="centre">
      <tbody>
        <tr><th colspan="2"></th><th colspan="3">Heures relevées</th><th colspan="3">Heures non relevées</th></tr>
        <tr><th>Matière</th><th>Colleur</th><th>Nombre d'élèves</th><th>Séances sans note</th><th>Durée totale</th><th>Nombre d'élèves</th><th>Séances sans note</th><th>Durée totale</th></tr>

FIN;
    while ( $r = $resultat->fetch_assoc() )
      echo "        <tr><td>${r['matiere']}</td><td>${r['colleur']}</td><td>${r['nb_rel']}</td><td>".format_duree($r['td_rel']).'</td><td>'.format_duree($r['total_rel'])."</td><td>${r['nb_nrel']}</td><td>".format_duree($r['td_nrel']).'</td><td>'.format_duree($r['total_nrel'])."</td></tr>\n";
    echo "      </tbody>\n    </table>\n  </article>\n\n";
    $resultat->free();
  }
  else
    echo "<div class=\"annonce\">Il n'y a encore aucune heure de colle déclarée cette année.</div>\n  </article>\n\n";
}

// Récupération des heures à relever, matière par matière
$resultat = $mysqli->query('SELECT m.nom AS matiere, SUM(nb) AS nb, SUM(duree*(description>\'\')) AS duree_td, SUM(duree) AS duree
                            FROM heurescolles AS h LEFT JOIN (SELECT COUNT(*) AS nb, heure FROM notes GROUP BY heure) AS n ON h.id = n.heure LEFT JOIN matieres AS m ON matiere = m.id
                            WHERE releve = 0 GROUP BY matiere ORDER BY ordre');
echo "\n  <article>\n    <h3>Heures de colles à relever actuellement</h3>\n";
if ( $resultat->num_rows )  {
  echo <<<FIN
    <table class="centre">
      <tbody>
        <tr><th>Matière</th><th>Nombre d'élèves</th><th>Séances sans note</th><th>Durée totale</th></tr>

FIN;
  while ( $r = $resultat->fetch_assoc() )  {
    echo "        <tr><td>${r['matiere']}</td><td>${r['nb']}</td><td>".format_duree($r['duree_td']).'</td><td>'.format_duree($r['duree'])."</td></tr>\n";
  }
  echo <<<FIN
      </tbody>
    </table>
    <input id="relevenotes" type="button" class="ligne" value="Relever les notes">
  </article>

FIN;
  $resultat->free();
}
else
  echo "<div class=\"annonce\">Il n'y a actuellement aucune nouvelle heure de colle à relever.</div>\n  </article>\n\n";

// Récupération des relevés déjà réalisés
$resultat = $mysqli->query('SELECT DATE_FORMAT(releve,\'%d/%m/%y\') AS date, DATE_FORMAT(releve,\'%y%m%d\') AS ref, SUM(duree) AS duree, SUM(nb) AS nb
                            FROM heurescolles AS h LEFT JOIN (SELECT COUNT(*) AS nb, heure FROM notes GROUP BY heure) AS n ON h.id = n.heure
                            WHERE releve > 0 GROUP BY releve ORDER BY releve DESC');
echo "\n  <article>\n    <h3>Relevés déjà réalisés</h3>\n";
if ( $resultat->num_rows )  {
  echo <<<FIN
    <table class="centre">
      <tbody>
        <tr><th>Date</th><th>Décomptes des heures</th><th>Détail des notes</th><th>Version imprimable</th></tr>

FIN;
  while ( $r = $resultat->fetch_assoc() )  {
    echo "        <tr><td>${r['date']}</td><td><a class=\"icon-download\" href=\"?export=decompte&datereleve=${r['ref']}\"></a>&nbsp;".format_duree($r['duree'])."</td><td><a class=\"icon-download\" href=\"?export=notes&datereleve=${r['ref']}\"></a>&nbsp;${r['nb']} notes</td><td><a class=\"icon-imprime\" onclick=\"printPage('?export=impression&datereleve=${r['ref']}');\"></a></td></tr>\n";
  }
  echo "      </tbody>\n    </table>\n  </article>\n\n";
  $resultat->free();
}
else
  echo "<div class=\"annonce\">Vous n'avez pas encore relevé de colles cette année.</div>\n  </article>\n\n";
$mysqli->close();
?>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Cette page n'est disponible que pour les utilisateurs de type administratif. Elle permet la relève des notes de colles saisies par les colleurs et les professeurs.</p>
    <p>Le bouton <em>Relever les notes</em> permet de réaliser une relève des notes de colles. Cela consiste à marquer comme relevées toutes les heures déclarées jusqu'à maintenant et non encore relevées. Une confirmation sera demandée. Toutes les colles non encore relevées le seront alors&nbsp;: il n'est pas possible de sélectionner seulement une période.</p>
    <p>L'ensemble des relevés est consigné dans un tableau. Y sont téléchargeables&nbsp;:
    <ul>
      <li>le décompte des heures au format xls, suffisant pour mettre au paiement les heures de colles</li>
      <li>le détail de l'ensemble des notes au format xls, si besoin de vérification</li>
      <li>l'ensemble des deux documents en format à imprimer, éventuellement en fichier pdf</li>
    </ul>
    <p>Les décomptes d'heures contiennent deux types de déclarations&nbsp;:</p>
    <ul>
      <li>des colles classiques, avec des notes pour chaque élève ou la mention «&nbsp;Non noté&nbsp;» ou «&nbsp;Absent&nbsp;»</li>
      <li>des séances sans note, pour payer des séances de cours ou travaux dirigés en heures de colles.</li>
    </ul>
    <p>Le bouton général <span class="icon-voirtout"></span> permet de visualiser un tableau récapitulatif global des totaux pour chaque colleur, depuis le début de l'année.</p>
    <h4>Du côté des colleurs</h4>
    <p>Les colleurs et professeurs ne peuvent plus modifier la durée ni le nombre d'élèves notés pendant les colles marquées comme relevées. Ils peuvent cependant encore modifier les notes, y compris pour les élèves «&nbsp;non notés&nbsp;» ou «&nbsp;absents&nbsp;».</p>
    <p>Les colleurs ont accès à la liste de leur colles et des dates de relèves. Ils sont donc au courant des heures qui doivent être mises au paiment.</p>
    <p>Les professeurs ont accès aux totaux du nombre d'élèves et du nombre d'heures qui ont été déclarées, relevées et non relevées. Cela leur permet de voir si un colleur déclare un nombre d'heures suspect.</p>
    <h4>Retards de déclaration</h4>
    <p>Le retard de déclaration d'un colleur est sans conséquence&nbsp;: à chaque relève, les colles non encore relevées le sont, indépendemment des dates des relèves précédentes. Il n'y a pas de régularisation globale à prévoir en juin.</p>
    <h4></h4>
    <h4>Binômes, élèves absents</h4>
    <p>Les textes officiels sont parfaitement clairs&nbsp;: l'heure de colle est indivisible, un binôme doit être payé une heure, au moins pour les matières où une heure est la durée réelle de la colle. C'est pour cela que la durée déclarée par les colleurs peut différer du simple produit nombre d'élèves par durée individuelle. Cela dépend cependant du budget global. Le plus sain est d'avoir débattu le sujet avec l'équipe pédagogique.</p>
    <p>Les élèves absents doivent rattraper leur colle. À défaut, le colleur doit quand même être payé&nbsp;: c'est pour cela qu'il n'y a pas de différence entre un élève noté et un élève absent dans le décompte.</p>
  </div>

  <script type="text/javascript">
  // Pour l'impression. Récupéré sur https://developer.mozilla.org/en-US/docs/Web/Guide/Printing
  function closePrint () {
    document.body.removeChild(this.__container__);
  }
  
  function setPrint () {
    this.contentWindow.__container__ = this;
    this.contentWindow.onbeforeunload = closePrint;
    this.contentWindow.onafterprint = closePrint;
    this.contentWindow.focus(); // Required for IE
    this.contentWindow.print();
  }
  
  function printPage (sURL) {
    var oHiddFrame = document.createElement("iframe");
    oHiddFrame.onload = setPrint;
    oHiddFrame.style.visibility = "hidden";
    oHiddFrame.style.position = "fixed";
    oHiddFrame.style.right = "0";
    oHiddFrame.style.bottom = "0";
    oHiddFrame.src = sURL;
    document.body.appendChild(oHiddFrame);
  }
  </script>

  <p id="log"></p>

<?php
fin(true);
?>
