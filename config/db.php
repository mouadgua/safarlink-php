<?php
// Fichier de configuration pour la connexion à la base de données
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // À modifier avec votre nom d'utilisateur
define('DB_PASSWORD', '');     // À modifier avec votre mot de passe
define('DB_NAME', 'safarilink'); // Le nom de la base de données fournie

// Tente de se connecter à la base de données MySQL
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8", DB_USERNAME, DB_PASSWORD);
    // Définir le mode d'erreur de PDO à Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Définir le mode de récupération par défaut à FETCH_ASSOC
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Afficher l'erreur de connexion
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
