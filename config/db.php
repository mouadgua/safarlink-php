<?php
// config/db.php - Configuration de la base de données

class Database {
    private $host = "localhost";
    private $db_name = "safarlink";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Erreur de connexion: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Fonction pour logger les actions administrateur
function logAdminAction($user_id, $action, $description = "") {
    $database = new Database();
    $db = $database->getConnection();
    
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