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
$user_type = $_SESSION['user']['user_type'];

// Déterminer l'onglet actif
$active_tab = $_GET['tab'] ?? 'upcoming';

// Récupérer les réservations selon le type d'utilisateur et l'onglet
$where_conditions = [
    'upcoming' => "AND t.departure_date >= CURDATE() AND b.status IN ('pending', 'confirmed')",
    'pending' => "AND b.status = 'pending'",
    'completed' => "AND b.status = 'completed'",
    'cancelled' => "AND b.status = 'cancelled'"
];

$query_conditions = $where_conditions[$active_tab] ?? $where_conditions['upcoming'];

if ($user_type == 'driver') {
    $query = "SELECT b.*, 
                     t.departure_address, t.destination_address, t.departure_date, t.departure_time,
                     t.price_per_seat, u.first_name as passenger_first_name, u.last_name as passenger_last_name,
                     u.rating as passenger_rating, e1.name as departure_establishment, 
                     e2.name as destination_establishment
              FROM trips t
              JOIN bookings b ON t.id = b.trip_id
              JOIN users u ON b.passenger_id = u.id
              LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
              LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
              WHERE t.driver_id = :user_id {$query_conditions}
              ORDER BY t.departure_date DESC, t.departure_time DESC";
} else {
    $query = "SELECT b.*, 
                     t.departure_address, t.destination_address, t.departure_date, t.departure_time,
                     t.price_per_seat, u.first_name as driver_first_name, u.last_name as driver_last_name,
                     u.rating as driver_rating, e1.name as departure_establishment, 
                     e2.name as destination_establishment
              FROM bookings b
              JOIN trips t ON b.trip_id = t.id
              JOIN users u ON t.driver_id = u.id
              LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
              LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
              WHERE b.passenger_id = :user_id {$query_conditions}
              ORDER BY t.departure_date DESC, t.departure_time DESC";
}

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les réservations par statut pour les badges
$count_query = "SELECT 
    SUM(CASE WHEN t.departure_date >= CURDATE() AND b.status IN ('pending', 'confirmed') THEN 1 ELSE 0 END) as upcoming,
    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings b
    JOIN trips t ON b.trip_id = t.id
    WHERE " . ($user_type == 'driver' ? "t.driver_id = :user_id" : "b.passenger_id = :user_id");

$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':user_id', $user_id);
$count_stmt->execute();
$counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?>

    <!-- Loader -->
    <?php include 'components/loader.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4">
            <!-- En-tête -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Mes Réservations</h1>
                <p class="text-gray-600">
                    <?php echo $user_type == 'driver' ? 
                        'Gérez vos trajets proposés et les réservations associées' : 
                        'Suivez l\'état de toutes vos réservations de covoiturage'; ?>
                </p>
            </div>

            <!-- Onglets -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex flex-wrap -mb-px">
                        <button class="tab-btn mr-8 py-4 px-1 border-b-2 font-medium text-sm 
                            <?php echo $active_tab == 'upcoming' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            data-tab="upcoming">
                            À venir
                            <?php if ($counts['upcoming'] > 0): ?>
                            <span class="ml-2 py-0.5 px-2.5 text-xs bg-orange-100 text-orange-600 rounded-full">
                                <?php echo $counts['upcoming']; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        
                        <button class="tab-btn mr-8 py-4 px-1 border-b-2 font-medium text-sm 
                            <?php echo $active_tab == 'pending' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            data-tab="pending">
                            En attente
                            <?php if ($counts['pending'] > 0): ?>
                            <span class="ml-2 py-0.5 px-2.5 text-xs bg-yellow-100 text-yellow-600 rounded-full">
                                <?php echo $counts['pending']; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        
                        <button class="tab-btn mr-8 py-4 px-1 border-b-2 font-medium text-sm 
                            <?php echo $active_tab == 'completed' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            data-tab="completed">
                            Terminés
                            <?php if ($counts['completed'] > 0): ?>
                            <span class="ml-2 py-0.5 px-2.5 text-xs bg-green-100 text-green-600 rounded-full">
                                <?php echo $counts['completed']; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        
                        <button class="tab-btn py-4 px-1 border-b-2 font-medium text-sm 
                            <?php echo $active_tab == 'cancelled' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            data-tab="cancelled">
                            Annulés
                            <?php if ($counts['cancelled'] > 0): ?>
                            <span class="ml-2 py-0.5 px-2.5 text-xs bg-red-100 text-red-600 rounded-full">
                                <?php echo $counts['cancelled']; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Barre d'outils -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex flex-wrap gap-4">
                        <select class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="all">Tous les trajets</option>
                            <option value="week">Cette semaine</option>
                            <option value="month">Ce mois</option>
                            <option value="year">Cette année</option>
                        </select>
                        
                        <input type="date" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        
                        <input type="text" placeholder="Rechercher..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    
                    <div class="flex gap-2">
                        <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-download mr-2"></i>Exporter
                        </button>
                        <button class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                            <i class="fas fa-filter mr-2"></i>Filtrer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Liste des réservations -->
            <div class="space-y-6">
                <?php if (empty($bookings)): ?>
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">
                        <?php echo getEmptyStateTitle($active_tab, $user_type); ?>
                    </h3>
                    <p class="text-gray-500 mb-4">
                        <?php echo getEmptyStateMessage($active_tab, $user_type); ?>
                    </p>
                    <?php echo getEmptyStateAction($active_tab, $user_type); ?>
                </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg">
                        <div class="p-6">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-4">
                                <div class="flex-1">
                                    <!-- En-tête avec statut -->
                                    <div class="flex items-center mb-2">
                                        <span class="text-xs font-medium px-2.5 py-0.5 rounded <?php echo getStatusClass($booking['status']); ?>">
                                            <?php echo getStatusText($booking['status']); ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-500">
                                            #<?php echo $booking['booking_code']; ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($booking['departure_date'])); ?> à <?php echo substr($booking['departure_time'], 0, 5); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Informations du trajet -->
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                                        <?php echo htmlspecialchars($booking['departure_establishment'] ?? $booking['departure_address']); ?> 
                                        → 
                                        <?php echo htmlspecialchars($booking['destination_establishment'] ?? $booking['destination_address']); ?>
                                    </h3>
                                    
                                    <!-- Informations utilisateur -->
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <span class="flex items-center">
                                            <i class="fas fa-user mr-1"></i>
                                            <?php echo $user_type == 'driver' ? 
                                                htmlspecialchars($booking['passenger_first_name'] . ' ' . $booking['passenger_last_name']) :
                                                htmlspecialchars($booking['driver_first_name'] . ' ' . $booking['driver_last_name']); ?>
                                        </span>
                                        <?php if ($user_type == 'passenger'): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-star text-yellow-400 mr-1"></i>
                                            <?php echo $booking['driver_rating']; ?> (<?php echo floor($booking['driver_rating'] * 20); ?> avis)
                                        </span>
                                        <?php endif; ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-chair mr-1"></i>
                                            <?php echo $booking['seats_booked']; ?> place(s)
                                        </span>
                                        <span class="flex items-center font-medium text-orange-600">
                                            <i class="fas fa-euro-sign mr-1"></i>
                                            <?php echo $booking['total_price']; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="mt-4 lg:mt-0 flex flex-col gap-2">
                                    <button onclick="window.location.href='booking-details.php?id=<?php echo $booking['id']; ?>'" 
                                            class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors text-sm">
                                        <i class="fas fa-eye mr-2"></i>Voir détails
                                    </button>
                                    
                                    <?php if ($booking['status'] == 'pending'): ?>
                                    <button class="px-4 py-2 border border-green-300 text-green-600 rounded-lg hover:bg-green-50 transition-colors text-sm">
                                        <i class="fas fa-check mr-2"></i>Confirmer
                                    </button>
                                    <?php elseif ($booking['status'] == 'completed' && $user_type == 'passenger'): ?>
                                    <button class="px-4 py-2 border border-orange-300 text-orange-600 rounded-lg hover:bg-orange-50 transition-colors text-sm">
                                        <i class="fas fa-star mr-2"></i>Noter
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                    <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors text-sm">
                                        <i class="fas fa-times mr-2"></i>Annuler
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Barre de progression pour les validations -->
                            <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">Validation du trajet:</span>
                                    <div class="flex items-center gap-4">
                                        <span class="flex items-center gap-1">
                                            <?php if ($booking['driver_confirmed']): ?>
                                                <i class="fas fa-check-circle text-green-500"></i>
                                                <span class="text-green-600">Conducteur</span>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-yellow-500"></i>
                                                <span class="text-yellow-600">Conducteur</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <?php if ($booking['passenger_confirmed']): ?>
                                                <i class="fas fa-check-circle text-green-500"></i>
                                                <span class="text-green-600">Passager</span>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-yellow-500"></i>
                                                <span class="text-yellow-600">Passager</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if (!empty($bookings)): ?>
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
        // Animation GSAP
        gsap.from('.booking-card', {
            duration: 0.6,
            y: 30,
            opacity: 0,
            stagger: 0.1,
            ease: "power2.out"
        });

        // Gestion des onglets
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.getAttribute('data-tab');
                window.location.href = `my-bookings.php?tab=${tab}`;
            });
        });

        // Animation au survol des cartes
        document.querySelectorAll('.booking-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                gsap.to(this, { y: -5, duration: 0.3 });
            });
            
            card.addEventListener('mouseleave', function() {
                gsap.to(this, { y: 0, duration: 0.3 });
            });
        });

        // Recherche en temps réel
        document.querySelector('input[placeholder="Rechercher..."]')?.addEventListener('input', function(e) {
            const searchTerm = this.value.toLowerCase();
            const bookings = document.querySelectorAll('.booking-card');
            
            bookings.forEach(booking => {
                const text = booking.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    booking.style.display = 'block';
                    gsap.to(booking, { opacity: 1, y: 0, duration: 0.3 });
                } else {
                    gsap.to(booking, { 
                        opacity: 0, 
                        y: -20, 
                        duration: 0.3,
                        onComplete: () => booking.style.display = 'none'
                    });
                }
            });
        });
    </script>
</body>
</html>

<?php
// Fonctions helper pour les états vides
function getEmptyStateTitle($tab, $user_type) {
    $titles = [
        'upcoming' => 'Aucune réservation à venir',
        'pending' => 'Aucune réservation en attente',
        'completed' => 'Aucun trajet terminé',
        'cancelled' => 'Aucune réservation annulée'
    ];
    
    return $titles[$tab] ?? 'Aucune réservation';
}

function getEmptyStateMessage($tab, $user_type) {
    $messages = [
        'upcoming' => $user_type == 'driver' ? 
            'Vous n\'avez pas de trajets à venir avec des réservations.' : 
            'Vous n\'avez pas de réservations de covoiturage à venir.',
        'pending' => $user_type == 'driver' ? 
            'Aucune réservation en attente de confirmation.' : 
            'Aucune réservation en attente de confirmation.',
        'completed' => $user_type == 'driver' ? 
            'Vous n\'avez pas encore complété de trajets.' : 
            'Vous n\'avez pas encore effectué de trajets.',
        'cancelled' => 'Aucune réservation annulée pour le moment.'
    ];
    
    return $messages[$tab] ?? 'Aucune réservation trouvée.';
}

function getEmptyStateAction($tab, $user_type) {
    if ($tab == 'upcoming') {
        if ($user_type == 'driver') {
            return '<a href="create-trip.php" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Proposer un trajet
                    </a>';
        } else {
            return '<a href="trips.php" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-search mr-2"></i>Rechercher un trajet
                    </a>';
        }
    }
    
    return '';
}

// Fonctions helper pour les statuts
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