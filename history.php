<?php
require_once 'config/init.php';

requireLogin();

$user_id = $_SESSION['user']['id'];
$user_type = $_SESSION['user']['user_type'];

// Récupérer l'historique selon le type d'utilisateur
if ($user_type == 'driver') {
    // Historique des trajets créés par le conducteur
    $query = "SELECT t.*, 
                     COUNT(b.id) as total_bookings,
                     e1.name as departure_establishment,
                     e2.name as destination_establishment
              FROM trips t
              LEFT JOIN bookings b ON t.id = b.trip_id
              LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
              LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
              WHERE t.driver_id = :user_id
              GROUP BY t.id
              ORDER BY t.departure_date DESC, t.departure_time DESC";
} else {
    // Historique des réservations du passager
    $query = "SELECT b.*, 
                     t.departure_address, t.destination_address, t.departure_date, t.departure_time,
                     t.price_per_seat, u.first_name as driver_first_name, u.last_name as driver_last_name,
                     e1.name as departure_establishment, e2.name as destination_establishment
              FROM bookings b
              JOIN trips t ON b.trip_id = t.id
              JOIN users u ON t.driver_id = u.id
              LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
              LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
              WHERE b.passenger_id = :user_id
              ORDER BY t.departure_date DESC, t.departure_time DESC";
}

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/loader.php'; ?>

    <?php include 'components/navbar.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4">
            <!-- En-tête -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Mon historique</h1>
                <p class="text-gray-600">
                    <?php echo $user_type == 'driver' ? 'Historique de tous vos trajets proposés' : 'Historique de toutes vos réservations'; ?>
                </p>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex flex-wrap gap-4">
                        <select class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="all">Tous les statuts</option>
                            <option value="completed">Terminés</option>
                            <option value="upcoming">À venir</option>
                            <option value="cancelled">Annulés</option>
                        </select>
                        
                        <input type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        
                        <input type="text" placeholder="Rechercher..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    
                    <button class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                </div>
            </div>

            <!-- Liste de l'historique -->
            <div class="space-y-6">
                <?php if (empty($history)): ?>
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">Aucun historique trouvé</h3>
                    <p class="text-gray-500 mb-4">
                        <?php echo $user_type == 'driver' ? 
                            'Vous n\'avez pas encore proposé de trajets.' : 
                            'Vous n\'avez pas encore effectué de réservations.'; ?>
                    </p>
                    <?php if ($user_type == 'driver'): ?>
                    <a href="create-trip.php" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Proposer un trajet
                    </a>
                    <?php else: ?>
                    <a href="trips.php" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-search mr-2"></i>Rechercher un trajet
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <?php foreach ($history as $item): ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                <div class="flex-1">
                                    <!-- En-tête avec statut -->
                                    <div class="flex items-center mb-2">
                                        <?php
                                        $status = $item['status'] ?? $item['driver_confirmed'];
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        if (isset($item['status'])) {
                                            // Pour les conducteurs
                                            switch($item['status']) {
                                                case 'completed':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    $status_text = 'Terminé';
                                                    break;
                                                case 'in_progress':
                                                    $status_class = 'bg-blue-100 text-blue-800';
                                                    $status_text = 'En cours';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    $status_text = 'Annulé';
                                                    break;
                                                default:
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    $status_text = 'Programmé';
                                            }
                                        } else {
                                            // Pour les passagers
                                            if ($item['driver_confirmed'] && $item['passenger_confirmed']) {
                                                $status_class = 'bg-green-100 text-green-800';
                                                $status_text = 'Confirmé';
                                            } elseif ($item['status'] == 'cancelled') {
                                                $status_class = 'bg-red-100 text-red-800';
                                                $status_text = 'Annulé';
                                            } else {
                                                $status_class = 'bg-yellow-100 text-yellow-800';
                                                $status_text = 'En attente';
                                            }
                                        }
                                        ?>
                                        <span class="text-xs font-medium px-2.5 py-0.5 rounded <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($item['departure_date'])); ?> à <?php echo substr($item['departure_time'], 0, 5); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Informations du trajet -->
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                                        <?php echo $item['departure_establishment'] ?? $item['departure_address']; ?> 
                                        → 
                                        <?php echo $item['destination_establishment'] ?? $item['destination_address']; ?>
                                    </h3>
                                    
                                    <!-- Informations supplémentaires -->
                                    <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                                        <?php if ($user_type == 'driver'): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-users mr-1"></i>
                                            <?php echo $item['total_bookings']; ?> réservation(s)
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-coins mr-1"></i>
                                            Total: <?php echo ($item['price_per_seat'] * $item['available_seats']); ?> Dhs
                                        </span>
                                        <?php else: ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-user mr-1"></i>
                                            Conducteur: <?php echo $item['driver_first_name'] . ' ' . $item['driver_last_name']; ?>
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-coins mr-1"></i>
                                            Prix: <?php echo $item['total_price']; ?> Dhs
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-chair mr-1"></i>
                                            Places: <?php echo $item['seats_booked']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="mt-4 md:mt-0 flex flex-col gap-2">
                                    <?php if ($user_type == 'driver' && $item['status'] == 'scheduled'): ?>
                                    <button class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors text-sm">
                                        Modifier
                                    </button>
                                    <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors text-sm">
                                        Annuler
                                    </button>
                                    <?php elseif ($user_type == 'passenger' && !$item['driver_confirmed']): ?>
                                    <button class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors text-sm">
                                        Voir détails
                                    </button>
                                    <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors text-sm">
                                        Annuler
                                    </button>
                                    <?php else: ?>
                                    <button class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm">
                                        Voir détails
                                    </button>
                                    <?php if (($item['status'] ?? 'completed') == 'completed'): ?>
                                    <button class="px-4 py-2 border border-orange-300 text-orange-600 rounded-lg hover:bg-orange-50 transition-colors text-sm">
                                        Noter
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Code de réservation pour les passagers -->
                            <?php if ($user_type == 'passenger' && isset($item['booking_code'])): ?>
                            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Code de réservation:</span>
                                        <span class="ml-2 font-mono text-lg font-bold"><?php echo $item['booking_code']; ?></span>
                                    </div>
                                    <?php if ($item['qr_code_url']): ?>
                                    <button class="flex items-center text-orange-600 hover:text-orange-700">
                                        <i class="fas fa-qrcode mr-1"></i>
                                        Voir QR Code
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if (!empty($history)): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <button class="px-3 py-1 rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="px-3 py-1 rounded-lg bg-orange-500 text-white">1</button>
                    <button class="px-3 py-1 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">2</button>
                    <button class="px-3 py-1 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">3</button>
                    <button class="px-3 py-1 rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.bg-white.rounded-xl');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>