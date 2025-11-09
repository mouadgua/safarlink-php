<?php
header('Content-Type: application/json');
// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier si le fichier de configuration existe
if (!file_exists('../config/db.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Fichier de configuration manquant']);
    exit;
}

session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    // Récupérer les données
    $input = file_get_contents('php://input');
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucune donnée reçue']);
        exit;
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Données JSON invalides']);
        exit;
    }
    
    // Validation des données
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email et mot de passe requis']);
        exit;
    }
    
    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($data['password']);
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Adresse email invalide']);
        exit;
    }
    
    // Récupérer l'utilisateur
    $query = "SELECT u.*, e.name as establishment_name 
              FROM users u 
              LEFT JOIN establishments e ON u.establishment_id = e.id 
              WHERE u.email = :email";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    
    if (!$stmt->execute()) {
        throw new Exception('Erreur lors de la recherche de l\'utilisateur');
    }
    
    if ($stmt->rowCount() == 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier le mot de passe
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    // Vérifier si le compte est activé (optionnel pour le moment)
    // if (!$user['is_verified']) {
    //     http_response_code(403);
    //     echo json_encode(['error' => 'Compte non vérifié. Veuillez vérifier votre email.']);
    //     exit;
    // }
    
    // Préparer les données de session (ne pas inclure le mot de passe)
    unset($user['password']);
    unset($user['verification_code']);
    
    $_SESSION['user'] = $user;
    $_SESSION['logged_in'] = true;
    
    // Mettre à jour la dernière connexion
    $update_query = "UPDATE users SET last_login = NOW() WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id', $user['id']);
    $update_stmt->execute();
    
    // Logger la connexion (si la fonction existe)
    if (function_exists('logAdminAction')) {
        logAdminAction($user['id'], 'USER_LOGIN', 'Connexion réussie');
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => $user
    ]);
    
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $exception->getMessage()]);
} catch (Exception $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur: ' . $exception->getMessage()]);
}
?>