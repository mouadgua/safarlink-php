<?php
require_once 'config/init.php';

if (isset($_GET['code']) && isset($_GET['email'])) {
    $verification_code = $_GET['code'];
    $email = urldecode($_GET['email']);
    
    // Vérifier le code
    $query = "SELECT id FROM users WHERE email = :email AND verification_code = :code";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':code', $verification_code);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Activer le compte
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $update_query = "UPDATE users SET is_verified = TRUE, verification_code = NULL WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $user['id']);
        
        if ($update_stmt->execute()) {
            $message = "Votre compte a été vérifié avec succès ! Vous pouvez maintenant vous connecter.";
            $success = true;
            
            // Logger l'action
            logAdminAction($db, $user['id'], 'EMAIL_VERIFIED', 'Email vérifié avec succès');
        } else {
            $message = "Erreur lors de l'activation du compte. Veuillez réessayer.";
            $success = false;
        }
    } else {
        $message = "Code de vérification invalide ou expiré.";
        $success = false;
    }
} else {
    $message = "Lien de vérification invalide.";
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
            <div class="text-center">
                <?php if ($success): ?>
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-green-600 text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Vérification réussie !</h1>
                <?php else: ?>
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times text-red-600 text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Échec de la vérification</h1>
                <?php endif; ?>
                
                <p class="text-gray-600 mb-6"><?php echo $message; ?></p>
                
                <div class="space-y-3">
                    <?php if ($success): ?>
                        <a href="login.php" class="w-full bg-orange-500 text-white py-3 px-4 rounded-lg hover:bg-orange-600 transition-colors block text-center">
                            Se connecter
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="w-full bg-orange-500 text-white py-3 px-4 rounded-lg hover:bg-orange-600 transition-colors block text-center">
                            S'inscrire à nouveau
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="w-full border border-gray-300 text-gray-700 py-3 px-4 rounded-lg hover:bg-gray-50 transition-colors block text-center">
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>