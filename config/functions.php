<?php
// config/functions.php - Fonctions utilitaires globales

// Fonction pour logger les actions
function logAdminAction($db, $user_id, $action, $description = "") {
    if (!$db) return false;

    $query = "INSERT INTO admin_logs (user_id, action, description, ip_address, user_agent) 
              VALUES (:user_id, :action, :description, :ip_address, :user_agent)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":action", $action);
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
    $stmt->bindParam(":user_agent", $_SERVER['HTTP_USER_AGENT']);
    
    return $stmt->execute();
}
?>