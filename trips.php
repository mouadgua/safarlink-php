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
    <!-- Loader -->
    <div class="loader-container" id="loader">
        <div class="loader"></div>
        <div class="loader-logo">Safar<span>Link</span></div>
    </div>

    <!-- Navbar Desktop -->
    <?php include 'components/navbar-desktop.php'; ?>

    <!-- Navbar Mobile -->
    <?php include 'components/navbar-mobile.php'; ?>

    <!-- Contenu principal -->
    <main class="pt-16 md:pt-24 pb-20 md:pb-8">
        <div class="container mx-auto px-4">
            <!-- En-tête -->
            <div class="mb-8 text-center">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Trouvez votre trajet idéal</h1>
                <p class="text-gray-600 max-w-2xl mx-auto">Découvrez des trajets partagés avec des conducteurs vérifiés et une communauté de confiance.</p>
            </div>

            <!-- Carte de recherche interactive -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Planifiez votre trajet</h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Sélection du point de départ -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Point de départ</label>
                        <div class="space-y-3">
                            <div class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer active" data-type="current" data-role="departure">
                                <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-location-arrow text-orange-500"></i>
                                </div>
                                <div>
                                    <div class="font-medium">Ma position actuelle</div>
                                    <div class="text-xs text-gray-500">Utiliser ma localisation</div>
                                </div>
                            </div>
                            
                            <div class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer" data-type="establishment" data-role="departure">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-school text-blue-500"></i>
                                </div>
                                <div>
                                    <div class="font-medium">Mon établissement</div>
                                    <div class="text-xs text-gray-500">Cité des Métiers et Compétences</div>
                                </div>
                            </div>
                            
                            <div class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer" data-type="custom" data-role="departure">
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-map-marker-alt text-green-500"></i>
                                </div>
                                <div>
                                    <div class="font-medium">Autre adresse</div>
                                    <div class="text-xs text-gray-500">Saisir une adresse manuellement</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sélection de la destination -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Destination</label>
                        <div class="space-y-3">
                            <div class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer" data-type="establishment" data-role="destination">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-school text-blue-500"></i>
                                </div>
                                <div>
                                    <div class="font-medium">Établissement</div>
                                    <div class="text-xs text-gray-500">Université de Rabat</div>
                                </div>
                            </div>
                            
                            <div class="location-option flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer active" data-type="custom" data-role="destination">
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-map-marker-alt text-green-500"></i>
                                </div>
                                <div>
                                    <div class="font-medium">Autre adresse</div>
                                    <div class="text-xs text-gray-500">Saisir une adresse manuellement</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Carte interactive -->
                    <div class="relative">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Visualisation du trajet</label>
                            <button id="expandMap" class="text-gray-500 hover:text-orange-500 transition-colors">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                        <div id="map" class="w-full"></div>
                        <div class="absolute bottom-3 left-3 bg-white px-3 py-2 rounded-lg shadow-md text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-orange-500 mr-2"></div>
                                <span>Départ: <span id="departureText">Position actuelle</span></span>
                            </div>
                            <div class="flex items-center mt-1">
                                <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                                <span>Arrivée: <span id="destinationText">À définir</span></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Champs d'adresse personnalisée -->
                <div id="customAddressDeparture" class="mt-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de départ</label>
                    <div class="flex gap-2">
                        <input type="text" placeholder="Saisissez une adresse..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        <button class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div id="customAddressDestination" class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Adresse de destination</label>
                    <div class="flex gap-2">
                        <input type="text" placeholder="Saisissez une adresse..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" value="Lyon, Part-Dieu">
                        <button class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Filtres (Desktop) -->
                <div class="hidden lg:block w-80 flex-shrink-0">
                    <div class="sticky-filters top-24 bg-white rounded-xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Filtres</h2>
                        
                        <!-- Date -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                            <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        
                        <!-- Heure -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Heure</label>
                            <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Toute la journée</option>
                                <option value="morning">Matin (6h-12h)</option>
                                <option value="afternoon">Après-midi (12h-18h)</option>
                                <option value="evening">Soir (18h-00h)</option>
                            </select>
                        </div>
                        
                        <!-- Nombre de places -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de places</label>
                            <div class="flex items-center space-x-2">
                                <button class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100">-</button>
                                <span class="w-8 text-center">1</span>
                                <button class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-100">+</button>
                            </div>
                        </div>
                        
                        <!-- Prix maximum -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prix maximum</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">0€</span>
                                <input type="range" min="0" max="50" value="25" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <span class="text-sm text-gray-500">50€</span>
                            </div>
                            <div class="text-center mt-1 text-sm text-gray-600">Jusqu'à 25€</div>
                        </div>
                        
                        <!-- Options -->
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
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded text-orange-500 focus:ring-orange-500">
                                    <span class="ml-2 text-sm text-gray-700">Musique autorisée</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded text-orange-500 focus:ring-orange-500">
                                    <span class="ml-2 text-sm text-gray-700">Trajet silencieux</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Bouton Appliquer -->
                        <button class="w-full py-3 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors">
                            Appliquer les filtres
                        </button>
                    </div>
                </div>

                <!-- Contenu des trajets -->
                <div class="flex-1">
                    <!-- Barre de recherche et bouton filtre mobile -->
                    <div class="bg-white rounded-xl shadow-md p-4 mb-6 flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">12 trajets</span> trouvés pour votre recherche
                        </div>
                        <button id="mobileFilterToggle" class="lg:hidden ml-4 px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                            <i class="fas fa-filter mr-2"></i> Filtres
                        </button>
                    </div>

                    <!-- Liste des trajets -->
                    <div class="space-y-6">
                        <!-- Trajet 1 -->
                        <div class="trip-card bg-white rounded-xl shadow-md overflow-hidden">
                            <div class="p-6">
                                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Départ dans 2h</span>
                                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">3 places disponibles</span>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-800">Paris → Lyon</h3>
                                        <p class="text-gray-600">Aujourd'hui, 14:30 - 18:45 (4h15)</p>
                                    </div>
                                    <div class="mt-4 md:mt-0">
                                        <span class="text-2xl font-bold text-orange-500">12€</span>
                                        <span class="text-gray-500 text-sm block">par personne</span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center mb-4">
                                    <div class="flex -space-x-2">
                                        <div class="w-8 h-8 rounded-full bg-orange-500 flex items-center justify-center text-white text-xs font-bold passenger-avatar">MJ</div>
                                        <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-bold passenger-avatar">TP</div>
                                        <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center text-white text-xs font-bold passenger-avatar">SL</div>
                                        <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-xs font-bold passenger-avatar">+2</div>
                                    </div>
                                    <span class="ml-3 text-sm text-gray-600">4 passagers déjà inscrits</span>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="flex items-center mb-4 md:mb-0">
                                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800">Marie Curie</h4>
                                            <div class="flex items-center">
                                                <i class="fas fa-star text-yellow-400 text-sm mr-1"></i>
                                                <span class="text-sm text-gray-600">4.8 (124 avis)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="px-6 py-2 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors">
                                        Réserver
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Trajet 2 -->
                        <div class="trip-card bg-white rounded-xl shadow-md overflow-hidden">
                            <div class="p-6">
                                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded">Départ demain</span>
                                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">2 places disponibles</span>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-800">Versailles → Orléans</h3>
                                        <p class="text-gray-600">Demain, 09:15 - 11:30 (2h15)</p>
                                    </div>
                                    <div class="mt-4 md:mt-0">
                                        <span class="text-2xl font-bold text-orange-500">15€</span>
                                        <span class="text-gray-500 text-sm block">par personne</span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center mb-4">
                                    <div class="flex -space-x-2">
                                        <div class="w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center text-white text-xs font-bold passenger-avatar">AL</div>
                                        <div class="w-8 h-8 rounded-full bg-pink-500 flex items-center justify-center text-white text-xs font-bold passenger-avatar">CP</div>
                                        <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-xs font-bold passenger-avatar">+1</div>
                                    </div>
                                    <span class="ml-3 text-sm text-gray-600">3 passagers déjà inscrits</span>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-center justify-between">
                                    <div class="flex items-center mb-4 md:mb-0">
                                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800">Thomas Martin</h4>
                                            <div class="flex items-center">
                                                <i class="fas fa-star text-yellow-400 text-sm mr-1"></i>
                                                <span class="text-sm text-gray-600">4.9 (87 avis)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button class="px-6 py-2 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors">
                                        Réserver
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
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

    <!-- Filtres Mobile (Overlay) -->
    <div id="mobileFilters" class="fixed inset-0 bg-white z-50 p-6 overflow-y-auto hidden">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Filtres</h2>
            <button id="closeMobileFilters" class="text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Contenu des filtres mobile -->
        <div class="space-y-6">
            <!-- Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>
            
            <!-- Heure -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Heure</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <option value="">Toute la journée</option>
                    <option value="morning">Matin (6h-12h)</option>
                    <option value="afternoon">Après-midi (12h-18h)</option>
                    <option value="evening">Soir (18h-00h)</option>
                </select>
            </div>
            
            <!-- Nombre de places -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de places</label>
                <div class="flex items-center space-x-2">
                    <button class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center">-</button>
                    <span class="w-8 text-center">1</span>
                    <button class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center">+</button>
                </div>
            </div>
            
            <!-- Prix maximum -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Prix maximum</label>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500">0€</span>
                    <input type="range" min="0" max="50" value="25" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                    <span class="text-sm text-gray-500">50€</span>
                </div>
                <div class="text-center mt-1 text-sm text-gray-600">Jusqu'à 25€</div>
            </div>
            
            <!-- Options -->
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
                    <label class="flex items-center">
                        <input type="checkbox" class="rounded text-orange-500 focus:ring-orange-500">
                        <span class="ml-2 text-sm text-gray-700">Musique autorisée</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" class="rounded text-orange-500 focus:ring-orange-500">
                        <span class="ml-2 text-sm text-gray-700">Trajet silencieux</span>
                    </label>
                </div>
            </div>
            
            <!-- Boutons -->
            <div class="pt-4 flex space-x-3">
                <button class="flex-1 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    Réinitialiser
                </button>
                <button class="flex-1 py-3 bg-orange-500 text-white font-medium rounded-lg hover:bg-orange-600 transition-colors">
                    Appliquer
                </button>
            </div>
        </div>
    </div>

    <script>
        // Initialisation de GSAP
        gsap.registerPlugin(ScrollTrigger);
        
        // Variables globales pour la carte
        let map, departureMarker, destinationMarker, routeLine;
        let isMapExpanded = false;
        
        // Initialisation de la carte Leaflet
        function initMap() {
            // Centrer sur la France
            map = L.map('map').setView([46.603354, 1.888334], 6);
            
            // Ajouter la couche de tuiles OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Ajouter des marqueurs par défaut
            departureMarker = L.marker([48.8566, 2.3522]).addTo(map)
                .bindPopup('Départ: Paris')
                .openPopup();
                
            destinationMarker = L.marker([45.7640, 4.8357]).addTo(map)
                .bindPopup('Arrivée: Lyon');
                
            // Ajouter une ligne pour simuler l'itinéraire
            routeLine = L.polyline([
                [48.8566, 2.3522],
                [45.7640, 4.8357]
            ], {color: 'orange', weight: 4}).addTo(map);
            
            // Ajuster la vue pour afficher l'ensemble de l'itinéraire
            map.fitBounds(routeLine.getBounds());
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
        });
        
        // Gestion des filtres mobile
        const mobileFilterToggle = document.getElementById('mobileFilterToggle');
        const closeMobileFilters = document.getElementById('closeMobileFilters');
        const mobileFilters = document.getElementById('mobileFilters');
        
        mobileFilterToggle.addEventListener('click', function() {
            mobileFilters.classList.remove('hidden');
        });
        
        closeMobileFilters.addEventListener('click', function() {
            mobileFilters.classList.add('hidden');
        });
        
        // Animation des avatars de passagers
        const passengerAvatars = document.querySelectorAll('.passenger-avatar');
        passengerAvatars.forEach(avatar => {
            avatar.addEventListener('mouseenter', function() {
                gsap.to(this, { scale: 1.1, duration: 0.2 });
            });
            
            avatar.addEventListener('mouseleave', function() {
                gsap.to(this, { scale: 1, duration: 0.2 });
            });
        });
        
        // Gestion des options de localisation
        const locationOptions = document.querySelectorAll('.location-option');
        const customAddressDeparture = document.getElementById('customAddressDeparture');
        const customAddressDestination = document.getElementById('customAddressDestination');
        const departureText = document.getElementById('departureText');
        const destinationText = document.getElementById('destinationText');
        const mapOverlay = document.getElementById('mapOverlay');
        const expandMapBtn = document.getElementById('expandMap');
        const mapElement = document.getElementById('map');
        
        locationOptions.forEach(option => {
            option.addEventListener('click', function() {
                const role = this.getAttribute('data-role');
                const type = this.getAttribute('data-type');
                
                // Désactiver toutes les options du même rôle
                document.querySelectorAll(`.location-option[data-role="${role}"]`).forEach(opt => {
                    opt.classList.remove('active');
                });
                
                // Activer l'option sélectionnée
                this.classList.add('active');
                
                // Mettre à jour l'interface en fonction du type
                if (role === 'departure') {
                    if (type === 'current') {
                        customAddressDeparture.classList.add('hidden');
                        departureText.textContent = 'Position actuelle';
                        // Ici, vous récupéreriez la position GPS de l'utilisateur
                    } else if (type === 'establishment') {
                        customAddressDeparture.classList.add('hidden');
                        departureText.textContent = 'Cité des Métiers et Compétences';
                    } else if (type === 'custom') {
                        customAddressDeparture.classList.remove('hidden');
                        departureText.textContent = 'Adresse personnalisée';
                    }
                } else if (role === 'destination') {
                    if (type === 'establishment') {
                        customAddressDestination.classList.add('hidden');
                        destinationText.textContent = 'Université de Rabat';
                    } else if (type === 'custom') {
                        customAddressDestination.classList.remove('hidden');
                        destinationText.textContent = 'Adresse personnalisée';
                    }
                }
                
                // Mettre à jour la carte (simulation)
                updateMap();
            });
        });
        
        // Fonction pour mettre à jour la carte (simulation)
        function updateMap() {
            // Dans une implémentation réelle, vous utiliseriez un service de routage
            // comme OpenRouteService ou Google Maps Directions API
            console.log("Mise à jour de la carte avec les nouvelles positions");
        }
        
        // Gestion de l'expansion de la carte
        expandMapBtn.addEventListener('click', function() {
            if (!isMapExpanded) {
                // Agrandir la carte
                mapElement.classList.add('map-expanded');
                mapOverlay.classList.add('active');
                map.invalidateSize(); // Redimensionner la carte Leaflet
                isMapExpanded = true;
                expandMapBtn.innerHTML = '<i class="fas fa-compress"></i>';
            } else {
                // Réduire la carte
                mapElement.classList.remove('map-expanded');
                mapOverlay.classList.remove('active');
                map.invalidateSize(); // Redimensionner la carte Leaflet
                isMapExpanded = false;
                expandMapBtn.innerHTML = '<i class="fas fa-expand"></i>';
            }
        });
        
        // Fermer la carte agrandie en cliquant sur l'overlay
        mapOverlay.addEventListener('click', function() {
            if (isMapExpanded) {
                mapElement.classList.remove('map-expanded');
                mapOverlay.classList.remove('active');
                map.invalidateSize();
                isMapExpanded = false;
                expandMapBtn.innerHTML = '<i class="fas fa-expand"></i>';
            }
        });
        
        // Correction du sticky filters sur desktop
        if (window.innerWidth >= 1024) {
            const stickyElement = document.querySelector('.sticky-filters');
            const originalOffsetTop = stickyElement.offsetTop;
            
            function handleScroll() {
                if (window.pageYOffset >= originalOffsetTop) {
                    stickyElement.style.position = 'sticky';
                    stickyElement.style.top = '6rem';
                } else {
                    stickyElement.style.position = 'relative';
                    stickyElement.style.top = 'auto';
                }
            }
            
            window.addEventListener('scroll', handleScroll);
        }
    </script>
</body>
</html>