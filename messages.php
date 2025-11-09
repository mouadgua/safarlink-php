<?php
require_once 'config/init.php';

requireLogin();

$user_id = $_SESSION['user']['id'];

// Récupérer les conversations
$query = "SELECT DISTINCT 
                 CASE 
                     WHEN m.sender_id = :user_id THEN m.receiver_id 
                     ELSE m.sender_id 
                 END as other_user_id,
                 u.first_name, u.last_name, u.avatar_url,
                 MAX(m.created_at) as last_message_time,
                 (SELECT content FROM messages 
                  WHERE (sender_id = :user_id AND receiver_id = other_user_id) 
                     OR (sender_id = other_user_id AND receiver_id = :user_id) 
                  ORDER BY created_at DESC LIMIT 1) as last_message,
                 (SELECT COUNT(*) FROM messages 
                  WHERE receiver_id = :user_id AND sender_id = other_user_id AND is_read = FALSE) as unread_count
          FROM messages m
          JOIN users u ON u.id = CASE 
                     WHEN m.sender_id = :user_id THEN m.receiver_id 
                     ELSE m.sender_id 
                 END
          WHERE m.sender_id = :user_id OR m.receiver_id = :user_id
          GROUP BY other_user_id, u.first_name, u.last_name, u.avatar_url
          ORDER BY last_message_time DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'une conversation spécifique
$selected_conversation = null;
$messages = [];

if (isset($_GET['conversation_id'])) {
    $other_user_id = $_GET['conversation_id'];
    
    // Vérifier que l'utilisateur a accès à cette conversation
    $check_query = "SELECT id FROM users WHERE id = :other_user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':other_user_id', $other_user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $selected_conversation = $other_user_id;
        
        // Récupérer les messages
        $messages_query = "SELECT m.*, 
                                  u.first_name, u.last_name, u.avatar_url
                           FROM messages m
                           JOIN users u ON m.sender_id = u.id
                           WHERE (m.sender_id = :user_id AND m.receiver_id = :other_user_id)
                              OR (m.sender_id = :other_user_id AND m.receiver_id = :user_id)
                           ORDER BY m.created_at ASC";
        $messages_stmt = $db->prepare($messages_query);
        $messages_stmt->bindParam(':user_id', $user_id);
        $messages_stmt->bindParam(':other_user_id', $other_user_id);
        $messages_stmt->execute();
        $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Marquer les messages comme lus
        $mark_read_query = "UPDATE messages SET is_read = TRUE 
                            WHERE receiver_id = :user_id AND sender_id = :other_user_id AND is_read = FALSE";
        $mark_read_stmt = $db->prepare($mark_read_query);
        $mark_read_stmt->bindParam(':user_id', $user_id);
        $mark_read_stmt->bindParam(':other_user_id', $other_user_id);
        $mark_read_stmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - SafarLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'components/navbar.php'; ?>

    <!-- Loader -->
    <?php include 'components/loader.php'; ?>

    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4 h-[calc(100vh-200px)]">
            <div class="bg-white rounded-xl shadow-md overflow-hidden h-full">
                <div class="flex h-full">
                    <!-- Liste des conversations (Sidebar) -->
                    <div class="w-full md:w-80 border-r border-gray-200 flex flex-col">
                        <!-- En-tête conversations -->
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-xl font-bold text-gray-800">Messages</h2>
                                <button class="w-8 h-8 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center hover:bg-orange-200 transition-colors">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                            
                            <!-- Barre de recherche -->
                            <div class="relative">
                                <input type="text" placeholder="Rechercher..." class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>

                        <!-- Liste des conversations -->
                        <div class="flex-1 overflow-y-auto">
                            <?php if (empty($conversations)): ?>
                            <div class="p-8 text-center text-gray-500">
                                <i class="fas fa-comments text-4xl mb-4"></i>
                                <p>Aucune conversation</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($conversations as $conv): ?>
                                <a href="messages.php?conversation_id=<?php echo $conv['other_user_id']; ?>" 
                                   class="conversation-item block p-4 border-b border-gray-100 hover:bg-gray-50 transition-colors <?php echo $selected_conversation == $conv['other_user_id'] ? 'bg-orange-50 border-r-2 border-orange-500' : ''; ?>">
                                    <div class="flex items-center gap-3">
                                        <!-- Avatar -->
                                        <div class="relative">
                                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium">
                                                <?php 
                                                $initials = strtoupper(substr($conv['first_name'], 0, 1) . substr($conv['last_name'], 0, 1));
                                                echo $initials;
                                                ?>
                                            </div>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-orange-500 text-white text-xs rounded-full flex items-center justify-center">
                                                <?php echo $conv['unread_count']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Contenu -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between mb-1">
                                                <h3 class="font-semibold text-gray-800 truncate">
                                                    <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                                </h3>
                                                <span class="text-xs text-gray-500">
                                                    <?php echo timeAgo($conv['last_message_time']); ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-600 truncate">
                                                <?php echo htmlspecialchars($conv['last_message'] ?? 'Aucun message'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Zone de conversation -->
                    <div class="flex-1 flex flex-col <?php echo !$selected_conversation ? 'hidden md:flex' : ''; ?>">
                        <?php if ($selected_conversation): ?>
                            <?php 
                            $other_user = null;
                            foreach ($conversations as $conv) {
                                if ($conv['other_user_id'] == $selected_conversation) {
                                    $other_user = $conv;
                                    break;
                                }
                            }
                            ?>
                            
                            <!-- En-tête de la conversation -->
                            <div class="p-4 border-b border-gray-200 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium">
                                    <?php 
                                    $initials = strtoupper(substr($other_user['first_name'], 0, 1) . substr($other_user['last_name'], 0, 1));
                                    echo $initials;
                                    ?>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">
                                        <?php echo htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">En ligne</p>
                                </div>
                                
                                <div class="ml-auto flex items-center gap-2">
                                    <button class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                                        <i class="fas fa-phone text-gray-600"></i>
                                    </button>
                                    <button class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                                        <i class="fas fa-video text-gray-600"></i>
                                    </button>
                                    <button class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center transition-colors">
                                        <i class="fas fa-ellipsis-v text-gray-600"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Messages -->
                            <div id="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
                                <?php if (empty($messages)): ?>
                                <div class="text-center text-gray-500 py-8">
                                    <i class="fas fa-comment-dots text-4xl mb-4"></i>
                                    <p>Aucun message échangé</p>
                                    <p class="text-sm">Envoyez le premier message !</p>
                                </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                    <div class="message-item flex <?php echo $message['sender_id'] == $user_id ? 'justify-end' : 'justify-start'; ?>">
                                        <div class="max-w-xs md:max-w-md px-4 py-2 rounded-2xl <?php echo $message['sender_id'] == $user_id ? 'bg-orange-500 text-white rounded-br-none' : 'bg-white border border-gray-200 rounded-bl-none'; ?>">
                                            <p class="text-sm"><?php echo htmlspecialchars($message['content']); ?></p>
                                            <div class="text-xs mt-1 <?php echo $message['sender_id'] == $user_id ? 'text-orange-100' : 'text-gray-500'; ?> text-right">
                                                <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                                <?php if ($message['sender_id'] == $user_id && $message['is_read']): ?>
                                                <i class="fas fa-check-double ml-1"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Zone de saisie -->
                            <div class="p-4 border-t border-gray-200">
                                <form id="messageForm" class="flex gap-2">
                                    <div class="flex-1">
                                        <input type="text" name="message" placeholder="Tapez votre message..." 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-full focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                                    </div>
                                    <button type="submit" class="w-10 h-10 bg-orange-500 text-white rounded-full flex items-center justify-center hover:bg-orange-600 transition-colors">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- État vide - Aucune conversation sélectionnée -->
                            <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                                <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                                <h3 class="text-xl font-medium text-gray-700 mb-2">Aucune conversation sélectionnée</h3>
                                <p class="text-gray-500 mb-6">Choisissez une conversation pour commencer à discuter</p>
                                <button class="px-6 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                                    <i class="fas fa-plus mr-2"></i>Nouvelle conversation
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Animation GSAP
        gsap.from('.conversation-item', {
            duration: 0.4,
            x: -20,
            opacity: 0,
            stagger: 0.05,
            ease: "power2.out"
        });

        gsap.from('.message-item', {
            duration: 0.5,
            y: 20,
            opacity: 0,
            stagger: 0.1,
            ease: "back.out(1.7)"
        });

        // Auto-scroll vers le bas des messages
        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        // Initial scroll
        setTimeout(scrollToBottom, 100);

        // Gestion de l'envoi de message
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageInput = this.querySelector('input[name="message"]');
            const message = messageInput.value.trim();
            
            if (message) {
                // Simulation d'envoi
                const messagesContainer = document.getElementById('messagesContainer');
                const newMessage = document.createElement('div');
                newMessage.className = 'message-item flex justify-end';
                newMessage.innerHTML = `
                    <div class="max-w-xs md:max-w-md px-4 py-2 rounded-2xl bg-orange-500 text-white rounded-br-none">
                        <p class="text-sm">${message}</p>
                        <div class="text-xs mt-1 text-orange-100 text-right">
                            ${new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'})}
                            <i class="fas fa-check ml-1"></i>
                        </div>
                    </div>
                `;
                
                messagesContainer.appendChild(newMessage);
                messageInput.value = '';
                
                // Animation du nouveau message
                gsap.from(newMessage, {
                    duration: 0.5,
                    y: 20,
                    opacity: 0,
                    ease: "back.out(1.7)"
                });
                
                scrollToBottom();
                
                // Simulation de réponse
                setTimeout(() => {
                    const responseMessage = document.createElement('div');
                    responseMessage.className = 'message-item flex justify-start';
                    responseMessage.innerHTML = `
                        <div class="max-w-xs md:max-w-md px-4 py-2 rounded-2xl bg-white border border-gray-200 rounded-bl-none">
                            <p class="text-sm">Merci pour votre message ! Je vous réponds rapidement.</p>
                            <div class="text-xs mt-1 text-gray-500 text-right">
                                ${new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'})}
                            </div>
                        </div>
                    `;
                    
                    messagesContainer.appendChild(responseMessage);
                    gsap.from(responseMessage, {
                        duration: 0.5,
                        y: 20,
                        opacity: 0,
                        ease: "back.out(1.7)"
                    });
                    
                    scrollToBottom();
                }, 2000);
            }
        });

        // Recherche en temps réel
        document.querySelector('input[placeholder="Rechercher..."]')?.addEventListener('input', function(e) {
            const searchTerm = this.value.toLowerCase();
            const conversations = document.querySelectorAll('.conversation-item');
            
            conversations.forEach(conv => {
                const name = conv.querySelector('h3').textContent.toLowerCase();
                const lastMessage = conv.querySelector('p').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || lastMessage.includes(searchTerm)) {
                    conv.style.display = 'block';
                    gsap.to(conv, { opacity: 1, duration: 0.3 });
                } else {
                    gsap.to(conv, { 
                        opacity: 0, 
                        duration: 0.3,
                        onComplete: () => conv.style.display = 'none'
                    });
                }
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
        return date('d/m', $time);
    }
}
?>