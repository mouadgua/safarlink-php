<?php
// config/init.php
// Point d'entrée unique pour la configuration de l'application.

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Créer une instance globale de la connexion à la base de données
$database = new Database();
$db = $database->getConnection();
?>