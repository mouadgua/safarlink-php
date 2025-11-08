<?php
// navbar-desktop.php - Composant Navbar Desktop réutilisable
?>
<!-- Navbar en haut (Desktop) -->
<nav class="navbar-top d-none d-md-flex">
    <div class="nav-container-desktop">
        <a class="navbar-brand logo-desktop" href="index.php">Safar<span>Link</span></a>
        <div class="nav-links-desktop">
            <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">Accueil</a>
            <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'trips.php' ? 'active' : ''; ?>" href="trips.php">Trajets</a>
            <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'blog.php' ? 'active' : ''; ?>" href="blog.php">Blog</a>
            <a class="nav-link-desktop <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>" href="contact.php">Contact</a>
        </div>
        <div class="auth-buttons-desktop">
            <button class="btn-login" onclick="window.location.href='login.php'">Connexion</button>
            <button class="btn-signup" onclick="window.location.href='register.php'">Inscription</button>
        </div>
    </div>
</nav>

<style>
    /* Navbar en haut (Desktop) */
    .navbar-top {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: var(--primary);
        border-radius: 50px;
        padding: 12px 30px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        transition: var(--transition);
        border: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 700px;
        max-width: 90%;
    }
    
    .navbar-top:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        transform: translateX(-50%) translateY(-2px);
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
</style>