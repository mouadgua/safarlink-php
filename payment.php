<?php
require_once 'config/init.php';

requireLogin();

$trip_id = $_GET['trip_id'] ?? null;
$seats = $_GET['seats'] ?? 1;

if (!$trip_id) {
    header('Location: trips.php');
    exit;
}

// Récupérer les détails du trajet
$query = "SELECT t.*, 
                 u.first_name as driver_first_name, 
                 u.last_name as driver_last_name,
                 u.rating as driver_rating,
                 u.total_ratings as driver_total_ratings,
                 e1.name as departure_establishment,
                 e2.name as destination_establishment
          FROM trips t
          JOIN users u ON t.driver_id = u.id
          LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
          LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
          WHERE t.id = :trip_id AND t.status = 'scheduled' AND t.departure_date >= CURDATE()";

$stmt = $db->prepare($query);
$stmt->bindParam(':trip_id', $trip_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header('Location: trips.php');
    exit;
}

$trip = $stmt->fetch(PDO::FETCH_ASSOC);
$total_price = $trip['price_per_seat'] * $seats;

// Traitement du paiement
if ($_POST) {
    $payment_method = $_POST['payment_method'] ?? '';
    
    if ($payment_method === 'cash') {
        // Paiement en espèces - créer la réservation directement
        $booking_code = generateBookingCode();
        $secret_code = generateSecretCode();
        
        $insert_query = "INSERT INTO bookings (trip_id, passenger_id, seats_booked, total_price, booking_code, secret_code, status, payment_method)
                         VALUES (:trip_id, :passenger_id, :seats_booked, :total_price, :booking_code, :secret_code, 'pending', 'cash')";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':trip_id', $trip_id);
        $insert_stmt->bindParam(':passenger_id', $_SESSION['user']['id']);
        $insert_stmt->bindParam(':seats_booked', $seats);
        $insert_stmt->bindParam(':total_price', $total_price);
        $insert_stmt->bindParam(':booking_code', $booking_code);
        $insert_stmt->bindParam(':secret_code', $secret_code);
        
        if ($insert_stmt->execute()) {
            $booking_id = $db->lastInsertId();
            $_SESSION['success_message'] = 'Réservation créée avec succès ! Paiement en espèces à effectuer auprès du conducteur.';
            header('Location: booking-confirmation.php?id=' . $booking_id);
            exit;
        }
    } elseif ($payment_method === 'card') {
        // Redirection vers PayPal
        $booking_code = generateBookingCode();
        $secret_code = generateSecretCode();
        
        // Sauvegarder la réservation en attente de paiement
        $insert_query = "INSERT INTO bookings (trip_id, passenger_id, seats_booked, total_price, booking_code, secret_code, status, payment_method)
                         VALUES (:trip_id, :passenger_id, :seats_booked, :total_price, :booking_code, :secret_code, 'payment_pending', 'card')";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':trip_id', $trip_id);
        $insert_stmt->bindParam(':passenger_id', $_SESSION['user']['id']);
        $insert_stmt->bindParam(':seats_booked', $seats);
        $insert_stmt->bindParam(':total_price', $total_price);
        $insert_stmt->bindParam(':booking_code', $booking_code);
        $insert_stmt->bindParam(':secret_code', $secret_code);
        
        if ($insert_stmt->execute()) {
            $booking_id = $db->lastInsertId();
            
            // Rediriger vers PayPal avec les détails du paiement
            $paypal_url = "https://www.paypal.com/cgi-bin/webscr?" . http_build_query([
                'cmd' => '_xclick',
                'business' => 'votre-email-paypal@example.com',
                'item_name' => "Réservation #{$booking_code} - {$trip['departure_address']} vers {$trip['destination_address']}",
                'amount' => $total_price,
                'currency_code' => 'MAD',
                'return' => 'http://votre-site.com/booking-confirmation.php?id=' . $booking_id,
                'cancel_return' => 'http://votre-site.com/payment.php?trip_id=' . $trip_id . '&seats=' . $seats
            ]);
            
            header('Location: ' . $paypal_url);
            exit;
        }
    }
}

function generateBookingCode() {
    return 'BK' . strtoupper(substr(uniqid(), -8));
}

function generateSecretCode() {
    return sprintf('%06d', mt_rand(1, 999999));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4 max-w-4xl">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Finaliser votre réservation</h1>
                
                <!-- Récapitulatif du trajet -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h2 class="text-lg font-semibold mb-3">Récapitulatif du trajet</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="font-medium"><?php echo htmlspecialchars($trip['departure_establishment'] ?? $trip['departure_address']); ?></p>
                            <p class="text-sm text-gray-600">→ <?php echo htmlspecialchars($trip['destination_establishment'] ?? $trip['destination_address']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-orange-600"><?php echo $total_price; ?> Dhs</p>
                            <p class="text-sm text-gray-600"><?php echo $seats; ?> place(s) × <?php echo $trip['price_per_seat']; ?> Dhs</p>
                        </div>
                    </div>
                </div>

                <!-- Choix du mode de paiement -->
                <form method="POST" class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Choisissez votre mode de paiement</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Paiement en espèces -->
                            <label class="relative">
                                <input type="radio" name="payment_method" value="cash" class="sr-only peer" required>
                                <div class="p-6 border-2 border-gray-300 rounded-xl cursor-pointer transition-all duration-200 peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:border-orange-300">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                            <i class="fas fa-money-bill-wave text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-800">Paiement en espèces</h3>
                                            <p class="text-sm text-gray-600">Payez directement au conducteur</p>
                                        </div>
                                    </div>
                                </div>
                            </label>

                            <!-- Paiement par carte -->
                            <label class="relative">
                                <input type="radio" name="payment_method" value="card" class="sr-only peer">
                                <div class="p-6 border-2 border-gray-300 rounded-xl cursor-pointer transition-all duration-200 peer-checked:border-orange-500 peer-checked:bg-orange-50 hover:border-orange-300">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                            <i class="fas fa-credit-card text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-800">Carte bancaire</h3>
                                            <p class="text-sm text-gray-600">Paiement sécurisé via PayPal</p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Bouton de confirmation -->
                    <div class="flex gap-4 pt-6 border-t border-gray-200">
                        <button type="button" onclick="window.history.back()" 
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Retour
                        </button>
                        <button type="submit" 
                                class="flex-1 px-6 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors font-semibold">
                            <i class="fas fa-lock mr-2"></i>Confirmer le paiement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>