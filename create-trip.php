<?php
require_once 'config/init.php';

requireLogin();

$user_id = $_SESSION['user']['id'];
$user_type = $_SESSION['user']['user_type'] ?? 'passenger';

// 1. VERIFICATION DE L'UTILISATEUR: Seul un conducteur peut accéder à cette page
if ($user_type !== 'driver') {
    $_SESSION['error_message'] = "Vous devez être enregistré comme Conducteur pour proposer un trajet.";
    header('Location: profile.php');
    exit;
}

// Récupérer l'établissement du conducteur (si défini)
$user_establishment_id = $_SESSION['user']['establishment_id'] ?? null;
$establishment_name = null;
$est_details_map = []; // Pour stocker les détails de tous les établissements
if ($user_establishment_id) {
    $est_query = "SELECT name FROM establishments WHERE id = :id";
    $est_stmt = $db->prepare($est_query);
    $est_stmt->bindParam(':id', $user_establishment_id);
    $est_stmt->execute();
    $establishment_name = $est_stmt->fetchColumn();
}

// Récupérer tous les établissements pour la liste déroulante
$all_est_query = "SELECT id, name, address, latitude, longitude FROM establishments ORDER BY name";
$all_est_stmt = $db->prepare($all_est_query);
$all_est_stmt->execute();
$establishments = $all_est_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($establishments as $est) {
    $est_details_map[$est['id']] = $est;
}

// --- 2. Traitement du formulaire d'insertion ---
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dep_est_id = !empty($_POST['departure_establishment_id']) ? $_POST['departure_establishment_id'] : null;
    $dest_est_id = !empty($_POST['destination_establishment_id']) ? $_POST['destination_establishment_id'] : null;
    $dep_addr = trim($_POST['departure_address']);
    $dest_addr = trim($_POST['destination_address']);
    $date = $_POST['departure_date'];
    $time = $_POST['departure_time'];
    $seats = (int)($_POST['available_seats']);
    $price = (float)($_POST['price_per_seat']);
    $description = trim($_POST['description'] ?? '');

    // Récupération et vérification des coordonnées
    $dep_lat = $_POST['departure_latitude'] ?? null;
    $dep_lon = $_POST['departure_longitude'] ?? null;
    $dest_lat = $_POST['destination_latitude'] ?? null;
    $dest_lon = $_POST['destination_longitude'] ?? null;

    // Validation des données
    if (empty($dep_addr) || empty($dest_addr) || $seats < 1 || $price < 1) {
        $error = "Veuillez remplir tous les champs obligatoires correctement (au moins 1 place, prix > 0).";
    } elseif (strtotime($date . ' ' . $time) < time()) {
        $error = "L'heure de départ ne peut pas être dans le passé.";
    } else {
        try {
            // Utiliser les coordonnées de l'établissement si l'adresse personnalisée est vide et un établissement est sélectionné
            if (empty($dep_lat) && $dep_est_id && isset($est_details_map[$dep_est_id])) {
                 $dep_lat = $est_details_map[$dep_est_id]['latitude'];
                 $dep_lon = $est_details_map[$dep_est_id]['longitude'];
            }
            if (empty($dest_lat) && $dest_est_id && isset($est_details_map[$dest_est_id])) {
                 $dest_lat = $est_details_map[$dest_est_id]['latitude'];
                 $dest_lon = $est_details_map[$dest_est_id]['longitude'];
            }
            
            // Si les coordonnées sont encore nulles, utiliser des valeurs par défaut
            if (empty($dep_lat)) $dep_lat = 33.573110;
            if (empty($dep_lon)) $dep_lon = -7.589843;
            if (empty($dest_lat)) $dest_lat = 34.020882;
            if (empty($dest_lon)) $dest_lon = -6.841650;

            $query = "INSERT INTO trips (
                        driver_id, departure_establishment_id, destination_establishment_id, 
                        departure_address, destination_address, 
                        departure_latitude, departure_longitude, destination_latitude, destination_longitude,
                        departure_date, departure_time, available_seats, price_per_seat, description
                      ) VALUES (
                        :driver_id, :dep_est_id, :dest_est_id, 
                        :dep_addr, :dest_addr, 
                        :dep_lat, :dep_lon, :dest_lat, :dest_lon,
                        :date, :time, :seats, :price, :description
                      )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':driver_id', $user_id);
            $stmt->bindParam(':dep_est_id', $dep_est_id, PDO::PARAM_INT);
            $stmt->bindParam(':dest_est_id', $dest_est_id, PDO::PARAM_INT);
            $stmt->bindParam(':dep_addr', $dep_addr);
            $stmt->bindParam(':dest_addr', $dest_addr);
            $stmt->bindParam(':dep_lat', $dep_lat);
            $stmt->bindParam(':dep_lon', $dep_lon);
            $stmt->bindParam(':dest_lat', $dest_lat);
            $stmt->bindParam(':dest_lon', $dest_lon);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':time', $time);
            $stmt->bindParam(':seats', $seats);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':description', $description);

            if ($stmt->execute()) {
                $success = "Votre trajet a été créé avec succès !";
                // Redirection vers la liste des trajets du chauffeur
                $_SESSION['success_message'] = $success;
                header('Location: my-bookings.php?tab=upcoming'); 
                exit;
            } else {
                $error = "Erreur lors de l'enregistrement du trajet.";
            }

        } catch (PDOException $e) {
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        .form-section {
            opacity: 0;
            transform: translateY(20px);
        }
        
        .custom-select {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-down" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 10px;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?> 
    <?php include 'components/loader.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4 max-w-4xl">
            <div class="mb-8 text-center form-section">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Proposer un nouveau trajet</h1>
                <p class="text-gray-600">Partagez votre route et rentabilisez vos déplacements.</p>
            </div>

            <?php if ($error): ?>
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg form-section" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg form-section" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-8 form-section">
                <form method="POST">
                    
                    <input type="hidden" name="departure_latitude" id="departure_latitude" value="<?php echo htmlspecialchars($_POST['departure_latitude'] ?? ''); ?>">
                    <input type="hidden" name="departure_longitude" id="departure_longitude" value="<?php echo htmlspecialchars($_POST['departure_longitude'] ?? ''); ?>">
                    <input type="hidden" name="destination_latitude" id="destination_latitude" value="<?php echo htmlspecialchars($_POST['destination_latitude'] ?? ''); ?>">
                    <input type="hidden" name="destination_longitude" id="destination_longitude" value="<?php echo htmlspecialchars($_POST['destination_longitude'] ?? ''); ?>">
                    
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-2">1. Itinéraire & Lieu</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        
                        <div>
                            <label for="departure_address" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt text-orange-500 mr-2"></i>Adresse de Départ
                            </label>
                            <input type="text" name="departure_address" id="departure_address" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                placeholder="Ex: 15 Rue de la Gare" value="<?php echo htmlspecialchars($_POST['departure_address'] ?? $establishment_name ?? ''); ?>">
                            <p class="mt-1 text-xs text-gray-500">Soyez précis pour faciliter le rendez-vous.</p>
                        </div>

                        <div>
                            <label for="departure_establishment_id" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-school text-blue-500 mr-2"></i>Établissement de Départ (Optionnel)
                            </label>
                            <select name="departure_establishment_id" id="departure_establishment_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg custom-select focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">--- Sélectionner un établissement ---</option>
                                <?php foreach ($establishments as $est): ?>
                                    <option value="<?php echo $est['id']; ?>" 
                                            <?php echo (isset($_POST['departure_establishment_id']) && $_POST['departure_establishment_id'] == $est['id']) || ($user_establishment_id && $est['id'] == $user_establishment_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($establishment_name): ?>
                            <p class="mt-1 text-xs text-green-600">Votre établissement actuel: <?php echo htmlspecialchars($establishment_name); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="destination_address" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-flag-checkered text-green-500 mr-2"></i>Adresse de Destination
                            </label>
                            <input type="text" name="destination_address" id="destination_address" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                placeholder="Ex: Aéroport de Paris" value="<?php echo htmlspecialchars($_POST['destination_address'] ?? ''); ?>">
                        </div>

                        <div>
                            <label for="destination_establishment_id" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-school text-blue-500 mr-2"></i>Établissement de Destination (Optionnel)
                            </label>
                            <select name="destination_establishment_id" id="destination_establishment_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg custom-select focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">--- Sélectionner un établissement ---</option>
                                <?php foreach ($establishments as $est): ?>
                                    <option value="<?php echo $est['id']; ?>" <?php echo (isset($_POST['destination_establishment_id']) && $_POST['destination_establishment_id'] == $est['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h2 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-2">2. Détails & Prix</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        
                        <div>
                            <label for="departure_date" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt text-gray-500 mr-2"></i>Date de Départ
                            </label>
                            <input type="date" name="departure_date" id="departure_date" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['departure_date'] ?? date('Y-m-d')); ?>">
                        </div>
                        
                        <div>
                            <label for="departure_time" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock text-gray-500 mr-2"></i>Heure de Départ
                            </label>
                            <input type="time" name="departure_time" id="departure_time" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                value="<?php echo htmlspecialchars($_POST['departure_time'] ?? date('H:i')); ?>">
                        </div>
                        
                        <div>
                            <label for="available_seats" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-chair text-gray-500 mr-2"></i>Places disponibles
                            </label>
                            <input type="number" name="available_seats" id="available_seats" required min="1" max="8"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                value="<?php echo htmlspecialchars($_POST['available_seats'] ?? 3); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        
                        <div>
                            <label for="price_per_seat" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-euro-sign text-orange-500 mr-2"></i>Prix par place (Dhs)
                            </label>
                            <input type="number" name="price_per_seat" id="price_per_seat" required min="1" step="0.50"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                value="<?php echo htmlspecialchars($_POST['price_per_seat'] ?? 10.00); ?>">
                            <p class="mt-1 text-xs text-gray-500">Suggérez un prix juste pour couvrir les frais (ex: 10.00 Dhs).</p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-info-circle text-gray-500 mr-2"></i>Description du trajet (Optionnel)
                            </label>
                            <textarea name="description" id="description" rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                placeholder="Ex: Je peux prendre une petite valise. Musique calme acceptée."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" 
                            class="w-full py-3 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition-colors flex items-center justify-center">
                        <i class="fas fa-car-side mr-2"></i>Proposer le trajet
                    </button>
                    
                    <a href="my-bookings.php?tab=upcoming" class="block w-full text-center mt-3 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Voir mes trajets existants
                    </a>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Animation GSAP pour les sections du formulaire
        gsap.from('.form-section', {
            duration: 0.8,
            y: 30,
            opacity: 0,
            stagger: 0.15,
            ease: "power3.out",
            delay: 0.5 
        });
        
        // S'assurer que la date de départ ne peut pas être antérieure à aujourd'hui
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('departure_date');
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
            
            // S'assurer que la valeur est valide si elle est dans le passé
            if (dateInput.value < today) {
                dateInput.value = today;
            }
        });
    </script>
</body>
</html>