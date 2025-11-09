<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - SafarLink</title>
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
        
        .signup-container {
            background-color: var(--primary);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .signup-container:hover {
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
        
        .name-fields {
            display: flex;
            gap: 15px;
        }
        
        .name-fields .form-group {
            flex: 1;
        }
        
        .user-type {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .user-type-option {
            flex: 1;
            text-align: center;
        }
        
        .user-type-option input {
            display: none;
        }
        
        .user-type-label {
            display: block;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            background-color: #fafafa;
        }
        
        .user-type-option input:checked + .user-type-label {
            border-color: var(--secondary);
            background-color: rgba(255, 215, 0, 0.1);
            color: var(--accent);
        }
        
        .terms {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 25px;
        }
        
        .terms input {
            margin-top: 4px;
            accent-color: var(--secondary);
        }
        
        .terms label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .terms a {
            color: var(--accent);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .terms a:hover {
            color: var(--secondary);
        }
        
        .btn-signup {
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
        
        .btn-signup:hover {
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
        
        .social-signup {
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
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .login-link a:hover {
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
            .signup-container {
                padding: 30px 20px;
            }
            
            .name-fields {
                flex-direction: column;
                gap: 0;
            }
            
            .user-type {
                flex-direction: column;
                gap: 10px;
            }
            
            .social-signup {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="logo">
            <h1>Safar<span>Link</span></h1>
            <p class="text-muted">Créez votre compte</p>
        </div>
        
        <!-- Zone d'affichage des erreurs -->
        <div id="error-message" class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg hidden" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i><span id="error-text"></span>
        </div>

        <form id="signupForm">
            <div class="name-fields">
                <div class="form-group">
                    <label for="firstName" class="form-label">Prénom</label>
                    <input type="text" id="firstName" class="form-control" placeholder="Votre prénom" required>
                </div>
                <div class="form-group">
                    <label for="lastName" class="form-label">Nom</label>
                    <input type="text" id="lastName" class="form-control" placeholder="Votre nom" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Adresse email</label>
                <input type="email" id="email" class="form-control" placeholder="votre@email.com" required>
            </div>
            
            <div class="form-group">
                <label for="phone" class="form-label">Téléphone</label>
                <input type="tel" id="phone" class="form-control" placeholder="+33 1 23 45 67 89">
            </div>
            
            <div class="user-type">
                <div class="user-type-option">
                    <input type="radio" id="passenger" name="userType" value="passenger" checked>
                    <label for="passenger" class="user-type-label">
                        <i class="fas fa-user me-1"></i> Passager
                    </label>
                </div>
                <div class="user-type-option">
                    <input type="radio" id="driver" name="userType" value="driver">
                    <label for="driver" class="user-type-label">
                        <i class="fas fa-car me-1"></i> Conducteur
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Mot de passe</label>
                <div class="password-input">
                    <input type="password" id="password" class="form-control" placeholder="Créez un mot de passe" required>
                    <button type="button" class="toggle-password" id="togglePassword">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                <div class="password-input">
                    <input type="password" id="confirmPassword" class="form-control" placeholder="Confirmez votre mot de passe" required>
                    <button type="button" class="toggle-password" id="toggleConfirmPassword">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="terms">
                <input type="checkbox" id="terms" required>
                <label for="terms">
                    J'accepte les <a href="#">conditions d'utilisation</a> et la <a href="#">politique de confidentialité</a>
                </label>
            </div>
            
            <button type="submit" class="btn-signup">Créer mon compte</button>
        </form>
        
        <div class="divider">
            <span>Ou inscrivez-vous avec</span>
        </div>
        
        <div class="social-signup">
            <button class="social-btn google-btn">
                <i class="fab fa-google" style="color: #DB4437;"></i>
                <span>Google</span>
            </button>
            <button class="social-btn facebook-btn">
                <i class="fab fa-facebook-f" style="color: #4267B2;"></i>
                <span>Facebook</span>
            </button>
        </div>
        
        <div class="login-link">
            <p>Vous avez déjà un compte ? <a href="login.php">Se connecter</a></p>
        </div>
        
        <div class="back-home">
            <a href="index.php"><i class="fas fa-arrow-left me-1"></i> Retour à l'accueil</a>
        </div>
    </div>

    <script>
        // Afficher/Masquer les mots de passe
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
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const icon = this.querySelector('i');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Gestion du formulaire
        document.getElementById('signupForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const submitButton = this.querySelector('button[type="submit"]');
            const errorDiv = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            
            if (password !== confirmPassword) {
                errorText.textContent = 'Les mots de passe ne correspondent pas !';
                errorDiv.classList.remove('hidden');
                return;
            }

            if (password.length < 6) {
                errorText.textContent = 'Le mot de passe doit contenir au moins 6 caractères.';
                errorDiv.classList.remove('hidden');
                submitButton.disabled = false;
                return;
            }

            const formData = {
                email: document.getElementById('email').value,
                password: password,
                firstName: document.getElementById('firstName').value,
                lastName: document.getElementById('lastName').value,
                phone: document.getElementById('phone').value,
                userType: document.querySelector('input[name="userType"]:checked').value
            };

            submitButton.disabled = true;
            submitButton.textContent = 'Création du compte...';

            try {
                const response = await fetch('api/register_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (response.ok) {
                    errorDiv.classList.add('hidden');
                    alert('Inscription réussie ! Veuillez vérifier votre email pour activer votre compte. Vous allez être redirigé vers la page de connexion.');
                    window.location.href = 'login.php';
                } else {
                    let errorMessage = 'Une erreur est survenue.';
                    errorMessage = data.msg || data.message || data.error_description || errorMessage;
                    
                    errorText.textContent = errorMessage;
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                errorText.textContent = 'Une erreur réseau est survenue. Veuillez réessayer.';
                errorDiv.classList.remove('hidden');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Créer mon compte';
            }
        });
    </script>
</body>
</html>