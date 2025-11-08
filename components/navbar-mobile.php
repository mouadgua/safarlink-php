<?php
// navbar-mobile.php - Composant Navbar Mobile réutilisable
?>
<!-- Navbar en bas (Mobile) -->
<nav class="navbar-bottom d-md-none">
    <div class="nav-container">
        <div class="nav-links">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-home"></i>
                <span>Accueil</span>
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'trips.php' ? 'active' : ''; ?>" href="trips.php">
                <i class="fas fa-route"></i>
                <span>Trajets</span>
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'blog.php' ? 'active' : ''; ?>" href="blog.php">
                <i class="fas fa-comment"></i>
                <span>Blog</span>
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'compte.php' ? 'active' : ''; ?>" href="compte.php">
                <i class="fas fa-user"></i>
                <span>Compte</span>
            </a>
        </div>
    </div>
</nav>

<style>
    /* Navbar en bas (Mobile) */
    .navbar-bottom {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: var(--primary);
        border-radius: 50px;
        padding: 12px 20px;
        box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        transition: var(--transition);
        border: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 90%;
        max-width: 500px;
    }
    
    .navbar-bottom:hover {
        box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.15);
        transform: translateX(-50%) translateY(-2px);
    }
    
    .nav-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }
    
    .nav-links {
        display: flex;
        align-items: center;
        justify-content: space-around;
        flex-grow: 1;
    }
    
    .nav-link {
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
    
    .nav-link i {
        font-size: 1.1rem;
    }
    
    .nav-link:hover {
        color: var(--secondary);
        background-color: rgba(255, 215, 0, 0.1);
    }
    
    .nav-link.active {
        color: var(--secondary);
        background-color: rgba(255, 215, 0, 0.15);
    }
</style>