/*//////////////////////////////////////////////////////////////////////////////
Éléments JavaScript pour l'administration de Cahier de Prépa

* Les éléments de classe "editable" peuvent être transformés en mode édition par
la fonction transforme, automatiquement exécutée. L'attribut data-id est alors
nécessaire, et doit être de la forme table|champ|id (table=action).
* Les éléments de classe "edithtml" sont considérés comme contenant des
informations présentées en html : la fonction textareahtml crée alors un élément
de type textarea, un élément de type div éditable (contenteditable=true), et
ajoute des boutons facilitant les modifications. La textarea et la div éditable
sont alternativement visibles, à la demande.
* Les liens de classe icon-aide lancent automatiquement une aide par la fonction
popup. Le contenu est récupéré dans les div de classe aide-xxx où xxx est la
valeur de l'attribut data-id du lien (ces div sont automatiquement non affichées
en css).
//////////////////////////////////////////////////////////////////////////////*/


///////////////
// Affichage //
///////////////

// Notification de résultat de requête AJAX
function affiche(message,etat) {
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
  if ( settings.url == 'recup.php' )
    switch ( settings.data['action'] )  {
      case 'prefs':  var action = 'récupérer les préférences de cet utilisateur'; break;
      case 'docs' :  var action = 'récupérer la liste des répertoires et documents disponibles'; break;
      case 'copies': var action = 'actualiser la liste des documents disponibles';
    }
  else  {
    var action = 'valider cette action';
    // Pour ne pas créer d'erreur si afficheform n'existe pas
    // afficheform est la fonction d'affichage du formulaire dans le cas de 
    // récupération de données sur recup.php
    settings.afficheform = Function.prototype ;
  }
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
            afficheform: settings.afficheform,
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

// Demande de confirmation, pour une suppression par exemple
function confirmation(question, element, action) {
  popup('<h3>Demande de confirmation</h3><p>'+question+'</p><p class="confirmation"><button class="icon-ok"></button>&nbsp;&nbsp;&nbsp;<button class="icon-annule"></button></p>',true);
  $('#fenetre .icon-ok').on("click", function () {
    action(element);
    $('#fenetre,#fenetre_fond').remove();
  });
  $('#fenetre .icon-annule').on("click",function () {
    $('#fenetre,#fenetre_fond').remove();
  });
}

// Fonction de pliage/dépliage vertical des lignes dans un tableau
function plie() {
  var lignes = $(this).parent().parent().nextUntil('.categorie');
  if ( $(this).hasClass('icon-deplie') )  {
    lignes.children().wrapInner('<div></div>').addClass('cache');
    lignes.find('div').slideUp(1000); 
    window.setTimeout(function() { 
      lignes.hide().children().html(function(){ return $(this).children().html(); });
    },1000);
  }
  else  {
    lignes.show();
    lignes.children().wrapInner('<div style="display:none;"></div>');
    lignes.find('div').slideDown(1000);
    window.setTimeout(function() { 
      lignes.children().html(function(){ return $(this).children().html(); }).removeClass('cache'); 
    },1000);
  }
  $(this).toggleClass('icon-plie icon-deplie');
}

//////////////////////////////////////////////////////////////
// Édition de textes : facilités de modication des éléments //
//////////////////////////////////////////////////////////////

// Transformation d'une textarea pour l'édition de code HTML
// Ajoute des boutons d'édition et la possibilité de commuter l'affichage avec
// une div éditable. Fonction à appliquer sur les textarea de classe edithtml
$.fn.textareahtml = function() {
  this.each(function() {
    var ta = $(this);
    var placeholder = this.getAttribute('placeholder');
    // Modification du placeholder
    this.setAttribute('placeholder',placeholder+'. Formattage en HTML, balises visibles.');
    // Ajout d'éléments : boutons, div éditable
    var ce = $('<div contenteditable="true" placeholder="'+placeholder+'"></div>').insertAfter(ta.before(boutons)).hide();
    var boutonretour = ta.prev().children(".icon-retour");
    // Classe 'ligne' aux boutons et à la div éditable si c'est le cas du textarea
    if ( ta.hasClass('ligne') ) {
      ce.addClass('ligne');
      ta.prev().addClass('ligne');
    }
    // Retour à la ligne par Entrée, nettoyage au copié-collé
    ta.on("keypress",function(e) {
        if (e.which == 13)
          this.value = nettoie(this.value);
      })
      .on("paste cut",function() {
        var el = this;
        setTimeout(function() {
          el.value = nettoie(el.value);
        }, 100);
      });
    ce.on("keypress",function(e) {
        if (e.which == 13)
          boutonretour.click();
      })
      .on("paste cut",function() {
        var el = this;
        setTimeout(function() {
          el.innerHTML = nettoie(el.innerHTML)+'<br>';
        }, 100);
      });
    // Clic bouton "nosource" : passage de textarea à div
    ta.prev().children('.icon-nosource').on("click", function(e) {
      e.preventDefault();
      // Modification des visibilités
      ta.hide();
      ce.show().css("min-height",ta.outerHeight());
      $(this).hide().prev().show();
      // Nettoyage et synchronisation (change -> mise à jour du placeholder)
      ce.focus().html(nettoie(ta.val())).change();
      // Mise en place du curseur à la fin
      if ( window.getSelection ) {
        var r = document.createRange();
        r.selectNodeContents(ce[0]);
        r.collapse(false);
        var s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
      }
      else {
        var r = document.body.createTextRange();
        r.moveToElementText(ce[0]);
        r.collapse(false);
        r.select();
      }
    });
    // Clic bouton "source" : passage de div à textarea
    ta.prev().children('.icon-source').on("click", function(e) {
      e.preventDefault();
      // Modification des visibilités
      ce.hide(0);
      ta.show(0).css("height",ce.height());
      $(this).hide().next().show();
      // Nettoyage et synchronisation (change -> mise à jour du placeholder)
      ta.focus().val(nettoie(ce.html()));
    }).hide();
    // Clic bouton aide
    ta.prev().children('.icon-aide').on("click",function(e) {
      e.preventDefault();
      aidetexte();
    });
    // Autres clics
    ta.prev().children().not('.icon-nosource,.icon-source,.icon-aide').on("click",function(e) {
      e.preventDefault();
      window['insertion_'+this.className.substring(5)]($(this));
    });
  });
}

// Édition "en place"
// Fonction à appliquer sur les éléments de classe editable
$.fn.editinplace = function() {
  this.each(function() {
    var el = $(this);
    // Enregistrement de la valeur originale, pour rétablissement si abandon
    el.data('original',( el.is('h3') ) ? el.text() : el.html());
    // Transformation
    $('<a class="icon-edite" title="Modifier"></a>').appendTo(el).on("click",transforme);
  });
}

// Transformation d'un élément h3/div de classe editable en input/textarea
function transforme() {
  var el = $(this).parent().addClass('avecform');
  // Création d'une textarea si div, d'un input sinon
  if ( el.is('div') )  {
    // Cas des informations et colles : case à cocher pour maj de la date de publi
    if ( el.hasClass('majpubli') )
      el.html('<form><textarea name="val" rows="'+(el.data('original').split(/\r\n|\r|\n/).length+3)+'"></textarea><p class="ligne"><label for="publi">Publier en tant que mise à jour&nbsp;: </label><input type="checkbox" id="publi" name="publi" value="1" checked></p></form>');
    else
      el.html('<form><textarea name="val" rows="'+(el.data('original').split(/\r\n|\r|\n/).length+3)+'"></textarea></form>');
    // Ne pas quitter avec un textarea plein
    $('textarea:visible').on('change',function(e) { $('body').data('nepassortir',true); $(this).off(e); });
  }
  else
    el.html('<form class="edition" onsubmit="$(this).children(\'a.icon-ok\').click(); return false;"><input type="text" name="val" value=""></form>');
  // Identifiant de l'input ou de la textarea
  var input = el.find('[name="val"]').val(el.data('original')).attr('placeholder',el.attr('placeholder'));
  // Boutons si edithtml
  if ( el.hasClass('edithtml') )
    input.textareahtml();
  // Envoi
  $('<a class="icon-ok" title="Valider"></a>').appendTo(el.children()).on("click",function() {
    // Identifiant de l'entrée à modifier : action|champ|id
    var id = el.data('id').split('|');
    // Nettoyage et synchronisation si besoin
    if (el.hasClass('edithtml') )
      input.val(nettoie( ( input.is(':visible') ) ? input.val() : input.next().html() ));
    // Envoi
    $.ajax({url: 'ajax.php',
            method: "post",
            data: { action:id[0], champ:id[1], id:id[2], val:input.val(), publi:el.find(':checkbox').is(':checked') || undefined },
            dataType: 'json',
            el: el,
            fonction: function(el) {
              var val = el.find('[name="val"]').val();
              el.removeClass('avecform').html(val).data('original',val);
              $('<a class="icon-edite" title="Modifier"></a>').appendTo(el).on("click",transforme);
            }
    });
  });
  // Annulation
  $('<a class="icon-annule" title="Annuler"></a>').appendTo(el.children()).on("click",function() {
    el.removeClass('avecform').html(el.data('original'));
    $('<a class="icon-edite" title="Modifier"></a>').appendTo(el).on("click",transforme);
  });
  // Récupération du focus avec curseur à la fin
  input.focus().val( ( el.hasClass('edithtml') ) ? nettoie(input.val()) : input.val() );
}

// Édition "en place" des propriétés des éléments des cahiers de texte
// Fonction à appliquer sur les éléments de classe titrecdt
$.fn.editinplacecdt = function() {
  this.each(function() {
    // Enregistrement de la valeur originale, pour rétablissement si abandon
    $(this).wrapInner('<span></span>').data('original',$(this).text());
    // Transformation
    $('<a class="icon-edite" title="Modifier"></a>').appendTo($(this)).on("click",transformecdt);
  });
}

// Transformation d'un élément de classe titrecdt pour son édition
function transformecdt() {
  var el = $(this).parent();
  // Création du formulaire
  $('.icon-edite',el).remove();
  var form = $('<form class="titrecdt"></form>').insertBefore(el.parent().children('div')).html($('#form-cdt').html());
  // Création de l'identifiant des champs à partir du name
  $('input, select',form).attr('id',function(){ return this.getAttribute('name'); });
  // Récupération des valeurs et modification initiale du formulaire
  var valeurs = el.data('donnees');
  for ( var cle in valeurs )
    $('#'+cle).val(valeurs[cle]);
  // Mise en place des facilités  
  form.init_cdt_boutons();
  // Ne pas quitter avec un textarea plein
  $('textarea',form).on('change',function(e) { $('body').data('nepassortir',true); $(this).off(e); });
  // Mise à jour du titre si modification
  $('input,#demigroupe',form).on('change keyup', function() {
    var t = new Date($('#jour').val().replace(/(.{2})\/(.{2})\/(.{4})/,function(tout,x,y,z) {return z+'-'+y+'-'+x; }));
    var dg = ( $('#demigroupe').val() == 1 ) ? ' (en demi-groupe)' : '';
    switch ( parseInt(seances[$('#tid').val()]) ) {
      case 0:
        var titre = jours[t.getDay()]+' '+$('#jour').val()+' à '+$('#h_debut').val()+' : '+$('#tid option:selected').text()+dg;
        break;
      case 1:
        var titre = jours[t.getDay()]+' '+$('#jour').val()+' de '+$('#h_debut').val()+' à '+$('#h_fin').val()+' : '+$('#tid option:selected').text()+dg;
        break;
      case 2:
        var titre = jours[t.getDay()]+' '+$('#jour').val()+' : '+$('#tid option:selected').text()+' pour le '+$('#pour').val()+dg;
        break;
      case 3:
        var titre = jours[t.getDay()]+' '+$('#jour').val()+' : '+$('#tid option:selected').text()+dg;
        break;
      case 4:
        var titre = jours[t.getDay()]+' '+$('#jour').val();
        break;
      case 5:
        var titre = '[Entrée hebdomadaire]';
    }
    $('span',el).text(titre);
  });
  // Envoi
  $('<a class="icon-ok" title="Valider"></a>').appendTo(el).on("click",function() {
    // Identifiant de l'entrée à modifier : action|id
    var id = el.parent().data('id').split('|');
    // Envoi
    $.ajax({url: 'ajax.php',
            method: "post",
            data: 'action=cdt-elems&id='+id[1]+'&'+form.serialize(),
            dataType: 'json',
            el: el,
            fonction: function(el) {
              var form = el.siblings('form');
              el.data('original',$('span',el).text()).data('donnees',{tid:$('#tid').val(),jour:$('#jour').val(),h_debut:$('#h_debut').val(),h_fin:$('#h_fin').val(),pour:$('#pour').val(),demigroupe:$('#demigroupe').val()});
              form.remove();
              $('a',el).remove();
              $('<a class="icon-edite" title="Modifier"></a>').appendTo(el).on("click",transformecdt);
            }
    }).done( function(data) {
      if ( ( data['etat'] == 'ok' ) && ( data['reload'] == 'oui' ) )
        location.reload(true);
    });
  });
  // Annulation
  $('<a class="icon-annule" title="Annuler"></a>').appendTo(el).on("click",function() {
    form.remove();
    $('span',el).html(el.data('original'));
    $('a',el).remove();
    $('<a class="icon-edite" title="Modifier"></a>').appendTo(el).on("click",transformecdt);
  });
}

// Nettoyage des contenus de textarea pour l'édition de code HTML
function nettoie(html) {
  // Suppression du span cdptmp ajouté par la fonction insert()
  if ( html.indexOf('cdptmp') > 0 ) {
    // Suppression des spans non vides
    var tmp = $('<div>'+html+'</div>');
    tmp.find('.cdptmp').contents().unwrap();
    html = tmp.html();
    // Suppression des spans restants, vides
    if ( html.indexOf('cdptmp') > 0 )
      html = html.replace(/<span class="cdptmp"><\/span>/g,'');
  }
  // Autres modifications
  return html.replace(/(<\/?[A-Z]+)([^>]*>)/g, function (tout,x,y) { return x.toLowerCase()+y; })  // Minuscules pour les balises
             .replace(/[\r\n ]+/g,' ')  // Suppression des retours à la ligne et espaces multiples
             .replace(/(<br>)+[ ]?<\/(p|div|li|h)/g, function (tout,x,y) { return '</'+y; }).replace(/<br>/g, '<br>\n')  // Suppression des <br> multiples
             .replace(/<(p|div|li|h)/g, function (x) { return '\n'+x; })  // Retour à la ligne avant <p>, <div>, <li>, <h*>
             .replace(/<\/(p|div|li|h.)>/g, function (x) { return x+'\n'; }) // Retour à la ligne après </p>, </div>, </li>, </h*>
             .replace(/<\/?(ul|ol)[^>]*>/g, function (x) { return '\n'+x+'\n'; })  // Retour à la ligne avant et après <ul>, </ul>, <ol>, </ol>
             // Formattage en paragraphe d'une ligne finie par <br> et non commencée par <p>, <div>, <ul>, <ol>, <li>, <h*>
             .replace(/^(?!(<p|<div|<ul|<ol|<li|<h))(.+)<br>$/gm, function (tout,x,y) { return '<p>'+y+'</p>'; })
             // Formattage en paragraphe d'une ligne non commencée par <p>, <div>, <ul>, <ol>, <li>, <h*>, non fermée par </p>, </div>, </ul>, </ol>, </li>, </h*>
             .replace(/^(?!(<(p|div|ul|ol|li)))[ ]?(.+)[ ]?$/gm, function (t,x,y,z) { return ( z.match(/.*(p|div|ul|ol|li|h.)>$/) ) ? z : '<p>'+z+'</p>'; })
             // Suppression des lignes contenant une seule balise <p>, </p>, <div>, </div>, <h*>, </h*>, <br> (avec éventuellement des espaces autour)
             // Suppression des lignes de paragraphes/div/titres vides (contenant des espaces ou un <br>)
             .replace(/^[ ]?(<\/?(br|p|div|h.)>){0,2}[ ]?(<\/(p|div|h.)>)?[ ]?$/gm,'').replace(/^\n/gm,'')
             // Indentation devant <li>
             .replace(/<li/g,'  <li');
}

// Insertion lancée par les boutons.
// Fonction générique pour l'utilisation des boutons insérés pour les textarea
// d'édition en HTML. Arguments :
//  * el = identifiant jquery du bouton
//  * debut = ce qui sera inséré avant la sélection
//  * fin = ce qui sera inséré après la sélection
//  * milieu = ce qui sera inséré à la place de la sélection (si renseigné)
// Après insertion, la sélection est conservée (ou placée sur milieu)
function insert(el,debut,fin,milieu) {
  // Récupération et modification du contenu
  var contenant = el.parent().siblings('textarea,[contenteditable]').filter(':visible')[0];
  if ( !contenant.hasAttribute('data-selection') )
    marqueselection(el);
  var texte = ( milieu === undefined ) ? debut+'Í'+contenant.getAttribute('data-selection')+'Ì'+fin : debut+'Í'+milieu+'Ì'+fin;
  var contenu = nettoie(contenant.getAttribute('data-contenu').replace(/Í.*Ì/,texte));
  // Affichage
  if ( contenant.tagName == 'TEXTAREA' )
    contenant.value = contenu.replace(/[ÍÌ]/g,'');
  else
    contenant.innerHTML = contenu.replace(/[ÍÌ]/g,'');
  // Suppression des attributs liés à la sélection
  marqueselection(el,true);
  // Resélection
  // Cas des textarea, navigateurs modernes
  if ( ( contenant.tagName == 'TEXTAREA' ) && ( contenant.selectionStart !== undefined ) ) {
    contenant.selectionStart = contenu.indexOf('Í');
    contenant.selectionEnd = contenu.indexOf('Ì')-1;
    contenant.focus();
  }
  // Cas des textarea et divs éditables, navigateurs anciens (IE<9)
  else if ( document.selection ) {
    // On doit compter les caractères de texte uniquement : suppression des
    // balises si contenant est div editable
    if ( contenant.tagName != 'TEXTAREA' )
      contenu = contenu.replace(/(<([^>]+)>)[\n]*/g,'');
    range = document.body.createTextRange();
    range.moveToElementText(contenant);
    range.collapse(true); 
    range.moveEnd("character", contenu.indexOf('Ì')-1);
    range.moveStart("character", contenu.indexOf('Í'));
    range.select();
  }
  // Cas des divs éditables, navigateurs modernes
  else if (window.getSelection) {
    contenant.innerHTML = contenu.replace('Í','<span class="cdptmp">').replace('Ì','</span>')+'<br>';
    selection = window.getSelection();
    range = document.createRange();
    range.selectNodeContents($(contenant).find('.cdptmp')[0]);
    selection.removeAllRanges();
    selection.addRange(range);
    contenant.focus();
  }
}

// Marquage de la sélection
// Crée deux attributs sur la textarea/div éditable visible :
//  * data-selection = la sélection en cours
//  * data-contenu = le contenu entier de la textarea/div éditable, où la
// sélection est encadrée par Í (début) et Ì (fin)
// Retourne le texte effectivement sélectionné. Arguments : 
//  * el = identifiant jquery du bouton "appelant" (via la fonction insert)
//  * efface = si true, on efface simplement les attributs
function marqueselection(el,efface) {
  var contenant = el.parent().siblings('textarea,[contenteditable]').filter(':visible')[0];
  if ( efface ) {
    contenant.removeAttribute('data-selection');
    contenant.removeAttribute('data-contenu');
    return true;
  }
  var original = ( contenant.tagName == 'TEXTAREA' ) ? contenant.value : contenant.innerHTML;
  var sel = '';
  // Cas des textarea, navigateurs modernes
  if ( ( contenant.tagName == 'TEXTAREA' ) && ( contenant.selectionStart !== undefined ) ) {
    contenant.focus();
    sel = contenant.value.substring(contenant.selectionStart,contenant.selectionEnd);
    contenant.value = contenant.value.substr(0,contenant.selectionStart)+'Í'+sel+'Ì'+contenant.value.substring(contenant.selectionEnd);
  }
  // Cas des divs éditables, navigateurs modernes
  else if ( window.getSelection ) {
    var range = window.getSelection().getRangeAt(0);
    if ( ( contenant == range.commonAncestorContainer ) || $.contains(contenant,range.commonAncestorContainer) ) {
      var sel = window.getSelection().toString();
      range.deleteContents();
      range.insertNode(document.createTextNode('Í'+sel+'Ì'));
    }
  }
  // Cas des navigateurs anciens (IE<9)
  else {
    var range = document.selection.createRange();
    if ( ( contenant == range.parentElement() ) || $.contains(contenant,range.parentElement()) ) {
      var sel = document.selection.createRange().text;
      document.selection.createRange().text = 'Í'+sel+'Ì';
    }
  }
  // Remise à l'orgine
  if ( contenant.tagName == 'TEXTAREA' ) {
    var contenu = contenant.value;
    contenant.value = original;
  }
  else {
    var contenu = contenant.innerHTML;
    $(contenant).html(original); // Bug IE8 : modification de innerHTML impossible
  }
  // Par défaut : sélection à la fin
  if ( contenu.indexOf('Ì') < 0 )
    contenu = contenu + 'ÍÌ';
  // Enregistrement et retour
  contenant.setAttribute('data-selection',sel);
  contenant.setAttribute('data-contenu',contenu);
  return sel;
}

////////////////////////////////////////////////////////////
// Édition de textes : boutons pour les textarea.edithtml //
////////////////////////////////////////////////////////////

// Définition des boutons
var boutons = '\
<p class="boutons">\
  <button class="icon-titres" title="Niveaux de titres"></button>\
  <button class="icon-par1" title="Paragraphe"></button>\
  <button class="icon-par2" title="Paragraphe important"></button>\
  <button class="icon-par3" title="Paragraphe très important"></button>\
  <button class="icon-retour" title="Retour à la ligne"></button>\
  <button class="icon-gras" title="Gras"></button>\
  <button class="icon-italique" title="Italique"></button>\
  <button class="icon-souligne" title="Souligné"></button>\
  <button class="icon-omega" title="Insérer une lettre grecque"></button>\
  <button class="icon-sigma" title="Insérer un signe mathématique"></button>\
  <button class="icon-exp" title="Exposant"></button>\
  <button class="icon-ind" title="Indice"></button>\
  <button class="icon-ol" title="Liste énumérée"></button>\
  <button class="icon-ul" title="Liste à puces"></button>\
  <button class="icon-lien1" title="Lien vers un document du site"></button>\
  <button class="icon-lien2" title="Lien internet"></button>\
  <button class="icon-tex" title="LATEX!"></button>\
  <button class="icon-source" title="Voir et éditer le code html"></button>\
  <button class="icon-nosource" title="Voir et éditer le texte formaté"></button>\
  <button class="icon-aide" title="Aide pour cet éditeur de texte"></button>\
</p>';

// Fonctions lancées par les boutons appelant une fenêtre par la fonction popup
function insertion_titres(el) {
  popup('<a class="icon-ok" title="Valider"></a><h3>Insertion d\'un titre</h3>\
  <p>Choisissez le type du titre ci-dessous. Vous pouvez éventuellement modifier le texte (ou pourrez le faire ultérieurement). Il est conseillé d\'utiliser des titres de niveau 2 pour les titres dans les programmes de colle.</p>\
  <input type="radio" name="titre" id="t3" value="3" checked><h3><label for="t3">Titre de niveau 1 (pour les I,II...)</label></h3><br>\
  <input type="radio" name="titre" id="t4" value="4"><h4><label for="t4">Titre de niveau 2 (pour les 1,2...)</label></h4><br>\
  <input type="radio" name="titre" id="t5" value="5"><h5><label for="t5">Titre de niveau 3 (pour les a,b...)</label></h5><br>\
  <input type="radio" name="titre" id="t6" value="6"><h6><label for="t6">Titre de niveau 4</label></h6><br>\
  <p class="ligne"><label for="texte">Texte&nbsp;: </label><input type="text" id="texte" value="'+marqueselection(el)+'" size="80"></p>\
  <hr><h3>Aperçu</h3><div id="apercu"></div>',true);
  // Modification automatique de l'aperçu
  $('#fenetre input').on("click keyup", function () {
    var balise = 'h'+$("[name='titre']:checked").val();
    $('#apercu').html('<'+balise+'>'+( ( $('#texte').val().length ) ? $('#texte').val() : 'Texte du titre' )+'</'+balise+'>');
  }).first().keyup();
  // Insertion par appui sur Entrée
  $('#texte').on("keypress",function(e) {
    if ( e.which == 13 )
      $('#fenetre a.icon-ok').click();
  }).focus();
  // Insertion
  $('#fenetre a.icon-ok').on("click",function () {
    var balise = 'h'+$("[name='titre']:checked").val();
    insert(el,'<'+balise+'>','</'+balise+'>',$('#texte').val());
    $('#fenetre,#fenetre_fond').remove();
  });
  // Suppression des attributs liés à la sélection lors de la fermeture
  $('#fenetre a.icon-ferme,#fenetre_fond').on("click",function () {
    marqueselection(el,true);
  });
}

function insertion_omega(el) {
  popup('<h3>Insertion d\'une lettre grecque</h3>\
  <p>Cliquez sur la lettre à insérer&nbsp;:</p>\
  <button>&alpha;</button> <button>&beta;</button> <button>&gamma;</button> <button>&Delta;</button> <button>&delta;</button> <button>&epsilon;</button> <button>&eta;</button> <button>&Theta;</button> <button>&theta;</button> <button>&Lambda;</button> <button>&lambda;</button> <button>&mu;</button> <button>&nu;</button> <button>&xi;</button> <button>&Pi;</button> <button>&pi;</button> <button>&rho;</button> <button>&Sigma;</button> <button>&sigma;</button> <button>&tau;</button> <button>&upsilon;</button> <button>&Phi;</button> <button>&phi;</button> <button>&Psi;</button> <button>&psi;</button> <button>&Omega;</button> <button>&omega;</button>',true);
  $('#fenetre button').on("click",function () {
    insert(el,'','',$(this).text());
    $('#fenetre,#fenetre_fond').remove();
  });
}

function insertion_sigma(el) {
  popup('<h3>Insertion d\'un symbole mathématique</h3>\
  <p>Cliquez sur le symbole à insérer&nbsp;:</p>\
  <button>&forall;</button> <button>&exist;</button> <button>&part;</button> <button>&nabla;</button> <button>&prod;</button> <button>&sum;</button> <button>&plusmn;</button> <button>&radic;</button> <button>&infin;</button> <button>&int;</button> <button>&prop;</button> <button>&sim;</button> <button>&cong;</button> <button>&asymp;</button> <button>&ne;</button> <button>&equiv;</button> <button>&le;</button> <button>&ge;</button> <button>&sub;</button> <button>&sup;</button> <button>&nsub;</button> <button>&sube;</button> <button>&supe;</button> <button>&isin;</button> <button>&notin;</button> <button>&ni;</button> <button>&oplus;</button> <button>&otimes;</button> <button>&sdot;</button> <button>&and;</button> <button>&or;</button> <button>&cap;</button> <button>&cup;</button> <button>&real;</button> <button>&image;</button> <button>&empty;</button> <button>&deg;</button> <button>&prime;</button> <button>&micro;</button> <button>&larr;</button> <button>&uarr;</button> <button>&rarr;</button> <button>&darr;</button> <button>&harr;</button> <button>&lArr;</button> <button>&uArr;</button> <button>&rArr;</button> <button>&dArr;</button> <button>&hArr;</button>',true);
  $('#fenetre button').on("click",function () {
    insert(el,'','',$(this).text());
    $('#fenetre,#fenetre_fond').remove();
  });
}

function insertion_ol(el) {
  popup('<a class="icon-ok" title="Valider"></a><h3>Insertion d\'une liste numérotée</h3>\
  <p>Choisissez le type de numérotation et la valeur de départ de la liste ci-dessous. Vous pouvez éventuellement modifier les différents éléments en les écrivant ligne par ligne. Vous pourrez ajouter un élément ultérieurement en l\'encadrant par les balises &lt;li&gt; et &lt;/li&gt;.</p>\
  <p class="ligne"><label for="t1">Numérotation numérique (1, 2, 3...)</label><input type="radio" name="type" id="t1" value="1" checked></p>\
  <p class="ligne"><label for="t2">Numérotation alphabétique majuscule (A, B, C...)</label><input type="radio" name="type" id="t2" value="A"></p>\
  <p class="ligne"><label for="t3">Numérotation alphabétique minuscule (a, b, c...)</label><input type="radio" name="type" id="t3" value="a"></p>\
  <p class="ligne"><label for="t4">Numérotation romaine majuscule (I, II, III...)</label><input type="radio" name="type" id="t4" value="I"></p>\
  <p class="ligne"><label for="t5">Numérotation romaine minuscule (i, ii, iii...)</label><input type="radio" name="type" id="t5" value="i"></p>\
  <p class="ligne"><label for="debut">Valeur de début (numérique)</label><input type="text" id="debut" value="1"></p>\
  <p class="ligne"><label for="lignes">Textes (chaque ligne correspond à un élément de la liste)&nbsp;: </label></p>\
  <textarea id="lignes" rows="5">'+marqueselection(el)+'</textarea>\
  <hr><h3>Aperçu</h3><div id="apercu"></div>',true);
  // Modification automatique de l'aperçu
  $('#fenetre :input').on("click keyup", function () {
    var debut = $('#debut').val();
    debut = ( debut.length && ( debut > 1 ) ) ? ' start="'+debut+'"' : '';
    $('#apercu').html('<ol type="'+$("[name='type']:checked").val()+'"'+debut+'><li>'
                       +( ( $('#lignes').val().length ) ? $('#lignes').val().trim('\n').replace(/\n/g,'</li><li>') : 'Première ligne</li><li>Deuxième ligne</li><li>...' )
                       +'</li></ol>');
  }).first().keyup();
  $('#lignes').focus();
  // Insertion
  $('#fenetre a.icon-ok').on("click",function () {
    var debut = $('#debut').val();
    debut = ( debut.length && ( debut > 1 ) ) ? ' start="'+debut+'"' : '';
    var elements = $('#lignes').val().trim('\n');
    // On ne souhaite garder en sélection que la dernière ligne
    var index = elements.lastIndexOf('\n');
    if ( index > 0 ) {
      var dernier = elements.substring(index+1);
      elements = elements.substring(0,index);
    }
    else
      var dernier = '';
    // Insertion
    insert(el,'<ol type="'+$("[name='type']:checked").val()+'"'+debut+'><li>'+elements.replace(/\n/g,'</li><li>')+'</li><li>','</li></ol>',dernier);
    $('#fenetre,#fenetre_fond').remove();
  });
  // Suppression des attributs liés à la sélection lors de la fermeture
  $('#fenetre a.icon-ferme,#fenetre_fond').on("click",function () {
    marqueselection(el,true);
  });
}

function insertion_ul(el) {
  popup('<a class="icon-ok" title="Valider"></a><h3>Insertion d\'une liste à puces</h3>\
  <p>Vous pouvez éventuellement modifier les différents éléments en les écrivant ligne par ligne (chaque ligne correspond à un élément de la la liste). Vous pourrez ajouter un élément ultérieurement en l\'encadrant par les balises &lt;li&gt; et &lt;/li&gt;.</p>\
  <textarea id="lignes" rows="5">'+marqueselection(el)+'</textarea>\
  <hr><h3>Aperçu</h3><div id="apercu"></div>',true);
  // Modification automatique de l'aperçu
  $('#lignes').on("click keyup", function () {
    $('#apercu').html('<ul><li>'
                      +( ( $('#lignes').val().length ) ? $('#lignes').val().trim('\n').replace(/\n/g,'</li><li>') : 'Première ligne</li><li>Deuxième ligne</li><li>...' )
                      +'</li></ul>');
  }).keyup().focus();
  // Insertion
  $('#fenetre a.icon-ok').on("click",function () {
    var elements = $('#lignes').val().trim('\n');
    // On ne souhaite garder en sélection que la dernière ligne
    var index = elements.lastIndexOf('\n');
    if ( index > 0 ) { var dernier = elements.substring(index+1); elements = elements.substring(0,index); }
    else var dernier = '';
    // Insertion
    insert(el,'<ul><li>'+elements.replace(/\n/g,'</li><li>')+'</li><li>','</li></ul>',dernier);
    $('#fenetre,#fenetre_fond').remove();
  });
  // Suppression des attributs liés à la sélection lors de la fermeture
  $('#fenetre a.icon-ferme,#fenetre_fond').on("click",function () {
    marqueselection(el,true);
  });
}

function insertion_lien1(el) {
  var sel = marqueselection(el);
  // Préparation : fenêtre et fermeture
  popup('<a class="icon-ok" title="Valider"></a><h3>Insertion d\'un lien vers un document de Cahier de Prépa</h3>\
  <div><p style="text-align:center; margin: 2em 0;">[Récupération des listes de documents]</p></div>\
  <div style="display:none;"><hr><h3>Aperçu</h3><div id="apercu" style="text-align:center;">[Veuillez choisir un document]</div></div>',true);
  $('#fenetre a.icon-ferme,#fenetre_fond').on("click",function () {
    marqueselection(el,true);
  });
  // Récupération des listes de répertoires et de documents
  $.ajax({url: 'recup.php',
          method: "post",
          data: { action:'docs' },
          dataType: 'json'})
    .done( function(data) {
      // Fonction de mise à jour de l'aperçu
      var majapercu = function() {
        var apercu = $('#apercu');
        var id = $('#doc').val();
        var texte = $('#doc option:selected').text();
        // Rien à afficher : texte par défaut
        if ( id == 0 )
          apercu.html(texte);
        // Cas des pdfs affichés
        else if ( $('#vue').is(':checked') ) {
          var l = $('#largeur').val();
          if ( texte.slice(-4,-1) == 'pdf') {
            // Pas de pdf affiché précédemment
            if ( apercu.children('.pdf').length == 0 )
              apercu.html('<div><object data="download?id='+id+'" type="application/pdf" height="100%" width="100%"> <a href="download?id='+id+'">'+texte+'</a> </object></div>');
            // Changement de document
            else if ( apercu.find('object').attr('data').substr(12) != id )
              apercu.find('object').attr('data','download?id='+id).html('<a href="download?id='+id+'">'+texte+'</a>');
            // Changement de format
            apercu.children().attr('class','pdf '+$('#format').val());
            // Changement de largeur : seulement si largeur spécifiée
            if ( l ) {
              if ( l == 100 )
                apercu.children().removeAttr('style').children().attr('width','100%').removeAttr('style');
              else  {
                apercu.children().css('padding-bottom',($('<div class="'+$('#format').val()+'"></div>').css('padding-bottom').slice(0,-1)*l/100)+'%');
                apercu.find('object').attr('width',l+'%').css('left',(100-l)/2+'%');
              }
            }
          }
          // Cas des images affichées
          else if ( 'jpgpegpng'.indexOf(texte.slice(-4,-1)) > -1 ) {
            // Pas d'image affichée précédemment
            if ( apercu.children('img').length == 0 )
              apercu.css('text-align','').html('<img src="download?id='+id+'">');
            // Changement de document
            else if ( apercu.children().attr('src').substr(12) != id )
              apercu.children().attr('src','download?id='+id);
            // Changement de largeur : seulement si largeur spécifiée
            if ( l ) {
              if ( l == 100 )
                apercu.children().removeAttr('style');
              else
                apercu.children().css('width',l+'%').css('margin-left',(100-l)/2+'%');
            }
          }
        }
        // Cas des liens classiques
        else
          $('#apercu').css('text-align','center').html('<a onclick="return false;" href="download?id='+this.value+'">'+$('#texte').val()+'</a>');
      }

      // Fonction d'affichage
      var affichedocs = function(data) {
        $('#fenetre > div:first').html('\
  <p>Choisissez ci-dessous le répertoire puis le document à insérer. Vous pouvez aussi modifier le texte visible. Cela reste modifiable ultérieurement&nbsp;: le texte est situé entre les deux balises &lt;a...&gt; et &lt;/a&gt;.</p>\
  <p class="ligne"><label for="mat">Matière&nbsp;:</label><select id="mat">'+data.mats+'</select></p>\
  <p class="ligne"><label for="rep">Répertoire&nbsp;:</label><select id="rep"></select></p>\
  <p class="ligne"><label for="doc">Document&nbsp;:</label><select id="doc"></select></p>\
  <p class="ligne"><label for="texte">Texte visible&nbsp;:</label><input type="text" id="texte" value="'+sel+'" size="80" data-auto="1"></p>\
  <p class="ligne"><label for="vue">Afficher dans la page (PDF et image uniquement)</label><input type="checkbox" id="vue">\
  <p class="ligne"><label for="largeur">Largeur en %&nbsp;:</label><input type="text" id="largeur" value="100" size="3"></p>\
  <p class="ligne"><label for="format">Format (PDF uniquement)</label><select id="format">\
    <option value="portrait">A4 vertical</option><option value="paysage">A4 horizontal</option><option value="hauteur50">Hauteur 50%</option>\
  </select>');
        $('#fenetre > div:last').show();
        // L'attribut data-auto vaut 1 si la valeur du texte est automatiquement
        // modifiée, pour valoir toujours le nom du document. Il est positionné
        // à 0 dès que l'on modifie manuellement l'entrée #texte, redevient égal
        // à 1 si on la vide.
        if ( $('#texte').val().length )
          $('#texte').attr('data-auto',0);
        // Actions sur #doc
        $('#doc').on("change keyup", function (e) {
          if ( e.which == 13 )
            $('#fenetre a.icon-ok').click();
          var texte = $('#doc option:selected').text();
          // Mise à jour automatique du texte si data-auto vaut 1
          if ( $('#texte').attr('data-auto') == 1 )
            $('#texte').val( ( this.value > 0 ) ? texte.substr(0,texte.lastIndexOf('(')-1) : '---' );
          // Visibilité des cases à cocher
          if ( 'pdfjpgpegpng'.indexOf(texte.slice(-4,-1)) > -1 )
            $('#vue').change().parent().show();
          else {
            $('#vue, #largeur, #format').parent().hide();
            $('#vue').prop('checked',false);
          }
          // Mise à jour de l'aperçu
          majapercu();
        });
        // Actions sur #texte
        $('#texte').on("change keypress", function (e) {
          if ( e.which == 0 )
            return;
          if ( e.which == 13 )
            $('#fenetre a.icon-ok').click();
          // Si vide : modification automatique
          if ( this.value.length == 0 )  {
            $(this).data('auto',1);
            $('#doc').change();
          }
          else  {
            $(this).data('auto',0);
            majapercu();
          }
        });
        // Actions sur #vue
        $('#vue').on("change", function () {
          if ( $('#vue').is(':checked') ) {
            if ( $('#doc option:selected').text().slice(-4,-1) == 'pdf' ) {
              $('#largeur, #format').parent().show();
              $('#texte').parent().hide();
            }
            else if ( 'jpgpegpng'.indexOf($('#doc option:selected').text().slice(-4,-1)) > -1 ) {
              $('#largeur').parent().show();
              $('#format, #texte').parent().hide();
            }
          }
          else {
            $('#texte').parent().show();;
            $('#largeur, #format').parent().hide();
          }
          majapercu();
        });
        // Actions sur #format
        $('#format').on("change keyup", function (e) {
          if ( e.which == 13 )
            $('#fenetre a.icon-ok').click();
          majapercu();
        });
        // Actions sur #largeur
        $('#largeur').on("keydown", function (e) {
          if ( e.which == 38 )
            ++this.value;
          else if ( e.which == 40 )
            --this.value;
        }).on("change keyup", function (e) {
          if ( e.which == 0 )
            return;
          if ( e.which == 13 )
            $('#fenetre a.icon-ok').click();
          if ( this.value != $(this).data('valeur') ) {
            $(this).data('valeur', this.value);
            majapercu();
          }
        }).attr('data-valeur',100);
        // Actions sur #rep
        $('#rep').on('change',function () {
          $('#doc').html(data.docs[this.value]).change();
        });
        // Actions sur #mat
        $('#mat').on('change',function () {
          $('#rep').html(data.reps[this.value]).change();
        }).focus().change();
        // Insertion
        $('#fenetre a.icon-ok').on('click', function () {
          if ( $('#doc').val() )  {
            if ( $('#vue').is(':checked') && ( 'pdfjpgpegpng'.indexOf($('#doc option:selected').text().slice(-4,-1)) > -1 ) )
              insert(el,$('#apercu').html(),'','');
            else
              insert(el,'<a href="download?id='+$('#doc').val()+'">','</a>',$('#texte').val());
            $('#fenetre,#fenetre_fond').remove();
          }
        });
        // Sélection a priori d'une matière (programme de colle, cahier de texte,
        // pages associées à une matière)
        $('#mat option').each( function() {
          if ( $('body').attr('data-matiere') == this.value )
            $('#mat').val(this.value).change();
        });
      }
      
      if ( 'mats' in data )
        affichedocs(data);
  });
}

function insertion_lien2(el) {
  popup('<a class="icon-ok" title="Valider"></a><h3>Insertion d\'un lien</h3>\
  <p class="ligne"><label for="texte">Texte visible&nbsp;: </label><input type="text" id="texte" value="'+marqueselection(el)+'" size="80"></p>\
  <p class="ligne"><label for="url">Adresse&nbsp;: </label><input type="text" id="url" value="http://" size="80"></p>\
  <hr><h3>Aperçu</h3><div id="apercu" style="text-align:center;"></div>',true);
  // Modification automatique de l'aperçu
  $('#fenetre input').on("click keyup", function () {
    $('#apercu').html( ( $('#texte').val().length ) ? '<a onclick="return false;" href="'+$('#url').val()+'">'+$('#texte').val()+'</a>' : '[Écrivez un texte visible]');
  }).on("keypress",function(e) {
    if ( e.which == 13 )
      $('#fenetre a.icon-ok').click();
  }).first().keyup().focus();
  // Insertion
  $('#fenetre a.icon-ok').on("click",function () {
    insert(el,'<a href="'+$('#url').val()+'">','</a>',$('#texte').val());
    $('#fenetre,#fenetre_fond').remove();
  });
  // Suppression des attributs liés à la sélection lors de la fermeture
  $('#fenetre a.icon-ferme,#fenetre_fond').on("click",function () {
    marqueselection(el,true);
  });
}

function insertion_tex(el) {
  // Chargement de MathJax si non déjà chargé
  var chargement = ( typeof MathJax == 'undefined' ) ? '<script type="text/javascript" src="/MathJax/MathJax.js?config=TeX-AMS-MML_HTMLorMML"></script>\
<script type="text/x-mathjax-config">MathJax.Hub.Config({tex2jax:{inlineMath:[["$","$"],["\\\\(","\\\\)"]]}});</script>' : '';
  // Récupération de la sélection et du type de formule éventuelle
  var sel = marqueselection(el);
  var type = 't1';
  if ( sel.length )
    switch ( sel.substring(0,2) ) {
      case '\\[' :
      case '$$'  : type = 't2';
      case '\\(' : sel = sel.substring(2,sel.length-2); break;
      default    : sel = sel.trim('$');
    }
  // Affichage de la fenêtre d'édition/aperçu
  popup(chargement+'<a class="icon-montre" title="Mettre à jour l\'aperçu"></a><a class="icon-ok" title="Valider"></a><h3>Insertion de formules LaTeX</h3>\
  <p>Vous pouvez ci-dessous entrer et modifier une formule LaTeX. L\'aperçu présent en bas sera mis à jour uniquement lorsque vous cliquez sur l\'icône <span class="icon-montre"></span>.</p>\
  <p class="ligne"><label for="t1">La formule est en ligne (pas de retour)</label><input type="radio" name="type" id="t1" value="1"></p>\
  <p class="ligne"><label for="t2">La formule est hors ligne (formule centrée)</label><input type="radio" name="type" id="t2" value="2"></p>\
  <textarea id="formule" rows="3">'+sel+'</textarea>\
  <hr><h3>Aperçu</h3><div id="apercu" style="text-align:center;">[Demandez l\'aperçu en cliquant sur l\'icône <span class="icon-montre"></span>]</div>',true);
  $('#'+type).prop("checked",true);
  $('#formule').focus();
  // Mise à jour de l'aperçu
  $('#fenetre a.icon-montre').on("click", function () {
    if ( $('#formule').val().length ) {
      $('#apercu').html( ( $('#t1').is(":checked") ) ? '$'+$('#formule').val()+'$' : '\\['+$('#formule').val()+'\\]').css('text-align','left');
      MathJax.Hub.Queue(["Typeset",MathJax.Hub,"apercu"]);
    }
    else
      $('#apercu').html('[Écrivez une formule]').css('text-align','center');
  });
  // Insertion
  $('#fenetre a.icon-ok').on("click",function () {
    if ( $('#t1').is(":checked") )
      insert(el,'$','$',$('#formule').val());
    else
      insert(el,'\\[','\\]',$('#formule').val());
    $('#fenetre,#fenetre_fond').remove();
  });
  // Suppression des attributs liés à la sélection lors de la fermeture
  $('#fenetre a.icon-ferme,#fenetre_fond').on("click",function () {
    marqueselection(el,true);
  });
}

// Fonctions lancées par les boutons à insertion directe
function insertion_par1(el)    { insert(el,'<p>','</p>'); }
function insertion_par2(el)    { insert(el,'<div class=\'note\'>','</div>'); }
function insertion_par3(el)    { insert(el,'<div class=\'annonce\'>','</div>'); }
function insertion_retour(el)  { insert(el,'<br>',''); }
function insertion_gras(el)    { insert(el,'<strong>','</strong>'); }
function insertion_italique(el) { insert(el,'<em>','</em>'); }
function insertion_souligne(el) { insert(el,'<u>','</u>'); }
function insertion_exp(el)     { insert(el,'<sup>','</sup>'); }
function insertion_ind(el)     { insert(el,'<sub>','</sub>'); }

// Aide
function aidetexte() {
  popup('<h3>Aide et explications</h3>\
  <p>Il y a deux modes d\'éditions possibles pour éditer un texte&nbsp;: le mode «&nbsp;balises visibles&nbsp;» et le mode «&nbsp;balises invisibles&nbsp;». Il est possible de passer de l\'un à l\'autre&nbsp;:</p>\
  <ul>\
    <li><span class="icon-source"></span> permet de passer en mode «&nbsp;balises visibles&nbsp;» (par défaut), où le texte à taper est le code HTML de l\'article. Ce mode est plus précis. Les boutons aux dessus aident à utiliser les bonnes balises.</li>\
    <li><span class="icon-nosource"></span> permet de passer en mode «&nbsp;balises invisibles&nbsp;», où le texte est tel qu\'il sera affiché sur la partie publique, et modifiable. Ce mode est moins précis, mais permet le copié-collé depuis une page web ou un document Word/LibreOffice.\
  </ul>\
  <p>Une fonction de nettoyage du code HTML, permettant d\'assurer une homogénéité et une qualité d\'affichage optimales, est lancée à chaque commutation entre les deux modes, à chaque clic sur un des boutons disponibles, à chaque copie/coupe de texte et à chaque passage à la ligne.</p>\
  <p>En HTML, toutes les mises en formes sont réalisées par un encadrement de texte entre deux balises&nbsp;: &lt;h3&gt; et &lt;/h3&gt; pour un gros titre, &lt;p&gt; et &lt;/p&gt; pour un paragraphe. Le retour à la ligne simple, qui ne doit exister que très rarement, est une balise simple &lt;br&gt;. Mais les boutons disponibles sont là pour vous permettre de réaliser le formattage que vous souhaitez&nbsp;:</p>\
  <ul>\
    <li><span class="icon-titres"></span>&nbsp;: différentes tailles de titres (fenêtre supplémentaire pour choisir)</li>\
    <li><span class="icon-par1"></span>&nbsp;: paragraphe classique, qui doit obligatoirement encadrer au minimum chaque ligne de texte. Apparaît automatiquement au passage à la ligne si on l\'oublie.</li>\
    <li><span class="icon-par2"></span>&nbsp;: paragraphe important, écrit en rouge</li>\
    <li><span class="icon-par3"></span>&nbsp;: paragraphe très important, écrit en rouge et encadré</li>\
    <li><span class="icon-retour"></span>&nbsp;: retour à la ligne. Identique à un appui sur Entrée, et souvent inutile.</li>\
    <li><span class="icon-gras"></span>&nbsp;: mise en gras du texte entre les balises</li>\
    <li><span class="icon-italique"></span>&nbsp;: mise en italique du texte entre les balises</li>\
    <li><span class="icon-souligne"></span>&nbsp;: soulignement du texte entre les balises</li>\
    <li><span class="icon-omega"></span>&nbsp;: lettres grecques (fenêtre supplémentaire pour choisir)</li>\
    <li><span class="icon-sigma"></span>&nbsp;: symboles mathématiques (fenêtre supplémentaire pour choisir)</li>\
    <li><span class="icon-exp"></span>&nbsp;: mise en exposant du texte entre les balises</li>\
    <li><span class="icon-ind"></span>&nbsp;: mise en indice du texte entre les balises</li>\
    <li><span class="icon-ol"></span>&nbsp;: liste numérotée. Une fenêtre supplémentaire permet de choisir le type (1,A,a,I,i) et la première valeur. Les différentes lignes de la liste sont constituées par les balises &lt;li&gt; et &lt;/li&gt;</li>\
    <li><span class="icon-ul"></span>&nbsp;: liste à puces. Les différentes lignes de la liste sont constituées par les balises &lt;li&gt; et &lt;/li&gt;</li>\
    <li><span class="icon-lien1"></span>&nbsp;: lien d\'un document disponible ici (fenêtre supplémentaire pour choisir)</li>\
    <li><span class="icon-lien2"></span>&nbsp;: lien vers un autre site web (fenêtre supplémentaire pour entre l\'adresse)</li>\
    <li><span class="icon-tex"></span>&nbsp;: insertion de code LaTeX (fenêtre supplémentaire pour le taper)</li>\
  </ul>\
  <p class="tex2jax_ignore">Il est possible d\'insérer du code en LaTeX, sur une ligne séparée (balises \\[...\\] ou balises $$...$$) ou au sein d\'une phrase (balises $...$ ou balises \\(...\\)). Il faut ensuite taper du code en LaTeX à l\'intérieur. La prévisualisation est réalisée en direct.</p>',false);
} 

///////////////////////////////
// Modification des articles //
///////////////////////////////

// Échange vertical de deux éléments
// el1 doit être le plus haut des deux
function echange(el1,el2) {
  if ( el1.length && el2.length ) {
    $('article').css('position','relative');
    el1.css('opacity',0.3); el2.css('opacity',0.3);
    el2.animate({top: el1.position().top-el2.position().top},1000);
    el1.animate({top: (el2.outerHeight(true)+el2.outerHeight())/2},1000,function() {
      el1.css('opacity',1); el2.css('opacity',1);
      el1.insertAfter(el2);
      el1.css({'position': 'static', 'top': 0});
      el2.css({'position': 'static', 'top': 0});
    });
  }
}

// Disparition de la partie publique
function cache(el) {
  var prop = el.parent().attr('data-id').split('|');
  $.ajax({url: 'ajax.php',
          method: "post",
          data: { cache:1, action:prop[0], id:prop[1] },
          dataType: 'json',
          el: el,
          fonction: function(el) {
            el.parent().addClass('cache');
            el.removeClass('icon-cache').addClass('icon-montre').off("click").on("click", function() {
              montre($(this));
            }).attr("title","Montrer à nouveau");
          }
  });
}

// Apparition sur la partie publique
function montre(el) {
  var prop = el.parent().attr('data-id').split('|');
  $.ajax({url: 'ajax.php',
          method: "post",
          data: { montre:1, action:prop[0], id:prop[1] },
          dataType: 'json',
          el: el,
          fonction: function(el) {
            el.parent().removeClass('cache');
            el.removeClass('icon-montre').addClass('icon-cache').off("click").on("click", function() {
              cache($(this));
            }).attr("title","Cacher à nouveau");
          }
  });
}

// Montée
function monte(el) {
  var parent = el.parent();
  var prop = parent.attr('data-id').split('|');
  $.ajax({url: 'ajax.php',
          method: "post",
          data: { monte:1, action:prop[0], id:prop[1] },
          dataType: 'json',
          el: parent,
          fonction: function(el) {
            if ( !(el.prev().prev().is('article')) ) {
              el.children('.icon-monte').hide(1000);
              el.prev().children('.icon-monte').show(1000);
            }
            if ( !(el.next().is('article')) ) {
              el.children('.icon-descend').show(1000);
              el.prev().children('.icon-descend').hide(1000);
            }
            echange(el.prev(),el);
          }
  });
}

// Descente
function descend(el) {
  var parent = el.parent();
  var prop = parent.attr('data-id').split('|');
  $.ajax({url: 'ajax.php',
          method: "post",
          data: { descend:1, action:prop[0], id:prop[1] },
          dataType: 'json',
          el: parent,
          fonction: function(el) {
            if ( !(el.prev().is('article')) ) {
              el.children('.icon-monte').show(1000);
              el.next().children('.icon-monte').hide(1000);
            }
            if ( !(el.next().next().is('article')) ) {
              el.children('.icon-descend').hide(1000);
              el.next().children('.icon-descend').show(1000);
            }
            echange(el,el.next());
          }
  });
}

// Suppression
function supprime(el) {
  var parent = el.parent();
  var prop = parent.data('id').split('|');
  var item = 'un élément';
  switch ( prop[0] ) {
    case 'infos': item = 'une information'; break;
    case 'pages': item = 'la matière <em>'+$('h3', parent).text()+'</em>. Les informations qui y sont écrites seront aussi supprimées'; break;
    case 'reps': item = 'le répertoire <em>'+$('.nom', parent).map(function() { return this.textContent || $(this).find('input').val(); }).get(0)+'</em>. <strong>Tous les sous-répertoires et documents qui s\'y trouvent seront aussi supprimés</strong>'; break;
    case 'docs': item = 'le document <em>'+$('.nom', parent).map(function() { return this.textContent || $(this).find('input').val(); }).get(0)+'</em>'; break;
    case 'colles': item = 'le programme de colle de la '+$('.edition',parent).text().toLowerCase(); break;
    case 'cdt-elems': item = 'un élément du cahier de texte'; break;
    case 'cdt-types': item = 'le type de séances <em>'+$('h3', parent).text()+'</em>. <strong>Les éléments du cahier de texte associés à ce type seront aussi supprimés</strong>'; break;
    case 'cdt-raccourcis': item = 'le raccourci de séance <em>'+$('h3', parent).text()+'</em>. Aucun élément du cahier de texte ne sera supprimé'; break;
    case 'notes': parent = parent.parent(); item = 'une colle ou séance sans note du <em>'+$('td:first',parent).text()+'</em>, d\'une durée de '+$('td:eq(3)',parent).text()+'. Toutes les notes de cette colle seront supprimées'; break;
    case 'matieres': item = 'la matière <em>'+$('h3', parent).text()+'</em>. <p class="note"><strong>ATTENTION&nbsp;: Les programmes de colles, le cahier de texte et les notes correspondantes seront toutes automatiquement supprimées.</strong></p> <p>Les répertoires, les documents, les pages d\'informations spécifiques et les éléments de l\'agenda associés à la matière seront conservés mais ne seront plus associés à une matière&nbsp;: ils seront désormais visibles dans le contexte «&nbsp;général&nbsp;».<br><strong>Si vous souhaitez simplement réinitialiser la matière, ce n\'est pas la bonne méthode</strong>&nbsp;: vous devriez pouvoir faire ce que vous souhaitez avec les possibilités de cette page'; break;
    case 'groupes': item = 'le groupe <em>'+( $('.editable', parent).text() || $('input:first', parent).val())+'</em>. Les utilisateurs concernés ne seront pas supprimés'; break;
    case 'agenda-elems': item = 'un événement de l\'agenda'; break;
    case 'agenda-types': item = 'le type d\'événement <em>'+$('h3', parent).text()+'</em>. <strong>Les événements de l\'agenda associés à ce type seront aussi supprimés</strong>'; break;
    case 'devoirs': item = 'un devoir. Toutes les copies et documents associés à ce devoir seront automatiquement supprimés'; break;
  }
  confirmation('Vous allez supprimer XXX.<br>Cette opération n\'est pas annulable.'.replace('XXX',item),this,function(el) {
    $.ajax({url: 'ajax.php',
            method: "post",
            data: { supprime:1, action:prop[0], id:prop[1] },
            dataType: 'json',
            el: parent,
            fonction: function(el) {
              if ( prop[0].match(/^(agenda-elems|colles)$/) )
                location.reload(true);
              else
                el.remove();
            }
    });
  });
}

// Modification de protection -- Informations uniquement 
function lock(el) {
  var parent = el.parent();
  var prop = parent.data('id').split('|');
  var protection = el.data('val');
  // Fenêtre de sélection
  popup('<a class="icon-ok" title="Valider ce choix"></a><h3>Accès à l\'information</h3><table id="selmult">\
  <tr class="categorie"><td>Accès public</td><td><input value="0" type="checkbox"></td></tr>\
  <tr class="categorie"><td>Utilisateurs identifiés</td><td><input value="6" type="checkbox"></td></tr>\
  <tr class="element"><td>Invités</td><td><input value="1" type="checkbox"></td></tr>\
  <tr class="element"><td>Élèves</td><td><input value="2" type="checkbox"></td></tr>\
  <tr class="element"><td>Colleurs</td><td><input value="3" type="checkbox"></td></tr>\
  <tr class="element"><td>Lycée</td><td><input value="4" type="checkbox"></td></tr>\
  <tr class="element"><td>Professeurs</td><td><input value="5" type="checkbox"></td></tr>\
  <tr class="categorie"><td>Information invisible</td><td><input value="32" type="checkbox"></td></tr>\
  </tbody></table>',true);
  // Initialisation
  var f = $('#fenetre');
  if ( ( protection == 0 ) || ( protection == 32 ) )
    $('input[value="'+protection+'"]', f).prop("checked",true).change();
  else  {
    $('input[value=6]', f).prop("checked",true).change();
    for ( var a=1; a<6; a++ )
      if ( ( (protection-1)>>(a-1) & 1 ) == 0 )
        $('input[value="'+a+'"]', f).prop('checked',true).change();
  }
  // Différenciation des lignes (pour cochage multiple et css)
  var f = $('#fenetre');
  $('input[value=0],input[value=6],input[value=32]', f).parent().parent().addClass('categorie');
  $('tr:not(.categorie)', f).addClass('element');
  // Décochage automatique
  $('input', f).on("click", function() {
    if ( ( this.value == 0 ) || ( this.value == 32 ) )
      $(this).parent().parent().siblings().find('input[type=checkbox]').prop("checked",false).change();
    else {
      $('input[value=0],input[value=32]', f).prop("checked",false).change();
      $('input[value=6]', f).prop("checked",true).change();
      // Cochage multiple si on clique sur "Utilisateurs identifiés"
      if ( this.value == 6 )
        $('tr:not(.categorie) input', f).prop("checked",true).change();
    }
  });
  // Clic sur toute la ligne
  $('tr', f).on("click",function(e) {
    if ( !$(e.target).is('input') )
      $(this).find('input').click();
  });
  // Mise en évidence
  $('input', f).on("change", function () {
    $(this).parent().parent().removeClass('sel');
    if ( this.checked )
      $(this).parent().parent().addClass('sel');
  });
  // Validation
  $('.icon-ok', f).on("click", function() {
    if ( $('input[value="32"]', f).prop('checked') )
      var val = 32;
    else if ( $('input[value="0"]', f).prop('checked') )
      var val = 0;
    else
      var val = 32 - $('input:checked:not([value=6])', f).map(function() { return this.value|0; }).get().reduce( function(acc,v) { return acc + Math.pow(2,(v-1)); },0);
    el.data('val',val);
    $.ajax({url: 'ajax.php',
        method: "post",
        data: { action:prop[0], id:prop[1], champ:'protection', val:val },
        dataType: 'json',
        el: parent,
        fonction: function(el) {
          // Recharger pour modifier si besoin l'affichage de l'information
          location.reload(true);
        }
    });
  });
}

// Ajout de colle : édition et remplacement
function ajoutecolle(el) {
  // Modification de l'article et insertion du formulaire
  var article = el.parent();
  el.before('<a class="icon-annule" title="Annuler"></a><a class="icon-ok" title="Valider"></a>');
  el.next().addBack().hide();
  var form = $('<form></form>').appendTo(article).html($('#form-ajoutecolle').html());
  // Ne pas quitter avec un textarea plein
  $('textarea',form).on('change',function(e) { $('body').data('nepassortir',true); $(this).off(e); });
  $('textarea',form).textareahtml();  
  $('input',form).attr('id','cache');
  // Actions des boutons annulation, aide, validation
  $('.icon-annule', article).on("click",function() {
    $('form,.icon-annule,.icon-ok', article).remove();
    el.next().addBack().show();
  });
  $('a.icon-ok', article).on("click", function() {
    // Nettoyage du texte
    $('textarea',form).each( function() {
      this.value = nettoie( ( $(this).is(':visible') ) ? this.value : $(this).next().html() );
    });
    // Envoi
    var id = article.data('id').split('|')[1];
    $.ajax({url: 'ajax.php',
            method: "post",
            data: form.serialize()+'&action=ajout-colle&id='+id,
            dataType: 'json',
            el: article,
            fonction: function(el) {
              var texte = $('textarea',el).val();
              var cache = $('input',el).is(":checked");
              if ( cache ) 
                el.addClass('cache');
              el.data('id','colles|'+id);
              $('.icon-aide',el).nextAll().remove();
              $('.icon-aide',el).after(
                ( cache ? '<a class="icon-montre" title="Afficher le programme de colles sur la partie publique"></a>' : '<a class="icon-cache" title="Rendre invisible le programme de colles sur la partie publique"></a>' )
                + '<a class="icon-supprime" title="Supprimer ce programme de colles"></a><div class="editable edithtml" data-id="colles|texte|'+id+'" placeholder="Texte du programme de colles">'+texte+'</div>');
              $('a.icon-cache,a.icon-montre,a.icon-supprime', el).on("click", function() {
                window[this.className.substring(5)]($(this));
              });
              $('.editable',el).editinplace();
            }
    });
  });
  // Envoi par appui sur Entrée
  $('input,select',form).on('keypress',function (e) {
    if ( e.which == 13 ) {
      e.preventDefault();
      $('a.icon-ok', article).click();
    }
  });
}

/////////////////
// Formulaires //
/////////////////

// Envoi automatique : pour les formulaire déjà présents
function valide() {
  var data = '';
  // Modification de planning
  if ( $('#planning').length )
    data = 'action=planning&'+$('form').serialize();
  else {
    var id = $(this).parent().attr('data-id').split('|');
    data = 'action='+id[0]+'&id='+id[1]+'&'+$(this).nextAll('form').serialize();
  }
  if ( data.length )
    $.ajax({url: 'ajax.php',
            method: "post",
            data: data,
            dataType: 'json',
            el: this,
            fonction: function(el) {
              if ( el.classList[1] != 'noreload' )
                location.reload(true);
            }
    }).done( function(data) {
      // Cas spécial de la modification de son adresse électronique :
      // envoi d'un code de confirmation à taper 
      if ( data['etat'] == 'confirm_mail' )  {
        $('[data-id="prefsperso|3"] p:first').html(data['message']).addClass('annonce');
        $('[data-id="prefsperso|3"] p:hidden').show().children('input').attr('disabled',false);
      }
    });
  else
    affiche('<p>Aucune donnée envoyée.</p>','nok');
}

// Formulaire généré lors des clics sur les boutons généraux
function formulaire() {
  var idform = this.className.split(' ')[0].substring(5); // .modifevnmt -> evnmt
  var action = $('#form-'+idform).data('action');
  // Suppression d'un éventuel contenu épinglé existant
  $('#epingle').remove();
  // Création du nouveau formulaire
  var article = $('<article id="epingle"><a class="icon-ferme" title="Fermer"></a>\
  <a class="icon-aide" title="Aide pour ce formulaire"></a>\
  <a class="icon-ok" title="Valider"></a></article>').insertBefore($('article,#calendrier,#parentsdoc+*').first());
  var form = $('<form></form>').appendTo(article).html($('#form-'+idform).html());
  // Ne pas quitter avec un textarea plein
  $('textarea',form).on('change',function(e) { $('body').data('nepassortir',true); $(this).off(e); });
  // Facilités et peuplement du formulaire
  $('.edithtml',form).textareahtml();
  // Création de l'identifiant des champs à partir du name
  $('input[name], select[name]:not([multiple])',form).attr('id',function(){ return this.getAttribute('name'); });
  // Actions spécifiques
  switch ( action ) {
    case 'reps': $(this).init_reps(); break;
    case 'ajout-rep': form.append('<input type="hidden" name="parent" value="'+$(this).parent().data('id').split('|')[1]+'">'); break;
    case 'docs': 
    case 'ajout-doc': $(this).init_docs(action); break;
    case 'cdt-elems': form.init_cdt_boutons(); break;
    case 'ajout-cdt-raccourci': form.init_cdt_raccourcis(); break;
    case 'notes':
    case 'ajout-notes': $(this).init_notes(action); break;
    case 'agenda-elems': $(this).init_evenements(); break;
    case 'ajout-agenda-types': $('[name="couleur"]',form).colpick(); break;
    case 'deplcolle': $('#ancien,#nouveau').each( function() { $(this).datetimepicker({ format: 'd/m/Y Ghi', timepicker: true }); }); break;
    case 'ajout-utilisateurs': form.init_ajout_utilisateurs(); break;
    case 'ajout-groupe': $('.usergrp span',form).on("click", utilisateursgroupe); break;
    case 'devoirs':
    case 'ajout-devoir': $(this).init_devoirs(action); break;
  }
  // Selections multiples (matière, accès)
  $('select[multiple]', form).each(selmult);
  // Actions des boutons fermeture, aide, validation
  $('#epingle .icon-ferme').on("click",function() { $('#epingle').remove(); });
  $('#epingle a.icon-aide').on("click", function() { popup($('#aide-'+idform).html(),false); });
  $('#epingle a.icon-ok').on("click", function() {
    // Nettoyage et synchronisation si besoin
    $('.edithtml',form).each( function() {
      this.value = nettoie( ( $(this).is(':visible') ) ? this.value : $(this).next().html() );
    });
    // Nettoyage des notes à ne pas mettre
    if ( ( action == 'notes' ) || ( action == 'ajout-notes' ) )
      $('#epingle select:not(:visible)').val('x');
    // Envoi
    $.ajax({url: 'ajax.php',
            method: "post",
            data: form.serialize()+'&action='+action,
            dataType: 'json',
            el: '',
            fonction: function(el) {
              location.reload(true);
            }
    });
  });
  // Envoi par appui sur Entrée
  $('input,select',form).on('keypress',function (e) {
    if ( e.which == 13 ) {
      e.preventDefault();
      $('#epingle a.icon-ok').click();
    }
  });
}

// Facilités du formulaire de modification des répertoires
// Attention, this est le bouton cliqué
$.fn.init_reps = function() {
  var el = $(this);
  var form = $('#epingle form');
  var sel = $('select[multiple]', form);
  // Identifiant
  var id = el.parent().data('id').split('|')[1];
  // Donnees : parent,menu,protection
  var donnees = el.parent().data('donnees').split('|');
  var protection = donnees[2];
  // Nom du répertoire : il faut enlever les parents éventuels, si on est dans #parentsdoc
  // split(/\/\s/) : regexp = "/"+"espace" ("&nbsp;" passé dans text())
  var nom = el.siblings('.nom').text().split(/\/\s/).pop() || el.parent().find('input').val();
  // Remplissage du formulaire
  $('em', form).text(nom);
  if ( ( protection == 0 ) || ( protection == 32 ) )
    sel.val(protection);
  else  {
    sel.val(6);
    for ( var a=1; a<6; a++ )
      if ( ( (protection-1)>>(a-1) & 1 ) == 0 )
        $('option[value="'+a+'"]', sel).prop('selected',true);
  }
  if ( donnees[0] == 0 )
    $('#nom,#parent,#menurep', form).parent().remove();
  else {
    $('#nom', form).val(nom);
    if ( donnees[1] == '1' )
      $('#menurep', form).prop('checked',true);
    // Désactivation des déplacements impossibles
    $('[data-parents*=",'+id+',"]', form).prop('disabled', true);
  }
  form.append('<input type="hidden" name="id" value="'+id+'">');
  // Bouton de vidage
  $('input[type="button"]', form).on("click", function() {
    var contexte = $(this).parent().find('em').text();
    confirmation('Vous allez vider le répertoire <em>'+contexte+'</em>. Cela supprimera définitivement l\'ensemble de ses sous-répertoires et des documents qu\'ils contiennent.<br>Cette opération n\'est pas annulable.',this,function(el) {
      $.ajax({url: 'ajax.php',
              method: "post",
              data: {action: 'reps', id: id, vide: 1 },
              dataType: 'json',
              el: '',
              fonction: function(el) {
                location.reload(true);
              }
      });
    });
  });
}

// Facilités du formulaire de modification des répertoires
// Attention, this est le bouton cliqué
// action vaut docs ou ajout-docs
$.fn.init_docs = function(action) {
  var el = $(this);
  var form = $('#epingle form');
  $('#dispo', form).parent().hide(); // Caché par défaut
  // Nom : du document si modification, du répertoire parent si ajout
  // On enlève les parents éventuels, si on est dans #parentsdoc
  var nom = el.siblings('.nom').text().split(/\/\s/).pop() || el.parent().find('input').val();
  $('em', form).text(nom);
  // Identifiant : celui du document, ou du répertoire parent si ajout
  var id = el.parent().data('id').split('|')[1];
  // Si modification : ajout de l'identifiant, du nom, de la disponibilité
  if ( action == 'docs' ) {
    var protection = el.parent().data('protection');
    form.append('<input type="hidden" name="id" value="'+id+'">');
    $('#nom', form).val(nom);
    var dispo = el.parent().data('dispo');
    if ( dispo )  {
      $('#dispo', form).val(dispo).parent().show();
      $('#affdiff', form).prop("checked",true);
    }
  }
  // Si ajout : récupération de la protection du répertoire parent
  else {
    var protection = el.parent().data('donnees').split('|')[2];
    form.append('<input type="hidden" name="parent" value="'+id+'">');
    // Modification automatique des saisies de noms
    $('input[type="file"]', form).attr('id','fichier').on('change',function () {
      var fichiers = this;
      $('input[id^="nom"]', form).parent().remove();
      for (var i = 0, n = fichiers.files.length, f = ''; i < n; i++) {
        $('.ligne',form).last().after('<p class="ligne"><label for="nom'+i+'">Nom à afficher'+(n>1 ? ' (fichier '+(i+1)+')' : '')+'&nbsp;: </label><input type="text" name="nom[]" id="nom'+i+'" value="" size="50"></p>');
        f = fichiers.files[i].name;
        $('#nom'+i, form).val(f.substring(f.lastIndexOf('\\')+1,f.lastIndexOf('.')) || f);
      }
    });
  }
  // Modification de la protection initiale
  var sel = $('select[multiple]', form);
  if ( ( protection == 0 ) || ( protection == 32 ) )
    sel.val(protection);
  else  {
    sel.val(6);
    for ( var a=1; a<6; a++ )
      if ( ( (protection-1)>>(a-1) & 1 ) == 0 )
        $('option[value="'+a+'"]', sel).prop('selected',true);
  }
  // Possibilité ou non de définir la date de disponibilité
  $('#dispo', form).datetimepicker({ format: 'd/m/Y Ghi', timepicker: true,
    onShow: function() { this.setOptions({minDate: new Date()}); }
  });
  $('#affdiff', form).on("click", function() {
    $('#dispo', form).parent().toggle(this.checked);
  });

  form.append('<input type="hidden" name="action" value="'+action+'">');
  // Envoi du fichier
  $('#epingle a.icon-ok').addClass('icon-envoidoc').removeClass('icon-ok').on("click", function() {
    // Test de connexion
    // Si reconnect() appelée, le paramètre connexion sert à obtenir un retour
    // en état ok/nok pour affichage si nok. Si ok, on réécrit le message. 
    $.ajax({url: 'docs.php',
            method: "post",
            data: 'connexion=1',
            dataType: 'json',
            el: '',
            fonction: function(el) {
              // Si transfert, pas d'affichage dans le div de log
              $('#log').hide();
              // Envoi réel du fichier ou des données
              var data = new FormData(form[0]);
              // Envoi
              $.ajax({url: 'ajax.php',
                      xhr: function () { 
                        // Évolution du transfert si fichier transféré
                        var xhr = $.ajaxSettings.xhr();
                        if ( xhr.upload && ( $('#fichier')[0].files.length > 0 ) )
                          $('#load').html('<p class="clignote">Transfert en cours<span></span></p><img src="js/ajax-loader.gif">');
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
}

// Facilités du formulaire de modification des propriétés des éléments de cahier
// de texte, utilisé par transformecdt() (élément existant) et formulaire()
// (nouvel élément)
$.fn.init_cdt_boutons = function() {
  var form = this;
  $('#jour,#pour').datetimepicker({ format: 'd/m/Y', timepicker: false });
  $('#h_debut').datetimepicker({ format: 'Ghi', datepicker: false,
    onClose: function(t,input) {
      $('#h_fin').val(function(i,v){ return v || ( input.val().length ? (parseInt(input.val().slice(0,-3))+2)+input.val().slice(-3) : ''); });
    }
  });
  $('#h_fin').datetimepicker({ format: 'Ghi', datepicker: false });
  // Ajout du zéro devant les dates et heures à 1 chiffre
  var zero = function(n) {
    return ( String(n).length == 1 ) ? '0'+n : String(n);
  }
  // Action lancée à la modification du raccourci
  $('#raccourci').on('change keyup', function() {
    var valeurs = raccourcis[this.value];
    for ( var cle in valeurs ) {
      // Modification de la date (prochain lundi/mardi..)
      if ( cle == 'jour' ) {
        var t = new Date;
        var j = parseInt(valeurs['jour']);
        t.setDate( ( j > t.getDay() ) ? t.getDate()-t.getDay()-7+j : t.getDate()-t.getDay()+j );
        $('#jour').val(zero(t.getDate())+'/'+zero(t.getMonth()+1)+'/'+t.getFullYear());
      }
      // Modification des autres jours
      else
        $('#'+cle).val(valeurs[cle]);
    }
    // Pour éviter d'être remis à zéro immédiatement
    $(this).data('modif',1);
    // Modifie les champs visibles
    $('#tid').change();
  }).data('modif',0);
  // Action lancée à la modification du type de séance
  $('#tid').on('change keyup', function() {
    switch ( parseInt(seances[this.value]) ) {
      case 0:
        $('#h_debut,#demigroupe').parent().show();
        $('#h_fin,#pour').parent().hide();
        break;
      case 1:
        $('#h_debut,#h_fin,#demigroupe').parent().show();
        $('#pour').parent().hide();
        break;
      case 2:
        $('#h_debut,#h_fin').parent().hide();
        $('#pour,#demigroupe').parent().show();
        break;
      case 3:
        $('#h_debut,#h_fin,#pour').parent().hide();
        $('#demigroupe').parent().show();
        break;
      default:
        $('#h_debut,#h_fin,#pour,#demigroupe').parent().hide();
    }
    // Mise à jour du champ de raccourci
    $('#jour').change();
  });
  // Action lancée à la modification des autres champs
  $('input,#demigroupe',form).on('change keyup', function() {
    // Remise à zéro du raccourci s'il n'a pas été modifié immédiatement
    if ( $('#raccourci').data('modif') == 0 )
      $('#raccourci').val(0);
    else
      $('#raccourci').data('modif',0);
  });
  // Envoi par appui sur Entrée
  $('input,select',form).on("keypress",function(e) {
    if ( e.which == 13 )
      $('a.icon-ok',el).click();
  });
  // Focus sur le premier champ et modification initiale
  $('select:first',form).focus();
  $('#tid').change();
}

// Facilités du formulaire de modification des propriétés des raccourcis de
// cahier de texte, utilisé sur les éléments de classe cdt-raccourcis
$.fn.init_cdt_raccourcis = function() {
  this.each(function() {
    var form = $(this);
    $('[id^="h_d"]',form).datetimepicker({ format: 'Ghi', datepicker: false,
      onClose: function(t,input) {
        $('[id^="h_f"]',form).val(function(i,v){ return v || ( input.val().length ? (parseInt(input.val().slice(0,-3))+2)+input.val().slice(-3) : ''); });
      }
    });
    $('[id^="h_fin"]').datetimepicker({ format: 'Ghi', datepicker: false });
    // Action lancée à la modification du type de séance
    $('[id^="type"]',form).on('change keyup', function() {
      switch ( parseInt(seances[this.value]) ) {
        case 0:
          $('[id^="h_d"],[id^="dem"]',form).parent().show();
          $('[id^="h_f"]',form).parent().hide();
          break;
        case 1:
          $('[id^="h_d"],[id^="h_f"],[id^="dem"]',form).parent().show();
          break;
        case 2:
        case 3:
          $('[id^="h_d"],[id^="h_f"]',form).parent().hide();
          $('[id^="dem"]',form).parent().show();
          break;
        default:
          $('[id^="h_d"],[id^="h_f"],[id^="dem"]',form).parent().hide();
      }
    }).change();
    // Envoi par appui sur Entrée
    $('input,select',form).on("keypress",function(e) {
      if ( e.which == 13 )
        $('a.icon-ok',form).click();
    });
  });
}

// Facilités spécifiques du formulaire d'édition des notes
$.fn.init_notes = function(action) {
  var el = $(this);
  var form = $('#epingle form').append($('#form-notes').html());
  var table = $('table',form);
  // Création de l'identifiant des champs à partir du name
  $('input, select',form).attr('id',function(){ return this.getAttribute('name'); });
  
  // Génération des select de notes
  $('tr[data-id]',table).append('<td>'+$('div',form).html()+'</td>');
  $('select',table).attr('name',function() {
    return 'e'+$(this).parent().parent().data('id');
  });
  $('div',form).remove();
  
  // On cache tous les élèves a priori s'il y a des groupes
  if ( $('input:checkbox',table).length ) {
    $('tr[data-id]',form).hide();
    // Clic sur les groupes
    $('input:checkbox', table).on("change", function () {
      if ( $('input:checkbox:last', table).prop('checked') )
        return $('tr[data-id]',form).show();
      // Récupération des élèves du groupe
      var ids = $('input:checked', table).map(function() { return this.value.split(','); }).get().concat();
      // On cache tous les élèves sauf ceux ayant déjà une note pour l'édition
      // de notes déjà saisies (lignes repérées par la classe "orig")
      $('tr[data-id]:not(.orig)',table).hide();
      for (var i=0; i<ids.length; i++)
        $('tr[data-id="'+ids[i]+'"]',form).show();
    });
  }

  // Fonction de marquage des déjà notés pour en prévenir la notation, utilisée
  // à l'initialisation et à la modification de semaine
  function marque_dejanotes(sid) {
    if ( sid == 0 )
      return true;
    // Récupération des déjà notés par les autres colleurs
    var dn = dejanotesautres[sid].split(',');
    for (var i=0; i<dn.length; i++)
      $('tr[data-id="'+dn[i]+'"]', form).addClass('dejanote').find('td:eq(0)').text( function() {
        return this.textContent+' (noté par un autre colleur)';
      });
    // Récupération des déjà notés par l'utilisateur
    var dn = dejanotesperso[sid].split(',');
    for (var i=0; i<dn.length; i++)
      $('tr[data-id="'+dn[i]+'"]:not(.orig)', form).addClass('dejanote').find('td:eq(0)').text( function() {
        return this.textContent+' (déjà noté par vous-même)';
      });
    // Désactivation et effacement des valeurs
    $('.dejanote select').prop('disabled',true).val('x');
  }

  // Facilités
  $('#jour').datetimepicker({ format: 'd/m/Y', timepicker: false, onShow: function() {
      if ( $('#td').is(':checked') )
        this.setOptions({ minDate: $('#form-ajoute option:eq(1)').data('date') ,
                          maxDate: new Date( new Date(( $('#form-ajoute option:last').data('date') ).replace(/(.{2})\/(.{2})\/(.{4})/,function(tout,x,y,z) {return z+'-'+y+'-'+x; })).getTime()+6*86400000 ) });
      else
        this.setOptions({ minDate: debut || $('#sid option:selected').data('date') ,
                          maxDate: new Date( new Date(( fin || $('#sid option:selected').next().data('date') ).replace(/(.{2})\/(.{2})\/(.{4})/,function(tout,x,y,z) {return z+'-'+y+'-'+x; })).getTime()-86400000 ) });
    }
  });
  $('#heure').datetimepicker({ format: 'Ghi', datepicker: false, defaultTime: '15h30' });
  $('#duree').datetimepicker({ format: 'Ghi', datepicker: false, defaultTime: '0h00', step: 10 })
             .on("change", function() { $(this).removeClass('auto'); });
  // Mise à jour de la durée à chaque nouvelle note
  $('select', table).on("change keyup", function() {
    var nb = $('table select:visible',form).filter(function() { return this.value != "x"; }).length;
    var duree = nb*(dureecolle || 20);
    if ( $('#duree').is('.auto') || duree > $('#duree').val().replace(/^(\d*)h(\d*)$/,function(tout,x,y) {return 60*(x|0)+(y|0); }) )
      $('#duree').val( (duree/60|0)+'h'+(duree%60||'') ).addClass('auto');
  });

  // Si nouvelles notes : gestion du changement de semaine et
  // de la possibilité de séance de TD
  if ( action == 'ajout-notes' ) {
    // Changement de semaine
    $('#sid').on("change keyup", function() {
      // Nettoyage des déjà notés précédents
      $('.dejanote td:first-child').text( function() {
        return this.textContent.replace(' (noté par un autre colleur)','').replace(' (déjà noté par vous-même)','');
      });
      $('.dejanote').removeClass('dejanote').find('select').prop('disabled',false);
      // Marquage des déjà notés
      marque_dejanotes($('#sid').val());
      // Réglage du jour si hors de la semaine
      // Ne pas considérer l'option "Choisir la semaine"
      if ( $('#sid option:selected').val() > 0 )  {
        if ( !$('#jour').val() )
          $('#jour').val($('#sid option:eq(1)').data('date'));
        var jour = new Date($('#jour').val().replace(/(.{2})\/(.{2})\/(.{4})/,function(tout,x,y,z) {return z+'-'+y+'-'+x; }));
        var debut = new Date($('#sid option:selected').data('date').replace(/(.{2})\/(.{2})\/(.{4})/,function(tout,x,y,z) {return z+'-'+y+'-'+x; }));
        var fin = new Date($('#sid option:selected').next().data('date').replace(/(.{2})\/(.{2})\/(.{4})/,function(tout,x,y,z) {return z+'-'+y+'-'+x; }));
        if ( ( jour < debut ) || ( jour > fin ) ) {
          debut = debut.toJSON();
          $('#jour').val(debut.substr(8,2)+'/'+debut.substr(5,2)+'/'+debut.substr(0,4));
        }
      }
    }).change();
    // Gestion des séances de TD
    $('#td').on("change keyup",function () {
      if ( this.checked ) {
        $('#sid').parent().hide();
        $('#description').parent().show();
        table.hide();
      }
      else {
        $('#sid').parent().show();
        $('#description').parent().hide();
        table.show();
      }
    });
    $('#description').parent().hide();
  }
  // Si édition de notes : affichage des notes modifiables, ajout ou modification
  // de la durée possible seulement pour les colles non encore relevées. 
  else {
    var tr = el.parent().parent();
    // Cas des colles classiques : semaine spécifiée
    if ( el.data('sid') ) {
      $('#description, #td').parent().remove();
      // Récupération des données : semaine, début, fin pour l'utilisation globale
      // dans le onShow de #jour et dans marque_dejanotes.
      var sid = el.data('sid');
      var debut = $('#form-ajoute option[value="'+sid+'"]').data('date');
      var fin = $('#form-ajoute option[value="'+sid+'"]').next().data('date');
      // Affichage des notes déjà mises. Les lignes sont marquées avec la classe
      // orig pour rester affichées lors du clic sur une checkbox de groupe, mais
      // ce marquage est supprimé à la première modification de la note
      var eleves = el.data('eleves').split('|');
      var notes = el.data('notes').split('|');
      for (var i=0; i<eleves.length; i++)
        $('tr[data-id="'+eleves[i]+'"]', form).addClass('orig').show()
          .find('select').val(notes[i]).on('change',function() {
            $(this).parent().parent().removeClass('orig');
          });
      // Initialisation du titre
      $('h3',form).text('Modifier des notes - semaine du '+$('select[name="sid"] option[value="'+sid+'"]').text().split(' ').slice(0,3).join(' '));
      // Si colle non déjà relevée (icône de suppression), on peut tout modifier
      if ( el.next().length )
        // Marquage des déjà notés de la semaine
        marque_dejanotes(sid);
      // Si colle déjà relevée : on ne peut pas ajouter/supprimer des notes ni
      // modifier la durée
      else {
        $('tr:not(.orig), .orig option[value="x"]',table).remove();
        $('#duree').prop('disabled',true);
        form.append('<p>Cette colle a déjà été relevée&nbsp;: il est impossible de modifier quels élèves ont été interrogés ou la durée de la colle. Vous pouvez corriger la date et l\'heure (dans la limite de la semaine enregistrée) ou les notes que vous avez mises vous pouvez mettre une note à un élève initialement absent qui a rattrapé sa colle.</p>');
      }
    }
    // Pas de note d'élève : on supprime le tableau et on récupère la description
    else {
      table.remove();
      $('#td').prop("checked",true).prop("disabled",true);
      $('#description').val(el.parent().prev().prev().prev().text());
      // Initialisation du titre
      $('h3',form).text('Modifier la séance de TD sans note');
      // Si colle déjà relevée, on ne peut pas modifier la durée
      if ( el.next().length == 0 ) {
        $('#duree').prop('disabled',true);
        form.append('<p>Cette séance a déjà été relevée&nbsp;: il est impossible de modifier sa durée. Vous pouvez corriger la date, l\'heure ou la description.</p>');
      }
    }
    // Initialisation de l'identifiant, du jour, de l'heure, de la durée
    $('#id').val(el.parent().data('id').split('|')[1]);
    $('#jour').val($('td:eq(0)',tr).text().replace(/(.{6})(.{2})/,function(tout,x,y) {return x+'20'+y; }));
    $('#heure').val($('td:eq(1)',tr).text().replace('-',''));
    $('#duree').val($('td:eq(3)',tr).text().replace(/.*m/,function(s) {return '0h'+s.slice(0,-1); }));
  }
}

// Facilités spécifiques du formulaire d'ajout d'utilisateurs
$.fn.init_ajout_utilisateurs = function() {
  // Gestion des apparitions sélectives des paragraphes
  $('#autorisation,#saisie').on("change",function() {
    var f = $('#epingle form');
    var a = $('#autorisation', f).val();
    if ( a == 0 ) {
      $('.affichesiinvite,.affichesiinvitation,.affichesimotdepasse', f).hide(0);
      $('textarea', f).prop('disabled',true).attr('placeholder','Zone de saisie des utilisateurs\nSélectionnez d\'abord un type d\'utilisateur');
    }
    else {
      var inv = ( a == 1 );
      var mdp = ( $('#saisie', f).val() == 2 );
      $('#saisie', f).parent().toggle(!inv);
      $('.affichesiinvite', f).toggle(inv);
      $('.affichesiinvitation', f).toggle(!inv && !mdp);
      $('.affichesimotdepasse', f).toggle(!inv && mdp);
      $('textarea', f).prop('disabled',false).attr('placeholder',function() {
        if ( inv )       return 'identifiant_1,motdepasse_1\nidentifiant_2,motdepasse_2\nidentifiant_3,motdepasse_3\n...';
        else if ( mdp )  return 'nom_1,prénom_1,motdepasse_1\nnom_2,prénom_2,motdepasse_2\nnom_3,prénom_3,motdepasse_3\n...';
        else             return 'nom_1,prénom_1,adresse_1\nnom_2,prénom_2,adresse_2\nnom_3,prénom_3,adresse_3\n...';
      });
    }
  }).change();
}

// Facilités spécifiques de modification des événements
$.fn.init_evenements = function() {
  var el = $(this);
  var form = $('#epingle form');
  $('textarea',form).attr('id','texte');
  
  // Cas de l'événement à modifier
  if ( el.is('.modifevnmt') )  {
    var id = el.attr('id').substr(1);
    var valeurs = evenements[id];
    ['type','matiere','debut','fin','texte'].forEach( function(cle) {
      $('#'+cle).val(valeurs[cle]);
    });
    $('#id').val(id);
    $('#texte').change();
    $('#jours').prop('checked',valeurs['je']);
    $('<a class="icon-supprime" title="Supprimer cette information"></a>').insertBefore($('.icon-ok'))
      .on('click',function(){ supprime($(this)); })
      .parent().data('id','agenda-elems|'+id);
  }
  
  // Gestion des sélections de date/heure
  $('#debut').datetimepicker({
    onShow: function()  {
      this.setOptions({maxDate: $('#fin').val() || false });
    },
    onClose: function(t,input) {
      $('#fin').val(function(i,v){ return v || input.val(); });
    }
  });
  $('#fin').datetimepicker({
    onShow: function()  {
      this.setOptions({minDate: $('#debut').val() || false });
    },
    onClose: function(t,input) {
      $('#debut').val(function(i,v){ return v || input.val(); });
    }
  });
  // Case "dates seulement" : changement de format, conservation de l'heure
  $('#jours').on('change',function() {
    var v;
    if ( this.checked )  {
      $('#debut,#fin').each( function() {
        v = this.value.split(' ');
        $(this).val(v[0]).attr('data-heure',v[1]).datetimepicker({ format: 'd/m/Y', timepicker: false });
      });
    }
    else  {
      $('#debut,#fin').each( function() {
        if ( this.hasAttribute('data-heure') )
          $(this).val(this.value+' '+$(this).attr('data-heure')).removeAttr('data-heure');
        $(this).datetimepicker({ format: 'd/m/Y Ghi', timepicker: true });
      });
    }
  }).change();
}

// Facilités du formulaire de modification des devoirs (transfert de copies)
// Attention, this est le bouton cliqué
$.fn.init_devoirs = function(action) {
  var el = $(this);
  var form = $('#epingle form');
  $('#dispo', form).parent().hide(); // Caché par défaut
  // Si modification : ajout de l'identifiant et des données
  if ( action == 'devoirs' ) {
    var donnees = el.parent().data('donnees').split('|');
    $('#id',form).val(el.parent().data('id').split('|')[1]);
    $('#description',form).val(el.parent().children('h3').children('span').text());
    $('#nom',form).val(donnees[0]);
    $('#deadline',form).val(donnees[1]);
    var dispo = el.parent().data('dispo');
    if ( donnees[2].length > 1 )  {
      $('#dispo', form).val(donnees[2]).parent().show();
      $('#affdiff', form).prop("checked",true);
    }
    var tmp = el.parent().children('div').clone();
    tmp.children('em:first').remove();
    if ( tmp.text().trim() )
      $('textarea', form).val(tmp.html().trim());
    tmp.remove();
  }
  // Possibilité ou non de définir la date de disponibilité
  $('#deadline',form).datetimepicker({ format: 'd/m/Y Ghi', timepicker: true });
  $('#dispo', form).datetimepicker({ format: 'd/m/Y Ghi', timepicker: true,
    onShow: function() { this.setOptions({minDate: new Date()}); }
  });
  $('#affdiff', form).on("click", function() {
    $('#dispo', form).parent().toggle(this.checked);
  });
}

// Modification des selects multiples (accès ou matière)
function selmult() {
  var sel = $(this);
  var isacces = this.getAttribute('name').indexOf('protection')+1 ? 1 : 0;
  
  // Fonction de mise à jour du texte affiché 
  function majselect(sel) {
    // Désélection et resélection pour raffraichissement
    sel.prev().children().prop('selected',false).text( function() {
      var options = $(isacces?'option:selected:not([value=6])':'option:selected',sel);
      if ( isacces && ( options.length == 5 ) ) return 'Tout utilisateur identifié'
      if ( options.length == 0 ) return 'Choisir ...';
      else                       return options.map(function() { return this.textContent; }).get().join(', ');
    }).prop('selected',true);
  }
  
  // Faux élément select de remplacement, correspondant au label, générant au clic une fenêtre de sélection
  $('<select id='+sel.prev().attr('for')+'><option selected hidden></option></select>').insertBefore(sel.hide(0)).attr('disabled',sel.attr('disabled')).on("mousedown",function(e)  {
    e.preventDefault();
    this.blur();
    // Fenêtre de sélection
    popup('<a class="icon-ok" title="Valider ce choix"></a><h3>'+sel.prev().prev().text().replace(':','')+'</h3><table id="selmult">'
      +$('option',sel).map(function() {
        return '<tr'+(this.selected?' class="sel"':'')+'><td>'+this.textContent+'</td><td><input type="checkbox" '+(this.selected?'checked ':'')+'value="'+this.value+'"></td></tr>'
      }).get().join('')
      +'</table>',true);
    var f = $('#fenetre');
    // Différenciation des lignes si accès (pour cochage multiple et css)
    if ( isacces ) {
      $('input[value=0],input[value=6],input[value=32]', f).parent().parent().addClass('categorie');
      $('tr:not(.categorie)', f).addClass('element');
      // Décochage automatique
      $('input', f).on("click", function() {
        if ( ( this.value == 0 ) || ( this.value == 32 ) )
          $(this).parent().parent().siblings().find('input[type=checkbox]').prop("checked",false).change();
        else {
          $('input[value=0],input[value=32]', f).prop("checked",false).change();
          $('input[value=6]', f).prop("checked",true).change();
          // Cochage multiple si on clique sur "Utilisateurs identifiés"
          if ( this.value == 6 )
            $('tr:not(.categorie) input', f).prop("checked",true).change();
        }
      });
    }
    // Cochage multiple si matières 
    else {
      $('#selmult', f).prepend('<tr class="categorie"><th></th><th><a class="icon-cocher"></a></th></tr>');
      $('.icon-cocher', f).on("click", cocher_utilisateurs);
    }
    // Clic sur toute la ligne
    $('tr', f).on("click",function(e) {
      if ( !$(e.target).is('input') )
        $(this).find('input').click();
    });
    // Mise en évidence
    $('input', f).on("change", function () { $(this).parent().parent().toggleClass('sel',this.checked); });
    // Validation
    $('.icon-ok', f).on("click", function() {
      // Mise à jour du select, valeur passée sous forme d'array
      sel.val( $('input:checked', f).map(function() { return this.value; }).get() );
      // Mise à jour de l'affichage
      majselect(sel);
      $('#fenetre, #fenetre_fond').remove();
      sel.change(); // Utile pour la liste de documents au transfert de copies
    });
  });
  majselect(sel);
}

///////////////////////////////////////////////////////
// Modifications des utilisateurs, matières, groupes //
///////////////////////////////////////////////////////

// Cochage/Décochage multiple des utilisateurs
function cocher_utilisateurs() {
  $(this).toggleClass('icon-cocher icon-decocher').parent().parent().nextUntil('.categorie').find('input').prop('checked',$(this).hasClass('icon-decocher')).change();
}

// Édition d'un compte utilisateur (utilisateurs.php et utilisateurs-mails.php)
function edite_utilisateur() {
  var id = $(this).parent().parent().data('id');
  // Récupération des données associées au compte
  $.ajax({url: 'recup.php',
          method: "post",
          data: { action:'prefs', id:id },
          dataType: 'json',
          afficheform: function(data) {
            if ( 'nom' in data ) {
              popup($('#form-edite').html(),true);
              var f = $('#fenetre');
              // Création de l'identifiant des champs à partir du name
              $('input[name]',f).attr('id',function(){ return this.getAttribute('name'); });
              // Suppression des paragraphes et des questions non valables
              if ( data['valide'] )             $('#comptedesactive, #demande, #invitation', f).remove();
              else if ( data['demande'] )       $('#compteactif, #comptedesactive, #invitation', f).remove();
              else if ( data['invitation'] )    $('#compteactif, #comptedesactive, #demande', f).remove();
              else                              $('#compteactif, #demande, #invitation', f).remove();
              if ( data['autorisation'] == 1 )    $('#nom, #prenom, #mail1, #mail2', f).parent().remove();
              // Personalisation du premier paragraphe
              $('p:first', f).html(function(i,code){ return code.replace('XXX', data['prenom'].length ? 'de <em>'+data['prenom']+' '+data['nom']+'</em>' : '<em>'+data['login']+'</em>')
                                                                .replace('YYY','<em>'+['Invité','Élève','Colleur','Lycée','Professeur'][data['autorisation']-1]+'</em>'); });
              // Peuplement du formulaire
              $('input[type="text"],input[type="email"]',f).val(function(){ return data[this.id]; });
              $('input[type="checkbox"]',f).prop("checked",function(){ return data[this.id]; });
              if ( !data['mailenvoi'] )
                $('[name="mailcopie"]', f).parent().remove();
              // Envoi par clic sur l'icône
              $('a.icon-ok', f).on("click", function() {
                $.ajax({url: 'ajax.php',
                        method: "post",
                        data: 'action=utilisateur&modif=prefs&id='+id+'&'+$('form', f).serialize(),
                        dataType: 'json',
                        el: '',
                        fonction: function(el) {
                          location.reload(true);
                        }
                });
              });
              // Envoi par appui sur Entrée
              $('input', f).on('keypress',function (e) {
                if ( e.which == 13 ) {
                  e.preventDefault();
                  $('a.icon-ok', f).click();
                }
              });
            }
          }
        });
}

// Initialisation du tableau des utilisateurs
function init_utilisateurs() {
  // Icônes de cochage multiple
  $('.icon-cocher').on("click", cocher_utilisateurs);

  // Édition de comptes uniques
  $('td .icon-edite').on("click", edite_utilisateur);

  // Désactivation, réactivation, suppression de comptes uniques et validation de demandes
  $('td .icon-desactive, td .icon-active, td .icon-supprutilisateur, td .icon-validutilisateur').on("click", modif_utilisateur);

  // Édition, désactivation, réactivation, suppression de comptes multiples et validation de demandes
  $('th .icon-desactive, th .icon-active, th .icon-supprutilisateur, th .icon-validutilisateur').on("click", modif_utilisateurs);

  // Clic sur toute la ligne (premières cases seulement)
  $('td:not(.icones)').on("click",function() { $(this).parent().find('input').click(); });

  // Mise en évidence
  $('#u input').on("change", function () { $(this).parent().parent().toggleClass('sel',this.checked); });
  
  /////////////
  // Fonctions

  // Désactivation, réactivation, suppression, validation d'un compte utilisateur
  function modif_utilisateur() {
    var question = '';
    var nom = $(this).parent().siblings().first();
    var compte = ( nom.text().length ) ? 'de <em>'+nom.next().text()+' '+nom.text()+'</em>' : 'd\'identifiant <em>'+nom.next().next().text()+'</em>';
    var categorie = $(this).parent().parent().prevUntil('.categorie').last().prev().text().split(' ')[0];
    switch ( this.className.substring(5) ) {
      case 'desactive':
        if ( categorie == 'Invités' )
          question = 'Vous allez désactiver le compte invité '+compte+'. Cela signifie que le compte ne sera pas supprimé mais sera non utilisable pour une connexion. Les associations éventuelles avec les matières seront conservées. Ce compte sera listé dans la partie inférieure du tableau.';
        else 
          question = 'Vous allez désactiver le compte '+compte+'. Cela signifie que le compte sera toujours visible pour les professeurs mais que l\'utilisateur correspondant ne pourra plus se connecter. <strong>Les notes de colles éventuelles seront conservées. Les données associées au compte seront conservées.</strong><br> Les accès spécifiques éventuels pourront être rétablis en réactivant le compte.<br> Ce compte sera listé dans la partie inférieure du tableau.<br> Cette possibilité est particulièrement utile pour un élève ou un colleur parti en cours d\'année et dont il faut conserver les notes de colles.';
        break;
      case 'active':
        if ( categorie == 'Invités' )
          question = 'Vous allez réactiver le compte invité '+compte+'. La connexion sera à nouveau possible. Ce compte apparaîtra à nouveau dans la partie principale du tableau.';
        else
          question = 'Vous allez réactiver le compte '+compte+'. Cela signifie que l\'utilisateur correspondant pourra à nouveau se connecter. Il retrouvera son compte, ses notes de colles éventuelles, ses préférences, ses accès spécifiques éventuels, sans modification. Ce compte apparaîtra à nouveau dans la partie principale du tableau.';
        break;
      case 'supprutilisateur':
        if ( categorie == 'Demandes' )
          question = 'Vous allez supprimer la demande '+compte+'. Cela signifie que cette demande ne conduira pas à une création de compte. Le demandeur ne sera pas prévenu de votre décision.<br> Une fois réalisée, cette opération est définitive, mais rien n\'empêche le demandeur d\'effectuer une nouvelle demande.<br> <strong>Si vous n\'attendez plus de nouvelle demande de création de compte, il est certainement préférable de supprimer cette possibilité à l\'aide du réglage accessible en cliquant sur l\'icône <span class="icon-prefs"></span> en haut à droite sur cette page</strong>';
        else if ( categorie == 'Invitations' ) {
          var textecolles = ( $(this).parent().prev().prev().text() == 'Élève' ) ? '<p class="note"><strong>ATTENTION : toutes les notes de colles qui ont déjà pu être déclarées sur ce compte seront supprimées. Cette suppression est définitive.</strong></p><p>' : '<br>';
          question = 'Vous allez supprimer l\'invitation '+compte+'. Cela signifie que cette invitation ne sera plus valable et que si la personne invitée clique sur le lien reçu par courriel, une erreur apparaîtra devant elle.'+textecolles+'<strong>L\'invitation envoyée n\'a pas de date de péremption&nbsp;: il est n\'est pas normal de supprimer l\'invitation pour la refaire, à moins de s\'être trompé d\'adresse électronique. Si la personne invitée vous dit ne pas réussir à s\'identifier, proposez-lui de passer par le lien <em>Mot de passe oublié</em>.</strong><br> La personne invitée ne sera pas prévenue de votre décision.';
        }
        else if ( categorie == 'Professeurs' )
          question = 'Vous allez supprimer le compte professeur '+compte+'. <strong>Cela signifie que toutes les préférences de ce compte seront perdues, ainsi que les éventuelles notes de colles.</strong> <p class="note"><strong>Supprimer un compte pour le recréer n\'est pas la bonne méthode pour réinitialiser un compte,</strong></p><p> par exemple si l\'utilisateur du compte vous indique ne pas arriver à se connecter. Dans ce cas, proposez-lui de passer par le lien <em>Mot de passe oublié</em>. Vous pouvez modifier sur la <a href="utilisateurs">gestion des utilisateurs</a> les nom, prénom, identifiant de connexion et adresse électronique de chaque utilisateur.<br> Pour conserver les données de l\'utilisateur mais lui empêcher la connexion, vous pouvez désactiver le compte en cliquant sur <span class="icon-desactive"></span>.<br> Les données des matières auxquelles il est associé sont indépendantes&nbsp;: elles ne seront pas supprimées.';
        else if ( categorie == 'Lycée' )
          question ='Vous allez supprimer le compte lycée '+compte+'. Cela signifie que toutes les préférences de ce compte seront perdues.';
        else if ( categorie == 'Colleurs' )
          question = 'Vous allez supprimer le compte colleur '+compte+'. <strong>Cela signifie que toutes les préférences de ce compte seront perdues, ainsi que les éventuelles notes de colles.</strong> <p class="note"><strong>Supprimer un compte pour le recréer n\'est pas la bonne méthode pour réinitialiser un compte,</strong></p><p> par exemple si l\'utilisateur du compte vous indique ne pas arriver à se connecter. Dans ce cas, proposez-lui de passer par le lien <em>Mot de passe oublié</em>. Vous pouvez modifier sur la <a href="utilisateurs">gestion des utilisateurs</a> les nom, prénom, identifiant de connexion et adresse électronique de chaque utilisateur.<br> Pour conserver les données de l\'utilisateur mais lui empêcher la connexion, vous pouvez désactiver le compte en cliquant sur <span class="icon-desactive"></span>.';
        else if ( categorie == 'Élèves' )
          question = 'Vous allez supprimer le compte élève '+compte+'. <strong>Cela signifie que toutes les données correspondant à ce compte seront perdues. Les groupes où il apparaît seront modifiés, les notes de colles éventuelles seront supprimées.</strong> <p class="note"><strong>ATTENTION : toutes les notes de colles qui ont déjà pu être déclarées sur ce compte seront supprimées. Cette suppression est définitive.<br> Supprimer un compte pour le recréer n\'est pas la bonne méthode pour réinitialiser un compte,</strong></p><p> par exemple si l\'utilisateur du compte vous indique ne pas arriver à se connecter. Dans ce cas, proposez-lui de passer par le lien <em>Mot de passe oublié</em>. Vous pouvez modifier sur la <a href="utilisateurs">gestion des utilisateurs</a> les nom, prénom, identifiant de connexion et adresse électronique de chaque utilisateur.<br> Pour conserver les données de l\'utilisateur mais lui empêcher la connexion, vous pouvez désactiver le compte en cliquant sur <span class="icon-desactive"></span>.';
        else if ( categorie == 'Invités' )
          question ='Vous allez supprimer le compte invité '+compte+'. Cela signifie que la connexion par ce compte ne sera plus possible.';
        else
          question = 'Vous allez supprimer le compte '+compte+' déjà désactivé. <strong>Cela signifie que toutes les données correspondant à ce compte seront perdues définitivement. Les groupes où il apparaît seront modifiés, les notes de colles éventuelles seront supprimées.</strong>';
        if ( categorie != 'Demandes' )
          question = question + '<br>Une fois réalisée, cette opération est définitive.';
        break;
      case 'validutilisateur':
        question = 'Vous allez valider la demande '+compte+'. Son compte sera immédiatement actif et un courriel va immédiatement être envoyé pour le/la prévenir.<br> Il sera automatiquement associé à toutes les matières&nbsp;: <strong>pensez à aller supprimer les matières qui ne le concernent pas sur la page de gestion des associations utilisateurs-matières.</strong>';
    }
    confirmation(question, this, function(el) {
      $.ajax({url: 'ajax.php',
              method: "post",
              data: { action:'utilisateur', modif:el.className.substring(5), id:$(el).parent().parent().data('id') },
              dataType: 'json',
              el: '',
              fonction: function() {
                location.reload(true);
              }
      });
    });
  }
  
  // Désactivation, réactivation, suppression, validation multiple de comptes utilisateurs
  function modif_utilisateurs() {
    var cases = $(this).parent().parent().nextUntil('.categorie').find(':checked');
    if ( cases.length == 0 ) {
      affiche('<p>Aucune case n\'est cochée, aucune action ne peut être réalisée.</p>','nok');
      return
    }
    var lignes = cases.parent().parent();
    var ids = lignes.map(function() { return $(this).data('id'); }).get().join(',');
    var comptes = lignes.map(function() {
      var nom = $(this).children().first().text();
      return ( nom.length ) ? '<em>'+$(this).children().eq(1).text()+' '+nom+'</em>' : '<em>'+$(this).children().eq(2).text()+'</em>';
    }).get().join(', ');
    var pos = comptes.lastIndexOf(',');
    if ( pos > 0 )
      comptes = comptes.substring(0,pos)+' et'+comptes.substring(pos+1);
    var question = '';
    var categorie = $(this).parent().parent().prev().children().text().split(' ')[0]
    switch ( this.className.substring(5) ) {
      case 'desactive':
        if ( categorie == 'Invités' )
          question = 'Vous allez désactiver les comptes invités '+comptes+'. Cela signifie que ces compte ne seront pas supprimés mais seront non utilisables pour une connexion. Les associations éventuelles avec les matières seront conservées. Ces comptes seront listés dans la partie inférieure du tableau.';
        else 
          question = 'Vous allez désactiver les comptes de '+comptes+'. Cela signifie que ces comptes seront toujours visibles pour les professeurs mais que les utilisateurs correspondant ne pourront plus se connecter. <strong>Les notes de colles éventuelles seront conservées. Les données associées aux comptes seront conservées.</strong><br> Les accès spécifiques éventuels pourront être rétablis en réactivant les comptes.<br> Ces comptes seront listés dans la partie inférieure du tableau.<br> Cette possibilité est particulièrement utile pour des élèves ou des colleurs partis en cours d\'année et dont il faut conserver les notes de colles.';
        break;
      case 'active':
        if ( categorie == 'Invités' )
          question = 'Vous allez réactiver les comptes invité '+comptes+'. La connexion sera à nouveau possible. Ces comptes apparaîtront à nouveau dans la partie principale du tableau.';
        else
          question = 'Vous allez réactiver les comptes de '+comptes+'. Cela signifie que les utilisateurs correspondant pourront à nouveau se connecter. Ils retrouveront leur compte, leurs notes de colles éventuelles, leurs préférences, leurs accès spécifiques éventuels, sans modification. Ces comptes apparaîtront à nouveau dans la partie principale du tableau.';
        break;
      case 'supprutilisateur':
        if ( categorie == 'Demandes' )
          question = 'Vous allez supprimer les demandes de '+comptes+'. Cela signifie que ces demandes ne conduiront pas à des créations de compte. Les demandeurs ne seront pas prévenus de votre décision.<br> Une fois réalisée, cette opération est définitive, mais rien n\'empêche les demandeurs d\'effectuer une nouvelle demande.<br> <strong>Si vous n\'attendez plus de nouvelle demande de création de compte, il est certainement préférable de supprimer cette possibilité à l\'aide du réglage accessible en cliquant sur l\'icône <span class="icon-prefs"></span> en haut à droite sur cette page</strong>';
        else if ( categorie == 'Invitations' ) {
          question = 'Vous allez supprimer les invitations de '+comptes+'. Cela signifie que ces invitations ne seront plus valables et que si les personnes invitées cliquent sur le lien reçu par courriel, une erreur apparaîtra devant elles. <p class="note"><strong>ATTENTION : toutes les notes de colles qui ont déjà pu être déclarées sur les comptes de types élèves seront supprimées. Ces suppressions sont définitives.</strong></p> <p><strong>Ces invitations envoyées n\'ont pas de date de péremption&nbsp;: il n\'est pas normal de supprimer une invitation pour la refaire, à moins de s\'être trompé d\'adresse électronique. Si une personne invitée vous dit ne pas réussir à s\'identifier, proposez-lui de passer par le lien <em>Mot de passe oublié</em>.</strong><br> Les personnes invitées ne seront pas prévenues de votre décision.';
        }
        else if ( categorie == 'Professeurs' )
          question = 'Vous allez supprimer les comptes professeurs de '+comptes+'. <strong>Cela signifie que toutes les préférences de ces comptes seront perdues, ainsi que les éventuelles notes de colles.</strong> <p class="note"><strong>Supprimer un compte pour le recréer n\'est pas la bonne méthode pour réinitialiser un compte,</strong></p><p> par exemple si l\'utilisateur d\'un compte vous indique ne pas arriver à se connecter. Dans ce cas, proposez-lui de passer par le lien <em>Mot de passe oublié</em>. Vous pouvez modifier sur la <a href="utilisateurs">gestion des utilisateurs</a> les nom, prénom, identifiant de connexion et adresse électronique de chaque utilisateur.<br> Pour conserver les données d\'un utilisateur mais lui empêcher la connexion, vous pouvez désactiver le compte en cliquant sur <span class="icon-desactive"></span>.<br> Les données des matières auxquelles ces utilisateurs sont associés sont indépendantes&nbsp;: elles ne seront pas supprimées.';
        else if ( categorie == 'Lycée' )
          question ='Vous allez supprimer les comptes lycée de '+comptes+'. Cela signifie que toutes les préférences de ces comptes seront perdues.';
        else if ( categorie == 'Colleurs' )
          question = 'Vous allez supprimer les comptes colleurs de '+comptes+'. <strong>Cela signifie que toutes les préférences de ces comptes seront perdues, ainsi que les éventuelles notes de colles.</strong> <p class="note"><strong>Supprimer un compte pour le recréer n\'est pas la bonne méthode pour réinitialiser un compte,</strong></p><p> par exemple si l\'utilisateur d\'un compte vous indique ne pas arriver à se connecter. Dans ce cas, proposez-lui de passer par le lien <em>Mot de passe oublié</em>. Vous pouvez modifier sur la <a href="utilisateurs">gestion des utilisateurs</a> les nom, prénom, identifiant de connexion et adresse électronique de chaque utilisateur.<br> Pour conserver les données d\'un utilisateur mais lui empêcher la connexion, vous pouvez désactiver le compte en cliquant sur <span class="icon-desactive"></span>.';
        else if ( categorie == 'Élèves' )
          question = 'Vous allez supprimer les comptes élèves de '+comptes+'. <strong>Cela signifie que toutes les données correspondant à ces comptes seront perdues. Les groupes où ils apparaissent seront modifiés, les notes de colles éventuelles seront supprimées.</strong> <p class="note"><strong>ATTENTION : toutes les notes de colles qui ont déjà pu être déclarées sur ces compte seront supprimées. Cette suppression est définitive.<br> Supprimer un compte pour le recréer n\'est pas la bonne méthode pour réinitialiser un compte,</strong></p><p> par exemple si l\'utilisateur d\'un compte vous indique ne pas arriver à se connecter. Dans ce cas, proposez-lui de passer par le lien <em>Mot de passe oublié</em>. Vous pouvez modifier sur la <a href="utilisateurs">gestion des utilisateurs</a> les nom, prénom, identifiant de connexion et adresse électronique de chaque utilisateur.<br> Pour conserver les données d\'un utilisateur mais lui empêcher la connexion, vous pouvez désactiver le compte en cliquant sur <span class="icon-desactive"></span>.';
        else if ( categorie == 'Invités' )
          question ='Vous allez supprimer les comptes invités de '+comptes+'. Cela signifie que la connexion par ces comptes ne sera plus possible.';
        else
          question = 'Vous allez supprimer les comptes de '+comptes+' déjà désactivés. <strong>Cela signifie que toutes les données correspondant à ces comptes seront perdues définitivement. Les groupes où ils apparaîssent seront modifiés, les notes de colles éventuelles seront supprimées. Dans le cas des comptes professeurs, les données des matières associées ne seront pas supprimées.</strong>';
        if ( categorie != 'Demandes' )
          question = question + '<br>Une fois réalisée, cette opération est définitive.';
        break;
      case 'validutilisateur':
        question = 'Vous allez valider les demandes de '+comptes+'. Leurs comptes seront immédiatement actifs et un courriel va immédiatement leur être envoyé pour les prévenir.<br> Ils seront automatiquement associés à toutes les matières&nbsp;: <strong>pensez à aller supprimer les matières qui ne les concernent pas sur la page de gestion des associations utilisateurs-matières.</strong>';
    }
    confirmation(question, this, function(el) {
      $.ajax({url: 'ajax.php',
              method: "post",
              data: { action:'utilisateurs', modif:el.className.substring(5), ids:ids },
              dataType: 'json',
              el: '',
              fonction: function() {
                location.reload(true);
              }
      });
    });
  }
}

// Initialisation du tableau des associations utilisateurs-matières
function init_utilisateurs_matieres() {
  
  // Affichage des icônes d'association
  // La case de tableau HTML contient deux valeurs séparées de "|" :
  // l'identifiant de la matière et la valeur 1 pour oui 0 pour non.
  $('tr:not(.categorie) td:not(:first-child,:last-child)').each( function() {
      var valeurs = this.textContent.split('|');
      this.innerHTML = ( valeurs[1] == 1 ) ? '<a class="icon-ok" data-id="'+valeurs[0]+'" title="Supprimer l\'association à la matière"></a>'
                                           : '<a class="icon-nok" data-id="'+valeurs[0]+'" title="Établir l\'association à la matière"></a>';
  });
  $('#umats a').on("click", association_um);
  
  // Icônes de cochage multiple
  $('.categorie [data-id]').on("click", association_ums).hide(0);
  $('.icon-cocher').on("click", cocher_utilisateurs).on("click",majicones)
  $('input[type="checkbox"]').on("click", majicones).on("change", function() {
    // Mise en évidence
    $(this).parent().parent().toggleClass('sel',this.checked);
  });;
  
  // Clic sur la case de nom pour cocher
  $('td:first-child').on("click",function() { $(this).parent().find('input').click(); });

  // Mise à jour des icônes de modifications multiples
  function majicones() {
    // Ligne de catégorie correspondant à l'icone ou à la case cliquée
    var tr = $(this).parent().parent();
    if ( !tr.hasClass('categorie') )
      tr = tr.prevAll('.categorie').first();
    // Cases cochées
    var cases = tr.nextUntil('.categorie').find(':checked');
    if ( cases.length == 0 )
      $('[data-id]',tr).hide(0);
    else
      $('[data-id]',tr).each( function() {
        var avant = $(this).hasClass("icon-ok");
        var apres = cases.parent().prevAll().find('.icon-ok[data-id="'+this.getAttribute('data-id')+'"]').length < cases.length/2;
        if ( avant != apres )
          $(this).toggleClass('icon-ok icon-nok').attr('title', (apres?'Établir':'Supprimer')+' l\'association à la matière de tous les cochés');
      }).show(0);
  }

  // Fonction d'association unique
  function association_um() {
    var val = $(this).hasClass("icon-ok");
    $.ajax({url: 'ajax.php',
            method: "post",
            data: { action:'utilisateur-matiere', id:$(this).parent().parent().data('id'), matiere:$(this).data('id'), val:1-val },
            dataType: 'json',
            el: $(this),
            fonction: function(el) {
              el.toggleClass('icon-ok icon-nok').attr('title', (val?'Établir':'Supprimer')+' l\'association à la matière');
            }
    });
  }
  
  // Fonction d'association multiple
  function association_ums() {
    var cases = $(this).parent().parent().nextUntil('.categorie').find(':checked');
    if ( cases.length == 0 ) {
      affiche('<p>Aucune case n\'est cochée, aucune action ne peut être réalisée.</p>','nok');
      return
    }
    var lignes = cases.parent().parent();
    var ids = lignes.map(function() { return $(this).data('id'); }).get().join(',');
    var comptes = lignes.children(':first-of-type').map(function() { return $(this).text().split('(')[0].trim(); }).get().join(', ');
    var pos = comptes.lastIndexOf(',');
    if ( pos > 0 )
      comptes = comptes.substring(0,pos)+' et'+comptes.substring(pos+1);
    var val = $(this).hasClass("icon-ok");
    var mid = this.getAttribute('data-id');
    var question = val ? 'Vous allez établir l\'association à la matière '+$('#m'+mid).text()+' pour les comptes de '+comptes+'. Cela signifie que ces utilisateurs auront accès aux ressources liées à cette matière, en fonction de l\'autorisation que vous avez fixée pour ces ressources.' : 'Vous allez supprimer l\'association à la matière '+$('#m'+mid).text()+' pour les comptes de '+comptes+'. Cela signifie que ces utilisateurs n\'auront plus accès aux ressources liées à cette matière. Si des notes de colles ont été saisies, elles seront automatiquement et définitivement supprimées de la base.';
    confirmation(question, this, function(el) {
      $.ajax({url: 'ajax.php',
              method: "post",
              data: { action:'utilisateurs-matieres', ids:ids, matiere:mid, val:val|0 },
              dataType: 'json',
              el: '',
              fonction: function(el) {
                location.reload(true);
              }
      });
    });
  }
  
}

// Initialisation du tableau des autorisation d'envoi de courriels
// et du tableau d'édition des adresses électroniques
function init_envoimails() {
  
  var t = $('#envoimails');
  // Affichage des icônes de validation
  // La case de tableau HTML contient deux valeurs séparées de "|" :
  // l'identifiant du groupe de réception et la valeur 1 pour oui 0 pour non.
  // Le groupe d'émission est le data-id de la ligne.
  $('td',t).each( function() {
      var valeurs = this.textContent.split('|');
      this.innerHTML = ( valeurs[1] == 1 ) ? '<a class="icon-ok" data-id="'+valeurs[0]+'" title="Supprimer l\'autorisation d\'envoi"></a>'
                                           : '<a class="icon-nok" data-id="'+valeurs[0]+'" title="Établir l\'autorisation d\'envoi"></a>';
  });

  // Icônes de validation simple
  $('td a',t).on("click", function() {
    var val = $(this).hasClass("icon-nok")|0;
    $.ajax({url: 'ajax.php',
            method: "post",
            data: { action:'prefsglobales', mails:1, depuis:$(this).parent().parent().data('id'), vers:$(this).data('id'), val:val },
            dataType: 'json',
            el: $(this),
            fonction: function(el) {
              el.toggleClass('icon-ok icon-nok').attr('title', (val?'Établir':'Supprimer')+' l\'autorisation d\'envoi');
            }
    });
  });

  // Icônes de validation multiple
  $('th span',t).on("click", function() {
    var val = $(this).hasClass("icon-ok");
    var ligne = $(this).parent().parent();
    $.ajax({url: 'ajax.php',
            method: "post",
            data: { action:'prefsglobales', mails:1, depuis:ligne.data('id'), vers:0, val:val|0 },
            dataType: 'json',
            el: ligne,
            fonction: function(el) {
              el.find('td a').toggleClass('icon-ok',val).toggleClass('icon-nok',!val).attr('title', (val?'Établir':'Supprimer')+' l\'autorisation d\'envoi');
            }
    });
  });
  
  // Édition de comptes uniques
  $('#umails .icon-edite').on("click", edite_utilisateur);
  
}

// Édition des utilisateurs associés à un groupe
function init_utilisateurs_groupes() {
  // Cases à cocher : utilisation des groupes pour les mails et/ou les notes
  $('article input[type="checkbox"]').on("change", function() {
    $.ajax({url: 'ajax.php',
            method: "post",
            data: { action:'groupes', champ:this.id.substr(0,5), id:this.id.substr(5), val:(this.checked|0) },
            dataType: 'json',
            el: '',
            fonction: function(el) {
              return true;
            }
    });
  });
  // Édition des utilisateurs des groupes
  $('.usergrp span').append('&nbsp;<a class="icon-edite" title="Éditer les utilisateurs de ce groupe"></a>').on("click",utilisateursgroupe);
}

// Affichage du tableau de sélection des utilisateurs d'un groupe
function utilisateursgroupe() { 
  // Création de la fenêtre et variables
  popup($('#form-utilisateurs').html(),true);
  var f = $('#fenetre');
  var span = $(this);
  article = span.parent().parent();
  $('table', f).attr('id','ugrp');

  // Modification du groupe indiqué en titre
  $('h3', f).append($('.editable', article).text() || $('input:first', article).val());

  // Pliage/dépliage (les spans ont déjà été ajoutés)
  $('.icon-deplie', f).on("click", plie)

  // Cochage multiple
  $('.icon-cocher', f).on("click", cocher_utilisateurs);

  // Clic sur toute la ligne
  $('tr:not(.categorie)', f).on("click",function(e) {
    if ( !$(e.target).is('input') )
      $(this).find('input').click();
  });

  // Mise en évidence
  $('input', f).on("change", function () { $(this).parent().parent().toggleClass('sel',this.checked); });
  
  // Sélection automatique des utilisateurs déjà choisis
  var ids = span.data('uids').toString();
  $('#u'+ids.replace(/,/g,',#u'), f).prop("checked",true).change();

  // Récupération des valeurs et envoi
  $('.icon-ok', f).on("click", function() {
    var ids = $('input:checked', f).map(function() { return this.id.replace('u',''); }).get().join(',');
    var noms = $('input:checked', f).parent().prev().map(function() { return this.textContent.split('(')[0].trim(); }).get().join(', ') || '[Personne]';
    // Si formulaire d'ajout, simple mise à jour du formulaire et du span
    if ( article.is('div') ) {
      $('#uids', article).val(ids);
      span.data('uids',ids);
      span.html(noms+'&nbsp;<a class="icon-edite" title="Éditer les utilisateurs de ce groupe"></a>');
      $('#fenetre, #fenetre_fond').remove();
    }
    // Si groupe déjà existant, envoi des données
    else
      $.ajax({url: 'ajax.php',
              method: "post",
              data: { action:'groupes', champ:'utilisateurs', id:article.data('id').split('|')[1], uids:ids },
              dataType: 'json',
              el: span,
              fonction: function(el) {
                // Mise à jour de la liste des utilisateurs
                el.data('uids',ids);
                el.html(noms+'&nbsp;<a class="icon-edite" title="Éditer les utilisateurs de ce groupe"></a>');
                $('#fenetre, #fenetre_fond').remove();
              }
      });
  });
};

// Suppression massive des éléments d'une matière ou des informations d'une page
function suppressionmultiple() {
  var prop = $(this).data('id').split('|');
  var contexte = $(this).parent().find('h3').text();
  var item = '';
  switch ( prop[2] ) {
    case 'infos': item = 'toutes les informations de la page <em>'+contexte+'</em>'; break;
    case 'colles': item = 'tous les programmes de colles de la matière <em>'+contexte+'</em>'; break;
    case 'cdt': item = 'tout le contenu du cahier de texte de la matière <em>'+contexte+'</em>'; break;
    case 'docs': item = 'tous les répertoires et documents de la matière <em>'+contexte+'</em>'; break;
    case 'notes': item = 'toutes les notes de la matière <em>'+contexte+'</em>'; break;
  }
  confirmation('Vous allez supprimer XXX.<br>Cette opération n\'est pas annulable.'.replace('XXX',item),this,function(el) {
    $.ajax({url: 'ajax.php',
            method: "post",
            data: 'action='+prop[0]+'&id='+prop[1]+'&supprime_'+prop[2]+'=1',
            dataType: 'json',
            el: $(el),
            fonction: function(el) {
              el.remove();
            }
    });
  });
}

////////////////////////////////////////
// Paramétrage et envoi des courriels //
////////////////////////////////////////

// Affichage du tableau de sélection des utilisateurs destinataires d'un courriel
function destinatairesmail() {
  popup($('#form-destinataires').html(),true);
  var f = $('#fenetre');
  
  // Pliage/dépliage (les spans ont déjà été ajoutés)
  $('.icon-deplie', f).on("click", plie)
  
  // Ajouts des identifiants pour la sélection automatique (début et groupes)
  $('tr:not(.gr) input.dest', f).attr('id',function() { return 'u'+this.value; });
  
  // Clic sur toute la ligne (deux premières cases seulement)
  $('tr:not(.categorie) td:nth-child(-n+2)', f).on("click",function(e) {
    if ( !$(e.target).is('input') )
      $(this).parent().find('input:first').click();
  });
  
  // Décochage automatique de l'autre case du même utilisateur et mise en évidence
  $('input', f).on("change", function () {
    var tr = $(this).parent().parent();
    if ( this.checked )
      tr.find('input:not(.'+this.className+')').prop("checked",false);
    tr.toggleClass('sel',tr.find('input:checked').length>0);
  });
  
  // Sélection automatique des utilisateurs déjà choisis
  var ids = $('[name="id-copie"]').val();
  $('#u'+ids.replace(/,/g,',#u')).prop("checked",true).change();
  ids = $('[name="id-bcc"]').val();
  $('#u'+ids.replace(/,/g,',#u')).parent().next().children().prop("checked",true).change();

  // Bouton de sélection multiple
  $('.categorie a', f).on("click keyup", function () {
    // Récupération des valeurs
    var classe = this.className.split(' ')[1]; // dest ou bcc
    var etat = (this.className.split(' ')[0] == 'icon-cocher');
    var titre = this.title;

    // Cochage et modifications
    $(this).parent().parent().nextUntil('.categorie').find('.'+classe+':not(:disabled)').prop('checked',etat).change();
    this.className = (etat?'icon-decocher ':'icon-cocher ')+classe;
    this.title = this.title.replace((etat?'Cocher':'Décocher'),(etat?'Décocher':'Cocher'));
    var classe2 = (classe == 'dest') ? 'bcc' : 'dest';
    $(this).parent().parent().find('.icon-decocher.'+classe2).each( function() {
      this.className = 'icon-cocher '+classe2;
      this.title = 'C'+this.title.substr(3); 
    });
  });
  
  // Groupes
  $('.gr input', f).on("click", function () {
    var ids = this.value;
    if ( this.className == 'dest' )
      $('#u'+ids.replace(/,/g,',#u')).prop("checked",this.checked).change();
    else
      $('#u'+ids.replace(/,/g,',#u')).parent().next().children().prop("checked",this.checked).change();
  });
  
  // Récupération des valeurs
  $('.icon-ok', f).on("click", function() {
    $('[name="id-copie"]').val( $('tr:not(.gr) .dest:checked',f).map(function() { return this.value; }).get().join(',') );
    $('[name="id-bcc"]').val(   $('tr:not(.gr) .bcc:checked', f).map(function() { return this.value; }).get().join(',') );
    $('#maildest').text(        $('tr:not(.gr) .dest:checked', f).parent().prev().map(function() { return this.textContent; }).get()
                        .concat($('tr:not(.gr) .bcc:checked', f).parent().prev().prev().map(function() { return this.textContent+' (CC)'; }).get())
                        .join(', ') || '[Personne]' );
    $('#fenetre, #fenetre_fond').remove();
  });
}

// Envoi des courriels
function envoimail() {
  // Pas d'envoi si pas de destinataire
  if ( $('.maildest').children('span').text() == '[Personne]' )
    affiche('Il faut au moins un destinataire pour envoyer le courriel.','nok');
  else if ( !$('[name="sujet"]').val().length )
    affiche('Il faut un sujet non vide pour envoyer le courriel.','nok');
  else
    $.ajax({url: 'ajax.php',
            method: "post",
            data: $('#mail').serialize(),
            dataType: 'json',
            el: '',
            fonction: function(el) {
              location.reload(true);
            }
    });
}

////////////////////////////////
// Relève des notes de colles //
////////////////////////////////
function relevenotes() { 
  confirmation('<p>Vous allez réaliser une relève des notes de colles. Cela consiste à marquer comme relevées toutes les heures déclarées jusqu\'à maintenant et non encore relevées. Vous pourrez alors télécharger le nouveau relevé au sein du tableau en bas de page.</p><p>Cette opération n\'est pas annulable.</p><p>Une fois que vous aurez réalisé ce relevé, les professeurs et colleurs ne pourront pas modifier le nombre d\'élèves et la durée correspondant aux colles relevées.</p>',this,function(el) {
    $.ajax({url: 'ajax.php',
            method: "post",
            data: 'action=releve-notes',
            dataType: 'json',
            el: '',
            fonction: function(el) {
              location.reload(true);
            }
    });
  });
}

//////////////////////////////////////////////
// Récupération/ajout de copies/corrections //
//////////////////////////////////////////////
function recapcopies() {
  // Suppression d'un éventuel contenu épinglé existant
  $('#epingle').remove();
  // Création du nouveau formulaire
  var nom = $(this).parent().children('h3').text();
  var article = $('<article id="epingle"><a class="icon-ferme" title="Fermer"></a>\
    <a class="icon-aide" title="Aide pour ce formulaire"></a>\
    <a class="icon-actualise" title="Actualiser"></a>\
    <a class="icon-ajoute" title="Ajouter des documents"></a>\
    <h3>Détails de ' + nom.substring(0,nom.lastIndexOf('('))+'</h3></article>').insertBefore($('article').first());
  var form = $('<form id="detail"></form>').appendTo(article).html($('#form-detail').html()).data('id',$(this).parent().data('id').split('|')[1]).data('ordre','alphaasc');
  $('input', form).first().val(form.data('id'));
  $('.icon-ferme', article).on("click",function() { article.remove(); });
  // Formulaire d'ajout de documents (corrections, sujets)
  $('a.icon-ajoute', article).on("click",function() {
    // Suppression de l'éventuel formulaire déjà présent
    $('#ajoute-copies').remove();
    // Nouveau formulaire
    var form1 = $('<form id="ajoute-copies"></form>').insertBefore(form).html($('#form-ajoute-copies').html());
    $('.icon-ferme', form1).on("click",function() { form1.remove(); });
    $('input', form1).first().val(form.data('id'));
    // Fabrication du select des élèves
    var sel_eleves = $('<select><option value="0">Choisir un élève</option></select>');
    enoms.forEach( function(e,i) {
      sel_eleves.append('<option value="'+eids[i]+'">'+e.replace(',',' ')+'</option>');
    });
    // Correspondance automatique des documents avec les élèves
    $('input[type="file"]', form1).attr('id','fichier').on('change',function () {
      // Suppression des anciennes lignes
      $('select[id^="nom"]', form1).parent().remove();
      var fichiers = this;
      var n = fichiers.files.length;
      // Affichage des nouvelles lignes
      for (var i = 0; i < n; i++) {
        var f = fichiers.files[i].name;
        var nom = f.substring(f.lastIndexOf('\\')+1,f.lastIndexOf('.')) || f;
        var p = $('<p class="ligne"><label for="eleve'+i+'">'+nom+'</label></p>').appendTo(form1);
        sel_eleves.clone().attr('id','eleve'+i).attr('name','eid[]').appendTo(p);
        // Recherche de correspondance : d'abord nom et prénom, utile en cas de jumeaux
        correspondance: {
          for (var j = 0; j < enoms.length; j++)
            if ( nom.toLowerCase().indexOf(enoms[j].replace(',',' ').toLowerCase()) > -1 )  {
              $('select',p).val(eids[j]);
              break correspondance;
            }
          for (var j = 0; j < enoms.length; j++)
            if ( nom.toLowerCase().indexOf(enoms[j].split(',')[0].toLowerCase()) > -1 )  {
              $('select',p).val(eids[j]);
              break correspondance;
            }
        }
        $('select',p).on("change",function() {
          $(this).toggleClass('nok',this.value==0);
        }).change();
      }
    });
    // Envoi
    $('.icon-ok',form1).on("click",function() {
      // Vérification que chaque document est associé à un élève
      if ( $('select.nok',form1).length )  {
        affiche('Certains documents n\'ont pas d\'élève associé.','nok');
        return;
      }
      // Test de connexion
      // Si reconnect() appelée, le paramètre connexion sert à obtenir un retour
      // en état ok/nok pour affichage si nok. Si ok, on réécrit le message. 
      $.ajax({url: 'copies.php',
              method: "post",
              data: 'connexion=1',
              dataType: 'json',
              el: '',
              fonction: function(el) {
                // Si transfert, pas d'affichage dans le div de log
                $('#log').hide();
                // Envoi réel du fichier ou des données
                var data = new FormData(form1[0]);
                // Envoi
                $.ajax({url: 'ajax.php',
                        xhr: function () { 
                          // Évolution du transfert si fichier transféré
                          var xhr = $.ajaxSettings.xhr();
                          if ( xhr.upload && ( $('#fichier')[0].files.length > 0 ) )
                            $('#load').html('<p class="clignote">Transfert en cours<span></span></p><img src="js/ajax-loader.gif">');
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
                          form1.remove();
                          $('#epingle a.icon-actualise').click();
                        }
                });
              }
      });
    });
  });
  // Remplissage du tableau
  $('a.icon-actualise', article).on("click",function() {
    // On marque les lignes à supprimer sans le faire de suite
    $('tr', form).slice(2).addClass('a_supprimer');
    $('p', form).addClass('a_supprimer');
    $.ajax({url: 'recup.php',
            method: "post",
            data: { action:'copies', id: form.data('id'), ordre: form.data('ordre'), types:  $('select[multiple] option:selected',form).map(function() { return this.value|0; }).get().join(',') },
            dataType: 'json',
            afficheform: function(data) {
              // On laisse uniquement une ligne pour conserver le layout
              $('tr.a_supprimer').slice(1).remove();
              // Récupération des valeurs et écriture 
              var lignes = data['lignes'];
              var table = $('tbody', form);
              var type = ['Copie ','Correction ','Sujet '];
              if ( lignes.length )  {
                presents = [];
                lignes.forEach( function(ligne) {
                  table.append('<tr data-id="'+ligne[0]+'"><td>'+ligne[1]+'</td><td>'+type[ligne[2]]+ligne[3]+'</td><td>'+ligne[4]+'</td><td>'+ligne[5]+'</td><td>'+ligne[6]+'</td>\
                                <td class="icones"><a class="icon-download" title="Télécharger ce document" href="?dl='+ligne[0]+'"></a> <a class="icon-supprime" title="Supprimer ce document"></a></td>\
                                <td class="icones"><input type="checkbox"></td></tr>');
                  presents.push(parseInt(ligne[8]));
                });
                if ( presents.length == eids.length ) 
                  table.parent().before('<p>Tous les élèves sont présents dans ce tableau.</p>');
                else  {
                  var absents = enoms.reduce( function(total,nom,i){ 
                    if ( presents.indexOf(eids[i]) == -1 )
                      return total+', '+nom.replace(',',' ');
                    return total;
                  },'').substr(2);
                  table.parent().before('<p>Élèves absents du tableau&nbsp;: '+absents+'.</p>');
                }
              }
              else 
                table.append('<tr><td class="centre" colspan="6">Aucun résultat trouvé</td></tr>');
              // On supprime l'éventuelle ligne laissée et le paragraphe d'indications
              $('.a_supprimer').remove();
              // Suppression individuelle 
              $('td a.icon-supprime', table).on("click", function() {
                var ligne = $(this).parent().parent();
                // Demande de confirmation
                confirmation('Vous allez supprimer un document. Cette action n\'est pas annulable.',$(this).parent().parent(), function(ligne) {
                  $.ajax({url: 'ajax.php',
                          method: "post",
                          data: { action:'suppr-copie', id:ligne.data('id') },
                          dataType: 'json',
                          el: ligne,
                          fonction: function(el) {
                            ligne.hide().remove();
                          }
                  });
                });
              });
            }
    });
  }).click();
  // Modifications de l'affichage du tableau
  $('select[multiple]', form).each(selmult).on("change",function() {
    $('#epingle a.icon-actualise').click();
  });
  $('th.icones', form).first().find('a').on("click",function() {
    form.data('ordre',this.className.substr(5));
    $('#epingle a.icon-actualise').click();
  });
  // Icônes de cochage multiple
  $('.icon-cocher', form).on("click", cocher_utilisateurs);
  // Téléchargement multiple
  $('th .icon-download', form).on("click", function() {
    var cases = $('input:checked', form);
    if ( cases.length == 0 ) {
      affiche('<p>Aucune case n\'est cochée, aucune action ne peut être réalisée.</p>','nok');
      return
    }
    var lignes = cases.parent().parent();
    var ids = lignes.map(function() { return $(this).data('id'); }).get().join(',');
    // Test de connexion : on fait le téléchargement en get, donc on doit
    // être connecté en connection non light avant
    $.ajax({url: 'copies.php',
            method: "post",
            data: 'connexion=1',
            dataType: 'json',
            el: '',
            fonction: function(el) {
              // Si connexion correcte, pas d'affichage dans le div de log
              $('#log').text('Génération du zip');
              window.location.href = 'copies.php?dlcopies&devoir='+form.data('id')+'&ids='+ids;
            }
    });
  });
  // Suppression multiple
  $('th .icon-supprime', form).on("click",  function() {
    var cases = $('input:checked', form);
    if ( cases.length == 0 ) {
      affiche('<p>Aucune case n\'est cochée, aucune action ne peut être réalisée.</p>','nok');
      return
    }
    var lignes = cases.parent().parent();
    var ids = lignes.map(function() { return $(this).data('id'); }).get().join(',');
    confirmation('Vous allez supprimer '+cases.length+' documents. Cette opération n\'est pas annulable.', this, function(el) {
      $.ajax({url: 'ajax.php',
              method: "post",
              data: { action:'suppr-copies', devoir:form.data('id'), ids:ids },
              dataType: 'json',
              el: '',
              fonction: function() {
                $('#epingle a.icon-actualise').click();
              }
      });
    });
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
                // Si 'recupok' : récupération de données
                case 'recupok':
                  settings.afficheform(data);
              }
            });

////////////////////////////////////////////////////////////////////////////
// Modification des éléments (nécessite le chargement complet de la page) //
////////////////////////////////////////////////////////////////////////////
$( function() {

  // Formulaires affichés par clic sur les icônes
  $('a.formulaire, .modifevnmt').on("click", formulaire);

  // Aide
  $('a.icon-aide').on("click", function() {
    popup($('#aide-'+( $(this).parent().data('id') || 'page' ).split('|')[0]).html(),false);
  });
  // Validation des formulaires déjà présents (pas pour les transformés)
  $('a.icon-ok').on("click", valide);

  // Boutons cache, montre, monte, descend, supprime, protection (lock), ajoute-colle
  $('a.icon-cache,a.icon-montre,a.icon-monte,a.icon-descend,a.icon-supprime,a.icon-lock,a.icon-ajoutecolle').on("click", function() {
    window[this.className.substring(5)]($(this));
  });

  // Div d'affichage des résultats des requêtes AJAX
  $('#log').hide().on("click", function() {
    $(this).hide(300);
  });

  // Édition en place des éléments de classe "editable"
  $('.editable').editinplace();

  // Déconnexion
  $('a.icon-deconnexion').on("click",function(e) {
    $.ajax({url: 'ajax.php',
            method: "post",
            data: { action:'deconnexion' },
            dataType: 'json',
            el: '',
            fonction: function(el) {
              location.reload(true);
            }
    });
  });

  // Menu mobile
  $('.icon-menu').on("click", function(e) {
    e.stopPropagation();
    $('#menu').toggleClass('visible');
    if ( $('#menu').hasClass('visible') )  {
      $('<div id="menu_fond"></div>').appendTo('body');
      $('#menu_fond').on("click", function() {
        $('#menu_fond').remove();
        $('#menu').removeClass('visible');
      });
    }
    else 
      $('#menu_fond').remove();
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

  /////////////////////////////
  // Spécifique cahier de texte

  // Édition des propriétés des éléments des cahiers de texte
  $('.titrecdt').editinplacecdt();

  // Édition des propriétés des raccourcis des cahiers de texte
  $('.cdt-raccourcis').init_cdt_raccourcis();

  ///////////////////////////////
  // Spécifique envoi de courriel

  // Envoi de courriel
  $('.icon-mailenvoi').on("click", envoimail);
  
  // Édition des utilisateurs destinataires d'un courriel
  $('#maildest, #maildest + .icon-edite').on("click", destinatairesmail);

  ///////////////////////////////
  // Spécifique réglages des utilisateurs, matières, groupes

  // Affichage des icônes de pliage sur les lignes ".categorie" des tableaux d'utilisateurs
  $('.categorie th:first-child').prepend(
    $('<span class="icon-deplie" title="Déplier/Replier cette catégorie"></span>').on("click", plie)
  );

  // Gestion des matières
  $('article select[multiple]').each(selmult);
  
  // Boutons de suppression massive des éléments de matière et des informations d'une page
  $('.supprmultiple').on("click", suppressionmultiple);
  
  // Gestion des utilisateurs
  $('#u').each(init_utilisateurs);
  
  // Gestion des associations utilisateurs-matières
  $('#umats').each(init_utilisateurs_matieres);

  // Gestion des autorisations d'envoi de courriels
  $('#envoimails').each(init_envoimails);

  // Édition des utilisateurs des groupes - fonction à ne lancer qu'une fois
  $('.usergrp').first().each(init_utilisateurs_groupes);

  /////////////////////////////
  // Spécifique planning annuel

  // Modification des valeurs du planning
  $('#planning select').change( function() {
    $(this).parent().prev().children('input').prop('checked',this.value == 0);
  });
  $('#planning input').change( function() {
      $(this).parent().next().children('select').val(0);
  });
  
  /////////////////////////////
  // Spécifique relève de notes
  $('#relevenotes').on("click", relevenotes);
  
  /////////////////////////////
  // Spécifique transfert de copies
  $('.devoir .icon-voirtout').on("click", recapcopies);
  $('.devoir .icon-ajoute').on("click", function()  {
    $(this).prev().each(recapcopies);
    $('#epingle a.icon-ajoute').click();
  });

  /////////////////////////////
  // Ne pas quitter avec un textarea plein
  $('body').data('nepassortir',false);
  $('textarea:visible').on('change',function(e) { $('body').data('nepassortir',true); $(this).off(e); });
  window.addEventListener('beforeunload', function (e) { if ( $('body').data('nepassortir') )  { e.preventDefault(); e.returnValue = ''; } });

});

