<?php
// navbar.php - Composant Navbar unifié responsive
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
?>

<!-- Navbar Unifiée -->
<nav class="navbar-unified">
    <!-- Version Desktop -->
    <div class="navbar-desktop">
        <div class="nav-container-desktop">
            <a class="navbar-brand logo-desktop" href="index.php">Safar<span>Link</span></a>
            <div class="nav-links-desktop">
                <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">Accueil</a>
                <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'trips.php' ? 'active' : ''; ?>" href="trips.php">Trajets</a>
                <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'blog.php' ? 'active' : ''; ?>" href="blog.php">Blog</a>
                <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>" href="contact.php">Contact</a>
            </div>
            <?php if (isset($_SESSION['user']) && isset($_SESSION['logged_in'])): ?>
            <div class="auth-buttons-desktop">
                <button class="btn-login" onclick="window.location.href='profile.php'">Mon Profil</button>
                <button class="btn-signup" onclick="window.location.href='logout.php'">Déconnexion</button>
            </div>
            <?php else: ?>
            <div class="auth-buttons-desktop">
                <button class="btn-login" onclick="window.location.href='login.php'">Connexion</button>
                <button class="btn-signup" onclick="window.location.href='register.php'">Inscription</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Version Mobile -->
    <div class="navbar-mobile">
        <div class="nav-container-mobile">
            <div class="nav-links-mobile">
                <a class="nav-link-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-home"></i>
                    <span>Accueil</span>
                </a>
                <a class="nav-link-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'trips.php' ? 'active' : ''; ?>" href="trips.php">
                    <i class="fas fa-route"></i>
                    <span>Trajets</span>
                </a>
                <a class="nav-link-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'blog.php' ? 'active' : ''; ?>" href="blog.php">
                    <i class="fas fa-comment"></i>
                    <span>Blog</span>
                </a>
                <?php if (isset($_SESSION['user']) && isset($_SESSION['logged_in'])): ?>
                <a class="nav-link-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </a>
                <?php else: ?>
                <a class="nav-link-mobile <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>" href="login.php">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Compte</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Variables CSS */
    :root {
        --primary: #ffffff;
        --secondary: #ffa215;
        --accent: #000000;
        --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Navbar Unifiée */
    .navbar-unified {
        position: fixed;
        z-index: 1000;
        width: 100%;
    }

    /* Version Desktop */
    .navbar-desktop {
        display: none;
    }

    /* Version Mobile */
    .navbar-mobile {
        display: block;
    }

    /* Styles Desktop */
    @media (min-width: 768px) {
        .navbar-desktop {
            display: block;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--primary);
            border-radius: 50px;
            padding: 12px 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
            min-width: 700px;
            max-width: 90%;
        }
        
        .navbar-desktop:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateX(-50%) translateY(-2px);
        }
        
        .navbar-mobile {
            display: none;
        }
        
        .nav-container-desktop {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .nav-links-desktop {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
        }
        
        .nav-link-desktop {
            color: var(--accent);
            font-weight: 500;
            margin: 0 12px;
            padding: 8px 16px;
            border-radius: 20px;
            transition: var(--transition);
            text-align: center;
            font-size: 0.95rem;
            text-decoration: none;
        }
        
        .nav-link-desktop:hover {
            color: var(--secondary);
            background-color: rgba(255, 162, 21, 0.1);
        }
        
        .nav-link-desktop.active {
            color: var(--secondary);
            background-color: rgba(255, 162, 21, 0.15);
        }
        
        .logo-desktop {
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--accent);
            letter-spacing: -0.5px;
            margin-right: 20px;
            text-decoration: none;
        }
        
        .logo-desktop span {
            color: var(--secondary);
        }
        
        .auth-buttons-desktop {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 20px;
        }
        
        .btn-login {
            background-color: transparent;
            color: var(--accent);
            border: 1px solid var(--secondary);
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .btn-login:hover {
            background-color: rgba(255, 162, 21, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-signup {
            background-color: var(--secondary);
            color: var(--accent);
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            box-shadow: 0 2px 10px rgba(255, 162, 21, 0.3);
            cursor: pointer;
        }
        
        .btn-signup:hover {
            background-color: #ffb533;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 162, 21, 0.4);
        }
    }

    /* Styles Mobile */
    @media (max-width: 767px) {
        .navbar-mobile {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--primary);
            border-radius: 50px;
            padding: 12px 20px;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
            width: 90%;
            max-width: 500px;
        }
        
        .navbar-mobile:hover {
            box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.15);
            transform: translateX(-50%) translateY(-2px);
        }
        
        .nav-container-mobile {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        
        .nav-links-mobile {
            display: flex;
            align-items: center;
            justify-content: space-around;
            flex-grow: 1;
        }
        
        .nav-link-mobile {
            color: var(--accent);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 20px;
            transition: var(--transition);
            text-align: center;
            font-size: 0.85rem;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        
        .nav-link-mobile i {
            font-size: 1.1rem;
        }
        
        .nav-link-mobile:hover {
            color: var(--secondary);
            background-color: rgba(255, 215, 0, 0.1);
        }
        
        .nav-link-mobile.active {
            color: var(--secondary);
            background-color: rgba(255, 215, 0, 0.15);
        }
    }

    /* Ajustement du main pour la navbar */
    main {
        padding-top: 80px;
        padding-bottom: 100px;
    }

    @media (min-width: 768px) {
        main {
            padding-top: 100px;
            padding-bottom: 40px;
        }
    }
</style>