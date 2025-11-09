<?php
// config/auth.php - Fonctions de gestion de l'authentification

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Fonction pour rediriger si non connecté
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Fonction pour rediriger si déjà connecté
function requireLogout() {
    if (isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}
?>