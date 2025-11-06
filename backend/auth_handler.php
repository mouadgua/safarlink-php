<?php


// Inclure le fichier de connexion à la base de données
require_once __DIR__ . '/../config/db.php';

// Fonction pour sécuriser les données utilisateur
function sanitize_input($data) {
    return htmlspecialchars(trim($data));
}

// --- LOGIQUE D'INSCRIPTION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'register') {
    $errors = [];

    // Récupération et validation des données
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $establishment_id = intval($_POST['establishment_id'] ?? 0);
    $birth_date = sanitize_input($_POST['birth_date'] ?? '');

    // 1. Validation de base
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password) || $establishment_id <= 0 || empty($birth_date)) {
        $errors[] = "Veuillez remplir tous les champs obligatoires.";
    }

    // 2. Validation du format de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Le format de l'email est invalide.";
    }

    // 3. Validation du mot de passe
    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }

    // 4. Validation de l'établissement
    try {
        $stmt_est = $pdo->prepare("SELECT id FROM establishments WHERE id = ?");
        $stmt_est->execute([$establishment_id]);
        if ($stmt_est->rowCount() === 0) {
            $errors[] = "L'établissement sélectionné est invalide.";
        }
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de la vérification de l'établissement.";
    }

    // 5. Vérification de l'existence de l'utilisateur
    if (empty($errors)) {
        try {
            $stmt_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_email->execute([$email]);
            if ($stmt_email->rowCount() > 0) {
                $errors[] = "Cet email est déjà enregistré.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la vérification de l'email.";
        }
    }

    // 6. Inscription si aucune erreur
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_role = 'passenger'; // Rôle par défaut

        try {
            $sql = "INSERT INTO users (email, password, first_name, last_name, birth_date, establishment_id, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $hashed_password, $first_name, $last_name, $birth_date, $establishment_id, $default_role]);

            // Connexion automatique après l'inscription (optionnel)
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $default_role;
            $_SESSION['first_name'] = $first_name;

            // Redirection vers la page d'accueil ou le tableau de bord
            header("Location: ../index.php?success=register");
            exit();

        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }

    // Stocker les erreurs en session et rediriger vers la page d'inscription
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: ../frontend/register.php");
        exit();
    }
}

// --- LOGIQUE DE CONNEXION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'login') {
    $errors = [];

    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "Veuillez entrer votre email et votre mot de passe.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, password, first_name, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];

                // Redirection vers la page d'accueil ou le tableau de bord
                header("Location: ../index.php?success=login");
                exit();
            } else {
                // Échec de la connexion
                $errors[] = "Email ou mot de passe incorrect.";
            }

        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la connexion : " . $e->getMessage();
        }
    }

    // Stocker les erreurs en session et rediriger vers la page de connexion
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_data'] = ['email' => $email];
        header("Location: ../frontend/login.php");
        exit();
    }
}

// --- LOGIQUE DE DÉCONNEXION (simple) ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    // Redirection vers la page d'accueil
    header("Location: ../index.php");
    exit();
}

// Redirection par défaut si accès direct au fichier
if (!isset($_POST['action']) && !isset($_GET['action'])) {
    header("Location: ../index.php");
    exit();
}
?>
