<?php
session_start();
require_once 'config/db.php';

// Initialisation de la base de données
$database = new Database();
$db = $database->getConnection();

// --- Logique de recherche et de filtrage (Récupération des paramètres) ---

// Récupération des paramètres avec valeurs par défaut
$departure = $_GET['departure'] ?? 'current'; // 'current', 'establishment', 'custom'
$destination = $_GET['destination'] ?? 'custom'; // 'establishment', 'custom'
$custom_dep_addr = $_GET['custom_dep_addr'] ?? '';
$custom_dest_addr = $_GET['custom_dest_addr'] ?? 'Lyon, Part-Dieu'; // Valeur par défaut du frontend
$date = $_GET['date'] ?? date('Y-m-d'); // Aujourd'hui par défaut
$time_of_day = $_GET['time_of_day'] ?? ''; // 'morning', 'afternoon', 'evening', 'all'
$seats = $_GET['seats'] ?? 1;
$max_price = $_GET['max_price'] ?? 50;

// Placeholder d'établissement
$demo_establishment_id_departure = 1; 
$demo_establishment_id_destination = 2; 

// Construction de la requête SQL
$query = "SELECT t.*, 
                 u.first_name, u.last_name, u.rating, u.total_ratings, u.avatar_url,
                 COUNT(b.id) as booked_seats_count,
                 e1.name as departure_establishment_name,
                 e2.name as destination_establishment_name
          FROM trips t
          JOIN users u ON t.driver_id = u.id
          LEFT JOIN bookings b ON t.id = b.trip_id AND b.status IN ('pending', 'confirmed')
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
];

// Ajout des contraintes de temps
if ($time_of_day == 'morning') {
    $query .= " AND t.departure_time BETWEEN '06:00:00' AND '12:00:00'";
} elseif ($time_of_day == 'afternoon') {
    $query .= " AND t.departure_time BETWEEN '12:00:01' AND '18:00:00'";
} elseif ($time_of_day == 'evening') {
    $query .= " AND t.departure_time BETWEEN '18:00:01' AND '23:59:59'";
}

// Ajout des contraintes de lieu de départ
if ($departure == 'establishment' && $demo_establishment_id_departure) {
    $query .= " AND t.departure_establishment_id = :dep_est_id";
    $params[':dep_est_id'] = $demo_establishment_id_departure;
} elseif ($departure == 'custom' && $custom_dep_addr) {
    $query .= " AND (t.departure_address LIKE :custom_dep_addr OR e1.name LIKE :custom_dep_addr)";
    $params[':custom_dep_addr'] = '%' . $custom_dep_addr . '%';
}

// Ajout des contraintes de lieu de destination
if ($destination == 'establishment' && $demo_establishment_id_destination) {
    $query .= " AND t.destination_establishment_id = :dest_est_id";
    $params[':dest_est_id'] = $demo_establishment_id_destination;
} elseif ($destination == 'custom' && $custom_dest_addr) {
    $query .= " AND (t.destination_address LIKE :custom_dest_addr OR e2.name LIKE :custom_dest_addr)";
    $params[':custom_dest_addr'] = '%' . $custom_dest_addr . '%';
}


// Regroupement et tri final
$query .= " GROUP BY t.id
            HAVING t.available_seats > booked_seats_count
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
    // Enlever ou commenter en production
    // echo "Erreur de base de données: " . $e->getMessage();
}

// --- Fonctions utilitaires pour l'affichage ---

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

function get_demo_duration($trip_id) {
    $hours = $trip_id % 3 + 2;
    $minutes = ($trip_id * 10) % 60;
    return sprintf('%sh%s', $hours, str_pad($minutes, 2, '0', STR_PAD_LEFT));
}

function get_demo_passengers($trip_id, $booked_count) {
    // Simule la présence de passagers avec des initiales pour l'UI
    $passengers = [];
    $initials_map = ['MJ', 'TP', 'SL', 'AL', 'CP', 'JD', 'EL', 'ZB'];
    $colors = ['bg-orange-500', 'bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-pink-500', 'bg-red-500'];

    $display_count = min($booked_count, 3); // N'afficher que les 3 premiers
    $remaining_count = $booked_count - $display_count;

    for ($i = 0; $i < $display_count; $i++) {
        $initials = $initials_map[($trip_id + $i) % count($initials_map)];
        $color = $colors[($trip_id + $i) % count($colors)];
        $passengers[] = [
            'initials' => $initials,
            'color' => $color
        ];
    }
    return ['display' => $passengers, 'remaining' => $remaining_count];
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
        
        /* Animation pour le loader */
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
            z-index: 100;
            border-radius: 0;
        }
        
        .map-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 99;
            display: none;
        }
        
        .map-overlay.active {
            display: block;
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

            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Planifiez votre trajet</h2>
                
                <form method="GET" action="trips.php" id="search-form">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Point de départ</label>
                            <div class="space-y-3" id="departure-options">
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $departure == 'current' ? 'active border-orange-500' : ''; ?>" data-type="current" data-role="departure">
                                    <input type="radio" name="departure" value="current" class="hidden" <?php echo $departure == 'current' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-location-arrow text-orange-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Ma position actuelle</div>
                                        <div class="text-xs text-gray-500">Utiliser ma localisation</div>
                                    </div>
                                </label>
                                
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $departure == 'establishment' ? 'active border-orange-500' : ''; ?>" data-type="establishment" data-role="departure">
                                    <input type="radio" name="departure" value="establishment" class="hidden" <?php echo $departure == 'establishment' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-school text-blue-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Mon établissement</div>
                                        <div class="text-xs text-gray-500">Cité des Métiers et Compétences</div>
                                    </div>
                                </label>
                                
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $departure == 'custom' ? 'active border-orange-500' : ''; ?>" data-type="custom" data-role="departure">
                                    <input type="radio" name="departure" value="custom" class="hidden" <?php echo $departure == 'custom' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-map-marker-alt text-green-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Autre adresse</div>
                                        <div class="text-xs text-gray-500">Saisir une adresse manuellement</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Destination</label>
                            <div class="space-y-3" id="destination-options">
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $destination == 'establishment' ? 'active border-orange-500' : ''; ?>" data-type="establishment" data-role="destination">
                                    <input type="radio" name="destination" value="establishment" class="hidden" <?php echo $destination == 'establishment' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-school text-blue-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Établissement</div>
                                        <div class="text-xs text-gray-500">Université de Rabat</div>
                                    </div>
                                </label>
                                
                                <label class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer <?php echo $destination == 'custom' ? 'active border-orange-500' : ''; ?>" data-type="custom" data-role="destination">
                                    <input type="radio" name="destination" value="custom" class="hidden" <?php echo $destination == 'custom' ? 'checked' : ''; ?>>
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-map-marker-alt text-green-500"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium">Autre adresse</div>
                                        <div class="text-xs text-gray-500">Saisir une adresse manuellement</div>
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
                                    <span>Départ: <span id="departureText"><?php echo $departure == 'current' ? 'Position actuelle' : ($departure == 'establishment' ? 'Cité des Métiers et Compétences' : htmlspecialchars($custom_dep_addr)); ?></span></span>
                                </div>
                                <div class="flex items-center mt-1">
                                    <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                                    <span>Arrivée: <span id="destinationText"><?php echo $destination == 'establishment' ? 'Université de Rabat' : htmlspecialchars($custom_dest_addr); ?></span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="customAddressDeparture" class="mt-4 <?php echo $departure != 'custom' ? 'hidden' : ''; ?>">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de départ</label>
                        <div class="flex gap-2">
                            <input type="text" name="custom_dep_addr" placeholder="Saisissez une adresse..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" value="<?php echo htmlspecialchars($custom_dep_addr); ?>">
                            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="customAddressDestination" class="mt-4 <?php echo $destination != 'custom' ? 'hidden' : ''; ?>">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de destination</label>
                        <div class="flex gap-2">
                            <input type="text" name="custom_dest_addr" placeholder="Saisissez une adresse..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" value="<?php echo htmlspecialchars($custom_dest_addr); ?>">
                            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prix maximum</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">0€</span>
                                <input type="range" name="max_price" id="max-price-range" min="0" max="50" value="<?php echo htmlspecialchars($max_price); ?>" oninput="document.getElementById('max-price-text').textContent = 'Jusqu\'à ' + this.value + '€'" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <span class="text-sm text-gray-500">50€</span>
                            </div>
                            <div id="max-price-text" class="text-center mt-1 text-sm text-gray-600">Jusqu'à <?php echo htmlspecialchars($max_price); ?>€</div>
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
                            <?php if (isset($_SESSION['user'])): ?>
                            <a href="create-trip.php" class="inline-flex items-center mt-4 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Proposer un trajet
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <?php foreach ($trips as $trip): 
                                $remaining_seats = get_remaining_seats($trip);
                                $departure_info = get_departure_info($trip['departure_date'], $trip['departure_time']);
                                $duration = get_demo_duration($trip['id']);
                                $passengers_data = get_demo_passengers($trip['id'], $trip['booked_seats_count']);
                                $passengers_count = $trip['booked_seats_count'];
                                $is_full = $remaining_seats <= 0;
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
                                            <?php echo date('d/m/Y', strtotime($trip['departure_date'])); ?>, 
                                            <?php echo substr($trip['departure_time'], 0, 5); ?> (<?php echo $duration; ?>)
                                        </p>
                                    </div>
                                    <div class="mt-4 md:mt-0 text-right">
                                        <span class="text-2xl font-bold text-orange-500"><?php echo number_format($trip['price_per_seat'], 0); ?>€</span>
                                        <span class="text-gray-500 text-sm block">par personne</span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center mb-4">
                                    <div class="flex -space-x-2">
                                        <?php foreach ($passengers_data['display'] as $p): ?>
                                            <div class="w-8 h-8 rounded-full <?php echo $p['color']; ?> flex items-center justify-center text-white text-xs font-bold passenger-avatar"><?php echo $p['initials']; ?></div>
                                        <?php endforeach; ?>
                                        <?php if ($passengers_data['remaining'] > 0): ?>
                                            <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-xs font-bold passenger-avatar">+<?php echo $passengers_data['remaining']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="ml-3 text-sm text-gray-600"><?php echo $passengers_count; ?> passager(s) déjà inscrit(s)</span>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="flex items-center mb-4 md:mb-0">
                                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-gray-600"></i>
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
                                    <?php else: ?>
                                    <a href="create-booking.php?trip_id=<?php echo $trip['id']; ?>&seats=<?php echo $seats; ?>" class="px-6 py-2 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors text-center">
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
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800">Filtres</h2>
                <button type="button" id="closeMobileFilters" class="text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <input type="hidden" name="departure" value="<?php echo htmlspecialchars($departure); ?>">
            <input type="hidden" name="destination" value="<?php echo htmlspecialchars($destination); ?>">
            <input type="hidden" name="custom_dep_addr" value="<?php echo htmlspecialchars($custom_dep_addr); ?>">
            <input type="hidden" name="custom_dest_addr" value="<?php echo htmlspecialchars($custom_dest_addr); ?>">
            
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prix maximum</label>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">0€</span>
                        <input type="range" name="max_price" id="max-price-range-mobile" min="0" max="50" value="<?php echo htmlspecialchars($max_price); ?>" oninput="document.getElementById('max-price-text-mobile').textContent = 'Jusqu\'à ' + this.value + '€'" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <span class="text-sm text-gray-500">50€</span>
                    </div>
                    <div id="max-price-text-mobile" class="text-center mt-1 text-sm text-gray-600">Jusqu'à <?php echo htmlspecialchars($max_price); ?>€</div>
                </div>
                
                <div>
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
        let map, departureMarker, destinationMarker, routeLine;
        let isMapExpanded = false;
        
        // Initialisation de la carte Leaflet
        function initMap() {
            // Centrer sur la France (Simulation)
            const depLat = <?php echo $trip['departure_latitude'] ?? 48.8566; ?>;
            const depLon = <?php echo $trip['departure_longitude'] ?? 2.3522; ?>;
            const destLat = <?php echo $trip['destination_latitude'] ?? 45.7640; ?>;
            const destLon = <?php echo $trip['destination_longitude'] ?? 4.8357; ?>;
            
            map = L.map('map').setView([46.603354, 1.888334], 6);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Ajouter des marqueurs basés sur les données (si disponibles)
            const departure = [depLat, depLon];
            const destination = [destLat, destLon];

            departureMarker = L.marker(departure, {icon: new L.DivIcon({className: 'custom-marker-departure', html: '<div class="w-4 h-4 rounded-full bg-orange-500 border-2 border-white shadow"></div>'})}).addTo(map)
                .bindPopup('Départ: <?php echo htmlspecialchars($custom_dep_addr); ?>');
                
            destinationMarker = L.marker(destination, {icon: new L.DivIcon({className: 'custom-marker-destination', html: '<div class="w-4 h-4 rounded-full bg-green-500 border-2 border-white shadow"></div>'})}).addTo(map)
                .bindPopup('Arrivée: <?php echo htmlspecialchars($custom_dest_addr); ?>');
                
            routeLine = L.polyline([departure, destination], {color: 'orange', weight: 4}).addTo(map);
            
            map.fitBounds(routeLine.getBounds(), {padding: [50, 50]});
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
        
        // Gestion du loader
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.getElementById('loader');
            
            // Initialiser la carte
            initMap();
            
            // Masquer le loader après le chargement de la page
            window.addEventListener('load', function() {
                setTimeout(function() {
                    loader.classList.add('hidden');
                }, 800);
            });
            
            // Fallback au cas où l'événement load ne se déclenche pas
            setTimeout(function() {
                loader.classList.add('hidden');
            }, 3000);

             // Initialiser le texte du prix max au chargement de la page
            const maxPriceRange = document.getElementById('max-price-range');
            if (maxPriceRange) {
                document.getElementById('max-price-text').textContent = 'Jusqu\'à ' + maxPriceRange.value + '€';
            }
            const maxPriceRangeMobile = document.getElementById('max-price-range-mobile');
            if (maxPriceRangeMobile) {
                 document.getElementById('max-price-text-mobile').textContent = 'Jusqu\'à ' + maxPriceRangeMobile.value + '€';
            }
        });
        
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
        
        // Gestion des options de localisation et soumission du formulaire
        document.querySelectorAll('.location-option').forEach(option => {
            option.addEventListener('click', function() {
                const role = this.getAttribute('data-role');
                const type = this.querySelector('input[type="radio"]').value; // Utiliser la valeur du radio
                
                // Gérer l'affichage des champs d'adresse personnalisée
                const customDep = document.getElementById('customAddressDeparture');
                const customDest = document.getElementById('customAddressDestination');
                
                if (role === 'departure') {
                    if (type === 'custom') {
                        customDep.classList.remove('hidden');
                    } else {
                        customDep.classList.add('hidden');
                    }
                } else if (role === 'destination') {
                    if (type === 'custom') {
                        customDest.classList.remove('hidden');
                    } else {
                        customDest.classList.add('hidden');
                    }
                }

                // Soumettre le formulaire de recherche après le clic pour appliquer les changements de lieu
                document.getElementById('search-form').submit();
            });
        });
        
        // Gestion de l'expansion de la carte (statique)
        const expandMapBtn = document.getElementById('expandMap');
        const mapElement = document.getElementById('map');
        
        if (expandMapBtn) {
            expandMapBtn.addEventListener('click', function() {
                if (!isMapExpanded) {
                    mapElement.classList.add('map-expanded');
                    mapElement.style.zIndex = '1000';
                    map.invalidateSize();
                    isMapExpanded = true;
                    expandMapBtn.innerHTML = '<i class="fas fa-compress"></i>';
                } else {
                    mapElement.classList.remove('map-expanded');
                    mapElement.style.zIndex = '1';
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
                // Mettre à jour l'autre champ (Desktop/Mobile)
                const otherInputId = inputId.includes('mobile') ? 'seats-input' : 'seats-input-mobile';
                const otherInput = document.getElementById(otherInputId);
                if(otherInput) {
                    otherInput.value = newValue;
                }
                
                // Mettre à jour le champ caché dans le formulaire de recherche de lieu
                document.querySelector('#search-form input[name="seats"]').value = newValue;
            }
        }

        // Correction du sticky filters sur desktop
        if (window.innerWidth >= 1024) {
            const stickyElement = document.querySelector('.sticky-filters');
            if (stickyElement) {
                const originalOffsetTop = stickyElement.offsetTop;
                
                function handleScroll() {
                    const navbarHeight = 80; // Hauteur de la navbar ajustée
                    if (window.pageYOffset >= originalOffsetTop - navbarHeight) {
                        stickyElement.style.position = 'sticky';
                        stickyElement.style.top = `${navbarHeight + 10}px`; // Ajuster le décalage
                    } else {
                        stickyElement.style.position = 'relative';
                        stickyElement.style.top = 'auto';
                    }
                }
                
                window.addEventListener('scroll', handleScroll);
            }
        }
    </script>
</body>
</html>