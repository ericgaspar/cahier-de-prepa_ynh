Version actuelle : 9.2.1 (12/04/20)
===================
Changements :
1.0   31/08/11 Première version
1.0.1 01/09/11 Correction de bugs pour l'installation, ajout du planning de spé
1.0.2 03/09/11 Correction de bugs pour l'installation (merci O. Bouverot)
1.0.3 14/09/11 Séparation des fichiers README et CHANGELOG
  Bug d'affichage du menu pour la partie d'administration (merci O. Bouverot)
  Problème d'accents pour les répertoires et documents (merci C. Chevalier)
1.0.4 14/09/11 Correction de bug pour les liens de téléchargement
  lorsqu'il y a des accents pour certains navigateurs (merci T. Chaboud)
1.0.5 15/09/11 Correction de bug pour le programme de colle à modifier, mauvais
  choix de matière si plusieurs disponibles (merci C. Chevalier)
1.0.6 25/09/11 Correction de bug d'affichage du menu (IE7 ; merci S. Boissière)
  Corrections des boutons d'édition du cahier de texte
  Réglage du serveur MySQL dans le fichier de configuration
  Passage à 100 caractères pour les nom et titres de documents et de répertoires
1.0.7 26/09/11 Régression sur l'installation (merci O. Bouverot)
2.0.1 05/09/12 Réécriture d'une grande partie des scripts. Ajout de nombreuses
  fonctionnalités :
  * accès en lecture protégé par mot de passe, de façon spécifique
  * refonte et simplification de l'interface d'administration (et de l'aide)
  * gestion des répertoires/documents (possibilité de renommer/déplacer les
    répertoires et documents ne modifie plus les liens)
  * affichage des répertoires et documents améliorée (avec icônes)
  * page d'accueil permettant une saisie rapide des données récurrentes
  * possibilité de rendre non accessible chaque information/document séparément
2.0.2 06/09/12 Corrections de bugs divers (merci O. Bouverot)
2.1.0 09/09/12 Correction d'importants bugs de connexions avec IE, ajout des
  académies à l'installation, correction de bugs mineurs.
  Ajout de la possibilité de taper des commandes LaTeX via MathJax (merci à O.
  Bouverot pour l'idée !)
  Suppression du .htaccess (configuration à mettre dans la configuration Apache)
2.1.1 12/09/12 Correction de bugs divers (modification des pages, moteur
  interne, formulaires)
2.1.2 13/09/12 Correction d'un bug sur les boutons de séances du cahier de texte
2.1.3 18/11/12 Correction de la mise en ordre des documents/répertoires
  Correction d'un bug affectant MySQL 5.5.5 et suivants 
2.2.0 11/01/13 Nouvelles fonctionnalités :
  * il n'est plus possible de mettre son utilisateur sans matière
  * les composantes d'une matière (cahier de texte, programme de colles,
  documents) sont désactivées par défaut
  * une matière n'est plus affichée dans le menu si les trois composantes sont
  désactivées
  * pages de documents : l'icône de répertoire devient celle d'un répertoire
  ouvert lorsqu'un répertoire est ouvert
  * on peut ajouter des liens vers les documents disponibles directement dans
  les textarea de l'interface d'administration
  * un avertissement s'affiche pour prévenir la déconnexion et permet la
  reconnexion sans recharger la page (donc sans perdre les données tapées)
3.0.0 23/08/13 Réécriture d'une partie du moteur interne. Ajout de nouvelles
  fonctionnalités :
  * nouvelle gestion des informations récentes, dans une table séparée,
  affichées sur le côté dans la partie publique
  * flux RSS disponible pour les informations récentes
  * modifications dans l'interface d'administration : possibilité de
  monter/descendre des items plus facilement ; simplification globale ; aide
  réécrite et complétée
  * plusieurs matières peuvent être affichées dans le menu de l'interface
  d'administration
  * gestion des documents facilitée : possibilité de mettre à jour un document
  * ajout d'une page de préférences utilisateur, possibilité de valider par
  défaut la protection par mot de passe des documents
  * clarification de l''utilisation de compte utilisateur de type élève
  * initialisation possible du planning des semaines
  Correction d'un bug pour l'enregistrement de document commençant par un accent
3.0.1 27/08/13 Correction d'un bug pour la suppression des répertoires (merci
  A. Senger), et de bugs divers, notamment à l'installation.
  Ajout d'une balise link pour le flux RSS dans haut.php (merci A. Carrade).
3.0.2 28/08/13 Correction d'un bug sur l'affichage des répertoires ne contenant
  que des documents non visibles (merci PH. Jondot).
3.0.3 03/09/13 Correction d'un bug modifiant les dates d'envoi et tailles de
  tous les documents lors de la mise à jour d'un seul (merci PH. Jondot)
3.1.0 24/10/13 Nouvelles fonctionnalités :
  * Modifications des cahiers de texte (voir toute l'année, affiche/efface
  les horaires en fonction du type de séance dans l'interface d'administration)
  * Possibilité de prévisualiser les textes tapés en textarea
  * Icônes dans les informations récentes, meilleure présentation des
  informations récentes, liens pour les documents dans le flux RSS
  Corrections de bugs :
  * Textarea pour les informations qui ne s'affiche pas au dépliage sous
  certaines versions de Safari/Safari Mobile (merci F. Evrard)
  * Rechargement anormal de la page courante après reconnexion via javascript
  * Affichage des documents dans l'ordre "naturel" (merci PJ. Desnoux)
  * Affichage de l'icône pour les documents à extension en majuscule
  * Liens incorrects dans le flux RSS
3.1.1 03/11/13 Correction d'un bug sur l'affichage du cahier de texte (merci
  O. Bouverot) et sur les liens des informations récentes (merci A. Carrade)
3.2.0 30/12/13 Améliorations et nouvelles fonctionnalités :
  * Modification de la page de lecture du flux RSS : affichage complet + lien
  * Ordre d'affichage des documents modifiable
  * Possibilité d'associer des pages d'informations à une matière
  * Possibilité d'afficher dans le menu des liens directs vers des répertoires
  Corrections de bugs :
  * Noms de documents contenant un / tronqués (merci E. Blanc)
  * Propagation de la demande d'identification pour les répertoires et documents
  mal réalisée
4.0.0 28/08/14 Réécriture d'une partie du moteur interne. Séparation des
  fichiers de la partie publique et de l'interface d'administration.
  Nouvelles fonctionnalités :
  * Nouvelle gestion des utilisateurs : comptes élèves/colleurs/professeurs
  personnels, gestion de l'oubli du mot de passe, demande de création de compte
  * Amélioration de la protection d'accès des contenus (plus de possibilités)
  * Préférences supplémentaires, identité complète, adresse mail, timeout
  * Possibilité d'envoi de courriel avec choix de destinataires
  * Gestion des notes de colles :
    * saisie par les colleurs sur la partie publique et par les professeurs sur
    l'interface d'administration
    * visualisation par les élèves une fois connectés
    * visualisation par les professeurs dans l'interface d'administration
  * Possibilité de regrouper les entrées des cahiers de texte par jour ou par
  semaine, et de gérer l'ensemble beaucoup plus précisément
  * Possibilité de mettre un titre vide dans les informations
  * Possibilité de supprimer toutes les informations d'une page
  * Possibilité de supprimer les répertoires non vides et leur contenu
  * Possibilité de supprimer toutes les entrées du cahier de texte, tous les
  programmes de colles, tous les documents, toutes les notes pour une matière
  * Suppression simplifiée d'une matière
  * Modifications dans l'organisation de l'interface d'administration
  * Titre du site unifié et modifiable
  * Développement important de l'aide
  * Meilleur cloisonnement des matières dans l'interface d'administration
  * Simplification de la gestion du planning annuel
  * Nouvelle méthode de sauvegarde plus efficace
  * Prise en charge de JQuery 1.11 (gain de rapidité)
  * Simplification du fichier de configuration
  * Simplification de l'installation
  * Menu automatiquement minimisé s'il ne rentre pas dans la fenêtre
4.0.1 31/08/14 Correction de divers bugs et fautes dans l'aide
4.0.2 02/09/14 Correction de bug (erreur de syntaxe)
4.0.3 02/09/14 Correction de bug (réinitialisation des mots de passe)
4.0.4 20/09/14 Correction de bugs multiples (merci O. Bouverot, E. Saudrais)
4.0.5 18/10/14 Correction de bug (redéfinition occasionnelle d'une fonction)
4.1.0 24/10/14 Nouvelles fonctionnalités :
  * Compte "invité" au mot de passe non modifiable (similaire aux comptes élève
  de la version 3)
  * Récupération des notes de colles en .xls
  * Impossibilité d'envoyer un courriel sans destinataire
  * Envoi de courriel possible aussi pour les colleurs
  * Possibilité de changer de matière pour les colleurs, plusieurs possibles
  * Gestion des groupes d'élèves/groupes de colles
    * Ajout, modification, suppression
    * Utilisation pour l'envoi de courriel
    * Utilisation pour la saisie des notes de colles
  * Ajout du nombre de comptes dans la gestion des utilisateurs
  Correction de bug :
  * Modification du nom du répertoire lors de la modification d'une matière
  * Correction d'une faille de sécurité XSS dans le nom/prénom/login
4.1.1 24/10/14 Correction d'un bug sur la modifications des préférences d'élèves
4.1.2 27/12/14 Corrections de bugs multiples (merci A. Lenormand)
4.1.3 26/02/15 Affichage des pdf en ligne, correction de bug (merci A. Tétar)
5.0.0 20/09/15 Réécriture d'une partie du moteur interne. Modification de
  l'interface administrative, réunion avec la partie publique.
  * Modifications sans rechargement (via Ajax)
  * Interface de saisie entièrement modifiée :
    * Possiblité de modifier sur place les titres, entrées et textes
    * Aide pour la saisie des textes (formats de textes, listes à puces,
      insertions de liens...)
    * Possiblité de copier-coller depuis un logiciel de traitement de texte ou
      une autre page web
    * Nettoyage automatique du code HTML saisi
  * Modification de la gestion des utilisateurs
    * Vérification systématique des adresses mails des nouveaux utilisateurs
    * Gestion des oublis de mot de passe simplifiée
    * Possibilité d'ajout multiple d'utilisateurs
    * Possibilité d'interdire les demandes de création de nouveau compte
  * Nouveau design
  * Design fonctionnel sur les écrans de petite taille ('responsive')
  * Possibilité d'afficher des documents PDF ou JPG/PNG en ligne
  * Possibilité de restreindre l'accès des comptes invités, élèves et colleurs à
  des matières
  * Possibilité d'envoyer des courriels pour les colleurs
  * Simplification de l'édition du cahier de texte
  * Bouton permettant directement l'impression des pages visualisées
  * Possiblité de rendre complètement invisible une page, y compris dans le menu
  * Saisie et visualisation des notes de colles améliorée et simplifiée
  * Possibilité pour les professeurs de corriger les adresses mails erronées
  * Possibilité de mettre des notes de colles en demi-point
  * (Certaines documentations sont manquantes)
5.0.1 27/09/15 Corrections de bugs multiples... (merci L. Beau, S. Ravier,
  P. Moreau, F. Evrard, E. Blairon, F. Baux, T. Perruchot, L. Whiteley,
  E. Droguet, P. Tondelier, C. Chevalier, E. De Pennart)
  * Retour du bandeau pour prévenir d'utilisateurs en attente de validation
5.1.0 28/10/15 Corrections de bugs, encore et amélioration des documentations
  Nouvelles fonctionnalités :
  * Visualisation directe du niveau de protection pour les contenus protégés
  * Retour des documentations des pages : utilisateurs, mail, docs, pages, notes
  * Nouvelles couleurs
  * Amélioration de la lisibilité des tableaux, choix des destinataires
6.0.0 30/08/16 Nouvelles fonctionnalités :
  * Agenda (merci O. Bouverot pour avoir insisté :-) )
  * Récupération des noms et adresses des utilisateurs en fichier excel
  * Flux RSS multiples et personnalisés
  * Améliorations des enregistrements des informations récentes
  * Améliorations et uniformisations d'affichage
6.1.0 25/08/17 Correction de bug : problème des invitations (liens avec @)
  * Nouvelle version du planning annuel pour 2017-2018
6.2.0 02/09/18 Version non publiée
  * Nouvelle version du planning annuel pour 2018-2019
8.0.1 15/10/18 Nouvelles fonctionnalités :
  * Envois de mails 
    * Envoi paramétré avec une adresse générale évitant les erreurs de transport
    * Possibilité pour tous les utilisateurs (y compris les élèves) d'envoyer
    des mails, réglage particulier à chaque compte
  * Gestion des accès entièrement revue, possibilité de réglage beaucoup plus fin
    pour la protection de l'accès de chaque ressource 
  * Création d'un nouveau type d'utilisateur : administration du lycée
  * Gestion des utilisateurs améliorée
    * Validations/suppressions/modifications multiples d'utilisateurs
    * Possibilité de "désactiver" un compte sans le supprimer, par exemple pour
    les élèves partis en cours d'année
    * Les groupes peuvent comprendre des utilisateurs non élèves 
  * Protection individuelle des informations
  * Possibilité de connexion automatique sans mot de passe pour les pages
    en lecture seule et sans donnée sensible.
  * Gestion des notes entièrement revue, avec un regroupement par heure et une
    nouvelle interface de saisie et modification. Les notes de colles peuvent 
    être relevées par l'administration. Les données récapitulatives (nombre
    d'élèves et nombre d'heures déclarées et relevées) sont affichées.
  * Possibilité de désactiver les fonctions cahier de texte/programme de colle/
    notes de colles : elles disparaissent alors du menu.
8.0.2 23/10/18 Correction de bugs
8.1.0 30/10/18 Correction de bugs et améliorations des notes de colles :
  * Possibilité d'impression du relevé de colles pour le compte administratif
  * Possibilité de déclaration de séances de cours ou TD sans note 
8.1.1 11/11/18 Correction de bugs
9.0.0 29/08/19 Nouvelles fonctionnalités :
  * Interface globale de connexion permettant une meilleure expérience des 
    utilisateurs de plusieurs Cahiers, et changement de Cahier en direct
  * Connexion possible à l'aide de son adresse électronique ou de l'identifiant
  * Nouvelle interface sur écran mobile et correction de bugs d'affichage
  * Nouvelle gestion de l'autorisation de l'envoi de courriel, plus lisible,
    par groupe d'utilisateurs.
  * Mise en avant des nouveaux contenus sur une page spéciale
  * Deux dates enregistrables pour les informations, programmes de colle,
    documents : date de première publication, date de mise à jour
  * Correction de bugs pouvant amener des incohérences après des modifications
    ou suppressions massives.
  * Demande de confirmation si un texte a été saisi sans être enregistré (mais
    dépend du réglage du navigateur)
  * Planning dépendant de la zone scolaire
9.0.1 29/08/19 Correction de bugs
9.0.2 08/09/19 Correction de bugs, nouvelles icônes de documents
9.1.0 19/03/20 Correction de bug : chargement des documents lourds
  Nouvelles fonctionnalités :
  * Système de récupération de copies dématérialisées
  * Affichage du bouton de notes dans le menu général si une seule matière
  * Modification possible de toutes les matières en tant que professeur
9.1.1 23/03/20 Nouvelles fonctionnalités :
  * Possibilité d'affichage différé pour les documents et transferts de copies
  * Améliorations d'interface pour les transferts de copies. Ajout d'un
  élément textuel permettant de lier le sujet.
9.2.0 02/04/20 Nouvelles fonctionnalités :
  * Envoi multiple de documents
  * Possibilité de voir le détail des copies envoyées, suppression 
  * Possibilité d'envoyer des corrections et sujets individuels aux élèves
9.2.1 12/04/20 Corrections de bugs

===================

Todo :

[ 10.0 ] Août 2020
  * Comptes administrateurs non nécessairement professeurs et inversement
  * Possibilité pour les administrateurs de laisser un message sur une page ?
  * Système de récupération des données et des documents
  * Affichage en ligne des vidéos et fichiers sonores
  * Pièce jointe sur les mails : téléchargement, enregistrement dans un répertoire
  hors d'atteinte, lien spécial à insérer dans le corps du mail.
  * Mode lecture, mode « vision en tant que » pour les administrateurs
  * Meilleure visiblité des protections dans le menu
  * Nombres de téléchargements des docs
  * Réglages des fichiers xls téléchargés (notes & utilisateurs)
  * Mise en place d'un système de forum ?
  * Améliorations de l'agenda
    * Possibilité de montrer/cacher un événement
    * Protection individuelle des événements
    * Possibilité de lier des événements à des personnes précises
    * Améliorations générales de l'ergonomie
    * Vue de l'agenda sous forme de liste d'événements
    * Recherche dans les événements
  * Raccourcis cdt : texte pré-défini
  * Paramétrage des styles pour les titres, des couleurs
  * Amélioration de l'affichage des notes de colles : page unique d'accès pour
    les élèves et les colleurs, vue simplifiée pour les élèves
  * Commentaires sur les notes de colles
  * Page "lycée" éditable par les comptes lycées et les comptes professeurs
  * Renvoi d'invitation

=======

Autres remarques / propositions :
  * Notes de colles : pouvoir préremplir de façon toujours identique
  * Page de statistiques des colles toutes matières confondues ?
  * FAQ
  * Page de préférences globales : création de compte, protection globale, titre
  * Harmonisation des matières : listes de matières pour les filières classiques.
  * Envoi multiple de documents
  * Interface : glisser-déplacer pour changer les ordres d'affichage
  * Tags dans les cahiers de texte
  * Programme de colles par quinzaine
  * ajouter la durée des séances dans le cahier de texte
  * bug : interdire les "." dans les clés
  * matière éditable depuis le menu -> modification de la matière seule (matieres.php accessible avec une clé)
  * notes : savoir combien de notes sont attribuées à un compte avant suppression (gestion des doublons)
  * modification de docs par les élèves
  * modification d'items de l'agenda par les élèves
  * correction : détection de la touche entrée sur les input/select plus globale (supprimer les copies multiples)
  * correction : utiliser le data-matiere associé à body dans les actions ajax
  * interne : séparer ajax.php/ajaxadmin.php, séparer 
  * notes : gérer plus spécifiquement l'affichage des notes pour les élèves démissionnaires
  
