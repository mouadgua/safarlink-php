<?php
header('Content-Type: application/json');

require_once '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

try {
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
    $required_fields = ['email', 'password', 'firstName', 'lastName', 'userType'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(['error' => 'Le champ ' . $field . ' est requis']);
            exit;
        }
    }
    
    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($data['password']);
    $firstName = htmlspecialchars(strip_tags(trim($data['firstName'])));
    $lastName = htmlspecialchars(strip_tags(trim($data['lastName'])));
    $phone = isset($data['phone']) ? htmlspecialchars(strip_tags(trim($data['phone']))) : null;
    $userType = in_array($data['userType'], ['driver', 'passenger']) ? $data['userType'] : 'passenger';
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Adresse email invalide']);
        exit;
    }
    
    // Validation du mot de passe
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Le mot de passe doit contenir au moins 6 caractères']);
        exit;
    }
    
    // Vérifier si l'email existe déjà
    $check_query = "SELECT id FROM users WHERE email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    
    if (!$check_stmt->execute()) {
        throw new Exception('Erreur lors de la vérification de l\'email');
    }
    
    if ($check_stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Cette adresse email est déjà utilisée']);
        exit;
    }
    
    // Hacher le mot de passe
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Générer un code de vérification
    $verification_code = sprintf("%06d", mt_rand(1, 999999));
    
    // Insérer l'utilisateur
    $query = "INSERT INTO users 
              (first_name, last_name, email, password, phone, user_type, verification_code, is_verified, created_at) 
              VALUES 
              (:first_name, :last_name, :email, :password, :phone, :user_type, :verification_code, 0, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':first_name', $firstName);
    $stmt->bindParam(':last_name', $lastName);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':user_type', $userType);
    $stmt->bindParam(':verification_code', $verification_code);
    
    if ($stmt->execute()) {
        $user_id = $db->lastInsertId();
        
        // Logger l'action (si la fonction existe)
        logAdminAction($db, $user_id, 'USER_REGISTER', 'Nouvel utilisateur inscrit: ' . $email);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Compte créé avec succès. Un email de vérification vous a été envoyé.',
            'user_id' => $user_id
        ]);
    } else {
        throw new Exception('Erreur lors de l\'insertion dans la base de données');
    }
    
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $exception->getMessage()]);
} catch (Exception $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur: ' . $exception->getMessage()]);
}
?>