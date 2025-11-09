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

// Récupérer les notifications
$query = "SELECT * FROM notifications 
          WHERE user_id = :user_id 
          ORDER BY created_at DESC 
          LIMIT 50";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les notifications non lues
$unread_query = "SELECT COUNT(*) as unread_count FROM notifications 
                 WHERE user_id = :user_id AND is_read = FALSE";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->bindParam(':user_id', $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

// Marquer comme lu si demandé
if (isset($_GET['mark_as_read']) && $_GET['mark_as_read'] == 'all') {
    $update_query = "UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':user_id', $user_id);
    $update_stmt->execute();
    
    header('Location: notifications.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?>

    <!-- Loader -->
    <?php include 'components/loader.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4 max-w-4xl">
            <!-- En-tête -->
            <div class="mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Notifications</h1>
                        <p class="text-gray-600">Restez informé de vos activités de covoiturage</p>
                    </div>
                    
                    <div class="mt-4 md:mt-0 flex items-center gap-4">
                        <?php if ($unread_count > 0): ?>
                        <span class="bg-orange-500 text-white text-sm px-3 py-1 rounded-full">
                            <?php echo $unread_count; ?> non lue(s)
                        </span>
                        <?php endif; ?>
                        
                        <button onclick="markAllAsRead()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-check-double mr-2"></i>Tout marquer comme lu
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex flex-wrap gap-2">
                        <button class="filter-btn px-4 py-2 bg-orange-500 text-white rounded-lg transition-colors" data-filter="all">
                            Toutes
                        </button>
                        <button class="filter-btn px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors" data-filter="booking">
                            Réservations
                        </button>
                        <button class="filter-btn px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors" data-filter="confirmation">
                            Confirmations
                        </button>
                        <button class="filter-btn px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors" data-filter="reminder">
                            Rappels
                        </button>
                        <button class="filter-btn px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors" data-filter="system">
                            Système
                        </button>
                    </div>
                    
                    <div class="flex-1 md:ml-auto">
                        <div class="relative">
                            <input type="text" placeholder="Rechercher..." class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des notifications -->
            <div class="space-y-4">
                <?php if (empty($notifications)): ?>
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <i class="fas fa-bell-slash text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-700 mb-2">Aucune notification</h3>
                    <p class="text-gray-500">Vous serez notifié dès qu'il y aura de nouvelles activités.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card bg-white rounded-xl shadow-md p-6 transition-all duration-300 <?php echo !$notification['is_read'] ? 'border-l-4 border-orange-500' : ''; ?>" data-type="<?php echo $notification['type']; ?>">
                        <div class="flex items-start gap-4">
                            <!-- Icône du type de notification -->
                            <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center 
                                <?php echo getNotificationIconClass($notification['type']); ?>">
                                <i class="fas <?php echo getNotificationIcon($notification['type']); ?> text-white"></i>
                            </div>
                            
                            <!-- Contenu de la notification -->
                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                    <span class="text-sm text-gray-500"><?php echo timeAgo($notification['created_at']); ?></span>
                                </div>
                                
                                <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($notification['message']); ?></p>
                                
                                <!-- Actions rapides -->
                                <div class="flex flex-wrap gap-2">
                                    <?php echo getNotificationActions($notification); ?>
                                </div>
                            </div>
                            
                            <!-- Indicateur non lu -->
                            <?php if (!$notification['is_read']): ?>
                            <div class="w-3 h-3 bg-orange-500 rounded-full flex-shrink-0 mt-2"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Chargement plus -->
            <?php if (count($notifications) >= 50): ?>
            <div class="mt-8 text-center">
                <button id="loadMore" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-spinner fa-spin mr-2 hidden"></i>
                    Charger plus de notifications
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Animation GSAP
        gsap.from('.notification-card', {
            duration: 0.6,
            y: 30,
            opacity: 0,
            stagger: 0.1,
            ease: "power2.out"
        });

        // Filtrage des notifications
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Mettre à jour les boutons actifs
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('bg-orange-500', 'text-white');
                    b.classList.add('border', 'border-gray-300', 'text-gray-700', 'hover:bg-gray-50');
                });
                
                this.classList.remove('border', 'border-gray-300', 'text-gray-700', 'hover:bg-gray-50');
                this.classList.add('bg-orange-500', 'text-white');
                
                // Filtrer les notifications
                document.querySelectorAll('.notification-card').forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-type') === filter) {
                        card.style.display = 'block';
                        gsap.to(card, { opacity: 1, y: 0, duration: 0.3 });
                    } else {
                        gsap.to(card, { 
                            opacity: 0, 
                            y: -20, 
                            duration: 0.3,
                            onComplete: () => card.style.display = 'none'
                        });
                    }
                });
            });
        });

        // Marquer tout comme lu
        function markAllAsRead() {
            window.location.href = 'notifications.php?mark_as_read=all';
        }

        // Chargement plus
        document.getElementById('loadMore')?.addEventListener('click', function() {
            const spinner = this.querySelector('.fa-spinner');
            const originalText = this.innerHTML;
            
            spinner.classList.remove('hidden');
            this.disabled = true;
            
            // Simulation de chargement
            setTimeout(() => {
                spinner.classList.add('hidden');
                this.innerHTML = originalText;
                this.disabled = false;
                alert('Fonctionnalité de chargement infini à implémenter');
            }, 1500);
        });

        // Marquer une notification comme lue au clic
        document.querySelectorAll('.notification-card').forEach(card => {
            card.addEventListener('click', function() {
                if (this.classList.contains('border-l-orange-500')) {
                    this.classList.remove('border-l-orange-500');
                    const dot = this.querySelector('.bg-orange-500');
                    if (dot) dot.style.display = 'none';
                    
                    // Ici, vous enverriez une requête AJAX pour marquer comme lu
                    console.log('Marquer notification comme lue');
                }
            });
        });
    </script>
</body>
</html>

<?php
// Fonctions helper pour les notifications
function getNotificationIcon($type) {
    switch ($type) {
        case 'booking': return 'fa-calendar-check';
        case 'confirmation': return 'fa-check-circle';
        case 'reminder': return 'fa-clock';
        case 'system': return 'fa-info-circle';
        default: return 'fa-bell';
    }
}

function getNotificationIconClass($type) {
    switch ($type) {
        case 'booking': return 'bg-blue-500';
        case 'confirmation': return 'bg-green-500';
        case 'reminder': return 'bg-yellow-500';
        case 'system': return 'bg-purple-500';
        default: return 'bg-gray-500';
    }
}

function getNotificationActions($notification) {
    $actions = '';
    
    switch ($notification['type']) {
        case 'booking':
            $actions = '
                <button class="px-3 py-1 bg-green-500 text-white text-sm rounded-lg hover:bg-green-600 transition-colors">
                    <i class="fas fa-check mr-1"></i>Accepter
                </button>
                <button class="px-3 py-1 bg-red-500 text-white text-sm rounded-lg hover:bg-red-600 transition-colors">
                    <i class="fas fa-times mr-1"></i>Refuser
                </button>
                <button class="px-3 py-1 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-eye mr-1"></i>Voir détails
                </button>
            ';
            break;
            
        case 'confirmation':
            $actions = '
                <button class="px-3 py-1 bg-orange-500 text-white text-sm rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fas fa-qrcode mr-1"></i>Voir QR Code
                </button>
                <button class="px-3 py-1 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-map-marker-alt mr-1"></i>Voir trajet
                </button>
            ';
            break;
            
        case 'reminder':
            $actions = '
                <button class="px-3 py-1 bg-blue-500 text-white text-sm rounded-lg hover:bg-blue-600 transition-colors">
                    <i class="fas fa-clock mr-1"></i>Programmer
                </button>
            ';
            break;
    }
    
    return $actions;
}

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
        return date('d/m/Y à H:i', $time);
    }
}
?>