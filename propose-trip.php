<?php
require_once 'config/init.php'; // Assure l'initialisation de la session et de la DB

// 1. SÉCURITÉ : Vérifier que l'utilisateur est connecté
requireLogin();

// 2. SÉCURITÉ : Vérifier que l'utilisateur est un conducteur
$user_id = $_SESSION['user']['id'];
$user_type = $_SESSION['user']['user_type'] ?? 'passenger';

if ($user_type !== 'driver') {
    $_SESSION['error_message'] = "Vous devez être enregistré comme Conducteur pour proposer un trajet.";
    header('Location: profile.php');
    exit;
}

// Récupérer tous les établissements pour les listes déroulantes
$establishments_query = "SELECT id, name, address, latitude, longitude FROM establishments ORDER BY name ASC";
$establishments_stmt = $db->query($establishments_query);
$establishments = $establishments_stmt->fetchAll(PDO::FETCH_ASSOC);
$establishments_js = json_encode($establishments); // Pour l'utiliser en JS

// 3. TRAITEMENT DU FORMULAIRE
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $departure_address = trim($_POST['departure_address_text']);
    $destination_address = trim($_POST['destination_address_text']);
    $departure_establishment_id = !empty($_POST['departure_establishment_id']) ? $_POST['departure_establishment_id'] : null;
    $destination_establishment_id = !empty($_POST['destination_establishment_id']) ? $_POST['destination_establishment_id'] : null;
    $departure_latitude = !empty($_POST['departure_latitude']) ? $_POST['departure_latitude'] : null;
    $departure_longitude = !empty($_POST['departure_longitude']) ? $_POST['departure_longitude'] : null;
    $destination_latitude = !empty($_POST['destination_latitude']) ? $_POST['destination_latitude'] : null;
    $destination_longitude = !empty($_POST['destination_longitude']) ? $_POST['destination_longitude'] : null;

    $departure_date = $_POST['departure_date'];
    $departure_time = $_POST['departure_time'];
    $available_seats = (int)($_POST['available_seats']);
    $price_per_seat = (float)($_POST['price_per_seat']);
    $description = trim($_POST['description'] ?? '');
    $trip_rules = isset($_POST['rules']) ? implode(',', $_POST['rules']) : '';
    $possible_detours = trim($_POST['possible_detours'] ?? '');

    // Validation simple des données
    if (empty($departure_address) || empty($destination_address) || empty($departure_date) || empty($departure_time)) {
        $error = "Veuillez remplir tous les champs d'itinéraire et de date.";
    } elseif ($available_seats < 1) {
        $error = "Vous devez proposer au moins une place.";
    } elseif ($price_per_seat <= 0) {
        $error = "Le prix par place doit être supérieur à zéro.";
    } elseif (strtotime($departure_date . ' ' . $departure_time) < time()) {
        $error = "La date de départ ne peut pas être dans le passé.";
    } else {
        // Si tout est valide, on insère dans la base de données
        try {
            $query = "INSERT INTO trips (
                        driver_id, departure_address, destination_address, 
                        departure_establishment_id, destination_establishment_id,
                        departure_latitude, departure_longitude, destination_latitude, destination_longitude,
                        departure_date, departure_time, available_seats, price_per_seat, 
                        description, trip_rules, possible_detours
                      ) VALUES (
                        :driver_id, :dep_addr, :dest_addr, 
                        :dep_est_id, :dest_est_id,
                        :dep_lat, :dep_lon, :dest_lat, :dest_lon,
                        :date, :time, :seats, :price, 
                        :description, :rules, :detours
                      )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':driver_id', $user_id);
            $stmt->bindParam(':dep_addr', $departure_address);
            $stmt->bindParam(':dest_addr', $destination_address);
            $stmt->bindParam(':dep_est_id', $departure_establishment_id, PDO::PARAM_INT);
            $stmt->bindParam(':dest_est_id', $destination_establishment_id, PDO::PARAM_INT);
            $stmt->bindParam(':dep_lat', $departure_latitude);
            $stmt->bindParam(':dep_lon', $departure_longitude);
            $stmt->bindParam(':dest_lat', $destination_latitude);
            $stmt->bindParam(':dest_lon', $destination_longitude);
            $stmt->bindParam(':date', $departure_date);
            $stmt->bindParam(':time', $departure_time);
            $stmt->bindParam(':seats', $available_seats);
            $stmt->bindParam(':price', $price_per_seat);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':rules', $trip_rules);
            $stmt->bindParam(':detours', $possible_detours);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Votre trajet a été créé avec succès !";
                header('Location: my-bookings.php?tab=upcoming'); 
                exit;
            } else {
                $error = "Une erreur est survenue lors de l'enregistrement du trajet.";
            }

        } catch (PDOException $e) {
            // En cas d'erreur de base de données, on l'affiche pour le débogage
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposer un trajet - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet & plugins -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <style>
        .rule-tag {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .rule-tag input:checked + label {
            background-color: #F97316; /* bg-orange-500 */
            color: white;
            border-color: #F97316;
        }
        .rule-tag input:checked + label:hover {
            background-color: #EA580C; /* bg-orange-600 */
        }
        .location-option.active {
            border-color: #F97316;
            background-color: #FFF7ED;
        }
        /* Cacher les contrôles de Leaflet Routing Machine */
        .leaflet-routing-container {
            display: none;
        }
        /* Style pour le geocoder */
        .leaflet-control-geocoder-form input {
            width: 100%;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?> 

    <main class="pt-24 pb-20">
        <div class="container mx-auto px-4 max-w-2xl">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Proposer un nouveau trajet</h1>
                <p class="text-gray-600">Partagez votre route et rentabilisez vos déplacements.</p>
            </div>

            <?php if ($error): ?>
                <div class="p-4 mb-6 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-8">
                <form method="POST" action="propose-trip.php" id="trip-form">
                    <!-- Champs cachés pour les coordonnées et adresses -->
                    <input type="hidden" name="departure_latitude" id="departure_latitude">
                    <input type="hidden" name="departure_longitude" id="departure_longitude">
                    <input type="hidden" name="destination_latitude" id="destination_latitude">
                    <input type="hidden" name="destination_longitude" id="destination_longitude">
                    <input type="hidden" name="departure_address_text" id="departure_address_text">
                    <input type="hidden" name="destination_address_text" id="destination_address_text">
                    
                    <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3">Itinéraire et Carte</h2>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        <!-- Colonne de sélection -->
                        <div class="space-y-6">
                            <!-- Départ -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Point de départ</label>
                                <div class="space-y-2">
                                    <div class="location-option p-3 border rounded-lg cursor-pointer" data-role="departure" data-type="current">
                                        <input type="radio" name="departure_type" value="current" class="hidden"> Ma position actuelle
                                    </div>
                                    <div class="location-option p-3 border rounded-lg cursor-pointer" data-role="departure" data-type="establishment">
                                        <input type="radio" name="departure_type" value="establishment" class="hidden">
                                        <select name="departure_establishment_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                            <option value="">Choisir un établissement de départ</option>
                                            <?php foreach ($establishments as $est): ?>
                                                <option value="<?php echo $est['id']; ?>"><?php echo htmlspecialchars($est['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="location-option p-3 border rounded-lg cursor-pointer active" data-role="departure" data-type="custom">
                                        <input type="radio" name="departure_type" value="custom" class="hidden" checked>
                                        <div id="geocoder-departure"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Arrivée -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Destination</label>
                                <div class="space-y-2">
                                    <div class="location-option p-3 border rounded-lg cursor-pointer" data-role="destination" data-type="establishment">
                                        <input type="radio" name="destination_type" value="establishment" class="hidden">
                                        <select name="destination_establishment_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                            <option value="">Choisir un établissement de destination</option>
                                            <?php foreach ($establishments as $est): ?>
                                                <option value="<?php echo $est['id']; ?>"><?php echo htmlspecialchars($est['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="location-option p-3 border rounded-lg cursor-pointer active" data-role="destination" data-type="custom">
                                        <input type="radio" name="destination_type" value="custom" class="hidden" checked>
                                        <div id="geocoder-destination"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Colonne de la carte -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Carte</label>
                            <div id="map" class="h-96 w-full rounded-lg border z-0"></div>
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Cliquez sur la carte pour définir un point ou faites glisser les marqueurs.
                            </p>
                        </div>
                    </div>


                    <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3">Horaires et Places</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label for="departure_date" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-2"></i>Date de Départ
                            </label>
                            <input type="date" name="departure_date" id="departure_date" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['departure_date'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div>
                            <label for="departure_time" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2"></i>Heure de Départ
                            </label>
                            <input type="time" name="departure_time" id="departure_time" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                value="<?php echo htmlspecialchars($_POST['departure_time'] ?? date('H:i')); ?>">
                        </div>
                        <div>
                            <label for="available_seats" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-chair mr-2"></i>Places disponibles
                            </label>
                            <input type="number" name="available_seats" id="available_seats" required min="1" max="8"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                value="<?php echo htmlspecialchars($_POST['available_seats'] ?? 3); ?>">
                        </div>
                        <div>
                            <label for="price_per_seat" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-coins mr-2"></i>Prix par place (Dhs)
                            </label>
                            <input type="number" name="price_per_seat" id="price_per_seat" required min="1" step="1"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                value="<?php echo htmlspecialchars($_POST['price_per_seat'] ?? 10); ?>">
                        </div>
                    </div>
                    
                    <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3">Règles et Préférences</h2>
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-check-circle mr-2"></i>Règles du trajet
                        </label>
                        <div class="flex flex-wrap gap-3">
                            <?php 
                            $rules = [
                                'no_smoking' => ['icon' => 'fa-smoking-ban', 'text' => 'Non-fumeur'],
                                'pets_allowed' => ['icon' => 'fa-paw', 'text' => 'Animaux acceptés'],
                                'music_allowed' => ['icon' => 'fa-music', 'text' => 'Musique bienvenue'],
                                'quiet_trip' => ['icon' => 'fa-comment-slash', 'text' => 'Trajet calme'],
                                'small_luggage' => ['icon' => 'fa-suitcase-rolling', 'text' => 'Petits bagages']
                            ];
                            foreach ($rules as $key => $rule): ?>
                                <div class="rule-tag">
                                    <input type="checkbox" name="rules[]" id="rule_<?php echo $key; ?>" value="<?php echo $key; ?>" class="hidden"
                                        <?php echo (isset($_POST['rules']) && in_array($key, $_POST['rules'])) ? 'checked' : ''; ?>>
                                    <label for="rule_<?php echo $key; ?>" class="flex items-center px-4 py-2 border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">
                                        <i class="fas <?php echo $rule['icon']; ?> mr-2"></i>
                                        <?php echo $rule['text']; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-8">
                        <label for="possible_detours" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-route mr-2"></i>Détours possibles (Optionnel)
                        </label>
                        <textarea name="possible_detours" id="possible_detours" rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            placeholder="Listez les villes ou points d'intérêt où vous pouvez faire un détour. Ex: Gare de péage de Bouznika, Centre commercial Marjane..."><?php echo htmlspecialchars($_POST['possible_detours'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-8">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>Description (Optionnel)
                        </label>
                        <textarea name="description" id="description" rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            placeholder="Ex: Je peux prendre une petite valise. Musique calme acceptée."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" 
                            class="w-full py-3 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition-colors flex items-center justify-center text-lg">
                        <i class="fas fa-car-side mr-2"></i>Proposer ce trajet
                    </button>
                </form>
            </div>
        </div>
    </main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const establishmentsData = <?php echo $establishments_js; ?>;
    let map, departureMarker, destinationMarker, routingControl;

    // --- INITIALISATION DE LA CARTE ---
    map = L.map('map').setView([33.58, -7.6], 9); // Centré sur Casablanca
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // --- MARQUEURS ---
    departureMarker = L.marker(map.getCenter(), { draggable: true }).addTo(map);
    destinationMarker = L.marker(map.getCenter(), { draggable: true }).addTo(map);

    // --- ROUTAGE ---
    routingControl = L.Routing.control({
        waypoints: [departureMarker.getLatLng(), destinationMarker.getLatLng()],
        routeWhileDragging: true,
        show: false,
        addWaypoints: false,
        lineOptions: { styles: [{ color: '#F97316', opacity: 0.8, weight: 6 }] }
    }).addTo(map);

    // --- GEOCODERS (RECHERCHE D'ADRESSE) ---
    const createGeocoder = (placeholder) => L.Control.geocoder({
        placeholder: placeholder,
        defaultMarkGeocode: false,
        geocoder: L.Control.Geocoder.nominatim({ geocodingQueryParams: { countrycodes: 'ma' } })
    });

    const geocoderDeparture = createGeocoder('Adresse de départ...');
    const geocoderDestination = createGeocoder('Adresse de destination...');
    geocoderDeparture.addTo(map);
    geocoderDestination.addTo(map);
    document.getElementById('geocoder-departure').appendChild(geocoderDeparture.getContainer());
    document.getElementById('geocoder-destination').appendChild(geocoderDestination.getContainer());

    // --- FONCTIONS DE MISE À JOUR ---
    function updateLocation(role, lat, lng, name) {
        const marker = (role === 'departure') ? departureMarker : destinationMarker;
        marker.setLatLng([lat, lng]);
        
        document.getElementById(`${role}_latitude`).value = lat;
        document.getElementById(`${role}_longitude`).value = lng;
        document.getElementById(`${role}_address_text`).value = name;

        if (role === 'departure') {
            geocoderDeparture.getContainer().querySelector('input').value = name;
        } else {
            geocoderDestination.getContainer().querySelector('input').value = name;
        }

        updateRoute();
    }

    function updateRoute() {
        routingControl.setWaypoints([
            departureMarker.getLatLng(),
            destinationMarker.getLatLng()
        ]);
    }

    function handleReverseGeocode(role, latlng) {
        const geocoder = L.Control.Geocoder.nominatim();
        geocoder.reverse(latlng, map.options.crs.scale(map.getZoom()), (results) => {
            const r = results[0];
            if (r) {
                updateLocation(role, latlng.lat, latlng.lng, r.name);
            }
        });
    }

    // --- ÉVÉNEMENTS ---

    // Clic sur la carte
    map.on('click', function(e) {
        const departureIsCustom = document.querySelector('input[name="departure_type"][value="custom"]').checked;
        const destinationIsCustom = document.querySelector('input[name="destination_type"][value="custom"]').checked;

        // Priorité au départ, sinon destination
        const role = departureIsCustom ? 'departure' : (destinationIsCustom ? 'destination' : null);
        if (role) {
            handleReverseGeocode(role, e.latlng);
        }
    });

    // Glisser-déposer des marqueurs
    departureMarker.on('dragend', function(e) {
        document.querySelector('input[name="departure_type"][value="custom"]').click();
        handleReverseGeocode('departure', e.target.getLatLng());
    });
    destinationMarker.on('dragend', function(e) {
        document.querySelector('input[name="destination_type"][value="custom"]').click();
        handleReverseGeocode('destination', e.target.getLatLng());
    });

    // Sélection d'une adresse dans le geocoder
    geocoderDeparture.on('markgeocode', function(e) {
        const { center, name } = e.geocode;
        updateLocation('departure', center.lat, center.lng, name);
    });
    geocoderDestination.on('markgeocode', function(e) {
        const { center, name } = e.geocode;
        updateLocation('destination', center.lat, center.lng, name);
    });

    // Clic sur les options de lieu
    document.querySelectorAll('.location-option').forEach(option => {
        option.addEventListener('click', function() {
            const role = this.dataset.role;
            const type = this.dataset.type;

            // Gérer l'état actif visuel
            document.querySelectorAll(`.location-option[data-role="${role}"]`).forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            this.querySelector('input[type="radio"]').checked = true;

            // Logique par type
            if (type === 'current') {
                navigator.geolocation.getCurrentPosition(pos => {
                    handleReverseGeocode(role, { lat: pos.coords.latitude, lng: pos.coords.longitude });
                }, () => alert('Impossible de récupérer votre position.'));
            } else if (type === 'establishment') {
                const select = this.querySelector('select');
                select.focus(); // Mettre le focus sur le select
            }
        });
    });

    // Changement dans les listes déroulantes d'établissements
    document.querySelector('select[name="departure_establishment_id"]').addEventListener('change', function() {
        const estId = this.value;
        if (estId) {
            const est = establishmentsData.find(e => e.id == estId);
            if (est) {
                updateLocation('departure', est.latitude, est.longitude, est.name);
            }
        }
    });
    document.querySelector('select[name="destination_establishment_id"]').addEventListener('change', function() {
        const estId = this.value;
        if (estId) {
            const est = establishmentsData.find(e => e.id == estId);
            if (est) {
                updateLocation('destination', est.latitude, est.longitude, est.name);
            }
        }
    });

    // --- INITIALISATION ---
    // Simuler un clic pour initialiser les geocoders avec des valeurs par défaut
    setTimeout(() => {
        const casablanca = { center: { lat: 33.5731, lng: -7.5898 }, name: 'Casablanca, Maroc' };
        const rabat = { center: { lat: 34.0208, lng: -6.8416 }, name: 'Rabat, Maroc' };
        geocoderDeparture.options.geocoder.geocode('Casablanca', (results) => {
            if (results[0]) updateLocation('departure', results[0].center.lat, results[0].center.lng, results[0].name);
        });
        geocoderDestination.options.geocoder.geocode('Rabat', (results) => {
            if (results[0]) updateLocation('destination', results[0].center.lat, results[0].center.lng, results[0].name);
        });
    }, 500);

});
</script>
</body>
</html>