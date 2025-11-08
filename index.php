<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafarLink - Covoiturage intelligent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ffffff;
            --secondary: #ffa215ff;
            --accent: #000000;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
            font-weight: 400;
            padding-bottom: 80px; /* Espace pour la navbar en bas */
        }
        
        .logo {
            display: none; /* Caché sur mobile */
        }
        
        .auth-buttons {
            display: none; /* Caché sur mobile */
        }
        
        /* Hero Section */
        .hero-section {
            height: 100vh;
            background: linear-gradient(135deg, var(--primary) 0%, #f8f9fa 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 0 15px;
        }
        
        .hero-content {
            text-align: center;
            max-width: 800px;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--accent);
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
            margin-bottom: 30px;
            color: #666;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 400;
        }
        
        .btn-primary-custom {
            background-color: var(--secondary);
            color: var(--accent);
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            background-color: #ffdf33;
        }
        
        /* Features Section */
        .features-section {
            padding: 80px 0;
            background-color: var(--primary);
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 50px;
            color: var(--accent);
        }
        
        .feature-card {
            background-color: var(--primary);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 20px;
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--accent);
        }
        
        /* How It Works Section */
        .how-it-works {
            padding: 80px 0;
            background-color: #f8f9fa;
        }
        
        .step-card {
            background-color: var(--primary);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            height: 100%;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .step-number {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 40px;
            background-color: var(--secondary);
            color: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        /* Testimonials Section */
        .testimonials-section {
            padding: 80px 0;
            background-color: var(--primary);
        }
        
        .testimonial-card {
            background-color: var(--primary);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .testimonial-text {
            font-style: italic;
            margin-bottom: 20px;
            color: #555;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--accent);
            font-weight: 600;
        }
        
        /* Footer */
        .footer {
            background-color: var(--accent);
            color: var(--primary);
            padding: 50px 0 30px;
        }
        
        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .footer-links h5 {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: var(--secondary);
        }
        
        .footer-links ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 8px;
        }
        
        .footer-links a {
            color: #ccc;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: var(--secondary);
        }
        
        .social-icons a {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            margin-right: 10px;
            color: var(--primary);
            transition: var(--transition);
        }
        
        .social-icons a:hover {
            background-color: var(--secondary);
            color: var(--accent);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #999;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        /* Desktop Styles */
        @media (min-width: 768px) {
            body {
                padding-bottom: 0; /* Retire l'espace pour la navbar en bas sur desktop */
            }
            
            .hero-title {
                font-size: 3.5rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
            
            .feature-card, .step-card, .testimonial-card {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
        <?php include("components/loader.php")?>

    <!-- Inclusion de la Navbar Desktop -->
    <?php include 'components/navbar-desktop.php'; ?>

    <!-- Inclusion de la Navbar Mobile -->
    <?php include 'components/navbar-mobile.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section" id="accueil">
        <div class="hero-content fade-in-up">
            <h1 class="hero-title">Voyagez malin avec <span style="color: var(--secondary);">SafarLink</span></h1>
            <p class="hero-subtitle">Notre plateforme de covoiturage connecte conducteurs et passagers pour des trajets économiques, écologiques et conviviaux. Rejoignez notre communauté dès aujourd'hui !</p>
            <div class="mt-4">
                <button class="btn btn-primary-custom me-2 mb-2" onclick="window.location.href='inscription.php'">Commencer maintenant</button>
                <button class="btn btn-outline-dark rounded-pill px-4 mb-2">En savoir plus</button>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="fonctionnalites">
        <div class="container">
            <h2 class="section-title">Nos Fonctionnalités</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <h3 class="feature-title">Trajets Optimisés</h3>
                        <p>Notre algorithme intelligent trouve les trajets les plus efficaces pour vous faire gagner du temps et de l'argent.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="feature-title">Sécurité Garantie</h3>
                        <p>Vérification des profils, système d'évaluation et assistance 24/7 pour votre tranquillité d'esprit.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <h3 class="feature-title">Écologique</h3>
                        <p>Réduisez votre empreinte carbone en partageant vos trajets et en favorisant une mobilité durable.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="comment-ca-marche">
        <div class="container">
            <h2 class="section-title">Comment ça marche</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h3 class="feature-title">Inscrivez-vous</h3>
                        <p>Créez votre compte en quelques minutes avec une vérification simple et sécurisée.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h3 class="feature-title">Planifiez votre trajet</h3>
                        <p>Indiquez votre point de départ et votre destination, puis choisissez votre option.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h3 class="feature-title">Voyagez</h3>
                        <p>Rencontrez votre conducteur ou passager et profitez d'un trajet agréable et économique.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section" id="temoignages">
        <div class="container">
            <h2 class="section-title">Ce que disent nos utilisateurs</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"SafarLink a révolutionné mes trajets quotidiens. Je fais des économies substantielles tout en rencontrant des personnes intéressantes."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">MJ</div>
                            <div>
                                <h5>Marie J.</h5>
                                <p>Utilisatrice depuis 2022</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"En tant que conducteur régulier, SafarLink me permet de rentabiliser mes déplacements. L'application est intuitive et fiable."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">TP</div>
                            <div>
                                <h5>Thomas P.</h5>
                                <p>Conducteur depuis 2021</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">"La fonction de planification de trajet est exceptionnelle. Je trouve toujours un covoiturage qui correspond à mon emploi du temps."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">SL</div>
                            <div>
                                <h5>Sophie L.</h5>
                                <p>Étudiante utilisatrice</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="footer-logo">Safar<span style="color: var(--secondary);">Link</span></div>
                    <p>La plateforme de covoiturage qui simplifie vos déplacements tout en respectant l'environnement.</p>
                    <div class="social-icons mt-4">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <div class="footer-links">
                        <h5>Liens rapides</h5>
                        <ul>
                            <li><a href="#accueil">Accueil</a></li>
                            <li><a href="#fonctionnalites">Fonctionnalités</a></li>
                            <li><a href="#comment-ca-marche">Comment ça marche</a></li>
                            <li><a href="#temoignages">Témoignages</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="footer-links">
                        <h5>Légal</h5>
                        <ul>
                            <li><a href="#">Conditions d'utilisation</a></li>
                            <li><a href="#">Politique de confidentialité</a></li>
                            <li><a href="#">Cookies</a></li>
                            <li><a href="#">Mentions légales</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="footer-links">
                        <h5>Contact</h5>
                        <ul>
                            <li><i class="fas fa-map-marker-alt me-2"></i> Paris, France</li>
                            <li><i class="fas fa-phone me-2"></i> +33 1 23 45 67 89</li>
                            <li><i class="fas fa-envelope me-2"></i> contact@safarlink.fr</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 SafarLink. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation au défilement
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.feature-card, .step-card, .testimonial-card');
            
            const fadeInOnScroll = function() {
                fadeElements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const elementVisible = 150;
                    
                    if (elementTop < window.innerHeight - elementVisible) {
                        element.classList.add('fade-in-up');
                    }
                });
            };
            
            // Déclencher une première fois au chargement
            fadeInOnScroll();
            
            // Puis à chaque défilement
            window.addEventListener('scroll', fadeInOnScroll);
            
            // Navigation fluide
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>