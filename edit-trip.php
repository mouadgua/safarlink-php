<?php
require_once 'config/init.php';

// 1. SÉCURITÉ : Vérifier que l'utilisateur est connecté et est un conducteur
requireLogin();
$user_id = $_SESSION['user']['id'];
if ($_SESSION['user']['user_type'] !== 'driver') {
    header('Location: profile.php');
    exit;
}

// 2. Récupérer l'ID du trajet et vérifier les permissions
$trip_id = $_GET['id'] ?? null;
if (!$trip_id) {
    header('Location: my-bookings.php');
    exit;
}

// 3. RÈGLE MÉTIER : Vérifier si le trajet a des réservations
$booking_check_query = "SELECT COUNT(*) FROM bookings WHERE trip_id = :trip_id";
$booking_stmt = $db->prepare($booking_check_query);
$booking_stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$booking_stmt->execute();
$booking_count = $booking_stmt->fetchColumn();

if ($booking_count > 0) {
    $_SESSION['error_message'] = "Ce trajet ne peut pas être modifié car il a déjà des réservations.";
    header('Location: my-bookings.php');
    exit;
}

// 4. Récupérer les données actuelles du trajet
$trip_query = "SELECT * FROM trips WHERE id = :trip_id AND driver_id = :driver_id";
$trip_stmt = $db->prepare($trip_query);
$trip_stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$trip_stmt->bindParam(':driver_id', $user_id, PDO::PARAM_INT);
$trip_stmt->execute();
$trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    $_SESSION['error_message'] = "Trajet non trouvé ou vous n'avez pas la permission de le modifier.";
    header('Location: my-bookings.php');
    exit;
}

// Convertir les règles en tableau pour le formulaire
$trip['trip_rules'] = !empty($trip['trip_rules']) ? explode(',', $trip['trip_rules']) : [];

// 5. TRAITEMENT DU FORMULAIRE DE MISE À JOUR
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données
    $departure_address = trim($_POST['departure_address']);
    $destination_address = trim($_POST['destination_address']);
    $departure_date = $_POST['departure_date'];
    $departure_time = $_POST['departure_time'];
    $available_seats = (int)($_POST['available_seats']);
    $price_per_seat = (float)($_POST['price_per_seat']);
    $description = trim($_POST['description'] ?? '');
    $trip_rules = isset($_POST['rules']) ? implode(',', $_POST['rules']) : '';
    $possible_detours = trim($_POST['possible_detours'] ?? '');

    // Validation (similaire à la création)
    if (empty($departure_address) || empty($destination_address) || empty($departure_date) || empty($departure_time) || $available_seats < 1 || $price_per_seat <= 0) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            $update_query = "UPDATE trips SET
                                departure_address = :dep_addr,
                                destination_address = :dest_addr,
                                departure_date = :date,
                                departure_time = :time,
                                available_seats = :seats,
                                price_per_seat = :price,
                                description = :description,
                                trip_rules = :rules,
                                possible_detours = :detours
                             WHERE id = :trip_id AND driver_id = :driver_id";
            
            $stmt = $db->prepare($update_query);
            $stmt->bindParam(':dep_addr', $departure_address);
            $stmt->bindParam(':dest_addr', $destination_address);
            $stmt->bindParam(':date', $departure_date);
            $stmt->bindParam(':time', $departure_time);
            $stmt->bindParam(':seats', $available_seats);
            $stmt->bindParam(':price', $price_per_seat);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':rules', $trip_rules);
            $stmt->bindParam(':detours', $possible_detours);
            $stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
            $stmt->bindParam(':driver_id', $user_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Votre trajet a été modifié avec succès !";
                header('Location: my-bookings.php?tab=upcoming'); 
                exit;
            } else {
                $error = "Une erreur est survenue lors de la mise à jour du trajet.";
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
    <title>Modifier le trajet - SafarLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .rule-tag input:checked + label { background-color: #F97316; color: white; border-color: #F97316; }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?> 

    <main class="pt-24 pb-20">
        <div class="container mx-auto px-4 max-w-2xl">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Modifier le trajet</h1>
                <p class="text-gray-600">Mettez à jour les informations de votre trajet.</p>
            </div>

            <?php if ($error): ?>
                <div class="p-4 mb-6 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-8">
                <form method="POST" action="edit-trip.php?id=<?php echo $trip_id; ?>">
                    
                    <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3">Itinéraire</h2>
                    <div class="grid grid-cols-1 gap-6 mb-8">
                        <div>
                            <label for="departure_address" class="block text-sm font-medium text-gray-700 mb-2">Adresse de Départ</label>
                            <input type="text" name="departure_address" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($trip['departure_address']); ?>">
                        </div>
                        <div>
                            <label for="destination_address" class="block text-sm font-medium text-gray-700 mb-2">Adresse de Destination</label>
                            <input type="text" name="destination_address" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($trip['destination_address']); ?>">
                        </div>
                    </div>

                    <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3">Détails du trajet</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label for="departure_date" class="block text-sm font-medium text-gray-700 mb-2">Date de Départ</label>
                            <input type="date" name="departure_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($trip['departure_date']); ?>">
                        </div>
                        <div>
                            <label for="departure_time" class="block text-sm font-medium text-gray-700 mb-2">Heure de Départ</label>
                            <input type="time" name="departure_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($trip['departure_time']); ?>">
                        </div>
                        <div>
                            <label for="available_seats" class="block text-sm font-medium text-gray-700 mb-2">Places disponibles</label>
                            <input type="number" name="available_seats" required min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($trip['available_seats']); ?>">
                        </div>
                        <div>
                            <label for="price_per_seat" class="block text-sm font-medium text-gray-700 mb-2">Prix par place (Dhs)</label>
                            <input type="number" name="price_per_seat" required min="1" step="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo htmlspecialchars($trip['price_per_seat']); ?>">
                        </div>
                    </div>
                    
                    <h2 class="text-xl font-semibold text-gray-800 mb-6 border-b pb-3">Règles et Préférences</h2>
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Règles du trajet</label>
                        <div class="flex flex-wrap gap-3">
                            <?php 
                            $rules_options = ['no_smoking' => 'Non-fumeur', 'pets_allowed' => 'Animaux acceptés', 'music_allowed' => 'Musique bienvenue', 'quiet_trip' => 'Trajet calme', 'small_luggage' => 'Petits bagages'];
                            foreach ($rules_options as $key => $text): ?>
                                <div class="rule-tag">
                                    <input type="checkbox" name="rules[]" id="rule_<?php echo $key; ?>" value="<?php echo $key; ?>" class="hidden" <?php echo in_array($key, $trip['trip_rules']) ? 'checked' : ''; ?>>
                                    <label for="rule_<?php echo $key; ?>" class="cursor-pointer flex items-center px-4 py-2 border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors"><?php echo $text; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-8">
                        <label for="possible_detours" class="block text-sm font-medium text-gray-700 mb-2">Détours possibles (Optionnel)</label>
                        <textarea name="possible_detours" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($trip['possible_detours']); ?></textarea>
                    </div>

                    <div class="mb-8">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optionnel)</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($trip['description']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="w-full py-3 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition-colors">
                        <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>