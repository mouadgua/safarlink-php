<?php
require_once 'config/init.php';

// --- NETTOYAGE AUTOMATIQUE DES TRAJETS PÉRIMÉS ---
// Annule les trajets programmés dont l'heure de départ est passée de plus d'une heure.
$cleanup_query = "UPDATE trips
                  SET status = 'cancelled'
                  WHERE status = 'scheduled'
                  AND CONCAT(departure_date, ' ', departure_time) < (NOW() - INTERVAL 1 HOUR)";
$cleanup_stmt = $db->prepare($cleanup_query);
$cleanup_stmt->execute();
// --- FIN DU NETTOYAGE ---


// Récupérer l'ID de l'utilisateur connecté pour la vérification
$current_user_id = $_SESSION['user']['id'] ?? null;

// --- Logique de recherche et de filtrage (Récupération des paramètres) ---

// Récupération des paramètres avec valeurs par défaut
$departure = $_GET['departure'] ?? 'custom'; 
$destination = $_GET['destination'] ?? 'custom'; 
$custom_dep_addr = $_GET['custom_dep_addr'] ?? ''; // On ne filtre plus par adresse par défaut
$custom_dest_addr = $_GET['custom_dest_addr'] ?? ''; // On ne filtre plus par adresse par défaut
$date = $_GET['date'] ?? date('Y-m-d'); 
$time_of_day = $_GET['time_of_day'] ?? ''; 
$seats = $_GET['seats'] ?? 1;
$max_price = $_GET['max_price'] ?? 50; 

// Coordonnées GPS utilisées pour la carte et la recherche
$dep_lat = $_GET['dep_lat'] ?? 33.573110; 
$dep_lon = $_GET['dep_lon'] ?? -7.589843;
$dest_lat = $_GET['dest_lat'] ?? 34.020882; 
$dest_lon = $_GET['dest_lon'] ?? -6.841650;

// Estimation de temps de trajet (Initialisée dynamiquement par JS/Routing)
$estimated_duration = "Calcul en cours...";

// Placeholder d'établissement
$demo_establishment_id_departure = 1; 
$demo_establishment_id_destination = 2; 

// Récupérer les détails des établissements pour les coordonnées par défaut des boutons
$est_details = [];
$est_query = "SELECT id, name, latitude, longitude FROM establishments WHERE id IN (1, 2)";
$est_stmt = $db->prepare($est_query);
$est_stmt->execute();
while ($row = $est_stmt->fetch(PDO::FETCH_ASSOC)) {
    $est_details[$row['id']] = $row;
}

$est_dep_coords = $est_details[1] ?? ['latitude' => 33.573110, 'longitude' => -7.589843, 'name' => 'Cité des Métiers et Compétences'];
$est_dest_coords = $est_details[2] ?? ['latitude' => 34.020882, 'longitude' => -6.841650, 'name' => 'Université Mohammed VI'];

// Construction de la requête SQL
$query = "SELECT t.*, 
                 u.first_name, u.last_name, u.rating, u.total_ratings, u.avatar_url,
                 (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status IN ('pending', 'confirmed')) as booked_seats_count,
                 e1.name as departure_establishment_name,
                 e2.name as destination_establishment_name,
                 b_user.id as user_booking_id
          FROM trips t
          JOIN users u ON t.driver_id = u.id
          LEFT JOIN bookings b_user ON t.id = b_user.trip_id AND b_user.passenger_id = :current_user_id
          LEFT JOIN establishments e1 ON t.departure_establishment_id = e1.id
          LEFT JOIN establishments e2 ON t.destination_establishment_id = e2.id
          WHERE t.status = 'scheduled'
          AND t.departure_date >= :departure_date
          AND t.available_seats >= :min_seats
          AND t.price_per_seat <= :max_price_per_seat
          ";

// Paramètres de la requête
$params = [
    ':departure_date' => $date,
    ':min_seats' => $seats,
    ':max_price_per_seat' => $max_price,
    ':current_user_id' => $current_user_id
];

// Ajout des contraintes de temps
if ($time_of_day == 'morning') {
    $query .= " AND t.departure_time BETWEEN '06:00:00' AND '12:00:00'";
} elseif ($time_of_day == 'afternoon') {
    $query .= " AND t.departure_time BETWEEN '12:00:01' AND '18:00:00'";
} elseif ($time_of_day == 'evening') {
    $query .= " AND t.departure_time BETWEEN '18:00:01' AND '23:59:59'";
}

// Ajout des contraintes de lieu
if ($departure == 'establishment' && $demo_establishment_id_departure) {
    $query .= " AND t.departure_establishment_id = :dep_est_id";
    $params[':dep_est_id'] = $demo_establishment_id_departure;
} elseif ($departure == 'custom' && !empty($custom_dep_addr)) { // On ajoute la condition seulement si l'adresse n'est pas vide
    $query .= " AND (t.departure_address LIKE :custom_dep_addr OR e1.name LIKE :custom_dep_addr)";
    $params[':custom_dep_addr'] = '%' . $custom_dep_addr . '%';
}

if ($destination == 'establishment' && $demo_establishment_id_destination) {
    $query .= " AND t.destination_establishment_id = :dest_est_id";
    $params[':dest_est_id'] = $demo_establishment_id_destination;
} elseif ($destination == 'custom' && !empty($custom_dest_addr)) { // On ajoute la condition seulement si l'adresse n'est pas vide
    $query .= " AND (t.destination_address LIKE :custom_dest_addr OR e2.name LIKE :custom_dest_addr)";
    $params[':custom_dest_addr'] = '%' . $custom_dest_addr . '%';
}

// Regroupement et tri final
$query .= " GROUP BY t.id
            HAVING (t.available_seats - (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status IN ('pending', 'confirmed'))) >= 1
            ORDER BY t.departure_date ASC, t.departure_time ASC";

// Exécution de la requête
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_trips = count($trips);
} catch (PDOException $e) {
    $trips = [];
    $total_trips = 0;
    error_log("Erreur lors de la récupération des trajets: " . $e->getMessage());
}

// Récupérer les participants pour chaque trajet affiché
$trip_ids = array_column($trips, 'id');
$participants = [];
if (!empty($trip_ids)) {
    $participants_query = "SELECT b.trip_id, u.first_name, u.last_name, u.avatar_url FROM bookings b JOIN users u ON b.passenger_id = u.id WHERE b.trip_id IN (" . implode(',', $trip_ids) . ") AND b.status IN ('pending', 'confirmed')";
    $participants_stmt = $db->query($participants_query);
    while ($p = $participants_stmt->fetch(PDO::FETCH_ASSOC)) {
        $participants[$p['trip_id']][] = $p;
    }
}
// --- Fonctions utilitaires ---

function get_remaining_seats($trip) {
    return $trip['available_seats'] - $trip['booked_seats_count'];
}

function get_departure_info($departure_date, $departure_time) {
    $datetime = new DateTime($departure_date . ' ' . $departure_time);
    $now = new DateTime();
    $diff = $now->diff($datetime);
    
    if ($diff->invert) {
        return ['class' => 'bg-red-100 text-red-800', 'text' => 'Passé'];
    } elseif ($diff->days == 0) {
        $hours = $diff->h;
        $minutes = $diff->i;
        if ($hours < 3 && $hours >= 0) {
            return ['class' => 'bg-green-100 text-green-800', 'text' => "Départ dans {$hours}h{$minutes}"];
        } else {
            return ['class' => 'bg-green-100 text-green-800', 'text' => 'Aujourd\'hui'];
        }
    } elseif ($diff->days == 1) {
        return ['class' => 'bg-yellow-100 text-yellow-800', 'text' => 'Départ demain'];
    } else {
        return ['class' => 'bg-blue-100 text-blue-800', 'text' => $datetime->format('d/m')];
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trajets - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

    <style>
        :root {
            --primary: #ffffff;
            --secondary: #ffa215;
            --accent: #000000;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .sticky-filters {
            transition: all 0.3s ease;
            height: fit-content;
        }
        
        .passenger-avatar {
            transition: transform 0.3s ease;
        }
        
        .passenger-avatar:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        .trip-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Styles pour le loader */
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, #f8f9fa 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.8s ease-out, visibility 0.8s ease-out;
        }
        
        .loader-container.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .loader {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 162, 21, 0.2);
            border-top: 4px solid var(--secondary);
            border-radius: 50%;
            animation: spin 1.2s cubic-bezier(0.5, 0.1, 0.5, 0.9) infinite;
            margin-bottom: 20px;
        }
        
        .loader-logo {
            font-weight: 700;
            font-size: 2rem;
            color: var(--accent);
            letter-spacing: -0.5px;
        }
        
        .loader-logo span {
            color: var(--secondary);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Styles pour la carte */
        #map {
            height: 300px;
            border-radius: 12px;
            z-index: 1;
        }
        
        .location-option {
            transition: all 0.3s ease;
        }
        
        .location-option:hover {
            background-color: #fef3e2;
        }
        
        .location-option.active {
            background-color: #fff7e6;
            border-color: #ffa215;
        }
        
        .map-expanded {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000; 
            border-radius: 0;
        }
        
        /* Styles de Leaflet Geocoder/Routing pour l'intégration dans le formulaire */
        .leaflet-control-geocoder {
            box-shadow: none;
            border: none;
            min-width: 100%;
            padding: 0;
        }
        .leaflet-control-geocoder .leaflet-control-geocoder-form input {
            padding: 0.5rem 1rem !important;
            border-radius: 0.5rem !important;
            border: 1px solid #d1d5db !important;
            width: 100% !important;
            box-sizing: border-box;
        }
        .leaflet-control-geocoder-form {
            display: block !important;
        }
        .leaflet-control-geocoder-alternatives {
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        
        /* Cacher les éléments de L.Routing.Control non nécessaires */
        .leaflet-routing-container {
            display: none;
        }
        .leaflet-routing-geocoders {
            display: none !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/loader.php'; ?>
    <?php include 'components/navbar.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4">
            <div class="mb-8 text-center">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Trouvez votre trajet idéal</h1>
                <p class="text-gray-600 max-w-2xl mx-auto">Découvrez des trajets partagés avec des conducteurs vérifiés et une communauté de confiance.</p>
            </div>

            <!-- Boutons d'action principaux -->
            <div id="show-search-button-container" class="flex justify-center items-center gap-4 mb-6">
                <button id="show-search-btn" class="px-6 py-3 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition-all duration-300 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-search mr-2"></i>Rechercher un trajet
                </button>

                <?php if (isset($_SESSION['user']) && $_SESSION['user']['user_type'] == 'driver'): ?>
                <a href="propose-trip.php" class="px-6 py-3 bg-white border border-orange-500 text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition-all duration-300 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-plus-circle mr-2"></i>Proposer un trajet
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

            <div id="search-container" class="bg-white rounded-xl shadow-md p-6 mb-6 hidden">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Planifiez votre trajet</h2>
                    <button id="hide-search-btn" class="text-sm text-gray-600 hover:text-red-500 transition-colors">
                        <i class="fas fa-times mr-1"></i>Masquer la recherche
                    </button>
                </div>
                
                <form method="GET" action="trips.php" id="search-form">
                    <input type="hidden" name="dep_lat" id="dep_lat" value="<?php echo htmlspecialchars($dep_lat); ?>">
                    <input type="hidden" name="dep_lon" id="dep_lon" value="<?php echo htmlspecialchars($dep_lon); ?>">
                    <input type="hidden" name="dest_lat" id="dest_lat" value="<?php echo htmlspecialchars($dest_lat); ?>">
                    <input type="hidden" name="dest_lon" id="dest_lon" value="<?php echo htmlspecialchars($dest_lon); ?>">
                    <input type="hidden" name="custom_dep_addr" id="custom_dep_addr_hidden" value="<?php echo htmlspecialchars($custom_dep_addr); ?>">
                    <input type="hidden" name="custom_dest_addr" id="custom_dest_addr_hidden" value="<?php echo htmlspecialchars($custom_dest_addr); ?>">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Point de départ</label>
                            <div class="space-y-3" id="departure-options">
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $departure == 'current' ? 'active border-orange-500' : ''; ?>" data-type="current" data-role="departure" data-lat="" data-lon="">
                                    <input type="radio" name="departure" value="current" class="hidden" <?php echo $departure == 'current' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-location-arrow text-orange-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Ma position actuelle</div>
                                        <div class="text-xs text-gray-500" id="current-dep-text">Utiliser ma localisation (si disponible)</div>
                                    </div>
                                </label>
                                
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $departure == 'establishment' ? 'active border-orange-500' : ''; ?>" data-type="establishment" data-role="departure" data-lat="<?php echo $est_dep_coords['latitude']; ?>" data-lon="<?php echo $est_dep_coords['longitude']; ?>" data-name="<?php echo htmlspecialchars($est_dep_coords['name']); ?>">
                                    <input type="radio" name="departure" value="establishment" class="hidden" <?php echo $departure == 'establishment' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-school text-blue-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Mon établissement</div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($est_dep_coords['name']); ?></div>
                                    </div>
                                </label>
                                
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $departure == 'custom' ? 'active border-orange-500' : ''; ?>" data-type="custom" data-role="departure" data-lat="<?php echo htmlspecialchars($dep_lat); ?>" data-lon="<?php echo htmlspecialchars($dep_lon); ?>" data-name="<?php echo htmlspecialchars($custom_dep_addr); ?>">
                                    <input type="radio" name="departure" value="custom" class="hidden" <?php echo $departure == 'custom' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-map-marker-alt text-green-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Autre adresse</div>
                                        <div class="text-xs text-gray-500">Saisir ou **cliquer sur la carte**</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Destination</label>
                            <div class="space-y-3" id="destination-options">
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $destination == 'establishment' ? 'active border-orange-500' : ''; ?>" data-type="establishment" data-role="destination" data-lat="<?php echo $est_dest_coords['latitude']; ?>" data-lon="<?php echo $est_dest_coords['longitude']; ?>" data-name="<?php echo htmlspecialchars($est_dest_coords['name']); ?>">
                                    <input type="radio" name="destination" value="establishment" class="hidden" <?php echo $destination == 'establishment' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-school text-blue-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Établissement</div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($est_dest_coords['name']); ?></div>
                                    </div>
                                </label>
                                
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $destination == 'custom' ? 'active border-orange-500' : ''; ?>" data-type="custom" data-role="destination" data-lat="<?php echo htmlspecialchars($dest_lat); ?>" data-lon="<?php echo htmlspecialchars($dest_lon); ?>" data-name="<?php echo htmlspecialchars($custom_dest_addr); ?>">
                                    <input type="radio" name="destination" value="custom" class="hidden" <?php echo $destination == 'custom' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-map-marker-alt text-green-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Autre adresse</div>
                                        <div class="text-xs text-gray-500">Saisir ou **cliquer sur la carte**</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-medium text-gray-700">Visualisation du trajet</label>
                                <button type="button" id="expandMap" class="text-gray-500 hover:text-orange-500 transition-colors">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                            <div id="map" class="w-full"></div>
                            <div class="absolute bottom-3 left-3 bg-white px-3 py-2 rounded-lg shadow-md text-sm">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full bg-orange-500 mr-2"></div>
                                    <span>Départ: <span id="departureText"><?php echo $departure == 'current' ? 'Position actuelle' : ($departure == 'establishment' ? $est_dep_coords['name'] : htmlspecialchars($custom_dep_addr)); ?></span></span>
                                </div>
                                <div class="flex items-center mt-1">
                                    <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                                    <span>Arrivée: <span id="destinationText"><?php echo $destination == 'establishment' ? $est_dest_coords['name'] : htmlspecialchars($custom_dest_addr); ?></span></span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Durée estimée: <span id="durationText"><?php echo $estimated_duration; ?></span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="customAddressDeparture" class="mt-4 <?php echo $departure != 'custom' ? 'hidden' : ''; ?>">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de départ</label>
                        <div class="flex gap-2">
                            <div class="flex-1" id="geocoder-dep-container">
                                </div>
                            <button type="button" id="search-dep-btn" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <p id="dep-coords" class="mt-1 text-xs text-gray-500">Coordonnées: Lat <?php echo number_format($dep_lat, 6); ?>, Lon <?php echo number_format($dep_lon, 6); ?></p>
                    </div>
                    
                    <div id="customAddressDestination" class="mt-4 <?php echo $destination != 'custom' ? 'hidden' : ''; ?>">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de destination</label>
                        <div class="flex gap-2">
                            <div class="flex-1" id="geocoder-dest-container">
                                </div>
                            <button type="button" id="search-dest-btn" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <p id="dest-coords" class="mt-1 text-xs text-gray-500">Coordonnées: Lat <?php echo number_format($dest_lat, 6); ?>, Lon <?php echo number_format($dest_lon, 6); ?></p>
                    </div>

                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                    <input type="hidden" name="time_of_day" value="<?php echo htmlspecialchars($time_of_day); ?>">
                    <input type="hidden" name="seats" value="<?php echo htmlspecialchars($seats); ?>">
                    <input type="hidden" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>">
                </form>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                <div class="hidden lg:block w-80 flex-shrink-0">
                    <form method="GET" action="trips.php" class="sticky-filters top-24 bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Filtres</h2>
                        
                        <input type="hidden" name="departure" value="<?php echo htmlspecialchars($departure); ?>">
                        <input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
                        <input type="hidden" name="custom_dep_addr" value="<?php echo htmlspecialchars($custom_dep_addr); ?>">
                        <input type="hidden" name="custom_dest_addr" value="<?php echo htmlspecialchars($custom_dest_addr); ?>">
                        <input type="hidden" name="dep_lat" value="<?php echo htmlspecialchars($dep_lat); ?>">
                        <input type="hidden" name="dep_lon" value="<?php echo htmlspecialchars($dep_lon); ?>">
                        <input type="hidden" name="dest_lat" value="<?php echo htmlspecialchars($dest_lat); ?>">
                        <input type="hidden" name="dest_lon" value="<?php echo htmlspecialchars($dest_lon); ?>">

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                            <input type="date" name="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" value="<?php echo htmlspecialchars($date); ?>">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Heure</label>
                            <select name="time_of_day" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="" <?php echo $time_of_day == '' ? 'selected' : ''; ?>>Toute la journée</option>
                                <option value="morning" <?php echo $time_of_day == 'morning' ? 'selected' : ''; ?>>Matin (6h-12h)</option>
                                <option value="afternoon" <?php echo $time_of_day == 'afternoon' ? 'selected' : ''; ?>>Après-midi (12h-18h)</option>
                                <option value="evening" <?php echo $time_of_day == 'evening' ? 'selected' : ''; ?>>Soir (18h-00h)</option>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de places</label>
                            <div class="flex items-center space-x-2">
                                <button type="button" onclick="updateSeats(-1, 'seats-input', 1, 8)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100">-</button>
                                <input type="number" name="seats" id="seats-input" min="1" max="8" value="<?php echo htmlspecialchars($seats); ?>" readonly class="w-8 text-center border-none focus:ring-0 p-0 m-0">
                                <button type="button" onclick="updateSeats(1, 'seats-input', 1, 8)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100">+</button>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prix maximum (Dhs)</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">0 Dhs</span>
                                <input type="range" name="max_price" id="max-price-range" min="0" max="50" value="<?php echo htmlspecialchars($max_price); ?>" oninput="document.getElementById('max-price-text').textContent = 'Jusqu\'à ' + this.value + ' Dhs'" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <span class="text-sm text-gray-500">50 Dhs</span>
                            </div>
                            <div id="max-price-text" class="text-center mt-1 text-sm text-gray-600">Jusqu'à <?php echo htmlspecialchars($max_price); ?> Dhs</div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Options</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded text-orange-500 focus:ring-orange-500">
                                    <span class="ml-2 text-sm text-gray-700">Animaux acceptés</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded text-orange-500 focus:ring-orange-500">
                                    <span class="ml-2 text-sm text-gray-700">Fumeur accepté</span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full py-3 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors">
                            Appliquer les filtres
                        </button>
                    </form>
                </div>

                <div class="flex-1">
                    <div class="bg-white rounded-xl shadow-md p-4 mb-6 flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <span class="font-medium"><?php echo $total_trips; ?> trajet(s)</span> trouvé(s) pour votre recherche
                        </div>
                        <button id="mobileFilterToggle" type="button" class="lg:hidden ml-4 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                            <i class="fas fa-filter mr-2"></i> Filtres
                        </button>
                    </div>

                    <div class="space-y-6">
                        <?php if (empty($trips)): ?>
                        <div class="bg-white rounded-xl shadow-md p-8 text-center">
                            <i class="fas fa-road text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-medium text-gray-700 mb-2">Aucun trajet trouvé</h3>
                            <p class="text-gray-500">Modifiez vos critères de recherche pour trouver plus d'options.</p>
                            <?php if (isset($_SESSION['user']) && $_SESSION['user']['user_type'] == 'driver'): ?>
                            <a href="propose-trip.php" class="inline-flex items-center mt-4 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Proposer un trajet
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <?php foreach ($trips as $trip): 
                                $remaining_seats = get_remaining_seats($trip);
                                $departure_info = get_departure_info($trip['departure_date'], $trip['departure_time']);
                                $trip_participants = $participants[$trip['id']] ?? [];
                                $passengers_count = $trip['booked_seats_count'];
                                $is_full = $remaining_seats <= 0;
                                $is_driver_of_trip = $current_user_id && $current_user_id == $trip['driver_id'];
                            ?>
                        <div class="trip-card bg-white rounded-xl shadow-md overflow-hidden <?php echo $is_full ? 'opacity-70' : ''; ?>">
                            <div class="p-6">
                                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <span class="text-xs font-medium px-2.5 py-0.5 rounded <?php echo $departure_info['class']; ?>"><?php echo $departure_info['text']; ?></span>
                                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                                                <?php echo $remaining_seats; ?> place(s) disponible(s)
                                            </span>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-800">
                                            <?php echo htmlspecialchars($trip['departure_establishment_name'] ?? $trip['departure_address']); ?> → 
                                            <?php echo htmlspecialchars($trip['destination_establishment_name'] ?? $trip['destination_address']); ?>
                                        </h3>
                                        <p class="text-gray-600">
                                            <?php echo date('d/m/Y', strtotime($trip['departure_date'])); ?>, <?php echo substr($trip['departure_time'], 0, 5); ?>
                                        </p>
                                    </div>
                                    <div class="mt-4 md:mt-0 text-right">
                                        <span class="text-2xl font-bold text-orange-500"><?php echo number_format($trip['price_per_seat'], 0); ?> Dhs</span>
                                        <span class="text-gray-500 text-sm block">par personne</span>
                                    </div>
                                </div>
                                
                                <?php if ($passengers_count > 0): ?>
                                <div class="flex items-center mb-4">
                                    <div class="flex -space-x-2">
                                        <?php foreach (array_slice($trip_participants, 0, 3) as $p): ?>
                                            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 text-xs font-bold passenger-avatar border-2 border-white">
                                                <?php if (!empty($p['avatar_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($p['avatar_url']); ?>" class="w-full h-full rounded-full object-cover">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($passengers_count > 3): ?>
                                            <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-xs font-bold passenger-avatar border-2 border-white">+<?php echo $passengers_count - 3; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="ml-3 text-sm text-gray-600"><?php echo $passengers_count; ?> passager(s) déjà inscrit(s)</span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="flex items-center mb-4 md:mb-0">
                                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3 text-gray-600 font-bold">
                                            <?php if (!empty($trip['avatar_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($trip['avatar_url']); ?>" class="w-full h-full rounded-full object-cover">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($trip['first_name'], 0, 1) . substr($trip['last_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($trip['first_name'] . ' ' . $trip['last_name']); ?></h4>
                                            <div class="flex items-center">
                                                <i class="fas fa-star text-yellow-400 text-sm mr-1"></i>
                                                <span class="text-sm text-gray-600"><?php echo number_format($trip['rating'], 1); ?> (<?php echo $trip['total_ratings']; ?> avis)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($is_full): ?>
                                    <button class="px-6 py-2 bg-gray-500 text-white font-medium rounded-lg opacity-50 cursor-not-allowed" disabled>
                                        Complet
                                    </button>
                                    <?php elseif ($is_driver_of_trip): ?>
                                    <a href="my-bookings.php?tab=upcoming" class="px-6 py-2 bg-blue-500 text-white font-medium rounded-lg hover:bg-blue-600 transition-colors text-center">
                                        Gérer mon trajet
                                    </a>
                                    <?php elseif ($trip['user_booking_id']): ?>
                                    <a href="booking-details.php?id=<?php echo $trip['user_booking_id']; ?>" class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors text-center">
                                        Voir la réservation
                                    </a>
                                    <?php else: ?>
                                    <a href="payment.php?trip_id=<?php echo $trip['id']; ?>&seats=<?php echo $seats; ?>" class="px-6 py-2 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors text-center">
                                        Réserver
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
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
                </div>
            </div>
        </div>
    </main>

    <div id="mobileFilters" class="fixed inset-0 bg-white z-50 p-6 overflow-y-auto hidden">
        <form method="GET" action="trips.php">
            <input type="hidden" name="search" value="1"> <!-- Marqueur de recherche -->
            <input type="hidden" name="departure" value="<?php echo htmlspecialchars($departure); ?>">
            <input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
            <input type="hidden" name="custom_dep_addr" value="<?php echo htmlspecialchars($custom_dep_addr); ?>">
            <input type="hidden" name="custom_dest_addr" value="<?php echo htmlspecialchars($custom_dest_addr); ?>">
            <input type="hidden" name="dep_lat" value="<?php echo htmlspecialchars($dep_lat); ?>">
            <input type="hidden" name="dep_lon" value="<?php echo htmlspecialchars($dep_lon); ?>">
            <input type="hidden" name="dest_lat" value="<?php echo htmlspecialchars($dest_lat); ?>">
            <input type="hidden" name="dest_lon" value="<?php echo htmlspecialchars($dest_lon); ?>">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Filtres</h2>
                <button type="button" id="closeMobileFilters" class="text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" value="<?php echo htmlspecialchars($date); ?>">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Heure</label>
                    <select name="time_of_day" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <option value="" <?php echo $time_of_day == '' ? 'selected' : ''; ?>>Toute la journée</option>
                        <option value="morning" <?php echo $time_of_day == 'morning' ? 'selected' : ''; ?>>Matin (6h-12h)</option>
                        <option value="afternoon" <?php echo $time_of_day == 'afternoon' ? 'selected' : ''; ?>>Après-midi (12h-18h)</option>
                        <option value="evening" <?php echo $time_of_day == 'evening' ? 'selected' : ''; ?>>Soir (18h-00h)</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de places</label>
                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="updateSeats(-1, 'seats-input-mobile', 1, 8)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center">-</button>
                        <input type="number" name="seats" id="seats-input-mobile" min="1" max="8" value="<?php echo htmlspecialchars($seats); ?>" readonly class="w-8 text-center border-none focus:ring-0 p-0 m-0">
                        <button type="button" onclick="updateSeats(1, 'seats-input-mobile', 1, 8)" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center">+</button>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix maximum (Dhs)</label>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">0 Dhs</span>
                        <input type="range" name="max_price" id="max-price-range-mobile" min="0" max="50" value="<?php echo htmlspecialchars($max_price); ?>" oninput="document.getElementById('max-price-text-mobile').textContent = 'Jusqu\'à ' + this.value + ' Dhs'" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <span class="text-sm text-gray-500">50 Dhs</span>
                    </div>
                    <div id="max-price-text-mobile" class="text-center mt-1 text-sm text-gray-600">Jusqu'à <?php echo htmlspecialchars($max_price); ?> Dhs</div>
                </div>
                
                <div class="pt-4 flex space-x-3">
                    <button type="reset" class="flex-1 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                        Réinitialiser
                    </button>
                    <button type="submit" class="flex-1 py-3 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors">
                        Appliquer
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Initialisation de GSAP
        gsap.registerPlugin(ScrollTrigger);
        
        // Variables globales pour la carte
        let map, departureMarker, destinationMarker, routingControl;
        let isMapExpanded = false;
        
        // Coordonnées initiales
        let depLat = parseFloat(document.getElementById('dep_lat').value);
        let depLon = parseFloat(document.getElementById('dep_lon').value);
        let destLat = parseFloat(document.getElementById('dest_lat').value);
        let destLon = parseFloat(document.getElementById('dest_lon').value);
        
        const depText = document.getElementById('departureText');
        const destText = document.getElementById('destinationText');
        const durationText = document.getElementById('durationText');
        
        // Initialisation des Geocoders pour les champs d'adresse
        let geocoderDep, geocoderDest;
        
        // Mise à jour de la durée du trajet
        function updateDurationDisplay(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            durationText.textContent = `${h}h ${m}m`;
        }
        
        // Custom marker icon pour le départ et l'arrivée
        const depIcon = L.divIcon({className: 'custom-marker-departure', html: '<div class="w-4 h-4 rounded-full bg-orange-500 border-2 border-white shadow"></div>', iconSize: [16, 16]});
        const destIcon = L.divIcon({className: 'custom-marker-destination', html: '<div class="w-4 h-4 rounded-full bg-green-500 border-2 border-white shadow"></div>', iconSize: [16, 16]});

        // Initialisation de la carte Leaflet
        function initMap() {
            map = L.map('map').setView([33.589886, -7.603869], 10); 
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Initialisation du Routing Control
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(depLat, depLon),
                    L.latLng(destLat, destLon)
                ],
                createMarker: function(i, wp, n) {
                    return L.marker(wp.latLng, {
                        draggable: true,
                        icon: i === 0 ? depIcon : destIcon
                    });
                },
                routeWhileDragging: false,
                show: false,
                addWaypoints: false,
                fitSelectedRoutes: true,
                collapsible: true,
                lineOptions: {styles: [{color: '#ffa215', weight: 6, opacity: 0.8}]},
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1'
                })
            }).addTo(map);

            // Gérer les événements de routage
            routingControl.on('routesfound', function(e) {
                const route = e.routes[0];
                const durationSeconds = route.summary.totalTime;
                updateDurationDisplay(durationSeconds);
                if (!isMapExpanded) {
                    map.fitBounds(e.routes[0].coordinates, {padding: [50, 50]});
                }
            });

            // Gérer les événements de glissement sur les marqueurs
            routingControl.on('markercreate', function(e) {
                if (e.waypointIndex === 0) {
                    departureMarker = e.marker;
                    departureMarker.on('dragend', updateLocationFromMarker);
                } else if (e.waypointIndex === 1) {
                    destinationMarker = e.marker;
                    destinationMarker.on('dragend', updateLocationFromMarker);
                }
            });

            // Initialisation des Geocoders de suggestions
            geocoderDep = L.Control.geocoder({
                placeholder: 'Saisissez l\'adresse de départ...',
                position: 'topleft',
                collapsed: false,
                defaultMarkGeocode: false,
                geocoder: L.Control.Geocoder.nominatim({
                    params: { countrycodes: 'ma' }
                })
            }).addTo(map);
            
            geocoderDest = L.Control.geocoder({
                placeholder: 'Saisissez l\'adresse de destination...',
                position: 'topleft',
                collapsed: false,
                defaultMarkGeocode: false,
                geocoder: L.Control.Geocoder.nominatim({
                    params: { countrycodes: 'ma' }
                })
            }).addTo(map);
            
            // Attacher les geocoders aux conteneurs du formulaire
            document.getElementById('geocoder-dep-container').appendChild(geocoderDep.getContainer());
            document.getElementById('geocoder-dest-container').appendChild(geocoderDest.getContainer());

            // Synchronisation de l'input Geocoder
            geocoderDep.on('markgeocode', function(e) {
                const result = e.geocode;
                updateLocationInputs('departure', result.name, result.center.lat, result.center.lng, true);
            });
            geocoderDest.on('markgeocode', function(e) {
                const result = e.geocode;
                updateLocationInputs('destination', result.name, result.center.lat, result.center.lng, true);
            });
            
            // Événement de clic sur la carte
            map.on('click', onMapClick);

            // Force l'affichage de la route au chargement
            routingControl.route();
        }

        // Met à jour la ligne de routage
        function updateRouteLine() {
            depLat = parseFloat(document.getElementById('dep_lat').value);
            depLon = parseFloat(document.getElementById('dep_lon').value);
            destLat = parseFloat(document.getElementById('dest_lat').value);
            destLon = parseFloat(document.getElementById('dest_lon').value);

            routingControl.setWaypoints([
                L.latLng(depLat, depLon),
                L.latLng(destLat, destLon)
            ]);

            routingControl.route();
        }
        
        // Fonction pour mettre à jour les coordonnées du marqueur glissé
        function updateLocationFromMarker(e) {
            const latlng = e.target.getLatLng();
            const role = e.target === departureMarker ? 'departure' : 'destination';
            
            L.Control.Geocoder.nominatim().reverse(latlng, map.options.crs.scale(map.getZoom()), function(results) {
                const address = results[0] ? results[0].name : `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
                updateLocationInputs(role, address, latlng.lat, latlng.lng, true);
            });
        }
        
        // Fonction pour gérer le clic sur la carte
        function onMapClick(e) {
            let role = null;
            
            const departureRadio = document.querySelector('input[name="departure"][value="custom"]');
            const destinationRadio = document.querySelector('input[name="destination"][value="custom"]');

            if (departureRadio.checked) {
                role = 'departure';
            } else if (destinationRadio.checked) {
                role = 'destination';
            } else {
                alert("Veuillez sélectionner 'Autre adresse' pour le départ ou la destination avant de cliquer sur la carte.");
                return;
            }

            const latlng = e.latlng;
            
            const targetMarker = role === 'departure' ? departureMarker : destinationMarker;
            if (targetMarker) targetMarker.setLatLng(latlng);
            map.panTo(latlng);
            
            L.Control.Geocoder.nominatim().reverse(latlng, map.options.crs.scale(map.getZoom()), function(results) {
                const address = results[0] ? results[0].name : `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
                updateLocationInputs(role, address, latlng.lat, latlng.lng, true);
            });
        }
        
        // Met à jour les champs de formulaire et le texte d'affichage
        function updateLocationInputs(role, address, lat, lon, submitForm = false) {
            const geocoderControl = role === 'departure' ? geocoderDep : geocoderDest;
            const geocoderInput = geocoderControl.getContainer().querySelector('input');
            const hiddenAddrInputId = role === 'departure' ? 'custom_dep_addr_hidden' : 'custom_dest_addr_hidden';
            const latInputId = role === 'departure' ? 'dep_lat' : 'dest_lat';
            const lonInputId = role === 'departure' ? 'dep_lon' : 'dest_lon';
            const coordsTextId = role === 'departure' ? 'dep-coords' : 'dest-coords';
            const displayTextElement = role === 'departure' ? depText : destText;
            
            document.getElementById(latInputId).value = lat;
            document.getElementById(lonInputId).value = lon;
            document.getElementById(hiddenAddrInputId).value = address;
            document.getElementById(coordsTextId).textContent = `Coordonnées: Lat ${lat.toFixed(6)}, Lon ${lon.toFixed(6)}`;
            
            if (geocoderInput) geocoderInput.value = address;
            displayTextElement.textContent = address.length > 30 ? address.substring(0, 30) + '...' : address;
            
            const customOption = document.querySelector(`.location-option[data-role="${role}"][data-type="custom"]`);
            if (customOption) {
                const customAddressDiv = role === 'departure' ? document.getElementById('customAddressDeparture') : document.getElementById('customAddressDestination');
                customAddressDiv.classList.remove('hidden');
            }

            updateRouteLine();
            
            if (submitForm) {
                document.getElementById('search-form').submit();
            }
        }

        // Animation des cartes de trajet
        gsap.from('.trip-card', {
            duration: 0.6,
            y: 30,
            opacity: 0,
            stagger: 0.1,
            ease: "power2.out",
            scrollTrigger: {
                trigger: '.trip-card',
                start: "top 80%",
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.getElementById('loader');
            
            initMap();
            
            geocoderDep.getContainer().querySelector('input').value = document.getElementById('custom_dep_addr_hidden').value;
            geocoderDest.getContainer().querySelector('input').value = document.getElementById('custom_dest_addr_hidden').value;

            // Logique des boutons de sélection de lieu
            document.querySelectorAll('.location-option').forEach(option => {
                option.addEventListener('click', function(e) {
                    const role = this.getAttribute('data-role');
                    const type = this.querySelector('input[type="radio"]').value;
                    
                    document.querySelectorAll(`.location-option[data-role="${role}"]`).forEach(opt => {
                        opt.classList.remove('active', 'border-orange-500');
                    });
                    this.classList.add('active', 'border-orange-500');

                    const customDep = document.getElementById('customAddressDeparture');
                    const customDest = document.getElementById('customAddressDestination');
                    
                    if (role === 'departure') {
                        customDep.classList.toggle('hidden', type !== 'custom');
                    } else if (role === 'destination') {
                        customDest.classList.toggle('hidden', type !== 'custom');
                    }
                    
                    if (type === 'current') {
                        e.preventDefault();
                        
                        const originalText = this.querySelector('.text-xs').textContent;
                        this.querySelector('.text-xs').textContent = 'Autorisation requise...';
                        
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(function(position) {
                                updateLocationInputs(role, 'Position actuelle', position.coords.latitude, position.coords.longitude, true);
                            }, function(error) {
                                alert("Erreur de géolocalisation: " + (error.message || "Refus ou indisponibilité") + ". Veuillez choisir une adresse personnalisée.");
                                
                                const customRadio = document.querySelector(`input[name="${role}"][value="custom"]`);
                                customRadio.checked = true;
                                
                                const customOption = document.querySelector(`.location-option[data-role="${role}"][data-type="custom"]`);
                                document.querySelectorAll(`.location-option[data-role="${role}"]`).forEach(opt => opt.classList.remove('active', 'border-orange-500'));
                                customOption.classList.add('active', 'border-orange-500');
                                
                                if (role === 'departure') customDep.classList.remove('hidden');
                                if (role === 'destination') customDest.classList.remove('hidden');
                                
                                option.querySelector('.text-xs').textContent = originalText;
                            });
                        } else {
                            alert("Votre navigateur ne supporte pas la géolocalisation.");
                            option.querySelector('.text-xs').textContent = originalText;
                        }
                    } else if (type === 'establishment') {
                        const lat = parseFloat(this.getAttribute('data-lat'));
                        const lon = parseFloat(this.getAttribute('data-lon'));
                        const name = this.getAttribute('data-name');
                        updateLocationInputs(role, name, lat, lon, true);
                    } else if (type === 'custom') {
                        updateRouteLine(); 
                        document.getElementById('search-form').submit();
                    }
                });
            });
            
            // Gestion de la soumission manuelle des adresses personnalisées
            document.getElementById('search-dep-btn').addEventListener('click', function(e) {
                e.preventDefault(); 
                geocodeAddress('departure', geocoderDep);
            });
            document.getElementById('search-dest-btn').addEventListener('click', function(e) {
                e.preventDefault(); 
                geocodeAddress('destination', geocoderDest);
            });

            function geocodeAddress(role, geocoderControl) {
                const address = geocoderControl.getContainer().querySelector('input').value;
                
                geocoderControl.options.geocoder.geocode(address, function(results) {
                    if (results.length > 0) {
                        const result = results[0];
                        updateLocationInputs(role, result.name, result.center.lat, result.center.lng, true);
                    } else {
                        alert("Adresse non trouvée. Veuillez réessayer.");
                    }
                });
            }

            // Gestion du loader
            window.addEventListener('load', function() {
                setTimeout(function() {
                    if(loader) loader.classList.add('hidden');
                }, 800);
            });
            setTimeout(function() {
                if(loader) loader.classList.add('hidden');
            }, 3000);

            // Initialiser les textes du prix max
            const maxPriceRange = document.getElementById('max-price-range');
            if (maxPriceRange) {
                document.getElementById('max-price-text').textContent = 'Jusqu\'à ' + maxPriceRange.value + ' Dhs';
            }
            const maxPriceRangeMobile = document.getElementById('max-price-range-mobile');
            if (maxPriceRangeMobile) {
                 document.getElementById('max-price-text-mobile').textContent = 'Jusqu\'à ' + maxPriceRangeMobile.value + ' Dhs';
            }
        });
        
        // Gestion de l'expansion de la carte
        const expandMapBtn = document.getElementById('expandMap');
        const mapElement = document.getElementById('map');
        
        if (expandMapBtn) {
            expandMapBtn.addEventListener('click', function() {
                if (!isMapExpanded) {
                    mapElement.classList.add('map-expanded');
                    map.invalidateSize();
                    isMapExpanded = true;
                    expandMapBtn.innerHTML = '<i class="fas fa-compress"></i>';
                } else {
                    mapElement.classList.remove('map-expanded');
                    map.invalidateSize();
                    isMapExpanded = false;
                    expandMapBtn.innerHTML = '<i class="fas fa-expand"></i>';
                }
            });
        }
        
        // Fonction pour mettre à jour les sièges
        function updateSeats(change, inputId, min, max) {
            const input = document.getElementById(inputId);
            let currentValue = parseInt(input.value);
            let newValue = currentValue + change;
            
            if (newValue >= min && newValue <= max) {
                input.value = newValue;
                const otherInputId = inputId.includes('mobile') ? 'seats-input' : 'seats-input-mobile';
                const otherInput = document.getElementById(otherInputId);
                if(otherInput) {
                    otherInput.value = newValue;
                }
                document.querySelector('#search-form input[name="seats"]').value = newValue;
            }
        }
        
        // Gestion des filtres mobile
        const mobileFilterToggle = document.getElementById('mobileFilterToggle');
        const closeMobileFilters = document.getElementById('closeMobileFilters');
        const mobileFilters = document.getElementById('mobileFilters');
        
        if (mobileFilterToggle) {
            mobileFilterToggle.addEventListener('click', function() {
                mobileFilters.classList.remove('hidden');
            });
        }
        
        if (closeMobileFilters) {
            closeMobileFilters.addEventListener('click', function() {
                mobileFilters.classList.add('hidden');
            });
        }
    </script>
</body>
</html>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const showSearchBtn = document.getElementById('show-search-btn');
        const hideSearchBtn = document.getElementById('hide-search-btn');
        const searchContainer = document.getElementById('search-container');
        const showSearchButtonContainer = document.getElementById('show-search-button-container');

        // Si une recherche a déjà été effectuée, on affiche le formulaire
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search')) {
            searchContainer.classList.remove('hidden');
            showSearchButtonContainer.classList.add('hidden');
        }

        showSearchBtn.addEventListener('click', function() {
            showSearchButtonContainer.classList.add('hidden');
            searchContainer.classList.remove('hidden');
            gsap.from(searchContainer, { height: 0, opacity: 0, duration: 0.5, ease: 'power2.out' });
        });

        hideSearchBtn.addEventListener('click', function() {
            gsap.to(searchContainer, { height: 0, opacity: 0, duration: 0.5, ease: 'power2.in', onComplete: () => searchContainer.classList.add('hidden') });
            showSearchButtonContainer.classList.remove('hidden');
        });
    });
</script>