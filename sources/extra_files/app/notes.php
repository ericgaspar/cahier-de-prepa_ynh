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

// Accès aux professeurs, colleurs, élèves connectés uniquement
// L'administration doit aller sur une autre page
$mysqli = connectsql();
if ( !$autorisation )  {
  $titre = 'Notes de colles';
  $actuel = false;
  include('login.php');
}
elseif ( ( $autorisation < 2 ) || ( $autorisation == 4 ) )  {
  debut($mysqli,'Notes de colles','Vous n\'avez pas accès à cette page.',$autorisation,' ');
  $mysqli->close();
  fin();
}
// Si connexion light : on doit répéter son mot de passe pour aller plus loin
// login.php contient fin()
if ( $_SESSION['light'] )  {
  $titre = 'Notes de colles';
  $actuel = 'colles';
  include('login.php');
}

////////////////////////////////////////
// Validation de la requête : matière //
////////////////////////////////////////

// Recherche de la matière concernée, variable $matiere
// Si le compte n'est associé qu'à une matière, on la choisit automatiquement.
// Sinon, on cherche $_REQUEST['cle'] dans les matières disponibles.
// notes=0 : pas de note saisie
// notes=1 : déjà des notes saisies
// notes=2 : fonction désactivée, pas d'affichage
$resultat = $mysqli->query("SELECT id, cle, nom, dureecolle FROM matieres WHERE FIND_IN_SET(id,'${_SESSION['matieres']}') AND notes < 2");
if ( $resultat->num_rows == 1 )  {
  $matiere = $resultat->fetch_assoc();
  $resultat->free();
}
elseif ( $resultat->num_rows )  {
  if ( !empty($_REQUEST) )  {
    while ( $r = $resultat->fetch_assoc() )
      if ( isset($_REQUEST[$r['cle']]) )  {
        $matiere = $r;
        break;
      }
  }
  $resultat->free();
  // Si aucune matière trouvée
  if ( !isset($matiere) )  {
    debut($mysqli,'Notes de colles','Mauvais paramètre d\'accès à cette page.',$autorisation,' ');
    $mysqli->close();
    fin();
  }
}
// Si aucune matière avec des notes n'est enregistrée
else  {
  debut($mysqli,'Notes de colles','Cette page ne contient aucune information.',$autorisation,' ');
  $mysqli->close();
  fin();
}
$mid = $matiere['id'];

//////////////////////////////////
// Exportation des notes en xls //
//////////////////////////////////
// Exportation uniquement si aucun header déjà envoyé
if ( ( $autorisation > 3 ) && isset($_REQUEST['xls']) && !headers_sent() )  {
  // Recherche des notes concernées
  $resultat = $mysqli->query("SELECT nom, GROUP_CONCAT( IFNULL(note,'') ORDER BY sid SEPARATOR '|') AS notes,
                              LEFT(REPLACE(AVG(IF(note='abs' OR note='nn',NULL,REPLACE(note,',','.'))),'.',','),4) AS moyenne
                              FROM ( SELECT s.id AS sid, u.id AS eid, IF(LENGTH(nom),CONCAT(nom,' ',prenom),CONCAT(login,' (identifiant)')) AS nom
                                     FROM semaines AS s LEFT JOIN utilisateurs AS u ON 1 WHERE colle AND u.autorisation=2 AND FIND_IN_SET($mid,u.matieres) ORDER BY IF(LENGTH(nom),nom,login)) AS t
                              LEFT JOIN notes ON sid = semaine AND eid = eleve AND matiere = $mid GROUP BY eid ORDER BY nom,sid");
  if ( $resultat->num_rows )  {
    // Fonctions de saisie
    function saisie_nombre($l, $c, $v)  {
      echo pack("sssss", 0x203, 14, $l, $c, 0).pack("d", $v);
      return;
    }
    function saisie_chaine($l, $c, $v)  {
      echo pack("ssssss", 0x204, 8 + strlen($v), $l, $c, 0, strlen($v)).$v;
      return;
    }
    // Envoi des headers
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=notes.xls");
    header("Content-Transfer-Encoding: binary");
    // Début du fichier xls
    echo pack("sssss", 0x809, 6, 0, 0x10, 0);
    // Remplissage
    $i = 0;
    $semaines = $mysqli->query('SELECT DATE_FORMAT(debut,\'%d/%m\') FROM semaines WHERE colle');
    while ( $r = $semaines->fetch_row() )
      saisie_chaine(0, ++$i, $r[0]);
    $semaines->free();
    saisie_chaine(0, $colmoy = ++$i, 'Moyenne');
    $i = 0;
    while ( $r = $resultat->fetch_assoc() )  {
      saisie_chaine(++$i, 0, utf8_decode($r['nom']));
      $notes = explode('|',$r['notes']);
      foreach ( $notes as $j => $n)
        if ( is_numeric($n) )
          saisie_nombre($i, $j+1, $n);
        elseif ( strlen($n) )
          saisie_chaine($i, $j+1, $n);
      saisie_chaine($i, $colmoy, $r['moyenne']);
    }
    // Fin du fichier xls
    echo pack("ss", 0x0A, 0x00);
    $resultat->free();
    $mysqli->close();
    exit();
  }
}

////////////
/// HTML ///
////////////
debut($mysqli,"Notes de colles - ${matiere['nom']}",$message,$autorisation,"notes?${matiere['cle']}",$mid,'datetimepicker');

// Affichage pour les élèves
if ( $autorisation == 2 )  {
  
  // Récupération de l'ensemble des notes, semaines, colleurs propres à l'élève
  $resultat = $mysqli->query("SELECT DATE_FORMAT(jour,'%w%Y%m%e') AS jour, n.note, IF(LENGTH(c.nom),c.nom,c.login) AS colleur
                              FROM notes AS n LEFT JOIN utilisateurs AS c ON n.colleur=c.id LEFT JOIN heurescolles AS h ON n.heure = h.id
                              WHERE n.eleve = ${_SESSION['id']} AND n.matiere = $mid ORDER BY jour DESC");
  // Affichage des notes concernées
  if ( $resultat->num_rows )  {
    while ( $r = $resultat->fetch_assoc() )
      echo "\n  <article>\n    <p><strong>".ucfirst(format_date($r['jour']))."</strong>&nbsp;: ${r['note']} (${r['colleur']})</p>\n  </article>\n";
    $resultat->free();
  }
  else
    echo "\n  <article>\n    <h2>Vous n'avez encore aucune note en ${matiere['nom']} cette année.</h2>\n  </article>\n";

}

// Affichage colleur et professeur
else  {
  
  // Fonction pour l'affichage des durées en heures/minutes. Argument en minutes.
  function format_duree($duree)  {
    if ( $duree == 0 )
      return '-';
    if ( $duree >= 60 )
      return intdiv($duree,60).'h'.( $duree%60 ?: '');
    return ($duree%60).'m';
  }

  // Pour les professeurs : affichage d'un tableau de notes global ou
  // d'un récapitulatif pour tous les colleurs de la matière
  if ( $autorisation == 5 )  {

    // Affichage unique du tableau récapitulatif
    if ( isset($_REQUEST['tableau']) )  {
      // Récupération des colleurs
      $resultat = $mysqli->query("SELECT id, IF(LENGTH(nom),CONCAT(prenom,' ',nom),login) AS nom
                                  FROM utilisateurs WHERE ( autorisation = 3 OR autorisation = 5 ) AND FIND_IN_SET($mid,matieres) ORDER BY IF(LENGTH(nom),nom,login)");
      $select_colleurs = '';
      while ( $r = $resultat->fetch_assoc() )
        $select_colleurs .= "\n      <option value=\"${r['id']}\">${r['nom']}</option>";
      $resultat->free();
      // Récupération des semaines
      $resultat = $mysqli->query("SELECT id, IF(LENGTH(nom),CONCAT(prenom,' ',nom),login) AS nom
                                  FROM utilisateurs WHERE ( autorisation = 3 OR autorisation = 5 ) AND FIND_IN_SET($mid,matieres) ORDER BY IF(LENGTH(nom),nom,login)");
      $select_colleurs = '';
      while ( $r = $resultat->fetch_assoc() )
        $select_colleurs .= "\n      <option value=\"${r['id']}\">${r['nom']}</option>";
      $resultat->free();
      
      $resultat = $mysqli->query('SELECT id, DATE_FORMAT(debut,\'%w%Y%m%e\') AS debut, DATE_FORMAT(ADDDATE(debut,7-DAYOFWEEK(debut)),\'%w%Y%m%e\') AS fin FROM semaines WHERE colle = 1');
      if ( $resultat->num_rows )  {
        $semaines_debut = $semaines_fin = '';
        while ( $r = $resultat->fetch_assoc() )  {
          $semaines_debut .= "\n      <option value=\"${r['id']}\">".format_date($r['debut']).'</option>';
          $semaines_fin .= "\n      <option value=\"${r['id']}\">".format_date($r['fin']).'</option>';
          $nmax = $r['id'];
        }
        $resultat->free();
        if ( !isset($_REQUEST['ndebut']) || !ctype_digit($ndebut = $_REQUEST['ndebut']) || ( $ndebut < 1 ) || ( $ndebut > $nmax ) )
          $ndebut = 1;
        if ( !isset($_REQUEST['nfin']) || !ctype_digit($nfin = $_REQUEST['nfin']) || ( $nfin < $ndebut ) || ( $nfin > $nmax ) )
          $nfin = $nmax;
        $select_semaines = "\n    entre <select id=\"sdebut\" onchange=\"window.location.href='?${matiere['cle']}&amp;tableau&amp;ndebut='+this.value+'&amp;nfin='+document.getElementById('sfin').value\">".str_replace("\"$ndebut\"","\"$ndebut\" selected",$semaines_debut)."\n    </select> et <select id=\"sfin\" onchange=\"window.location.href='?${matiere['cle']}&amp;tableau&amp;ndebut='+document.getElementById('sdebut').value+'&amp;nfin='+this.value\">".str_replace("\"$nfin\"","\"$nfin\" selected",$semaines_fin)."\n    </select>";
      }
      // S'il n'y a pas de semaines de colles dans le planning, la suite n'a pas de sens
      else  {
        $select_semaines = '';
        $ndebut = $nfin = 0;
      }
      
      // Icônes d'action générales et barre de sélection
      echo <<<FIN

  <div id="icones">
    <a class="icon-download" href="?${matiere['cle']}&amp;xls" title="Télécharger le tableau de notes en xls"></a>
    <a class="icon-retour" href="?${matiere['cle']}" title="Revenir à l'affichage normal"></a>
  </div>

  <p id="recherchenote" class="topbarre">
    Voir les notes de <select id="colleurs" onchange="if (this.value>0) { $('[data-colleur]').attr('class','collnosel'); $('[data-colleur=&quot;'+this.value+'&quot;]').attr('class','collsel'); } else $('[data-colleur]').removeClass();">
      <option value="0">tous les colleurs</option>$select_colleurs
    </select>$select_semaines
  </p>

FIN;
      // Recherche des notes concernées
      $resultat = $mysqli->query("SELECT nom, GROUP_CONCAT( IF(ISNULL(note),'<td></td>',CONCAT('<td data-colleur=\"',colleur,'\">',note,'</td>')) ORDER BY sid SEPARATOR '') AS notes,
                                  LEFT(REPLACE(AVG(IF(note='abs' OR note='nn',NULL,REPLACE(note,',','.'))),'.',','),4) AS moyenne
                                  FROM ( SELECT s.id AS sid,  u.id AS eid, CONCAT('<td>',IF(LENGTH(nom),CONCAT(nom,' ',prenom),login),'</td>') AS nom
                                         FROM semaines AS s LEFT JOIN utilisateurs AS u ON 1 
                                         WHERE colle AND s.id >= $ndebut AND s.id <= $nfin AND u.autorisation=2 AND FIND_IN_SET($mid,u.matieres) ORDER BY IF(LENGTH(nom),nom,login)) AS t
                                  LEFT JOIN notes ON sid = semaine AND eid = eleve AND matiere = $mid GROUP BY eid ORDER BY nom,sid");
      if ( $resultat->num_rows )  {
        echo "\n  <table>\n    <thead>\n      <tr><th></th>";
        $semaines = $mysqli->query("SELECT DATE_FORMAT(debut,'%d/%m') FROM semaines WHERE colle AND id >= $ndebut AND id <= $nfin");
        $nb = $semaines->num_rows;
        while ( $r = $semaines->fetch_row() )
          echo "<th class=\"vertical\"><span>${r[0]}</span></th>";
        echo '<th class="vertical"><span>Moyenne</span></th>';
        $semaines->free();
        echo "</tr>\n    </thead>\n    <tbody>\n";
        while ( $r = $resultat->fetch_assoc() )
          if ( strlen(str_replace('<td></td>','',$r['notes'])) )
            echo "      <tr>${r['nom']}${r['notes']}<td>${r['moyenne']}</td></tr>\n";
          else
            echo "      <tr>${r['nom']}<td class=\"pasnote\" colspan=\"$nb+1\">Pas encore de note pour cet élève</td></tr>\n";
        $resultat->free();
        echo "    </tbody>\n  </table>\n\n";
      }
      else
        echo "\n  <article>\n    <h2>Il n'y a encore aucune note de colle en ${matiere['nom']} cette année.</h2>\n  </article>\n\n";
      echo '  <p id="log"></p>';
      fin(true);
    }
    
    // Affichage "normal" : Récapitulatif de la matière
    echo <<<FIN
  
  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter des notes de colles"></a>
    <a class="icon-prefs formulaire" title="Modifier les réglages des notes de colles"></a>
    <a class="icon-voirtout" href="?${matiere['cle']}&amp;tableau" title="Voir le tableau de notes"></a>
    <a class="icon-download" href="?${matiere['cle']}&amp;xls" title="Télécharger le tableau de notes en xls"></a>
    <a class="icon-aide" title="Aide pour les modifications des notes de colles"></a>
  </div>

FIN;

    // Récupération du décompte de la matière
    $resultat = $mysqli->query("SELECT COUNT(*) FROM notes WHERE matiere = $mid");
    $r = $resultat->fetch_row();
    $resultat->free();
    $resultat = $mysqli->query("SELECT colleur AS cid, IF(LENGTH(nom),CONCAT(LEFT(prenom,1),'. ',nom),login) AS cnom,
                                       SUM(duree*(releve>0)) AS total_rel, SUM(duree*(releve>0)*(description>'')) AS td_rel,
                                       SUM(duree*(releve=0)) AS total_nrel, SUM(duree*(releve=0)*(description>'')) AS td_nrel
                                FROM heurescolles AS h LEFT JOIN utilisateurs AS u ON h.colleur=u.id
                                WHERE matiere = $mid GROUP BY colleur ORDER BY IF(LENGTH(nom),nom,login)");
    if ( $resultat->num_rows )  {
      echo  <<<FIN
  <article>
    <h3>Récapitulatif de la matière</h3>
    <table id="notes">
      <tbody>
        <tr><th></th>

FIN;
      $ligne_eleves = $ligne_heures_rel = $ligne_heures_nrel = $ligne_heures = $ligne_moyenne = '';
      $total = array('nb'=>0,'total_rel'=>0,'td_rel'=>0,'total_nrel'=>0,'td_nrel'=>0);
      while ( $r = $resultat->fetch_assoc() )  {
        $resultat2 = $mysqli->query("SELECT COUNT(*) AS nb, LEFT(REPLACE(AVG(IF(note='abs' OR note='nn',NULL,REPLACE(note,',','.'))),'.',','),4) AS moy FROM notes WHERE colleur = ${r['cid']} AND matiere = $mid");
        $s = $resultat2->fetch_assoc();
        $resultat2->free();
        echo "          <th class=\"vertical\"><span>${r['cnom']}</span></th>\n";
        $ligne_eleves .= "<td>${s['nb']}</td>";
        $ligne_heures_rel .= '<td>'.format_duree($r['total_rel']).( $r['td_rel'] ? '&nbsp;('.format_duree($r['td_rel']).')' : '' ).'</td>';
        $ligne_heures_nrel .= '<td>'.format_duree($r['total_nrel']).( $r['td_nrel'] ? '&nbsp;('.format_duree($r['td_nrel']).')' : '' ).'</td>';
        $ligne_heures .= '<td>'.format_duree($r['total_rel']+$r['total_nrel']).( ($d=$r['td_rel']+$r['td_nrel']) ? '&nbsp;('.format_duree($d).')' : '' ).'</td>';
        $ligne_moyenne .= "<td>${s['moy']}</td>";
        $total['nb'] += $s['nb'];
        $total['total_rel'] += $r['total_rel'];
        $total['td_rel'] += $r['td_rel'];
        $total['total_nrel'] += $r['total_nrel'];
        $total['td_nrel'] += $r['td_nrel'];
      }
      $resultat->free();
      // Totaux d'heures
      $ligne_heures_rel = ( $total['td_rel'] ? "<tr><th>Nombre d'heures relevées (dont séances sans note)</th>$ligne_heures_rel<td>".format_duree($total['total_rel']).'&nbsp;('.format_duree($total['td_rel']).')</td></tr>'
                                             : "<tr><th>Nombre d'heures relevées</th>$ligne_heures_rel<td>".format_duree($total['total_rel']).'</td></tr>' );
      $ligne_heures_nrel = ( $total['td_nrel'] ? "<tr><th>Nombre d'heures non relevées (dont séances sans note)</th>$ligne_heures_nrel<td>".format_duree($total['total_nrel']).'&nbsp;('.format_duree($total['td_nrel']).')</td></tr>'
                                               : "<tr><th>Nombre d'heures non relevées</th>$ligne_heures_nrel<td>".format_duree($total['total_nrel']).'</td></tr>' );
      $ligne_heures = ( ($d=$total['td_rel']+$total['td_nrel']) ? "<tr><th>Nombre d'heures total (dont séances sans note)</th>$ligne_heures<td>".format_duree($total['total_rel']+$total['total_nrel']).'&nbsp;('.format_duree($d).')</td></tr>'
                                                                : "<tr><th>Nombre d'heures total</th>$ligne_heures<td>".format_duree($total['total_rel']+$total['total_nrel']).'</td></tr>' );
      // Moyenne globale
      $resultat = $mysqli->query("SELECT LEFT(REPLACE(AVG(IF(note='abs' OR note='nn',NULL,REPLACE(note,',','.'))),'.',','),4) FROM notes WHERE matiere = $mid");
      $r = $resultat->fetch_row();
      $resultat->free();
      echo <<<FIN
          <th class="vertical"><span>Total</span></th>
        </tr>
        <tr><th>Nombre d'élèves interrogés</th>$ligne_eleves<td>${total['nb']}</td></tr>
        $ligne_heures
        $ligne_heures_rel
        $ligne_heures_nrel
        <tr><th>Moyenne</th>$ligne_moyenne<td>${r[0]}</td></tr>
      </tbody>
    </table>
  </article>
  
FIN;
    }
  }
  // Pour les colleurs : récapitulatif personnel uniquement
  else  {
    echo <<<FIN
  
  <div id="icones">
    <a class="icon-ajoute formulaire" title="Ajouter des notes de colles"></a>
    <a class="icon-aide" title="Aide pour les modifications des notes de colles"></a>
  </div>

FIN;

    // Récupération du décompte personnel
    $resultat = $mysqli->query("SELECT SUM(duree*(releve>0)) AS total_rel, SUM(duree*(releve>0)*(description>'')) AS td_rel,
                                       SUM(duree*(releve=0)) AS total_nrel, SUM(duree*(releve=0)*(description>'')) AS td_nrel
                                FROM heurescolles WHERE colleur = ${_SESSION['id']} AND matiere = $mid");
    $r = $resultat->fetch_assoc();
    $resultat->free();
    $resultat = $mysqli->query("SELECT COUNT(*) AS nb, LEFT(REPLACE(AVG(IF(note='abs' OR note='nn',NULL,REPLACE(note,',','.'))),'.',','),4) AS moy FROM notes WHERE colleur = ${_SESSION['id']} AND matiere = $mid");
    $s = $resultat->fetch_assoc();
    $resultat->free();
    $moyenne = ( is_null($s['moy']) ? '-' : "${s['moy']}/20" );
    $ligne_heures_rel = ( $r['td_rel'] ? '<p><strong>Nombre d\'heures relevées (dont séances sans note)</strong>&nbsp;:&nbsp;'.format_duree($r['total_rel']).'&nbsp;('.format_duree($r['td_rel']).')</p>'
                                       : '<p><strong>Nombre d\'heures relevées</strong>&nbsp;:&nbsp;'.format_duree($r['total_rel']).'</p>' );
    $ligne_heures_nrel = ( $r['td_nrel'] ? '<p><strong>Nombre d\'heures non relevées (dont séances sans note)</strong>&nbsp;:&nbsp;'.format_duree($r['total_nrel']).'&nbsp;('.format_duree($r['td_nrel']).')</p>'
                                         : '<p><strong>Nombre d\'heures non relevées</strong>&nbsp;:&nbsp;'.format_duree($r['total_nrel']).'</p>' );
    echo <<<FIN
  
  <article>
    <h3>Récapitulatif personnel</h3>
    <p><strong>Nombre d'élèves interrogés</strong>&nbsp;:&nbsp;${s['nb']}</p>
    $ligne_heures_rel
    $ligne_heures_nrel
    <p><strong>Moyenne</strong>&nbsp;:&nbsp;$moyenne</p>
  </article>
  
FIN;
  }

  // Récupération de l'ensemble des élèves associés à la matière
  $resultat = $mysqli->query("SELECT id, IF(LENGTH(nom),CONCAT(nom,' ',prenom),login) AS nomcomplet, IF(LENGTH(nom),CONCAT(prenom,' ',nom),login) AS prenomnom,
                              IF(LENGTH(nom),CONCAT(LEFT(prenom,1),'. ',nom),login) AS initiale, IF(mdp>'0',1,0) AS actif
                              FROM utilisateurs WHERE autorisation = 2 AND FIND_IN_SET($mid,matieres) ORDER BY IF(LENGTH(nom),nom,login)");
  $eleves = array();
  while ( $r = $resultat->fetch_assoc() )
    $eleves[$r['id']] = $r;
  $resultat->free();
  
  // Détail personnel des colles déclarées : récupération de l'ensemble des
  // notes mises, regroupées par heure - commun colleurs et professeurs
  $limite = ( isset($_REQUEST['voirtout']) ? '' : 'LIMIT 10' );
  $resultat = $mysqli->query("SELECT id, semaine AS sid,
                              DATE_FORMAT(jour,'%d/%m/%y') AS jour, TIME_FORMAT(h.heure,'%kh%i') AS heure, duree,
                              DATE_FORMAT(releve,'%d/%m') AS releve, description,
                              GROUP_CONCAT( eleve ORDER BY nom SEPARATOR '|') AS eleves,
                              GROUP_CONCAT( note  ORDER BY nom SEPARATOR '|') AS notes
                              FROM heurescolles AS h 
                              LEFT JOIN ( SELECT heure,semaine,note,eleve,IF(LENGTH(nom),nom,login) AS nom FROM notes JOIN utilisateurs AS e ON eleve = e.id ) AS n ON n.heure = id
                              WHERE colleur = ${_SESSION['id']} AND matiere = $mid
                              GROUP BY id ORDER BY h.jour DESC, h.heure DESC $limite");
  // Affichage
  if ( $n = $resultat->num_rows )  {
    if ( isset($_REQUEST['voirtout']) || ( $n < 10 ) )  {
      $titre = 'Liste de vos colles';
      $icone = '';
    }
    else  {
      $titre = 'Liste de vos dernières colles';
      $icone = "<a class=\"icon-voirtout\" href=\"?${matiere['cle']}&amp;voirtout\"></a>\n    ";
    }
    echo  <<<FIN
  <article>
    $icone<h3>$titre</h3>
    <table id="notes">
      <tbody>
        <tr><th>Jour</th><th>Heure</th><th>Élèves (notes) ou Description</th><th>Durée</th><th>Relève</th><th></th></tr>

FIN;
    // Affichage de chaque heure
    while ( $r = $resultat->fetch_assoc() )  {
      $heure = ( $r['heure'] == '0h00' ) ? '-' : $r['heure'];
      $duree = format_duree($r['duree']);
      if ( $r['releve'] == '00/00' )  {
        $r['releve'] = '-';
        $supprime = "\n            <a class=\"icon-supprime\" title=\"Supprimer cette colle\"></a>";
      }
      else  {
        $supprime = '';
      }
      // Cas des séances de TD sans note
      if ( is_null($r['sid']) )  {
        $texte = $r['description'];
        $data = '';
      }
      // Cas des colles classiques
      else  {
        $notes = array_combine(explode('|',$r['eleves']),explode('|',$r['notes']));
        $texte = array();
        foreach( $notes as $e => $n )
          $texte[] = "{$eleves[$e]['initiale']} ($n)";
        $texte = implode(', ',$texte);
        $data = " data-eleves=\"${r['eleves']}\" data-notes=\"${r['notes']}\" data-sid=\"${r['sid']}\"";
      }
      echo <<<FIN
        <tr>
          <td>${r['jour']}</td><td>$heure</td><td>$texte</td><td>$duree</td><td>${r['releve']}</td>
          <td class="icones" data-id="notes|${r['id']}">
            <a class="icon-edite formulaire"$data title="Éditer cette colle"></a>$supprime
          </td>
        </tr>

FIN;
    }
    $resultat->free();
    echo <<<FIN
      </tbody>
    </table>
  </article>

FIN;
  }
  else
    echo "\n  <article>\n    <h2>Vous n'avez encore saisi aucune note en ${matiere['nom']} cette année.</h2>\n  </article>\n";

  // Professeurs uniquement : détail des colles déclarées par tous les colleurs
  if ( $autorisation == 5 )  {
    $limite = ( isset($_REQUEST['voirtout']) ? '' : 'LIMIT 40' );
    $resultat = $mysqli->query("SELECT IF(LENGTH(nom),CONCAT(LEFT(prenom,1),'. ',nom),login) AS colleur,
                                DATE_FORMAT(jour,'%d/%m/%y') AS jour, TIME_FORMAT(h.heure,'%kh%i') AS heure, duree,
                                DATE_FORMAT(releve,'%d/%m') AS releve, description,
                                GROUP_CONCAT( eleve ORDER BY enom SEPARATOR '|') AS eleves,
                                GROUP_CONCAT( note  ORDER BY enom SEPARATOR '|') AS notes
                                FROM heurescolles AS h JOIN utilisateurs AS c ON colleur = c.id
                                LEFT JOIN ( SELECT heure,note,eleve,IF(LENGTH(nom),nom,login) AS enom FROM notes JOIN utilisateurs AS e ON eleve = e.id ) AS n ON n.heure = h.id
                                WHERE colleur != ${_SESSION['id']} AND matiere = $mid
                                GROUP BY h.id ORDER BY h.jour DESC, h.heure DESC, nom $limite");
    // Affichage
    if ( $n = $resultat->num_rows )  {
      if ( isset($_REQUEST['voirtout']) || ( $n < 40 ) )  {
        $titre = 'Liste des colles de vos colleurs';
        $icone = '';
      }
      else  {
        $titre = 'Liste de des dernières colles de vos colleurs';
        $icone = "<a class=\"icon-voirtout\" href=\"?${matiere['cle']}&amp;voirtout\"></a>\n    ";
      }
      echo  <<<FIN
  <article>
    $icone<h3>$titre</h3>
    <table id="notes">
      <tbody>
        <tr><th>Colleur</th><th>Jour</th><th>Heure</th><th>Élèves (notes) ou Description</th><th>Durée</th><th>Relève</th></tr>

FIN;
      // Affichage de chaque heure
      while ( $r = $resultat->fetch_assoc() )  {
        $heure = ( $r['heure'] == '0h00' ) ? '-' : $r['heure'];
        $duree = format_duree($r['duree']);
        $releve = ( $r['releve'] == '00/00' ) ? '-' : $r['releve'];
        // Cas des séances de TD sans note
        if ( is_null($r['notes']) )
          $texte = $r['description'];
        // Cas des colles classiques
        else  {
          $notes = array_combine(explode('|',$r['eleves']),explode('|',$r['notes']));
          $texte = array();
          foreach( $notes as $e => $n ) {
            $texte[] = "{$eleves[$e]['initiale']} ($n)";
          }
          $texte = implode(', ',$texte);
        }
        echo <<<FIN
        <tr>
          <td>${r['colleur']}</td><td>${r['jour']}</td><td>$heure</td><td>$texte</td><td>$duree</td><td>$releve</td>
        </tr>

FIN;
      }
      $resultat->free();
      echo <<<FIN
      </tbody>
    </table>
  </article>

FIN;
    }
  }

  // Table contenant les élèves et les groupes
  $table = '';
  foreach ( $eleves as $id => $eleve )
    if ( $eleve['actif'] )
      $table .= "        <tr data-id=\"$id\"><td>${eleve['nomcomplet']}</td></tr>\n";
  // Récupération des groupes de colles et préparation à l'affichage
  $resultat = $mysqli->query("SELECT g.id, g.nom, GROUP_CONCAT(e.id) AS eid,
                              GROUP_CONCAT( IF(LENGTH(e.nom),CONCAT(e.prenom,' ',e.nom),e.login) ORDER BY IF(LENGTH(e.nom),e.nom,e.login) SEPARATOR ', ') AS eleves
                              FROM groupes AS g JOIN utilisateurs AS e ON FIND_IN_SET(e.id,g.utilisateurs)
                              WHERE g.notes=1 AND FIND_IN_SET($mid,e.matieres) AND e.autorisation = 2
                              GROUP BY g.id ORDER BY g.nom_nat");
  if ( $resultat->num_rows )  {
    $table .= "        <tr><th>Groupes de colles</th><th></th></tr>\n";
    while ( $r = $resultat->fetch_assoc() )
      $table .= "        <tr><td><label for=\"g${r['id']}\">Groupe ${r['nom']}&nbsp;: ${r['eleves']}</label></td><td><input type=\"checkbox\" class=\"grpnote\" name=\"g${r['id']}\" value=\"${r['eid']}\"></td></tr>\n";
    $resultat->free();
    $table .= "        <tr><td><strong>Voir tous les élèves</strong></td><td><input type=\"checkbox\"></td></tr>\n";
  }

  // Récupération de l'ensemble des semaines
  // notesperso : élèves déjà notés par le colleur concerné
  // notesautres : élèves déjà notés par les autres colleurs
  // n : nombre de notes du colleur concerné
  $resultat = $mysqli->query("SELECT s.id, DATE_FORMAT(debut,'%w%Y%m%e') AS debut, DATE_FORMAT(debut,'%d/%m/%Y') AS datedebut, colle, v.nom AS vacances, 
                              IFNULL(GROUP_CONCAT(IF(n.colleur=${_SESSION['id']},n.eleve,NULL)),'') AS notesperso,
                              IFNULL(GROUP_CONCAT(IF(n.colleur=${_SESSION['id']},NULL,n.eleve)),'') AS notesautres,
                              COUNT(IF(n.colleur=${_SESSION['id']},1,NULL)) AS n
                              FROM semaines AS s LEFT JOIN vacances AS v ON s.vacances = v.id
                              LEFT JOIN (SELECT * FROM notes WHERE matiere = $mid) AS n ON s.id = n.semaine GROUP BY s.id");
  $select_semaines = "\n        <option value=\"0\">Choisir une semaine</option>";
  $notesperso = $notesautres = array();
  while ( $r = $resultat->fetch_assoc() )  {
    if ( $r['colle'] == 0 )
      $select_semaines .= "\n        <option disabled data-date=\"${r['datedebut']}\">".( $r['vacances'] ?: format_date($r['debut']).' (pas de colle)' ).'</option>';
    else  {
      $select_semaines .= "\n        <option value=\"${r['id']}\" data-date=\"${r['datedebut']}\">".format_date($r['debut']).( $r['n'] ? " (${r['n']} notes déjà saisies)" : '').'</option>';
      $notesperso[$r['id']] = $r['notesperso'];
      $notesautres[$r['id']] = $r['notesautres'];
    }
  }
  $resultat->free();

  // Réglage à la semaine actuelle pour l'ajout de notes
  $resultat = $mysqli->query('SELECT id, DATE_FORMAT(debut,\'%d/%m/%Y\') FROM semaines WHERE colle AND debut<CURDATE() ORDER BY DATEDIFF(CURDATE(),debut) LIMIT 1');
  if ( $resultat->num_rows )  {
    $r = $resultat->fetch_row();
    $select_semaines = str_replace("\"${r[0]}\"","\"${r[0]}\" selected",$select_semaines);
    $resultat->free();
  }
  $mysqli->close();
  
  // Aide et formulaire d'ajout
?>

  <script type="text/javascript">
    dejanotesperso = <?php echo json_encode($notesperso); ?>;
    dejanotesautres = <?php echo json_encode($notesautres); ?>;
    dureecolle = <?php echo $matiere['dureecolle']; ?>
  </script>

  <form id="form-edite" data-action="notes">
    <input type="hidden" name="id" value="">
    <h3 class="edition">Modifier des notes</h3>
  </form>
   
  <form id="form-ajoute" data-action="ajout-notes">
    <input type="hidden" name="matiere" value="<?php echo $mid; ?>">
    <h3 class="edition">Ajouter des notes de colles</h3>
    <p class="ligne"><label for="sid">Semaine</label>
      <select name="sid"><?php echo $select_semaines; ?>

      </select>
    </p>
  </form>
  
  <form id="form-notes">
    <p class="ligne"><label for="jour">Jour&nbsp;: </label><input type="text" name="jour" value="<?php echo $r[1]; ?>" size="8"></p>
    <p class="ligne"><label for="heure">Heure&nbsp;: </label><input type="text" name="heure" value="" size="5" placeholder="Heure de la colle (facultatif)"></p>
    <p class="ligne"><label for="duree">Durée&nbsp;: </label><input type="text" name="duree" value="0h" size="4" placeholder="Durée de la colle (obligatoire)"></p>
    <table class="notes">
      <tbody>
<?php echo $table; ?>
      </tbody>
    </table>
    <p class="ligne"><label for="description">Description&nbsp;: </label><input type="text" name="description" value="" size="100" placeholder="Description de la séance (obligatoire)"></p>
    <p class="ligne"><label for="td">Séance de TD sans note&nbsp;: </label><input type="checkbox" name="td"></p>
    <div><select><option value="x"></option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option><option value="9">9</option><option value="8">8</option><option value="7">7</option><option value="6">6</option><option value="abs">Absent</option><option value="nn">Non noté</option><option value="16">16</option><option value="17">17</option><option value="18">18</option><option value="19">19</option><option value="20">20</option><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option><option value="0">0</option><option value="0,5">0,5</option><option value="1,5">1,5</option><option value="2,5">2,5</option><option value="3,5">3,5</option><option value="4,5">4,5</option><option value="5,5">5,5</option><option value="6,5">6,5</option><option value="7,5">7,5</option><option value="8,5">8,5</option><option value="9,5">9,5</option><option value="10,5">10,5</option><option value="11,5">11,5</option><option value="12,5">12,5</option><option value="13,5">13,5</option><option value="14,5">14,5</option><option value="15,5">15,5</option><option value="16,5">16,5</option><option value="17,5">17,5</option><option value="18,5">18,5</option><option value="19,5">19,5</option></select></div>
  </form>
  
  <form id="form-prefs" data-action="prefsmatiere">
    <h3 class="edition">Réglages des notes de colles en <?php echo $matiere['nom']; ?></h3>
    <p>Il n'est pas possible de désactiver cette fonction ici. Vous pouvez le faire depuis la page de <a href="matieres">gestion des matières</a>.</p>
    <p>Pour modifier les semaines correspondant à des notes de colle, il faut vous rendre sur la page de <a href="planning">gestion du planning annuel</a>.</p>
    <p>Pour modifier les utilisateurs (élèves comme colleurs) concernés par cette matière, il faut vous rendre sur la page de <a href="utilisateurs-matieres">gestion des associations utilisateurs-matières</a>.</p>
    <p class="ligne"><label for="dureecolle">Durée des colles en minutes par élève&nbsp;: </label><input type="text" id="dureecolle" name="dureecolle" placeholder="Valeur par défaut. Typiquement 20 ou 30." value="<?php echo $matiere['dureecolle']; ?>" size="3"></p>
    <input type="hidden" name="id" value="<?php echo $mid; ?>">
  </form>

<?php if ( $autorisation == 3 ) { ?>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici consulter les notes et heures de colles que vous avez déclarées, de les modifier en cliquant sur le bouton <span class="icon-edite"></span>, de les supprimer en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée) et d'en ajouter en cliquant sur le bouton <span class="icon-ajoute"></span>.</p>
    <h4>Saisie des notes</h4>
    <p>Vous pouvez saisir les notes par heure de colle ou par jour. La durée est précalculée automatiquement, mais vous pouvez modifier la valeur calculée avant de valider votre saisie.</p>
    <p>Vous ne pouvez mettre des notes de colles que dans certaines matières. Les professeurs de la classe ont la possibilité de modifier les matières qui vous sont associées.</p>
    <p>Un élève ne peut avoir qu'une seule note par matière et par semaine. Dans les formulaires d'ajout ou de modification des notes, les élèves ayant déjà eu une note par un autre colleur lors de la semaine concernée sont indiqués et non notables.</p> 
    <p>Une fois saisie, une colle peut être modifiée mais doit rester sur la même semaine, pour éviter les conflits de note.</p>
    <h4>Saisie des séances sans note</h4>
    <p>Vous pouvez également déclarer et modifier des séances de cours ou de travaux dirigés sans note, qui seront alors payés comme des colles si le lycée utilise ces saisies pour mettre au paiement vos heures. Les séances sans notes ne sont pas soumises aux mêmes restrictions de semaines que les colles.</p>
    <h4>Vérification des professeurs</h4>
    <p>Les professeurs de la classe peuvent voir et télécharger le détail des notes que vous avez mises. Ils ont aussi accès dans un tableau synthétique à l'ensemble des durées que vous avez déclarées, pour vos colles comme pour vos séances sans note.</p>
    <h4>Déclaration administrative</h4>   
    <p>Si le lycée utilise ces saisies pour mettre au paiement vos heures, ce sont normalement les durées déclarées qui comptent. Il est ainsi possible de déclarer une heure pour un binôme. Attention, les textes officiels précisent que chaque heure de colle est indivisible, mais la question du budget global peut modifier cela. Il est nécessaire que l'ensemble des colleurs se comportent de façon identique à ce niveau&nbsp;: parlez-en au professeur référent. L'administration a accès au détail des notes.</p>
    <p>Pour les colles relevées par l'administration, la date de la relève est inscrite dans le tableau et la colle n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier les notes saisies.</p>
    <p>Pour les séances sans note relevées par l'administration, la date de la relève est inscrite dans le tableau et la séance n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier le jour de la séance ou sa description.</p>
    <h4>Absences et retards</h4>
    <p>Lorsqu'un élève est absent, il vaut mieux le noter dans la colle et le marquer absent. Il sera toujours possible de le noter plus tard. Si la colle est relevée par l'administration entre son absence et son rattrapage, la note reste modifiable.</p>
    <p>Les élèves ne peuvent pas avoir deux notes par semaine dans la même matière&nbsp;: il est toujours préférable de noter le jour initialement prévu dans le colloscope plutôt que le jour de rattrapage le cas échéant.</p>
    <h4>Paramétrage</h4>
    <p>L'ensemble de cette fonctionnalité est paramétrable par le professeur de la classe. La durée de colle par élève est propre à chaque matière, mais la liste des semaines correspondant à des colles est globale à toutes les matières. Si vous voyez une anomalie, pensez à en parler au professeur référent.</p>
  </div>

  <div id="aide-edite">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier, supprimer ou ajouter des notes (ou la description pour les séances sans note) de la colle sélectionnée. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>La semaine déjà saisie n'est pas modifiable. Le jour de la colle est contraint dans cette semaine. L'heure est modifiable. Sa saisie n'est pas obligatoire. La restriction des semaines est supprimée pour les séances sans note.</p>
    <p>La saisie peut contenir autant d'élèves que vous le souhaitez, ou correspondre à une heure unique. La durée de la colle s'adapte automatiquement à la hausse. Elle reste modifiable aant validation.</p>
    <p>Si des groupes de colles ont été définis par les professeurs, vous pouvez cocher les cases correspondantes pour n'afficher que les notes déjà saisies et ces groupes-là. Les notes déjà saisies restent affichées lors du cochage/décochage des groupes, sauf lorsque vous venez de les modifier.</p>
    <p>Un élève ne peut avoir qu'une seule note par matière et par semaine. Les élèves ayant déjà eu une note par un autre colleur lors de la semaine concernée sont indiqués et non notables.</p>
    <p>Seules les notes non vides et visibles au moment où vous cliquez sur le bouton <span class="icon-ok"></span> sont effectivement envoyées pour l'enregistrement. Les notes que vous avez saisies mais qui ne sont pas affichées parce que vous avez décoché le groupe de colles correspondant ne sont pas envoyées.</p>
    <p>Les notes laissées vides ne sont pas enregistrées. En dehors des notes numériques, les choix possibles sont <em>Absent</em> et <em>Non noté</em>.</p>
    <h4>Déclaration administrative</h4>   
    <p>Si le lycée utilise ces saisies pour mettre au paiement vos heures, ce sont normalement les durées déclarées qui comptent. Il est ainsi possible de déclarer une heure pour un binôme. Attention, les textes officiels précisent que chaque heure de colle est indivisible, mais la question du budget global peut modifier cela. Il est nécessaire que l'ensemble des colleurs se comportent de façon identique à ce niveau&nbsp;: parlez-en au professeur référent. L'administration a accès au détail des notes.</p>
    <p>Pour les colles relevées par l'administration, la date de la relève est inscrite dans le tableau et la colle n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier les notes saisies.</p>
    <p>Pour les séances sans note relevées par l'administration, la date de la relève est inscrite dans le tableau et la séance n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier le jour de la séance ou sa description.</p>
    <h4>Absences et retards</h4>
    <p>Lorsqu'un élève est absent, il vaut mieux le noter dans la colle et le marquer absent. Il sera toujours possible de le noter plus tard. Si la colle est relevée par l'administration entre son absence et son rattrapage, la note reste modifiable.</p>
    <p>Les élèves ne peuvent pas avoir deux notes par semaine dans la même matière&nbsp;: il est toujours préférable de noter le jour initialement prévu dans le colloscope plutôt que le jour de rattrapage le cas échéant.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet d'ajouter des notes de colles ou de déclarer une séance sans note. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Pour des notes de colles, vous devez commencer par choisir la semaine correspondant aux notes que vous allez saisir. Vous pouvez ensuite choisir le jour dans cette semaine. La saisie de l'heure n'est pas obligatoire.</p>
    <p>Si des groupes de colles ont été définis par les professeurs, vous pouvez cocher les cases correspondantes pour n'afficher que les élèves de ces groupes-là.</p>
    <p>La saisie peut contenir autant d'élèves que vous le souhaitez, ou correspondre à une heure unique. La durée de la colle s'adapte automatiquement à la hausse à chaque nouvelle note saisie. Elle reste modifiable avant validation.</p>
    <p>Un élève ne peut avoir qu'une seule note par matière et par semaine. Les élèves ayant déjà eu une note par un autre colleur lors de la semaine choisie sont indiqués et non notables.</p>
    <p>Seules les notes non vides et visibles au moment où vous cliquez sur le bouton <span class="icon-ok"></span> sont effectivement envoyées pour l'enregistrement. Les notes que vous avez saisies mais qui ne sont pas affichées parce que vous avez décoché le groupe de colles correspondant ne sont pas envoyées.</p>
    <p>Les notes laissées vides ne sont pas enregistrées. En dehors des notes numériques, les choix possibles sont <em>Absent</em> et <em>Non noté</em>.</p>
    <h4>Saisie des séances sans note</h4>
    <p>Vous pouvez également déclarer une séance de cours ou de travaux dirigés sans note, qui sera alors payée comme une colle si le lycée utilise ces saisies pour mettre au paiement vos heures. Les séances sans notes ne sont pas soumises aux mêmes restrictions de semaines que les colles.</p>
    <h4>Déclaration administrative</h4>   
    <p>Si le lycée utilise ces saisies pour mettre au paiement vos heures, ce sont normalement les durées déclarées qui comptent. Il est ainsi possible de déclarer une heure pour un binôme. Attention, les textes officiels précisent que chaque heure de colle est indivisible, mais la question du budget global peut modifier cela. Il est nécessaire que l'ensemble des colleurs se comportent de façon identique à ce niveau&nbsp;: parlez-en au professeur référent. L'administration a accès au détail des notes. Les professeurs associés à la matières ont accès au détail de vos heures déclarées.</p>
    <p>Pour les colles relevées par l'administration, la date de la relève est inscrite dans le tableau et la colle n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier les notes saisies.</p>
    <h4>Absences et retards</h4>
    <p>Lorsqu'un élève est absent, il vaut mieux le noter dans la colle et le marquer absent. Il sera toujours possible de le noter plus tard. Si la colle est relevée par l'administration entre son absence et son rattrapage, la note reste modifiable.</p>
    <p>Les élèves ne peuvent pas avoir deux notes par semaine dans la même matière&nbsp;: il est toujours préférable de noter le jour initialement prévu dans le colloscope plutôt que le jour de rattrapage le cas échéant.</p>
  </div>
  
<?php } else { ?>

  <div id="aide-page">
    <h3>Aide et explications</h3>
    <p>Il est possible ici consulter les notes et heures de colles que vous et vos colleurs avez déclarées, de modifier les vôtres en cliquant sur le bouton <span class="icon-edite"></span>, de les supprimer en cliquant sur le bouton <span class="icon-supprime"></span> (une confirmation sera demandée) et d'en ajouter en cliquant sur le bouton <span class="icon-ajoute"></span>.</p>
    <h4>Saisie des notes</h4>
    <p>Vous pouvez saisir les notes par heure de colle ou par jour. La durée est précalculée automatiquement, mais vous pouvez modifier la valeur calculée avant de valider votre saisie. La durée de colle par élève, permettant le précalcul, est modifiable dans les préférences accessibles en cliquant sur le bouton <span class="icon-prefs"></span>.</p>
    <p>Vous ne pouvez mettre des notes de colles que dans les matières qui vous sont associées. Ces associations sont modifiables à la <a href="utilisateurs-matieres">gestion utilisateurs-matieres</a>.</p>
    <p>Un élève ne peut avoir qu'une seule note par matière et par semaine. Dans les formulaires d'ajout ou de modification des notes, les élèves ayant déjà eu une note par un autre colleur lors de la semaine concernée sont indiqués et non notables.</p> 
    <p>Une fois saisie, une colle peut être modifiée mais doit rester sur la même semaine, pour éviter les conflits de note.</p>
    <h4>Saisie des séances sans note</h4>
    <p>Vous pouvez également déclarer et modifier des séances de cours ou de travaux dirigés sans note, qui seront alors payés comme des colles si le lycée utilise ces saisies pour mettre au paiement vos heures. Les séances sans notes ne sont pas soumises aux mêmes restrictions de semaines que les colles.</p>
    <h4>Consultation globale des notes et déclarations</h4>
    <p>En tant que professeur associé à cette matière, vous avez la possibilité de consulter en ligne et en téléchargement l'ensemble des notes qui ont été saisies par vos colleurs. Les utilisateurs de type colleurs n'ont pas droit à cette fonctionnalité.</p>
    <p>En tant que professeur, vous avez aussi la possibilité de consulter l'ensemble des heures déclarées. Vous pouvez grâce à cela savoir si vos colleurs sont en retard sur leurs déclarations et suivre les relevés de l'administration pour chaque colleur le cas échéant. Vous pouvez aussi vérifier les totaux du nombre d'élèves notés et du nombre d'heures déclarées réalisées.</p>
    <p>Les boutons généraux permettent les actions suivantes&nbsp;:</p>
    <ul>
      <li>le bouton <span class="icon-prefs"></span> permet de modifier uniquement la durée de colle par élève.</li>
      <li>le bouton <span class="icon-download"></span> permet de récupérer le tableau récapitulatif global des notes en fichier de type <code>xls</code>, éditable par un logiciel tableur (Excel, LibreOffice Calc...)</li>
      <li>le bouton <span class="icon-voirtout"></span> permet de voir en ligne ce tableau récapitulatif. Il est alors possible de faire ressortir les notes de chaque colleur séparément à l'aide d'un menu déroulant. Cet affichage peut alors être supprimé par un clic sur le bouton <span class="icon-ferme"></span>.</li>
    </ul>
    <h4>Déclaration administrative</h4>
    <p>Si le lycée utilise ces saisies pour mettre au paiement vos heures, ce sont normalement les durées déclarées qui comptent. Il est ainsi possible de déclarer une heure pour un binôme. Attention, les textes officiels précisent que chaque heure de colle est indivisible, mais la question du budget global peut modifier cela. Il est nécessaire que l'ensemble des colleurs se comporte de façon identique à ce niveau et que l'équipe pédagogique soit cohérente. Pensez à en parler avec vos collègues et votre administration. L'administration a accès au détail des notes.</p>
    <p>Pour les colles relevées par l'administration, la date de la relève est inscrite dans le tableau et la colle n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier les notes saisies.</p>
    <p>Pour les séances sans note relevées par l'administration, la date de la relève est inscrite dans le tableau et la séance n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier le jour de la séance ou sa description.</p>
    <h4>Absences et retards</h4>
    <p>Lorsqu'un élève est absent, il vaut mieux le noter dans la colle et le marquer absent. Il sera toujours possible de le noter plus tard. Si la colle est relevée par l'administration entre son absence et son rattrapage, la note reste modifiable.</p>
    <p>Les élèves ne peuvent pas avoir deux notes par semaine dans la même matière&nbsp;: il est toujours préférable de noter le jour initialement prévu dans le colloscope plutôt que le jour de rattrapage le cas échéant.</p>
    <h4>Autres paramétrage</h4>
    <p>Toutes les semaines ne sont pas des semaines de colles et vous pouvez modifier quelles semaines sont associées ou non à des colles à la <a href="planning">gestion du planning</a>. Ce réglage est valable pour toutes les matières et est aussi utilisé pour afficher les programmes de colles. Ce réglage n'est pas utilisé pour les séances sans note.</p>
    <p>La durée de colle par élève, qui permet le précalcul immédiat de la durée des colles, est propre à chaque matière. Vous pouvez la modifier dans les préférences accessibles en cliquant sur le bouton <span class="icon-prefs"></span>.</p>
  </div>

  <div id="aide-edite">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet de modifier, supprimer ou ajouter des notes (ou la description pour les séances sans note) de la colle sélectionnée. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>La semaine déjà saisie n'est pas modifiable. Le jour de la colle est contraint dans cette semaine. L'heure est modifiable. Sa saisie n'est pas obligatoire. La restriction des semaines est supprimée pour les séances sans note.</p>
    <p>La saisie peut contenir autant d'élèves que vous le souhaitez, ou correspondre à une heure unique. La durée de la colle s'adapte automatiquement à la hausse. Elle reste modifiable aant validation.</p>
    <p>Si des groupes de colles ont été définis, vous pouvez cocher les cases correspondantes pour n'afficher que les notes déjà saisies et ces groupes-là. Les notes déjà saisies restent affichées lors du cochage/décochage des groupes, sauf lorsque vous venez de les modifier. Vous pouvez définir et modifier les groupes de colles à la <a href="groupes">gestion des groupes</a>. Ces modifications sont valables pour toutes les matières auxquelles sont associés les élèves. Créer des groupes n'est pas obligatoire pour pouvoir saisir des notes.</p>
    <p>Un élève ne peut avoir qu'une seule note par matière et par semaine. Les élèves ayant déjà eu une note par un autre colleur lors de la semaine concernée sont indiqués et non notables.</p>
    <p>Seules les notes non vides et visibles au moment où vous cliquez sur le bouton <span class="icon-ok"></span> sont effectivement envoyées pour l'enregistrement. Les notes que vous avez saisies mais qui ne sont pas affichées parce que vous avez décoché le groupe de colles correspondant ne sont pas envoyées.</p>
    <p>Les notes laissées vides ne sont pas enregistrées. En dehors des notes numériques, les choix possibles sont <em>Absent</em> et <em>Non noté</em>.</p>
    <h4>Déclaration administrative</h4>
    <p>Si le lycée utilise ces saisies pour mettre au paiement vos heures, ce sont normalement les durées déclarées qui comptent. Il est ainsi possible de déclarer une heure pour un binôme. Attention, les textes officiels précisent que chaque heure de colle est indivisible, mais la question du budget global peut modifier cela. Il est nécessaire que l'ensemble des colleurs se comportent de façon identique à ce niveau et que l'équipe pédagogique soit cohérente. Pensez à en parler avec vos collègues et votre administration. L'administration a accès au détail des notes.</p>
    <p>Pour les colles relevées par l'administration, la date de la relève est inscrite dans le tableau et la colle n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier les notes saisies.</p>
    <p>Pour les séances sans note relevées par l'administration, la date de la relève est inscrite dans le tableau et la séance n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier le jour de la séance ou sa description.</p>
    <h4>Absences et retards</h4>
    <p>Lorsqu'un élève est absent, il vaut mieux le noter dans la colle et le marquer absent. Il sera toujours possible de le noter plus tard. Si la colle est relevée par l'administration entre son absence et son rattrapage, la note reste modifiable.</p>
    <p>Les élèves ne peuvent pas avoir deux notes par semaine dans la même matière&nbsp;: il est toujours préférable de noter le jour initialement prévu dans le colloscope plutôt que le jour de rattrapage le cas échéant.</p>
  </div>

  <div id="aide-ajoute">
    <h3>Aide et explications</h3>
    <p>Ce formulaire permet d'ajouter des notes de colles ou de déclarer une séance sans note. Il sera validé par un clic sur <span class="icon-ok"></span>, et abandonné (donc supprimé) par un clic sur <span class="icon-ferme"></span>.</p>
    <p>Pour des notes de colles, vous devez commencer par choisir la semaine correspondant aux notes que vous allez saisir. Vous pouvez ensuite choisir le jour dans cette semaine. La saisie de l'heure n'est pas obligatoire.</p>
    <p>Si des groupes de colles ont été définis, vous pouvez cocher les cases correspondantes pour n'afficher que les notes déjà saisies et ces groupes-là. Les notes déjà saisies restent affichées lors du cochage/décochage des groupes, sauf lorsque vous venez de les modifier. Vous pouvez définir et modifier les groupes de colles à la <a href="groupes">gestion des groupes</a>. Ces modifications sont valables pour toutes les matières auxquelles sont associés les élèves. Créer des groupes n'est pas obligatoire pour pouvoir saisir des notes.</p>
    <p>La saisie peut contenir autant d'élèves que vous le souhaitez, ou correspondre à une heure unique. La durée de la colle s'adapte automatiquement à la hausse à chaque nouvelle note saisie. Elle reste modifiable avant validation.</p>
    <p>Un élève ne peut avoir qu'une seule note par matière et par semaine. Les élèves ayant déjà eu une note par un autre colleur lors de la semaine choisie sont indiqués et non notables.</p>
    <p>Seules les notes non vides et visibles au moment où vous cliquez sur le bouton <span class="icon-ok"></span> sont effectivement envoyées pour l'enregistrement. Les notes que vous avez saisies mais qui ne sont pas affichées parce que vous avez décoché le groupe de colles correspondant ne sont pas envoyées.</p>
    <p>Les notes laissées vides ne sont pas enregistrées. En dehors des notes numériques, les choix possibles sont <em>Absent</em> et <em>Non noté</em>.</p>
    <h4>Saisie des séances sans note</h4>
    <p>Vous pouvez également déclarer une séance de cours ou de travaux dirigés sans note, qui sera alors payée comme une colle si le lycée utilise ces saisies pour mettre au paiement vos heures. Les séances sans notes ne sont pas soumises aux mêmes restrictions de semaines que les colles.</p>
    <h4>Déclaration administrative</h4>   
    <p>Si le lycée utilise ces saisies pour mettre au paiement vos heures, ce sont normalement les durées déclarées qui comptent. Il est ainsi possible de déclarer une heure pour un binôme. Attention, les textes officiels précisent que chaque heure de colle est indivisible, mais la question du budget global peut modifier cela. Il est nécessaire que l'ensemble des colleurs se comportent de façon identique à ce niveau et que l'équipe pédagogique soit cohérente. Pensez à en parler avec vos collègues et votre administration. L'administration a accès au détail des notes.</p>
    <p>Pour les colles relevées par l'administration, la date de la relève est inscrite dans le tableau et la colle n'est plus modifiable sur sa durée ni supprimable. Il est par contre toujours possible de modifier les notes saisies.</p>
    <h4>Absences et retards</h4>
    <p>Lorsqu'un élève est absent, il vaut mieux le noter dans la colle et le marquer absent. Il sera toujours possible de le noter plus tard. Si la colle est relevée par l'administration entre son absence et son rattrapage, la note reste modifiable.</p>
    <p>Les élèves ne peuvent pas avoir deux notes par semaine dans la même matière&nbsp;: il est toujours préférable de noter le jour initialement prévu dans le colloscope plutôt que le jour de rattrapage le cas échéant.</p>
  </div>

<?php } ?>

  <p id="log"></p>
  
  <script type="text/javascript" src="js/datetimepicker.min.js"></script>
<?php
}

fin($autorisation==3 || $autorisation==5);
?>
