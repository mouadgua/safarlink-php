<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - SafarLink</title>
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
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #f8f9fa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background-color: var(--primary);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-weight: 700;
            font-size: 2.5rem;
            color: var(--accent);
            letter-spacing: -0.5px;
        }
        
        .logo span {
            color: var(--secondary);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--accent);
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: #fafafa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
            background-color: var(--primary);
        }
        
        .password-input {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--accent);
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember-me input {
            accent-color: var(--secondary);
        }
        
        .forgot-password {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .forgot-password:hover {
            color: var(--secondary);
        }
        
        .btn-login {
            background-color: var(--secondary);
            color: var(--accent);
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
            width: 100%;
            cursor: pointer;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            background-color: #ffdf33;
        }
        
        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
            color: #666;
        }
        
        .divider::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #e0e0e0;
        }
        
        .divider span {
            background-color: var(--primary);
            padding: 0 15px;
            position: relative;
            z-index: 1;
        }
        
        .social-login {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .social-btn {
            flex: 1;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .google-btn:hover {
            border-color: #DB4437;
        }
        
        .facebook-btn:hover {
            border-color: #4267B2;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .signup-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .signup-link a:hover {
            color: var(--secondary);
        }
        
        .back-home {
            text-align: center;
            margin-top: 15px;
        }
        
        .back-home a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .back-home a:hover {
            color: var(--accent);
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .social-login {
                flex-direction: column;
            }
            
            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Safar<span>Link</span></h1>
            <p class="text-muted">Connectez-vous à votre compte</p>
        </div>
        
        <form id="loginForm">
            <div class="form-group">
                <label for="email" class="form-label">Adresse email</label>
                <input type="email" id="email" class="form-control" placeholder="votre@email.com" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Mot de passe</label>
                <div class="password-input">
                    <input type="password" id="password" class="form-control" placeholder="Votre mot de passe" required>
                    <button type="button" class="toggle-password" id="togglePassword">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="remember-forgot">
                <div class="remember-me">
                    <input type="checkbox" id="remember">
                    <label for="remember">Se souvenir de moi</label>
                </div>
                <a href="#" class="forgot-password">Mot de passe oublié ?</a>
            </div>
            
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        
        <div class="divider">
            <span>Ou connectez-vous avec</span>
        </div>
        
        <div class="social-login">
            <button class="social-btn google-btn">
                <i class="fab fa-google" style="color: #DB4437;"></i>
                <span>Google</span>
            </button>
            <button class="social-btn facebook-btn">
                <i class="fab fa-facebook-f" style="color: #4267B2;"></i>
                <span>Facebook</span>
            </button>
        </div>
        
        <div class="signup-link">
            <p>Vous n'avez pas de compte ? <a href="register.php">S'inscrire</a></p>
        </div>
        
        <div class="back-home">
            <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Retour à l'accueil</a>
        </div>
    </div>

    <script>
        // Afficher/Masquer le mot de passe
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Gestion du formulaire
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Connexion en cours...';

            try {
                const response = await fetch('api/login_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (response.ok) {
                    alert('Connexion réussie ! Vous allez être redirigé.');
                    window.location.href = 'index.php';
                } else {
                    alert(`Erreur: ${data.error_description || data.error || 'Une erreur est survenue.'}`);
                }
            } catch (error) {
                alert('Une erreur réseau est survenue. Veuillez réessayer.');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Se connecter';
            }
        });
    </script>
</body>
</html>