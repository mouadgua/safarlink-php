<?php
require_once 'config/init.php';

requireLogin();

$booking_id = $_GET['id'] ?? null;

if (!$booking_id) {
    header('Location: my-bookings.php');
    exit;
}

// Récupérer les détails de la réservation
$query = "SELECT b.*, 
                 t.*,
                 u.first_name as driver_first_name, 
                 u.last_name as driver_last_name,
                 u.phone as driver_phone,
                 u.email as driver_email,
                 u.rating as driver_rating,
                 u.total_ratings as driver_total_ratings,
                 u.car_model as vehicle_model,
                 u.car_color as vehicle_color,
                 u.license_plate as vehicle_plate,
                 e1.name as departure_establishment,
                 e2.name as destination_establishment
          FROM bookings b
          JOIN trips t ON b.trip_id = t.id
          JOIN users u ON t.driver_id = u.id
          LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
          LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
          WHERE b.id = :booking_id AND b.passenger_id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':booking_id', $booking_id);
$stmt->bindParam(':user_id', $_SESSION['user']['id']);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header('Location: my-bookings.php');
    exit;
}

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

// Générer le QR Code
$qr_data = json_encode([
    'booking_code' => $booking['booking_code'],
    'secret_code' => $booking['secret_code'],
    'trip_id' => $booking['trip_id']
]);

$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);

// Mettre à jour l'URL du QR code dans la base de données
$update_query = "UPDATE bookings SET qr_code_url = :qr_url WHERE id = :booking_id";
$update_stmt = $db->prepare($update_query);
$update_stmt->bindParam(':qr_url', $qr_url);
$update_stmt->bindParam(':booking_id', $booking_id);
$update_stmt->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Réservation - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4 max-w-4xl">
            <!-- En-tête de confirmation -->
            <div class="bg-white rounded-xl shadow-md p-8 text-center mb-6">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-3xl text-green-600"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Réservation confirmée !</h1>
                <p class="text-gray-600">Votre réservation a été enregistrée avec succès</p>
                <div class="mt-4 p-4 bg-orange-50 rounded-lg inline-block">
                    <span class="text-orange-700 font-mono text-xl font-bold">#<?php echo $booking['booking_code']; ?></span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Informations principales -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Informations du conducteur -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Informations du conducteur</h2>
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-16 h-16 rounded-full bg-orange-500 flex items-center justify-center text-white text-xl font-bold">
                                <?php echo strtoupper(substr($booking['driver_first_name'], 0, 1) . substr($booking['driver_last_name'], 0, 1)); ?>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($booking['driver_first_name'] . ' ' . $booking['driver_last_name']); ?></h3>
                                <div class="flex items-center gap-4 mt-1">
                                    <div class="flex items-center">
                                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                                        <span class="text-gray-600"><?php echo $booking['driver_rating']; ?> (<?php echo $booking['driver_total_ratings']; ?> avis)</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-phone text-gray-400"></i>
                                <span class="text-gray-600"><?php echo $booking['driver_phone']; ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-envelope text-gray-400"></i>
                                <span class="text-gray-600"><?php echo $booking['driver_email']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Informations du véhicule -->
                    <?php if ($booking['vehicle_model']): ?>
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Véhicule</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span class="text-gray-600">Modèle:</span>
                                <p class="font-medium"><?php echo htmlspecialchars($booking['vehicle_model']); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-600">Couleur:</span>
                                <p class="font-medium"><?php echo htmlspecialchars($booking['vehicle_color']); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-600">Plaque:</span>
                                <p class="font-medium"><?php echo htmlspecialchars($booking['vehicle_plate']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Détails du trajet -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Détails du trajet</h2>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-800">Départ</h3>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($booking['departure_establishment'] ?? $booking['departure_address']); ?></p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo date('d/m/Y', strtotime($booking['departure_date'])); ?> à 
                                        <?php echo substr($booking['departure_time'], 0, 5); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center text-white">
                                    <i class="fas fa-flag-checkered"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-800">Arrivée</h3>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($booking['destination_establishment'] ?? $booking['destination_address']); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                            <div>
                                <span class="text-gray-600">Places réservées:</span>
                                <p class="font-medium"><?php echo $booking['seats_booked']; ?></p>
                            </div>
                            <div>
                                <span class="text-gray-600">Prix total:</span>
                                <p class="font-medium text-orange-600"><?php echo $booking['total_price']; ?> Dhs</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Code et code secret -->
                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-md p-6 text-center">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Votre QR Code</h2>
                        <img src="<?php echo $qr_url; ?>" alt="QR Code" class="mx-auto mb-4 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-600">Présentez ce QR Code au conducteur pour valider votre présence</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-md p-6 text-center">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Code secret</h2>
                        <div class="p-4 bg-orange-50 rounded-lg">
                            <span class="text-orange-700 font-mono text-2xl font-bold tracking-widest">
                                <?php echo htmlspecialchars($booking['secret_code']); ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">Utilisez ce code si le QR Code ne fonctionne pas</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Prochaines étapes</h2>
                        <div class="space-y-3 text-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs">1</div>
                                <span>Présentez votre QR Code au conducteur</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs">2</div>
                                <span>Le conducteur validera votre présence</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs">3</div>
                                <span>Profitez de votre trajet !</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex gap-4 justify-center">
                <button onclick="window.print()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-print mr-2"></i>Imprimer
                </button>
                <button onclick="window.location.href='my-bookings.php'" class="px-6 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fas fa-list mr-2"></i>Voir mes réservations
                </button>
            </div>
        </div>
    </main>
</body>
</html>