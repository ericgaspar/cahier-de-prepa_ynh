<?php
// Fichier de configuration de Cahier de Prépa

// Adresse web
// Domaine : le nom de domaine (pas de "https://" ni de "/" final)
// Chemin : la suite de l'adresse, commençant et finissant par "/"
$domaine = '';
$chemin = '';

// Accès à la base de données MySQL
/* $serveur est le nom du serveur. localhost est la valeur par défaut.
 * $base est le nom de la base de données utilisée. 12 caractères maximum.
 * $mdp est le mot de passe qui sera utilisé en interne uniquement, et demandé
une fois unique par le script d'installation que vous devez lancer pour
terminer l'installation. */
$serveur = 'localhost';
$base = '';
$mdp = '';

// Mail administrateur
// Adresse dont proviendront les mails envoyés par l'interface et où seront
// redirigées l'ensemble des erreurs
$mailadmin = 'admin@cahier-de-prepa.fr';

// Adresse d'envoi des mails
// Utilisée pour envoyer les mails en évitant les erreurs de mauvais
// serveur SMTP (mettre le même nom de domaine que le serveur web)
// Les adresses réelles des expéditeurs sont utilisées si vide
$mailenvoi = 'ne-pas-repondre@cahier-de-prepa.fr';

// Interface globale
// Si l'interface globale est présente, on l'indique avec le chemin relatif du
// répertoire où elle se trouve (avec slash final).
// Laisser la chaîne vide ou égale à false désactive la fonctionnalité.
$interfaceglobale = false;

?>
