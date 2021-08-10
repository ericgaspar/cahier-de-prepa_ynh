/*//////////////////////////////////////////////////////////////////////////////
Éléments JavaScript pour l'utilisation de base de Cahier de Prépa

Copie partielle de edition.js
//////////////////////////////////////////////////////////////////////////////*/

// Notification de résultat de requête AJAX
function affiche(message,etat) {
  // Div d'affichage des résultats des requêtes AJAX
  if ( !$('#log').length )
    $('<div id="log"></div>').appendTo('body').hide().on("click", function() { $(this).hide(); });
  $('#log').removeClass().addClass(etat).html(message).append('<span class="icon-ferme"></span>').fadeIn().off("click").on("click",function() {
    window.clearTimeout(extinction);
    $(this).fadeOut(800);
  });
  extinction = window.setTimeout(function() { $('#log').fadeOut(800); },6000);
}


// Demande de reconnexion si connexion perdue 
// settings : paramètres du premier envoi ajax auquel le serveur a répondu login ou mdp
// light : true si connexion par cookie à compléter (mdp seul), false si connexion
//         complète nécessaire (login,mdp)
function reconnect(settings,light) {
  // Suppression d'une éventuelle fenêtre de la fonction popup existante
  $('#fenetre,#fenetre_fond').remove();
  var action = 'valider cette action';
  // Création de la fenêtre d'indentification
  if ( light )
    popup('<a class="icon-ok" title="Valider"></a><h3>Connexion nécessaire</h3>\
           <p>Votre connexion est active, mais vous devez saisir de nouveau votre mot de passe pour '+action+'.</p>\
           <form>\
           <p class="ligne"><label for="motdepasse">Mot de passe&nbsp;: </label><input type="password" name="motdepasse" id="motdepasse"></p>\
           </form>',true);
  else
    popup('<a class="icon-ok" title="Valider"></a><h3>Connexion nécessaire</h3>\
           <p>Votre connexion a été automatiquement désactivée. Vous devez vous connecter à nouveau pour '+action+'.</p>\
           <form>\
           <p class="ligne"><label for="login">Identifiant&nbsp;: </label><input type="text" name="login" id="login"></p>\
           <p class="ligne"><label for="motdepasse">Mot de passe&nbsp;: </label><input type="password" name="motdepasse" id="motdepasse"></p>\
           </form>',true);
  $('#fenetre input:first').focus();
  // Envoi (les données déjà envoyées settings.data ont été automatiquement sérialisées)
  $('#fenetre a.icon-ok').on("click",function () {
    $.ajax({url: settings.url,
            method: "post",
            data: $("#fenetre form").serialize()+'&'+settings.data,
            dataType: 'json',
            el: settings.el,
            fonction: settings.fonction
    }).done( function(data) {
      // Si erreur d'identification, on reste bloqué là
      if ( data['etat'] != 'mdpnok' )
        $('#fenetre,#fenetre_fond').remove();
     });
  });
  // À la suppression : ajout d'une notification
  $('#fenetre a.icon-ferme').on("click",function () {
    affiche('Modification non effectuée, connexion nécessaire','nok');
  });
  // Envoi par appui sur Entrée si input
  $('#fenetre input').on('keypress',function (e) {
    if (e.which == 13)  {
      $('#fenetre a.icon-ok').click();
      return false;
    }
  });
}

// Affichage par-dessus le contenu de la page
// * contenu : chaine en HTML qui sera utilisée comme contenu de la fenêtre
// * modal : si true, fenêtre modale (impossible de continuer à éditer la page)
function popup(contenu,modal) {
  // Suppression d'une éventuelle fenêtre de la fonction popup existante
  $('#fenetre,#fenetre_fond').remove();
  // Création
  var el = $('<article id="fenetre"></article>').appendTo('body').html(contenu).focus();
  if ( modal )
    $('<div id="fenetre_fond"></div>').appendTo('body').click(function() {
      $('#fenetre,#fenetre_fond').remove();
    });
  // Si fenêtre non modale, possibilité de l'épingler en haut de la page
  else
    $('<a class="icon-epingle" title="Épingler à la page"></a>').prependTo(el).on("click",function() {
      $('#fenetre_fond').remove();
      $(this).remove();
      el.removeAttr('id').insertBefore($('article,#calendrier,#parentsdoc+*').first());
    });
  // Bouton de fermeture
  $('<a class="icon-ferme" title="Fermer"></a>').prependTo(el).on("click",function() {
    el.remove();
    $('#fenetre_fond').remove();
  });
}

///////////////////
// Requêtes AJAX //
///////////////////
$(document).ajaxSend( function(ev,xhr,settings) {
              $('#load').show(200);
              // Sécurité anti XSS : Ajout du token CSRF
              if ( $('body').attr('data-csrf-token') != undefined )  {
                if ( settings.data.append )
                  settings.data.append('csrf-token',$('body').attr('data-csrf-token'));
                else
                  settings.data = 'csrf-token='+$('body').attr('data-csrf-token')+'&'+settings.data;
              }
            })
           .ajaxStop( function() {
              $('#load').hide(200);
            })
           .ajaxSuccess( function(ev,xhr,settings) {
              var data = xhr.responseJSON;
              switch ( data['etat'] ) {
                // Si ok, on l'affiche dans le log et on lance la "fonction" de mise à jour de l'affichage
                case 'ok':
                  $('body').data('nepassortir',false);
                  affiche(data['message'],'ok');
                  settings.fonction(settings.el);
                  break;
                // Si non ok, on l'affiche dans le log et on ne fait rien de plus
                case 'nok':
                  affiche(data['message'],'nok');
                  break;
                // Si 'login' : il faut se reconnecter
                // Si 'mdp' : il faut compléter une connexion light obtenue par cookie
                case 'login':
                case 'mdp':
                  reconnect(settings,data['etat']=='mdp');
                  break;
                // Si 'recupok' : récupération de données (pour l'échange de Cahier)
                case 'recupok':
                  settings.afficheform(data);
              }
            });

////////////////////////////////////////////////////////////////////////////
// Modification des éléments (nécessite le chargement complet de la page) //
////////////////////////////////////////////////////////////////////////////
$( function() {

  // Connexion
  $('a.icon-connexion').on('click',function(e) {
    // Suppression du menu si visible en mode mobile
    if ( $('#menu').hasClass('visible') )
      $('.icon-menu').click();

    // Création de fenêtre
    var el = $('<div id="fenetre"></div>').appendTo('body').html('\
<a class="icon-ferme" title="Fermer"></a><a class="icon-ok" title="Valider"></a><h3>Connexion</h3>\
<form>\
  <p>Veuillez entrer votre identifiant et votre mot de passe&nbsp;:</p>\
  <input class="ligne" type="text" name="login" placeholder="Identifiant">\
  <input class="ligne" type="password" name="motdepasse" placeholder="Mot de passe">\
  <p class="oubli"><label for="permconn">Se souvenir de moi</label><input type="checkbox" name="permconn" id="permconn" value="1">\
  <p class="oubli"><a href="gestioncompte?oublimdp">Identifiant ou mot de passe oublié&nbsp;?</a></p>\
  <p class="oubli"><a href="gestioncompte?creation">Créer un compte</a></p>\
</form>');
    // Fond grisé
    $('<div id="fenetre_fond"></div>').appendTo('body').click(function() {
      $('#fenetre,#fenetre_fond').remove();
    });
    // Bouton de fermeture
    $('#fenetre a.icon-ferme').on("click",function() {
      $('#fenetre,#fenetre_fond').remove();
    });
    // Envoi
    $('#fenetre a.icon-ok').on("click",function () {
      $.ajax({url: 'ajax.php',
              method: "post",
              data: $('#fenetre form').serialize()+'&connexion=1', 
              dataType: 'json', 
              el: '', 
              fonction: function(el) { location.reload(true); } 
            });
    });
    // Envoi par appui sur Entrée
    $('#fenetre input').on('keypress',function (e) {
      if ( e.which == 13 ) {
        $('#fenetre a.icon-ok').click();
        return false;
      }
    });
    // Focus initial
    $('#fenetre form input:first').focus();
  });

  // Déconnexion  
  $('a.icon-deconnexion').on('click',function(e) {
    $.ajax({url: 'ajax.php', method: "post", data: { action:'deconnexion' }, dataType: 'json', el: '', fonction: function(el) { location.reload(true); } });
  });

  // Affichage des informations sur le flux RSS
  $('a.icon-rss').on("click", function() { popup($('#aide-rss').html(),false); });

  // Menu mobile
  $('.icon-menu').on("click", function(e) {
    e.stopPropagation();
    $('#menu').toggleClass('visible');
    if ( $('#menu').hasClass('visible') )  {
      $('<div id="menu_fond"></div>').appendTo('body');
      $('#menu,#menu_fond').on("click", function() {
        $('#menu_fond').remove();
        $('#menu').removeClass('visible');
      });
    }
    else 
      $('#menu_fond').remove();
  });

  // Pop-up pour les événements de l'agenda
  $('.evnmt').on("click", function() {
    var donnees = evenements[this.id.substr(1)];
    var el = $('<div id="fenetre"></div>').appendTo('body').html('<h3>'+donnees.titrebis+'</h3>\n<h3 style="margin-bottom: 1em;">'+donnees.date+'</h3>\n<p>'+donnees.texte+'</p>').focus();
    $('<div id="fenetre_fond"></div>').appendTo('body').click(function() {
      $('#fenetre,#fenetre_fond').remove();
    });
    $('<a class="icon-ferme" title="Fermer"></a>').prependTo(el).on("click",function() {
      el.remove();
      $('#fenetre_fond').remove();
    });
  });
  
  // Changement de Cahier si interface globale et si compte global existant contenant au moins un autre Cahier
  $('a.icon-echange').on("click",function() {
    $.ajax({url: 'recup.php',
            method: "post",
            data: { action:'compteglobal' },
            dataType: 'json',
            afficheform: function(data) {
              popup('<h3>Changer de Cahier</h3><div></div><p>Cette liste est éditable sur l\'<a href="/connexion/">interface de connexion globale</a>.</p>',true);
              var f = $('#fenetre');
              // Récupération des valeurs et écriture 
              var cahiers = data['cahiers'];
              for ( var rep in cahiers )
                $('div',f).attr('id','cahiers').append('<a href="/'+rep+'/">'+cahiers[rep]+'</a>');
            }
    });
  });
  
  // Envoi de documents pour les élèves (Transfert de copies)
  $('.devoir button.icon-ok').on("click", function(e) {
    e.preventDefault();
    // Test de connexion
    // Si reconnect() appelée, le paramètre connexion sert à obtenir un retour
    // en état ok/nok pour affichage si nok. Si ok, on réécrit le message. 
    $.ajax({url: 'copies.php',
            method: "post",
            data: 'connexion=1',
            dataType: 'json',
            el: $(this),
            fonction: function(el) {
              el.removeClass('clignote');
              // Pour ne pas rater le chargement
              $('#log').hide();
              $('#load').html('<p class="clignote">Transfert en cours<span></span></p><img src="js/ajax-loader.gif">');
              // Envoi réel du fichier ou des données
              var form = el.parent().parent();
              form.append('<input type="hidden" name="action" value="ajout-copie"><input type="hidden" name="id" value="'+form.parent().data('id')+'">');
              var data = new FormData(form[0]);
              // Envoi
              $.ajax({url: 'ajax.php',
                      xhr: function () { // Évolution du transfert
                        var xhr = $.ajaxSettings.xhr();
                        if (xhr.upload)
                          xhr.upload.addEventListener('progress', function (e) {
                            if (e.lengthComputable)
                              $('#load span').html(' - ' + Math.round(e.loaded / e.total * 100) + '%');
                          }, false);
                        return xhr;
                      },
                      method: "post",
                      data: data,
                      dataType: 'json',
                      contentType:false,
                      processData:false,
                      el: '',
                      fonction: function(el) {
                        location.reload(true);
                      }
              });
            }
    });
  });
  // Téléchargement des copies et documents associés
  $('.devoir a span').parent().css('cursor','pointer').on("click", function() {
    $(this).siblings('.icon-download').click();
  });
  $('.devoir a.icon-download').on("click", function() {
    // Test de connexion : on fait le téléchargement en get, donc on doit
    // être connecté en connection non light avant
    $.ajax({url: 'copies.php',
            method: "post",
            data: 'connexion=1',
            dataType: 'json',
            el: $(this),
            fonction: function(el) {
              // Si connexion correcte, pas d'affichage dans le div de log
              $('#log').hide();
              window.location.href = 'copies.php?dl='+el.data('id');
            }
    });
  });
  // Suppression de copies transférées
  $('.devoir a.icon-supprime').on("click", function() {
    var bouton = $(this);
    // Demande de confirmation
    popup('<h3>Demande de confirmation</h3><p>Vous allez supprimer cette copie. Votre professeur ne pourra plus la récupérer après cela. Cette action n\'est pas annulable.</p><p class="confirmation"><button class="icon-ok"></button>&nbsp;&nbsp;&nbsp;<button class="icon-annule"></button></p>',true);
    $('#fenetre .icon-ok').on("click", function () {
      // Envoi
      $.ajax({url: 'ajax.php',
              method: "post",
              data: { action:'suppr-copie', id:bouton.data('id') },
              dataType: 'json',
              el: bouton,
              fonction: function(el) {
                el.parent().remove();
              }
      });
      $('#fenetre,#fenetre_fond').remove();
    });
    $('#fenetre .icon-annule').on("click",function () {
      $('#fenetre,#fenetre_fond').remove();
    });
  });

  /////////////////////////////
  // Ne pas quitter avec un fichier non transféré
  $('body').data('nepassortir',false);
  $('input[type="file"]').on('change',function(e) { 
    $('body').data('nepassortir',true); $(this).off(e);
    // Clignotement pour les étourdis
    $(this).next().addClass('clignote');
  });
  window.addEventListener('beforeunload', function (e) { if ( $('body').data('nepassortir') )  { e.preventDefault(); e.returnValue = ''; } });
  
});
