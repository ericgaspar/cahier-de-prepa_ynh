<?php
// Sécurité : ne doit pas être exécuté s'il n'est pas lancé par un script autorisé
if ( !defined('OK') )  exit();

// Variables fournies par le script appelant : $titre, $actuel, $mysqli

////////////
/// HTML ///
////////////
$lien = ( $actuel ) ? '' : '<br><a href=".">Retour à la page d\'accueil</a>';
debut($mysqli,$titre,'',$autorisation,$actuel);
$mysqli->close();
if ( isset($_SESSION['light']) && $_SESSION['light'] )
  echo <<<FIN
  
  <article>
    <a class="icon-ok" title="Valider"></a>
    <h3>Vérification de mot de passe</h3>
    <form>
      <p>Cette page contient des données sensibles. Vous êtes déjà connecté, mais vous devez fournir à nouveau votre mot de passe pour y accéder.</p>
      <input class="ligne" type="password" name="motdepasse" autofocus placeholder="Mot de passe">
    </form>
  </article>
FIN;
else  
  echo <<<FIN

  <div class="warning">Ce contenu est protégé. Vous devez vous connecter pour l'afficher.$lien</div>
  
  <article>
    <a class="icon-ok" title="Valider"></a>
    <h3>Se connecter</h3>
    <form>
      <p>Veuillez entrer votre identifiant et votre mot de passe&nbsp;:</p>
      <input class="ligne" type="text" name="login" autofocus placeholder="Identifiant">
      <input class="ligne" type="password" name="motdepasse" placeholder="Mot de passe">
      <p class="oubli"><label for="permconn">Se souvenir de moi</label><input type="checkbox" name="permconn" id="permconn" value="1">
      <p class="oubli"><a href="gestioncompte?oublimdp">Identifiant ou mot de passe oublié&nbsp;?</a></p>
      <p class="oubli"><a href="gestioncompte?creation">Créer un compte</a></p>
    </form>
  </article>
FIN;

echo <<<FIN

  <script type="text/javascript">
$( function() {
  // Envoi
  $('a.icon-ok').on("click",function () {
    $.ajax({url: 'ajax.php',
            method: "post",
            data: $('form').serialize()+'&connexion=1', 
            dataType: 'json', 
            el: '', 
            beforeSend: function() { $('#load').show(200); },
            complete: function() { $('#load').hide(200); },
            fonction: function(el) { location.reload(true); } 
          }).done( function(data) {
            // Si erreur d'identification, on reste bloqué là
            if ( data['etat'] == 'nok' )
              $('form p:first').html(data['message']).addClass('warning');
          });
  });
  // Envoi par appui sur Entrée
  $('input').on('keypress',function (e) {
    if ( e.which == 13 ) {
      $('a.icon-ok').click();
      return false;
    }
  });
});
  </script>

  <p id="log"></p>
    
FIN;
fin();
?>
