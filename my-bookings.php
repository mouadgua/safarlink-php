<?php
require_once 'config/init.php';

requireLogin();

$user_id = $_SESSION['user']['id'];
$user_type = $_SESSION['user']['user_type'];

// --- NETTOYAGE AUTOMATIQUE DES TRAJETS ET RÉSERVATIONS PÉRIMÉES ---
// 1. Annule les trajets programmés dont l'heure de départ est passée de plus d'une heure.
$cleanup_trips_query = "UPDATE trips
                        SET status = 'cancelled'
                        WHERE status = 'scheduled'
                        AND CONCAT(departure_date, ' ', departure_time) < (NOW() - INTERVAL 1 HOUR)";
$db->query($cleanup_trips_query);

// 2. Annule les réservations en attente dont la date de départ est passée.
$cleanup_bookings_query = "UPDATE bookings b JOIN trips t ON b.trip_id = t.id
                           SET b.status = 'cancelled'
                           WHERE b.status = 'pending' AND CONCAT(t.departure_date, ' ', t.departure_time) < NOW()";
$db->query($cleanup_bookings_query);
// --- FIN DU NETTOYAGE ---

// Déterminer l'onglet actif
$active_tab = $_GET['tab'] ?? 'upcoming';

// Récupérer les réservations selon le type d'utilisateur et l'onglet
$where_conditions = [
    'upcoming' => "AND t.departure_date >= CURDATE() AND (b.status IS NULL OR b.status IN ('pending', 'confirmed'))",
    'confirmations' => "AND b.status = 'pending'", // Pour les conducteurs, réservations à confirmer
    'pending' => "AND b.status = 'pending'", // Pour les passagers, réservations en attente
    'completed' => "AND b.status = 'completed'",
    'cancelled' => "AND b.status = 'cancelled'"
];

$query_conditions = $where_conditions[$active_tab] ?? $where_conditions['upcoming'];

if ($user_type == 'driver') {
    if ($active_tab === 'confirmations') {
        // Vue par réservation pour les confirmations
        $query = "SELECT b.*, t.departure_address, t.destination_address, t.departure_date, t.departure_time,
                         p.first_name as passenger_first_name, p.last_name as passenger_last_name
                  FROM bookings b
                  JOIN trips t ON b.trip_id = t.id
                  JOIN users p ON b.passenger_id = p.id
                  WHERE t.driver_id = :user_id AND b.status = 'pending'
                  ORDER BY b.created_at ASC";
    } else {
        // Vue par trajet pour les autres onglets
        $query = "SELECT t.*, 
                         (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status IN ('pending', 'confirmed')) as booking_count,
                         (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status = 'pending') as pending_booking_count,
                         (SELECT id FROM bookings WHERE trip_id = t.id ORDER BY created_at ASC LIMIT 1) as first_booking_id
                  FROM trips t
                  WHERE t.driver_id = :user_id";
        
        if ($active_tab === 'upcoming') $query .= " AND t.status = 'scheduled' AND t.departure_date >= CURDATE()";
        if ($active_tab === 'completed') $query .= " AND t.status = 'completed'";
        if ($active_tab === 'cancelled') $query .= " AND t.status = 'cancelled'";

        $query .= " GROUP BY t.id ORDER BY t.departure_date DESC, t.departure_time DESC";
    }
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
    SUM(CASE WHEN t.driver_id = :user_id AND b.status = 'pending' THEN 1 ELSE 0 END) as confirmations,
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
            <!-- En-tête avec bouton d'action -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo $user_type == 'driver' ? 'Mes Trajets Proposés' : 'Mes Réservations'; ?></h1>
                    <p class="text-gray-600">
                        <?php echo $user_type == 'driver' ? 
                            'Gérez les trajets que vous avez proposés et les réservations associées' : 
                            'Suivez l\'état de toutes vos réservations de covoiturage'; ?>
                    </p>
                </div>
                <?php if ($user_type == 'driver'): ?>
                <a href="propose-trip.php" class="mt-4 md:mt-0 px-5 py-2.5 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition-colors shadow">
                    <i class="fas fa-plus-circle mr-2"></i>Nouveau Trajet
                </a>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

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

                        <?php if ($user_type == 'driver'): ?>
                        <button class="tab-btn mr-8 py-4 px-1 border-b-2 font-medium text-sm 
                            <?php echo $active_tab == 'confirmations' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            data-tab="confirmations">
                            Confirmations
                            <?php if ($counts['confirmations'] > 0): ?>
                            <span class="ml-2 py-0.5 px-2.5 text-xs bg-yellow-100 text-yellow-600 rounded-full">
                                <?php echo $counts['confirmations']; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <?php else: ?>
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
                        <?php endif; ?>
                        
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
                                        <span class="text-xs font-medium px-2.5 py-0.5 rounded <?php echo getStatusClass($booking['status'] ?? ''); ?>">
                                            <?php echo getStatusText($user_type == 'driver' && $active_tab !== 'confirmations' ? ($booking['status'] ?? '') : ($booking['status'] ?? '')); ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-500">
                                            #<?php echo $booking['booking_code'] ?? 'N/A'; ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($booking['departure_date'])); ?> à <?php echo substr($booking['departure_time'], 0, 5); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Informations du trajet -->
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                                        <?php echo htmlspecialchars($booking['departure_address']); ?> 
                                        → 
                                        <?php echo htmlspecialchars($booking['destination_address']); ?>
                                    </h3>
                                    
                                    <!-- Informations utilisateur -->
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <span class="flex items-center">
                                            <i class="fas fa-user mr-1"></i>
                                            <?php if ($user_type == 'driver'): ?>
                                                <?php if ($active_tab === 'confirmations'): ?>
                                                    <?php echo htmlspecialchars($booking['passenger_first_name'] . ' ' . $booking['passenger_last_name']); ?>
                                                <?php else: ?>
                                                    <?php echo ($booking['booking_count'] ?? 0) . ' réservation(s)'; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($booking['driver_first_name'] . ' ' . $booking['driver_last_name']); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($user_type == 'passenger'): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-star text-yellow-400 mr-1"></i>
                                            <?php echo $booking['driver_rating']; ?> (<?php echo floor($booking['driver_rating'] * 20); ?> avis)
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-chair mr-1"></i>
                                            <?php echo $booking['seats_booked'] ?? 1; ?> place(s)
                                        </span>
                                        <span class="flex items-center font-medium text-orange-600">
                                            <i class="fas fa-coins mr-1"></i>
                                            <?php echo $booking['total_price'] ?? ($booking['price_per_seat'] * ($booking['seats_booked'] ?? 1)); ?> Dhs
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="mt-4 lg:mt-0 flex flex-col gap-2">
                                    <?php
                                        // Déterminer le bon ID pour le lien de détails
                                        $details_id = ($user_type == 'passenger' || $active_tab == 'confirmations') ? $booking['id'] : ($booking['first_booking_id'] ?? null);
                                    ?>
                                    <?php if ($details_id): ?>
                                    <button onclick="window.location.href='booking-details.php?id=<?php echo $details_id; ?>'" 
                                            class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors text-sm">
                                        <i class="fas fa-eye mr-2"></i>Voir détails
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($user_type == 'driver' && $active_tab == 'upcoming' && ($booking['status'] ?? '') == 'scheduled'): ?>
                                        <?php if (($booking['booking_count'] ?? 0) > 0): ?>
                                            <button onclick="confirmStartTrip(<?php echo $booking['id']; ?>, <?php echo $booking['available_seats']; ?>, <?php echo $booking['booking_count']; ?>)" 
                                                    class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm">
                                                <i class="fas fa-play-circle mr-2"></i>Commencer la course
                                            </button>
                                        <?php else: ?>
                                            <button onclick="window.location.href='edit-trip.php?id=<?php echo $booking['id']; ?>'" class="px-4 py-2 border border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors text-sm">
                                                <i class="fas fa-edit mr-2"></i>Modifier
                                            </button>
                                        <?php endif; ?>
                                    <?php elseif ($user_type == 'passenger' && ($booking['status'] ?? '') == 'completed'): ?>
                                    <button onclick="window.location.href='review.php?booking_id=<?php echo $booking['id']; ?>'" class="px-4 py-2 border border-orange-300 text-orange-600 rounded-lg hover:bg-orange-50 transition-colors text-sm">
                                        <i class="fas fa-star mr-2"></i>Noter
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array(($booking['status'] ?? ''), ['pending', 'confirmed'])): ?>
                                    <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors text-sm">
                                        <i class="fas fa-times mr-2"></i>Annuler
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Barre de progression pour les validations -->
                            <?php if (in_array(($booking['status'] ?? ''), ['pending', 'confirmed'])): ?>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">Validation du trajet:</span>
                                    <div class="flex items-center gap-4">
                                        <span class="flex items-center gap-1">
                                            <?php if ($booking['driver_confirmed'] ?? false): ?>
                                                <i class="fas fa-check-circle text-green-500"></i>
                                                <span class="text-green-600">Conducteur</span>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-yellow-500"></i>
                                                <span class="text-yellow-600">Conducteur</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <?php if ($booking['passenger_confirmed'] ?? false): ?>
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

        // Fonction pour confirmer le démarrage d'un trajet non complet
        function confirmStartTrip(tripId, availableSeats, bookedSeats) {
            if (bookedSeats < availableSeats) {
                const message = "Attention : Ce trajet n'est pas complet. Si vous commencez la course maintenant, vous ne pourrez plus recevoir de nouvelles réservations.\n\nEn cas de plainte d'un passager suite à cette situation, vous pourriez être sanctionné.\n\nVoulez-vous vraiment commencer la course ?";
                if (confirm(message)) {
                    window.location.href = `start-trip.php?id=${tripId}`;
                }
            } else {
                // Si le trajet est complet, on y va directement
                window.location.href = `start-trip.php?id=${tripId}`;
            }
        }
    </script>
</body>
</html>

<?php
// Fonctions helper pour les états vides
function getEmptyStateTitle($tab, $user_type) {
    $titles_driver = [
        'upcoming' => 'Aucun trajet à venir',
        'confirmations' => 'Aucune réservation à confirmer',
        'pending' => 'Aucune réservation en attente',
        'completed' => 'Aucun trajet terminé',
        'cancelled' => 'Aucune réservation annulée'
    ];

    $titles_passenger = [
        'upcoming' => 'Aucune réservation à venir',
        'pending' => 'Aucune réservation en attente',
        'completed' => 'Aucun trajet terminé',
        'cancelled' => 'Aucune réservation annulée'
    ];
    
    $titles = $user_type == 'driver' ? $titles_driver : $titles_passenger;
    
    return $titles[$tab] ?? 'Aucun élément';
}

function getEmptyStateMessage($tab, $user_type) {
    $messages = [
        'upcoming' => $user_type == 'driver' ? 
            'Vous n\'avez pas de trajets programmés.' : 
            'Vous n\'avez pas de réservation de covoiturage à venir.',
        'confirmations' => 'Toutes les réservations sont confirmées. Excellent travail !',
        'pending' => $user_type == 'driver' ? 
            'Aucune réservation en attente de confirmation.' : 
            'Aucune réservation en attente de confirmation.',
        'completed' => $user_type == 'driver' ? 
            'Vous n\'avez pas encore complété de trajets.' : 
            'Vous n\'avez pas encore effectué de trajets.',
        'cancelled' => 'Aucune réservation annulée pour le moment.'
    ];
    
    return $messages[$tab] ?? 'Aucun élément trouvé.';
}

function getEmptyStateAction($tab, $user_type) {
    if ($tab == 'upcoming') {
        if ($user_type == 'driver') {
            return '<a href="propose-trip.php" class="inline-flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
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
        case 'scheduled': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'confirmed': return 'Confirmé';
        case 'pending': return 'En attente';
        case 'cancelled': return 'Annulé';
        case 'completed': return 'Terminé';
        case 'scheduled': return 'Programmé';
        default: return 'Inconnu';
    }
}
?>