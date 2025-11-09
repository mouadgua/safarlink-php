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

// 3. Récupérer les données du trajet et vérifier que l'utilisateur est bien le conducteur
$trip_query = "SELECT * FROM trips WHERE id = :trip_id AND driver_id = :driver_id";
$trip_stmt = $db->prepare($trip_query);
$trip_stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$trip_stmt->bindParam(':driver_id', $user_id, PDO::PARAM_INT);
$trip_stmt->execute();
$trip = $trip_stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    $_SESSION['error_message'] = "Trajet non trouvé ou vous n'avez pas la permission d'y accéder.";
    header('Location: my-bookings.php');
    exit;
}

// 4. Récupérer tous les passagers ayant réservé ce trajet (confirmés ou en attente)
$passengers_query = "SELECT 
                        b.id as booking_id, b.seats_booked, b.secret_code, b.passenger_confirmed,
                        p.id as passenger_id, p.first_name, p.last_name, p.avatar_url
                     FROM bookings b
                     JOIN users p ON b.passenger_id = p.id
                     WHERE b.trip_id = :trip_id AND b.status IN ('pending', 'confirmed')";
$passengers_stmt = $db->prepare($passengers_query);
$passengers_stmt->bindParam(':trip_id', $trip_id, PDO::PARAM_INT);
$passengers_stmt->execute();
$passengers = $passengers_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Démarrage du trajet - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'components/navbar.php'; ?>

    <main class="pt-24 pb-20">
        <div class="container mx-auto px-4 max-w-4xl">
            
            <!-- En-tête -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Démarrage du trajet</h1>
                <p class="text-gray-600">Validez la présence de vos passagers avant de commencer la course.</p>
            </div>

            <!-- Résumé du trajet -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <?php echo htmlspecialchars($trip['departure_address']); ?> 
                    → 
                    <?php echo htmlspecialchars($trip['destination_address']); ?>
                </h3>
                <p class="text-gray-600">
                    Départ le <?php echo date('d/m/Y', strtotime($trip['departure_date'])); ?> à 
                    <?php echo substr($trip['departure_time'], 0, 5); ?>
                </p>
            </div>

            <!-- Section principale -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Colonne des passagers -->
                <div>
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Passagers en attente</h2>
                    <div id="passengers-list" class="space-y-4">
                        <?php if (empty($passengers)): ?>
                            <p class="text-gray-500">Aucun passager n'a encore réservé ce trajet.</p>
                        <?php else: ?>
                            <?php foreach ($passengers as $passenger): ?>
                                <div id="passenger-card-<?php echo $passenger['booking_id']; ?>" class="bg-white rounded-xl shadow p-4 transition-all duration-300">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-500 font-bold text-lg">
                                                <?php echo strtoupper(substr($passenger['first_name'], 0, 1) . substr($passenger['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']); ?></p>
                                                <p class="text-sm text-gray-500"><?php echo $passenger['seats_booked']; ?> place(s) réservée(s)</p>
                                            </div>
                                        </div>
                                        <div id="status-<?php echo $passenger['booking_id']; ?>">
                                            <?php if ($passenger['passenger_confirmed']): ?>
                                                <span class="flex items-center text-green-600">
                                                    <i class="fas fa-check-circle mr-2"></i>Validé
                                                </span>
                                            <?php else: ?>
                                                <button onclick="showValidation(<?php echo $passenger['booking_id']; ?>, '<?php echo htmlspecialchars(addslashes($passenger['first_name'])); ?>')" class="px-3 py-1 bg-gray-200 text-gray-700 text-sm rounded-full hover:bg-gray-300">
                                                    Valider
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Colonne de validation -->
                <div id="validation-section" class="bg-white rounded-xl shadow-lg p-6 sticky top-24 h-fit">
                    <h2 id="validation-title" class="text-2xl font-semibold text-gray-800 mb-4">Validation</h2>
                    <div id="validation-placeholder" class="text-center py-10">
                        <i class="fas fa-qrcode text-5xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">Sélectionnez un passager pour commencer la validation.</p>
                    </div>

                    <div id="validation-content" class="hidden space-y-6">
                        <form id="code-validation-form" onsubmit="validateCode(event)">
                            <input type="hidden" id="current-booking-id" value="">
                            <div>
                                <label for="secret_code" class="block text-sm font-medium text-gray-700 mb-2">Code secret du passager</label>
                                <input type="text" id="secret_code" name="secret_code" maxlength="6" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-center text-2xl font-mono tracking-widest focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                       placeholder="_ _ _ _ _ _">
                            </div>
                            <button type="submit" class="mt-4 w-full py-2 bg-orange-500 text-white font-semibold rounded-lg hover:bg-orange-600 transition-colors">
                                Valider le code
                            </button>
                        </form>
                        <div class="relative flex items-center justify-center">
                            <div class="flex-grow border-t border-gray-200"></div>
                            <span class="flex-shrink mx-4 text-gray-400 text-sm">OU</span>
                            <div class="flex-grow border-t border-gray-200"></div>
                        </div>
                        <button class="w-full py-3 border-2 border-dashed border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 hover:border-orange-400 transition-colors">
                            <i class="fas fa-camera mr-2"></i>Scanner le QR Code
                        </button>
                    </div>
                    <div id="validation-result" class="mt-4"></div>
                </div>
            </div>
        </div>
    </main>

<script>
function showValidation(bookingId, passengerName) {
    // UI updates
    document.getElementById('validation-placeholder').classList.add('hidden');
    document.getElementById('validation-content').classList.remove('hidden');
    document.getElementById('validation-title').textContent = `Valider ${passengerName}`;
    document.getElementById('current-booking-id').value = bookingId;
    document.getElementById('secret_code').value = '';
    document.getElementById('validation-result').innerHTML = '';
    document.getElementById('secret_code').focus();

    // Highlight selected passenger
    document.querySelectorAll('.bg-white.rounded-xl.shadow.p-4').forEach(card => {
        card.classList.remove('ring-2', 'ring-orange-500');
    });
    document.getElementById(`passenger-card-${bookingId}`).classList.add('ring-2', 'ring-orange-500');
}

async function validateCode(event) {
    event.preventDefault();
    const bookingId = document.getElementById('current-booking-id').value;
    const secretCode = document.getElementById('secret_code').value;
    const resultDiv = document.getElementById('validation-result');

    resultDiv.innerHTML = `<p class="text-blue-600"><i class="fas fa-spinner fa-spin mr-2"></i>Validation en cours...</p>`;

    try {
        // NOTE: In a real app, this would be an API endpoint.
        // For this example, we'll simulate it. We'll find the passenger's data from the PHP-generated data.
        const passengersData = <?php echo json_encode($passengers); ?>;
        const passenger = passengersData.find(p => p.booking_id == bookingId);

        // Simulate network delay
        await new Promise(resolve => setTimeout(resolve, 500));

        if (passenger && passenger.secret_code === secretCode) {
            resultDiv.innerHTML = `<p class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-2"></i>Code correct ! Passager validé.</p>`;
            
            // Update UI for the passenger
            const statusDiv = document.getElementById(`status-${bookingId}`);
            statusDiv.innerHTML = `
                <span class="flex items-center text-green-600 font-semibold">
                    <i class="fas fa-check-circle mr-2"></i>Validé
                </span>`;
            
            // In a real app, you would also send an AJAX request to update the database:
            // await fetch('api/validate_passenger.php', { method: 'POST', body: JSON.stringify({ booking_id: bookingId }) });

        } else {
            resultDiv.innerHTML = `<p class="text-red-600 font-semibold"><i class="fas fa-times-circle mr-2"></i>Code incorrect. Veuillez réessayer.</p>`;
            gsap.from(document.getElementById('validation-section'), { x: 5, duration: 0.05, repeat: 5, yoyo: true });
        }

    } catch (error) {
        resultDiv.innerHTML = `<p class="text-red-600">Une erreur est survenue.</p>`;
    }
}
</script>
</body>
</html>
```

### 2. Ajout du bouton "Commencer la course" sur `my-bookings.php`

J'ai ajouté un bouton qui n'apparaît que pour les trajets à venir du conducteur.

```diff
--- a/c:/wamp64/www/safarlink-php/my-bookings.php
+++ b/c:/wamp64/www/safarlink-php/my-bookings.php