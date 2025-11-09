<?php
session_start();
require_once 'config/db.php';

// Logger la déconnexion si l'utilisateur était connecté
if (isset($_SESSION['user'])) {
    $database = new Database();
    $db = $database->getConnection();
    logAdminAction($_SESSION['user']['id'], 'USER_LOGOUT', 'Déconnexion');
}

// Détruire la session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également
// le cookie de session.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Rediriger vers la page d'accueil
header('Location: index.php');
exit;
?>