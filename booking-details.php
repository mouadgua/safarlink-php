<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user']['id'];

// Récupérer les détails de la réservation
$booking_id = $_GET['id'] ?? null;

if (!$booking_id) {
    header('Location: my-bookings.php');
    exit;
}

$query = "SELECT b.*, 
                 t.*,
                 u.first_name as driver_first_name, 
                 u.last_name as driver_last_name,
                 u.phone as driver_phone,
                 u.rating as driver_rating,
                 u.total_ratings as driver_total_ratings,
                 e1.name as departure_establishment,
                 e2.name as destination_establishment,
                 p.first_name as passenger_first_name,
                 p.last_name as passenger_last_name,
                 p.phone as passenger_phone
          FROM bookings b
          JOIN trips t ON b.trip_id = t.id
          JOIN users u ON t.driver_id = u.id
          JOIN users p ON b.passenger_id = p.id
          LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
          LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
          WHERE b.id = :booking_id AND (b.passenger_id = :user_id OR t.driver_id = :user_id)";

$stmt = $db->prepare($query);
$stmt->bindParam(':booking_id', $booking_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header('Location: my-bookings.php');
    exit;
}

$booking = $stmt->fetch(PDO::FETCH_ASSOC);
$is_driver = $booking['driver_id'] == $user_id;

// Générer ou récupérer le QR Code
if (empty($booking['qr_code_url'])) {
    $booking_code = $booking['booking_code'];
    $secret_code = $booking['secret_code'];
    
    // Générer le QR Code avec QR Code Monkey
    $qr_data = json_encode([
        'booking_code' => $booking_code,
        'secret_code' => $secret_code,
        'trip_id' => $booking['trip_id']
    ]);
    
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data);
    
    // Sauvegarder l'URL du QR code
    $update_query = "UPDATE bookings SET qr_code_url = :qr_url WHERE id = :booking_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':qr_url', $qr_url);
    $update_stmt->bindParam(':booking_id', $booking_id);
    $update_stmt->execute();
    
    $booking['qr_code_url'] = $qr_url;
}

// Traitement des actions
if ($_POST) {
    if (isset($_POST['confirm_booking'])) {
        if ($is_driver) {
            $confirm_query = "UPDATE bookings SET driver_confirmed = TRUE WHERE id = :booking_id";
        } else {
            $confirm_query = "UPDATE bookings SET passenger_confirmed = TRUE WHERE id = :booking_id";
        }
        
        $confirm_stmt = $db->prepare($confirm_query);
        $confirm_stmt->bindParam(':booking_id', $booking_id);
        
        if ($confirm_stmt->execute()) {
            // Créer une notification
            $notification_user = $is_driver ? $booking['passenger_id'] : $booking['driver_id'];
            $notification_title = $is_driver ? 'Conducteur confirmé' : 'Passager confirmé';
            $notification_message = $is_driver ? 
                "Le conducteur a confirmé votre réservation #{$booking['booking_code']}" :
                "Le passager a confirmé votre trajet #{$booking['booking_code']}";
                
            $notification_query = "INSERT INTO notifications (user_id, title, message, type, related_booking_id) 
                                   VALUES (:user_id, :title, :message, 'confirmation', :booking_id)";
            $notification_stmt = $db->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $notification_user);
            $notification_stmt->bindParam(':title', $notification_title);
            $notification_stmt->bindParam(':message', $notification_message);
            $notification_stmt->bindParam(':booking_id', $booking_id);
            $notification_stmt->execute();
            
            header('Location: booking-details.php?id=' . $booking_id);
            exit;
        }
    }
    
    if (isset($_POST['cancel_booking'])) {
        $cancel_query = "UPDATE bookings SET status = 'cancelled' WHERE id = :booking_id";
        $cancel_stmt = $db->prepare($cancel_query);
        $cancel_stmt->bindParam(':booking_id', $booking_id);
        
        if ($cancel_stmt->execute()) {
            // Notifier l'autre partie
            $notification_user = $is_driver ? $booking['passenger_id'] : $booking['driver_id'];
            $notification_title = 'Réservation annulée';
            $notification_message = "La réservation #{$booking['booking_code']} a été annulée";
            
            $notification_query = "INSERT INTO notifications (user_id, title, message, type, related_booking_id) 
                                   VALUES (:user_id, :title, :message, 'system', :booking_id)";
            $notification_stmt = $db->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $notification_user);
            $notification_stmt->bindParam(':title', $notification_title);
            $notification_stmt->bindParam(':message', $notification_message);
            $notification_stmt->bindParam(':booking_id', $booking_id);
            $notification_stmt->execute();
            
            header('Location: booking-details.php?id=' . $booking_id);
            exit;
        }
    }
    
    if (isset($_POST['validate_code'])) {
        $entered_code = $_POST['validation_code'];
        
        if ($entered_code === $booking['secret_code']) {
            if ($is_driver) {
                $validate_query = "UPDATE bookings SET driver_confirmed = TRUE WHERE id = :booking_id";
            } else {
                $validate_query = "UPDATE bookings SET passenger_confirmed = TRUE WHERE id = :booking_id";
            }
            
            $validate_stmt = $db->prepare($validate_query);
            $validate_stmt->bindParam(':booking_id', $booking_id);
            $validate_stmt->execute();
            
            $validation_success = true;
        } else {
            $validation_error = "Code incorrect. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Réservation - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Inclusion de la Navbar Desktop -->
    <?php include 'components/navbar-desktop.php'; ?>

    <?php include 'components/navbar.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4 max-w-6xl">
            <!-- En-tête -->
            <div class="mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Réservation #<?php echo $booking['booking_code']; ?></h1>
                        <div class="flex items-center gap-4">
                            <span class="px-3 py-1 rounded-full text-sm font-medium 
                                <?php echo getStatusClass($booking['status']); ?>">
                                <?php echo getStatusText($booking['status']); ?>
                            </span>
                            <span class="text-gray-600">
                                <?php echo date('d/m/Y à H:i', strtotime($booking['departure_date'] . ' ' . $booking['departure_time'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-4 md:mt-0 flex gap-2">
                        <button onclick="window.print()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-print mr-2"></i>Imprimer
                        </button>
                        <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-share-alt mr-2"></i>Partager
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Colonne principale -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Carte du trajet -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Itinéraire</h2>
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
                        
                        <!-- Carte Leaflet -->
                        <div id="map" class="h-64 mt-4 rounded-lg border border-gray-300"></div>
                    </div>

                    <!-- Informations du trajet -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Détails du trajet</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Informations générales</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Places réservées:</span>
                                        <span class="font-medium"><?php echo $booking['seats_booked']; ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Prix par place:</span>
                                        <span class="font-medium"><?php echo $booking['price_per_seat']; ?>€</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total:</span>
                                        <span class="font-medium text-orange-600"><?php echo $booking['total_price']; ?>€</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Durée estimée:</span>
                                        <span class="font-medium">4h15</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Validation</h3>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Conducteur:</span>
                                        <span class="flex items-center gap-1">
                                            <?php if ($booking['driver_confirmed']): ?>
                                                <i class="fas fa-check-circle text-green-500"></i>
                                                <span class="text-green-600">Confirmé</span>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-yellow-500"></i>
                                                <span class="text-yellow-600">En attente</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600">Passager:</span>
                                        <span class="flex items-center gap-1">
                                            <?php if ($booking['passenger_confirmed']): ?>
                                                <i class="fas fa-check-circle text-green-500"></i>
                                                <span class="text-green-600">Confirmé</span>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-yellow-500"></i>
                                                <span class="text-yellow-600">En attente</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($booking['description']): ?>
                        <div class="mt-6">
                            <h3 class="font-medium text-gray-700 mb-2">Description</h3>
                            <p class="text-gray-600 bg-gray-50 p-3 rounded-lg"><?php echo htmlspecialchars($booking['description']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Informations de contact -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <?php echo $is_driver ? 'Informations passager' : 'Informations conducteur'; ?>
                        </h2>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-full bg-orange-500 flex items-center justify-center text-white text-xl font-bold">
                                <?php 
                                if ($is_driver) {
                                    $initials = strtoupper(substr($booking['passenger_first_name'], 0, 1) . substr($booking['passenger_last_name'], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($booking['driver_first_name'], 0, 1) . substr($booking['driver_last_name'], 0, 1));
                                }
                                echo $initials;
                                ?>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-800">
                                    <?php echo $is_driver ? 
                                        htmlspecialchars($booking['passenger_first_name'] . ' ' . $booking['passenger_last_name']) :
                                        htmlspecialchars($booking['driver_first_name'] . ' ' . $booking['driver_last_name']); ?>
                                </h3>
                                <div class="flex items-center gap-4 mt-1">
                                    <?php if (!$is_driver): ?>
                                    <div class="flex items-center">
                                        <i class="fas fa-star text-yellow-400 mr-1"></i>
                                        <span class="text-gray-600"><?php echo $booking['driver_rating']; ?> (<?php echo $booking['driver_total_ratings']; ?> avis)</span>
                                    </div>
                                    <?php endif; ?>
                                    <span class="text-gray-500">
                                        <i class="fas fa-phone mr-1"></i>
                                        <?php echo $is_driver ? $booking['passenger_phone'] : $booking['driver_phone']; ?>
                                    </span>
                                </div>
                            </div>
                            <button onclick="window.location.href='messages.php?conversation_id=<?php echo $is_driver ? $booking['passenger_id'] : $booking['driver_id']; ?>'" 
                                    class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                <i class="fas fa-comment mr-2"></i>Message
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Colonne latérale -->
                <div class="space-y-6">
                    <!-- QR Code et validation -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Validation</h2>
                        
                        <!-- QR Code -->
                        <div class="text-center mb-6">
                            <img src="<?php echo $booking['qr_code_url']; ?>" alt="QR Code" class="mx-auto mb-3 rounded-lg border border-gray-200">
                            <p class="text-sm text-gray-600">Scannez ce QR code pour valider</p>
                        </div>
                        
                        <!-- Validation manuelle -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="font-medium text-gray-700 mb-3">Validation manuelle</h3>
                            <form method="POST">
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Code secret à 6 chiffres</label>
                                        <input type="text" name="validation_code" maxlength="6" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-center text-xl font-mono focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                               placeholder="000000">
                                    </div>
                                    <button type="submit" name="validate_code" 
                                            class="w-full py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                        Valider manuellement
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (isset($validation_success)): ?>
                            <div class="mt-3 p-3 bg-green-100 text-green-700 rounded-lg text-sm">
                                <i class="fas fa-check-circle mr-2"></i>Validation réussie !
                            </div>
                            <?php elseif (isset($validation_error)): ?>
                            <div class="mt-3 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $validation_error; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Actions</h2>
                        <div class="space-y-3">
                            <?php if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed'): ?>
                                <?php if (($is_driver && !$booking['driver_confirmed']) || (!$is_driver && !$booking['passenger_confirmed'])): ?>
                                <form method="POST">
                                    <button type="submit" name="confirm_booking" 
                                            class="w-full py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors flex items-center justify-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Confirmer la réservation
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ?');">
                                    <button type="submit" name="cancel_booking" 
                                            class="w-full py-3 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors flex items-center justify-center">
                                        <i class="fas fa-times-circle mr-2"></i>
                                        Annuler la réservation
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <button onclick="window.location.href='messages.php?conversation_id=<?php echo $is_driver ? $booking['passenger_id'] : $booking['driver_id']; ?>'" 
                                    class="w-full py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center">
                                <i class="fas fa-comment mr-2"></i>
                                Contacter
                            </button>
                            
                            <?php if ($booking['status'] == 'completed' && !$is_driver): ?>
                            <button class="w-full py-3 border border-orange-300 text-orange-600 rounded-lg hover:bg-orange-50 transition-colors flex items-center justify-center">
                                <i class="fas fa-star mr-2"></i>
                                Noter le trajet
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Informations de réservation -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Détails réservation</h2>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Code réservation:</span>
                                <span class="font-mono font-bold"><?php echo $booking['booking_code']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date réservation:</span>
                                <span><?php echo date('d/m/Y à H:i', strtotime($booking['created_at'])); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Statut:</span>
                                <span class="<?php echo getStatusClass($booking['status']); ?> px-2 py-1 rounded-full text-xs">
                                    <?php echo getStatusText($booking['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Initialisation de la carte Leaflet
        function initMap() {
            const map = L.map('map').setView([46.603354, 1.888334], 6);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Simulation d'itinéraire Paris-Lyon
            const paris = [48.8566, 2.3522];
            const lyon = [45.7640, 4.8357];
            
            L.marker(paris).addTo(map)
                .bindPopup('Départ: Paris')
                .openPopup();
                
            L.marker(lyon).addTo(map)
                .bindPopup('Arrivée: Lyon');
                
            L.polyline([paris, lyon], {color: 'orange', weight: 4}).addTo(map);
            
            map.fitBounds([paris, lyon]);
        }

        // Animation GSAP
        gsap.from('.bg-white', {
            duration: 0.6,
            y: 30,
            opacity: 0,
            stagger: 0.1,
            ease: "power2.out"
        });

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Animation du QR Code
            gsap.from('img[alt="QR Code"]', {
                duration: 1,
                scale: 0,
                rotation: 360,
                ease: "back.out(1.7)"
            });
        });

        // Auto-focus sur le champ de code de validation
        document.querySelector('input[name="validation_code"]')?.focus();
    </script>
</body>
</html>

<?php
// Fonctions helper
function getStatusClass($status) {
    switch ($status) {
        case 'confirmed': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        case 'completed': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'confirmed': return 'Confirmé';
        case 'pending': return 'En attente';
        case 'cancelled': return 'Annulé';
        case 'completed': return 'Terminé';
        default: return 'Inconnu';
    }
}
?>