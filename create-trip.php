<?php
session_start();
require_once 'config/db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user']['id'];
$trip_id = $_GET['trip_id'] ?? null;
$seats_to_book = (int)($_GET['seats'] ?? 1);

if (!$trip_id || $seats_to_book <= 0) {
    header('Location: trips.php');
    exit;
}

// 1. Récupérer les détails du trajet
$trip_query = "SELECT t.*, u.first_name, u.last_name, u.phone, 
                      e1.name as dep_name, e2.name as dest_name
               FROM trips t
               JOIN users u ON t.driver_id = u.id
               LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
               LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
               WHERE t.id = :trip_id AND t.status = 'scheduled'";
$trip_stmt = $db->prepare($trip_query);
$trip_stmt->bindParam(':trip_id', $trip_id);
$trip_stmt->execute();
$trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    $_SESSION['error_message'] = "Ce trajet n'est pas disponible ou a été annulé.";
    header('Location: trips.php');
    exit;
}

// 2. Vérifier la disponibilité des places
$booked_query = "SELECT SUM(seats_booked) as total_booked FROM bookings WHERE trip_id = :trip_id AND status IN ('pending', 'confirmed')";
$booked_stmt = $db->prepare($booked_query);
$booked_stmt->bindParam(':trip_id', $trip_id);
$booked_stmt->execute();
$total_booked = $booked_stmt->fetch(PDO::FETCH_ASSOC)['total_booked'] ?? 0;

$remaining_seats = $trip['available_seats'] - $total_booked;

if ($seats_to_book > $remaining_seats) {
    $_SESSION['error_message'] = "Seulement {$remaining_seats} place(s) est/sont disponible(s).";
    header('Location: trips.php');
    exit;
}

$total_price = $seats_to_book * $trip['price_per_seat'];

// 3. Logique de création de la réservation (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    
    // Génération d'un code unique
    $booking_code = substr(md5(uniqid(mt_rand(), true)), 0, 10);
    $secret_code = rand(100000, 999999);
    
    $insert_query = "INSERT INTO bookings (trip_id, passenger_id, seats_booked, total_price, booking_code, secret_code, status) 
                     VALUES (:trip_id, :passenger_id, :seats, :total_price, :code, :secret, 'pending')";
    
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':trip_id', $trip_id);
    $insert_stmt->bindParam(':passenger_id', $user_id);
    $insert_stmt->bindParam(':seats', $seats_to_book);
    $insert_stmt->bindParam(':total_price', $total_price);
    $insert_stmt->bindParam(':code', $booking_code);
    $insert_stmt->bindParam(':secret', $secret_code);

    if ($insert_stmt->execute()) {
        $new_booking_id = $db->lastInsertId();
        
        // Créer une notification pour le conducteur
        $notification_message = "Vous avez reçu une nouvelle réservation (#{$booking_code}) de {$seats_to_book} place(s).";
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, related_booking_id) 
                               VALUES (:user_id, 'Nouvelle réservation', :message, 'booking', :booking_id)";
        $notification_stmt = $db->prepare($notification_query);
        $notification_stmt->bindParam(':user_id', $trip['driver_id']);
        $notification_stmt->bindParam(':message', $notification_message);
        $notification_stmt->bindParam(':booking_id', $new_booking_id);
        $notification_stmt->execute();
        
        $_SESSION['success_message'] = "Réservation effectuée (Code: {$booking_code}) ! En attente de confirmation du conducteur.";
        header('Location: booking-details.php?id=' . $new_booking_id);
        exit;
    } else {
        $_SESSION['error_message'] = "Erreur lors de la création de la réservation.";
        header('Location: trips.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmer Réservation - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/loader.php'; ?>   
    <?php include 'components/navbar.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4 max-w-2xl">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Confirmer votre réservation</h1>
                
                <div class="space-y-4 mb-8 p-4 border border-orange-300 bg-orange-50 rounded-lg">
                    <h2 class="text-xl font-semibold text-orange-700">Trajet: <?php echo htmlspecialchars($trip['dep_name'] ?? $trip['departure_address']); ?> → <?php echo htmlspecialchars($trip['dest_name'] ?? $trip['destination_address']); ?></h2>
                    <p class="text-gray-600">
                        <i class="fas fa-calendar mr-2"></i>Date: **<?php echo date('d/m/Y à H:i', strtotime($trip['departure_date'] . ' ' . $trip['departure_time'])); ?>**
                    </p>
                    <p class="text-gray-600">
                        <i class="fas fa-user-circle mr-2"></i>Conducteur: **<?php echo htmlspecialchars($trip['first_name'] . ' ' . $trip['last_name']); ?>** (<?php echo htmlspecialchars($trip['phone']); ?>)
                    </p>
                    <hr class="border-orange-200">
                    <div class="flex justify-between items-center text-lg font-medium">
                        <span class="text-gray-700"><i class="fas fa-chair mr-2"></i>Places réservées:</span>
                        <span class="text-orange-600"><?php echo $seats_to_book; ?></span>
                    </div>
                    <div class="flex justify-between items-center text-xl font-bold">
                        <span class="text-gray-800"><i class="fas fa-euro-sign mr-2"></i>Montant Total:</span>
                        <span class="text-orange-700"><?php echo number_format($total_price, 2); ?>€</span>
                    </div>
                </div>

                <form method="POST">
                    <p class="text-sm text-gray-500 mb-4">
                        En cliquant sur Confirmer, vous acceptez les conditions de SafarLink et effectuez la réservation. Le paiement est simulé.
                    </p>
                    <button type="submit" name="confirm_booking" 
                            class="w-full py-3 bg-green-500 text-white font-medium rounded-lg hover:bg-green-600 transition-colors">
                        <i class="fas fa-check-circle mr-2"></i>Confirmer la réservation
                    </button>
                    <a href="trips.php" class="block w-full text-center mt-3 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-times mr-2"></i>Annuler et retourner aux trajets
                    </a>
                </form>
            </div>
        </div>
    </main>
</body>
</html>