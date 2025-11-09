<?php
require_once 'config/init.php';

// Vérifier si l'utilisateur est connecté
requireLogin();

$user_id = $_SESSION['user']['id'];

// Récupérer les données réelles de l'utilisateur depuis la base de données
$query = "SELECT u.*, e.name as establishment_name 
          FROM users u 
          LEFT JOIN establishments e ON u.establishment_id = e.id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Mettre à jour les données de session avec les données fraîches
$_SESSION['user'] = array_merge($_SESSION['user'], $user);

// Récupérer les statistiques réelles depuis la base de données
if ($user['user_type'] == 'driver') {
    // Statistiques pour conducteur
    $stats_query = "SELECT 
        COUNT(DISTINCT t.id) as trips_completed,
        COUNT(b.id) as passengers_transported,
        COALESCE(AVG(r.rating), 0) as average_rating,
        COALESCE(SUM(b.total_price), 0) as total_earnings
        FROM trips t
        LEFT JOIN bookings b ON t.id = b.trip_id AND b.status = 'completed'
        LEFT JOIN reviews r ON r.reviewed_user_id = t.driver_id
        WHERE t.driver_id = :user_id AND t.status = 'completed'";
} else {
    // Statistiques pour passager
    $stats_query = "SELECT 
        COUNT(DISTINCT b.id) as trips_completed,
        COUNT(b.id) as trips_booked,
        COALESCE(AVG(r.rating), 0) as average_rating,
        COALESCE(SUM(b.total_price), 0) as total_savings
        FROM bookings b
        LEFT JOIN trips t ON b.trip_id = t.id
        LEFT JOIN reviews r ON r.reviewed_user_id = b.passenger_id
        WHERE b.passenger_id = :user_id AND b.status = 'completed'";
}

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id);
$stats_stmt->execute();
$user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les avis récents
$reviews_query = "SELECT r.*, 
                         u.first_name as reviewer_first_name, 
                         u.last_name as reviewer_last_name,
                         r.created_at
                  FROM reviews r
                  JOIN users u ON r.reviewer_id = u.id
                  WHERE r.reviewed_user_id = :user_id
                  ORDER BY r.created_at DESC
                  LIMIT 5";
$reviews_stmt = $db->prepare($reviews_query);
$reviews_stmt->bindParam(':user_id', $user_id);
$reviews_stmt->execute();
$recent_reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les trajets à venir
if ($user['user_type'] == 'driver') {
    // Trajets à venir pour conducteur
    $trips_query = "SELECT t.*, 
                           COUNT(b.id) as passengers_count,
                           e1.name as departure_establishment,
                           e2.name as destination_establishment
                    FROM trips t
                    LEFT JOIN bookings b ON t.id = b.trip_id AND b.status IN ('pending', 'confirmed')
                    LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
                    LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
                    WHERE t.driver_id = :user_id 
                    AND t.departure_date >= CURDATE() 
                    AND t.status = 'scheduled'
                    GROUP BY t.id
                    ORDER BY t.departure_date ASC, t.departure_time ASC
                    LIMIT 5";
} else {
    // Réservations à venir pour passager
    $trips_query = "SELECT b.*, 
                           t.departure_address, t.destination_address, 
                           t.departure_date, t.departure_time,
                           t.price_per_seat, u.first_name as driver_first_name,
                           u.last_name as driver_last_name,
                           e1.name as departure_establishment,
                           e2.name as destination_establishment
                    FROM bookings b
                    JOIN trips t ON b.trip_id = t.id
                    JOIN users u ON t.driver_id = u.id
                    LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
                    LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
                    WHERE b.passenger_id = :user_id 
                    AND t.departure_date >= CURDATE() 
                    AND b.status IN ('pending', 'confirmed')
                    ORDER BY t.departure_date ASC, t.departure_time ASC
                    LIMIT 5";
}

$trips_stmt = $db->prepare($trips_query);
$trips_stmt->bindParam(':user_id', $user_id);
$trips_stmt->execute();
$upcoming_trips = $trips_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        :root {
            --primary: #ffffff;
            --secondary: #ffa215;
            --accent: #000000;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .profile-section {
            opacity: 0;
            transform: translateY(20px);
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Inclusion de la Navbar Unifiée -->
    <?php include 'components/navbar.php'; ?>

    <!-- Loader -->
    <?php include 'components/loader.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']); // On supprime le message pour ne pas l'afficher à nouveau
                    ?>
                </div>
            <?php endif; ?>
            <!-- En-tête du profil -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6 profile-section">
                <div class="flex flex-col md:flex-row items-center">
                    <!-- Avatar -->
                    <div class="w-24 h-24 rounded-full bg-orange-500 flex items-center justify-center text-white text-2xl font-bold mb-4 md:mb-0 md:mr-6">
                        <?php 
                        $initials = '';
                        if (isset($user['first_name']) && isset($user['last_name'])) {
                            $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
                        } else {
                            $initials = 'UU';
                        }
                        echo $initials;
                        ?>
                    </div>
                    
                    <!-- Informations utilisateur -->
                    <div class="flex-1 text-center md:text-left">
                        <h1 class="text-2xl font-bold text-gray-800">
                            <?php echo htmlspecialchars($user['first_name'] ?? 'Utilisateur') . ' ' . htmlspecialchars($user['last_name'] ?? ''); ?>
                        </h1>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($user['email'] ?? 'Email non disponible'); ?></p>
                        
                        <!-- Badge type utilisateur -->
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                            <?php echo ($user['user_type'] ?? 'passenger') == 'driver' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                            <i class="fas <?php echo ($user['user_type'] ?? 'passenger') == 'driver' ? 'fa-car' : 'fa-user'; ?> mr-2"></i>
                            <?php echo ($user['user_type'] ?? 'passenger') == 'driver' ? 'Conducteur' : 'Passager'; ?>
                        </div>
                        
                        <!-- Rating (seulement si l'utilisateur a des avis) -->
                        <?php if ($user_stats['average_rating'] > 0): ?>
                        <div class="flex items-center justify-center md:justify-start mt-2">
                            <div class="flex text-yellow-400">
                                <?php
                                $rating = $user_stats['average_rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($rating)) {
                                        echo '<i class="fas fa-star"></i>';
                                    } elseif ($i == ceil($rating) && $rating != floor($rating)) {
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="ml-2 text-gray-600">
                                <?php echo number_format($rating, 1); ?> 
                                (<?php 
                                // Compter le nombre total d'avis
                                $count_reviews_query = "SELECT COUNT(*) as total FROM reviews WHERE reviewed_user_id = :user_id";
                                $count_reviews_stmt = $db->prepare($count_reviews_query);
                                $count_reviews_stmt->bindParam(':user_id', $user_id);
                                $count_reviews_stmt->execute();
                                $total_reviews = $count_reviews_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                echo $total_reviews; 
                                ?> avis)
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Bouton édition -->
                    <button class="mt-4 md:mt-0 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-edit mr-2"></i>Modifier
                    </button>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Sidebar avec statistiques verticales -->
                <div class="lg:w-1/4 space-y-6">
                    <!-- Statistiques verticales -->
                    <div class="bg-white rounded-xl shadow-md p-6 profile-section">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Mes statistiques</h2>
                        <div class="space-y-4">
                            <?php if ($user['user_type'] == 'driver'): ?>
                            <!-- Statistiques Conducteur -->
                            <div class="stat-card p-4 bg-orange-50 rounded-lg border-l-4 border-orange-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-orange-600">
                                            <?php echo $user_stats['trips_completed'] ?? 0; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Trajets effectués</div>
                                    </div>
                                    <i class="fas fa-route text-orange-500 text-xl"></i>
                                </div>
                            </div>
                            
                            <div class="stat-card p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-blue-600">
                                            <?php echo $user_stats['passengers_transported'] ?? 0; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Passagers transportés</div>
                                    </div>
                                    <i class="fas fa-users text-blue-500 text-xl"></i>
                                </div>
                            </div>
                            
                            <div class="stat-card p-4 bg-green-50 rounded-lg border-l-4 border-green-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-green-600">
                                            <?php echo number_format($user_stats['average_rating'] ?? 0, 1); ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Note moyenne</div>
                                    </div>
                                    <i class="fas fa-star text-green-500 text-xl"></i>
                                </div>
                            </div>
                            
                            <div class="stat-card p-4 bg-purple-50 rounded-lg border-l-4 border-purple-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-purple-600">
                                            <?php echo $user_stats['total_earnings'] ?? 0; ?> Dhs
                                        </div>
                                        <div class="text-sm text-gray-600">Revenus totaux</div>
                                    </div>
                                    <i class="fas fa-coins text-purple-500 text-xl"></i>
                                </div>
                            </div>
                            
                            <?php else: ?>
                            <!-- Statistiques Passager -->
                            <div class="stat-card p-4 bg-orange-50 rounded-lg border-l-4 border-orange-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-orange-600">
                                            <?php echo $user_stats['trips_completed'] ?? 0; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Trajets effectués</div>
                                    </div>
                                    <i class="fas fa-route text-orange-500 text-xl"></i>
                                </div>
                            </div>
                            
                            <div class="stat-card p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-blue-600">
                                            <?php echo $user_stats['trips_booked'] ?? 0; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Trajets réservés</div>
                                    </div>
                                    <i class="fas fa-ticket-alt text-blue-500 text-xl"></i>
                                </div>
                            </div>
                            
                            <div class="stat-card p-4 bg-green-50 rounded-lg border-l-4 border-green-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-green-600">
                                            <?php echo number_format($user_stats['average_rating'] ?? 0, 1); ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Note moyenne</div>
                                    </div>
                                    <i class="fas fa-star text-green-500 text-xl"></i>
                                </div>
                            </div>
                            
                            <div class="stat-card p-4 bg-purple-50 rounded-lg border-l-4 border-purple-500">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-2xl font-bold text-purple-600">
                                            <?php echo $user_stats['total_savings'] ?? 0; ?> Dhs
                                        </div>
                                        <div class="text-sm text-gray-600">Économies totales</div>
                                    </div>
                                    <i class="fas fa-piggy-bank text-purple-500 text-xl"></i>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="bg-white rounded-xl shadow-md p-6 profile-section">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Actions rapides</h2>
                        <div class="space-y-3">
                            <?php if ($user['user_type'] == 'driver'): ?>
                        <button onclick="window.location.href='propose-trip.php'" class="w-full text-left p-3 rounded-lg border border-gray-300 hover:bg-orange-50 hover:border-orange-300 transition-colors">
                                <i class="fas fa-plus-circle text-orange-500 mr-3"></i>
                                <span>Proposer un trajet</span>
                            </button>
                            <?php else: ?>
                            <button onclick="window.location.href='trips.php'" class="w-full text-left p-3 rounded-lg border border-gray-300 hover:bg-orange-50 hover:border-orange-300 transition-colors">
                                <i class="fas fa-search text-orange-500 mr-3"></i>
                                <span>Rechercher un trajet</span>
                            </button>
                            <?php endif; ?>
                            
                            <button onclick="window.location.href='history.php'" class="w-full text-left p-3 rounded-lg border border-gray-300 hover:bg-orange-50 hover:border-orange-300 transition-colors">
                                <i class="fas fa-history text-orange-500 mr-3"></i>
                                <span>Historique des trajets</span>
                            </button>
                            
                            <button onclick="window.location.href='my-bookings.php'" class="w-full text-left p-3 rounded-lg border border-gray-300 hover:bg-orange-50 hover:border-orange-300 transition-colors">
                                <i class="fas fa-calendar text-orange-500 mr-3"></i>
                                <span>Mes réservations</span>
                            </button>
                            
                            <button class="w-full text-left p-3 rounded-lg border border-gray-300 hover:bg-orange-50 hover:border-orange-300 transition-colors">
                                <i class="fas fa-cog text-orange-500 mr-3"></i>
                                <span>Paramètres</span>
                            </button>
                            
                            <button onclick="window.location.href='logout.php'" class="w-full text-left p-3 rounded-lg border border-red-300 hover:bg-red-50 hover:border-red-400 transition-colors">
                                <i class="fas fa-sign-out-alt text-red-500 mr-3"></i>
                                <span class="text-red-600">Déconnexion</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenu principal -->
                <div class="lg:w-3/4 space-y-6">
                    <!-- Section véhicule (uniquement pour conducteurs) -->
                    <?php if ($user['user_type'] == 'driver'): ?>
                    <div class="bg-white rounded-xl shadow-md p-6 profile-section">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Mon véhicule</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Plaque d'immatriculation</label>
                                <p class="text-gray-900 font-medium">
                                    <?php echo htmlspecialchars($user['license_plate'] ?? 'Non renseignée'); ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
                                <p class="text-gray-900 font-medium">
                                    <?php echo htmlspecialchars($user['car_model'] ?? 'Non renseigné'); ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                                <p class="text-gray-900 font-medium">
                                    <?php echo htmlspecialchars($user['car_color'] ?? 'Non renseignée'); ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Établissement</label>
                                <p class="text-gray-900 font-medium">
                                    <?php echo htmlspecialchars($user['establishment_name'] ?? 'Non renseigné'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Derniers avis -->
                    <div class="bg-white rounded-xl shadow-md p-6 profile-section">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Derniers avis</h2>
                        <div class="space-y-4">
                            <?php if (!empty($recent_reviews)): ?>
                                <?php foreach ($recent_reviews as $review): ?>
                                <div class="border-l-4 border-orange-500 pl-4 py-2">
                                    <div class="flex items-center mb-2">
                                        <div class="flex text-yellow-400 mr-2">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $review['rating']) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <span class="text-sm text-gray-500">
                                            <?php echo timeAgo($review['created_at']); ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-700 mb-2">"<?php echo htmlspecialchars($review['comment'] ?? 'Aucun commentaire'); ?>"</p>
                                    <p class="text-sm text-gray-600">
                                        - <?php echo htmlspecialchars($review['reviewer_first_name'] . ' ' . $review['reviewer_last_name']); ?>
                                    </p>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-comment-slash text-4xl mb-4"></i>
                                <p>Aucun avis pour le moment</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Prochains trajets -->
                    <div class="bg-white rounded-xl shadow-md p-6 profile-section">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">
                            <?php echo $user['user_type'] == 'driver' ? 'Mes prochains trajets' : 'Mes prochaines réservations'; ?>
                        </h2>
                        <div class="space-y-4">
                            <?php if (!empty($upcoming_trips)): ?>
                                <?php foreach ($upcoming_trips as $trip): ?>
                                <div class="p-4 rounded-lg border <?php echo ($trip['status'] ?? 'confirmed') == 'confirmed' ? 'border-orange-200 bg-orange-50' : 'border-blue-200 bg-blue-50'; ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex-1">
                                            <h3 class="font-medium text-gray-800">
                                                <?php echo htmlspecialchars($trip['departure_establishment'] ?? $trip['departure_address']); ?> 
                                                → 
                                                <?php echo htmlspecialchars($trip['destination_establishment'] ?? $trip['destination_address']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                <?php echo date('d/m/Y', strtotime($trip['departure_date'])); ?> à 
                                                <?php echo substr($trip['departure_time'], 0, 5); ?>
                                            </p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded-full 
                                            <?php echo ($trip['status'] ?? 'confirmed') == 'confirmed' ? 'bg-orange-500 text-white' : 'bg-blue-500 text-white'; ?>">
                                            <?php echo ($trip['status'] ?? 'confirmed') == 'confirmed' ? 'Confirmé' : 'En attente'; ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">
                                            <?php 
                                            if ($user['user_type'] == 'driver') {
                                                echo ($trip['passengers_count'] ?? 0) . ' passager(s)';
                                            } else {
                                                echo $trip['seats_booked'] . ' place(s)';
                                            }
                                            ?>
                                        </span>
                                        <span class="font-medium 
                                            <?php echo ($trip['status'] ?? 'confirmed') == 'confirmed' ? 'text-orange-600' : 'text-blue-600'; ?>">
                                            <?php 
                                            if ($user['user_type'] == 'driver' && isset($trip['passengers_count'])) {
                                                echo (($trip['price_per_seat'] ?? 0) * ($trip['passengers_count'] ?? 0)) . ' Dhs';
                                            } else {
                                                echo ($trip['total_price'] ?? '0') . ' Dhs';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-road text-4xl mb-4"></i>
                                <p>Aucun trajet à venir</p>
                                <?php if ($user['user_type'] == 'driver'): ?>
                                <button onclick="window.location.href='create-trip.php'" class="mt-4 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                    Proposer un trajet
                                </button>
                                <?php else: ?>
                                <button onclick="window.location.href='trips.php'" class="mt-4 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                    Rechercher un trajet
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Informations de contact -->
                    <div class="bg-white rounded-xl shadow-md p-6 profile-section">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Informations de contact</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-phone text-gray-400 w-5 mr-3"></i>
                                <div>
                                    <div class="text-sm text-gray-500">Téléphone</div>
                                    <div class="text-gray-700 font-medium">
                                        <?php echo htmlspecialchars($user['phone'] ?? 'Non renseigné'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-envelope text-gray-400 w-5 mr-3"></i>
                                <div>
                                    <div class="text-sm text-gray-500">Email</div>
                                    <div class="text-gray-700 font-medium">
                                        <?php echo htmlspecialchars($user['email'] ?? 'Non renseigné'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-calendar text-gray-400 w-5 mr-3"></i>
                                <div>
                                    <div class="text-sm text-gray-500">Membre depuis</div>
                                    <div class="text-gray-700 font-medium">
                                        <?php 
                                        $created_at = $user['created_at'] ?? date('Y-m-d H:i:s');
                                        echo date('m/Y', strtotime($created_at)); 
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <i class="fas fa-user-tag text-gray-400 w-5 mr-3"></i>
                                <div>
                                    <div class="text-sm text-gray-500">Statut</div>
                                    <div class="text-gray-700 font-medium">
                                        <?php echo $user['user_type'] == 'driver' ? 'Conducteur' : 'Passager'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Animation GSAP
        document.addEventListener('DOMContentLoaded', function() {
            // Masquer le loader
            const loader = document.getElementById('loader');
            if (loader) {
                setTimeout(() => {
                    loader.classList.add('hidden');
                }, 500);
            }

            // Animation des sections
            gsap.to('.profile-section', {
                duration: 0.6,
                y: 0,
                opacity: 1,
                stagger: 0.1,
                ease: "power2.out",
                delay: 0.5
            });

            // Animation des cartes de statistiques
            gsap.from('.stat-card', {
                duration: 0.8,
                x: -50,
                opacity: 0,
                stagger: 0.15,
                ease: "back.out(1.7)",
                delay: 0.8
            });
        });

        // Gestion des boutons d'action
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelectorAll('button');
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.onclick) return; // Si déjà un onclick défini
                    
                    const text = this.querySelector('span')?.textContent;
                    switch(text) {
                        case 'Paramètres':
                            alert('Page paramètres à implémenter');
                            break;
                        case 'Modifier':
                            alert('Édition du profil à implémenter');
                            break;
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
// Fonction helper pour le formatage du temps
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $time_diff = time() - $time;
    
    if ($time_diff < 60) {
        return 'À l\'instant';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . ' min';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . ' h';
    } else {
        return date('d/m/Y', $time);
    }
}
?>