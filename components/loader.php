<?php
// loader.php - Composant Loader réutilisable
?>
<!-- Loader Smooth -->
<div class="loader-container" id="loader">
    <div class="loader"></div>
    <div class="loader-logo">Safar<span>Link</span></div>
</div>

<style>
    /* Loader Smooth */
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
</style>

<script>
    // Script pour le loader
    document.addEventListener('DOMContentLoaded', function() {
        const loader = document.getElementById('loader');
        
        // Masquer le loader après le chargement de la page
        window.addEventListener('load', function() {
            setTimeout(function() {
                loader.classList.add('hidden');
            }, 800); // Délai pour voir l'animation
        });
        
        // Fallback au cas où l'événement load ne se déclenche pas
        setTimeout(function() {
            loader.classList.add('hidden');
        }, 3000);
    });
</script>