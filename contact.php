<?php
/**
 * EduConnect - Contact
 * Contact form and support information
 */

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $subject = htmlspecialchars($_POST['subject'] ?? '');
    $message_text = htmlspecialchars($_POST['message'] ?? '');
    
    // Validate inputs
    if ($name && $email && $subject && $message_text) {
        // Here you would typically send an email or save to database
        $message = 'Merci pour votre message! Nous vous répondrons bientôt.';
    } else {
        $message = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="Assets/css/stylemanus.css">
    <title>Contact - EduConnect</title>
</head>

<body>

    <!-- Navigation -->
    <nav>
        <a href="index.php" class="logo">
            <i class='bx bxs-graduation'></i>
            Edu <span>Connect</span>
        </a>
        <div class="links">
            <a href="index.php">Accueil</a>
            <a href="services.php">Services</a>
            <a href="apropos.php">À Propos</a>
            <a href="contact.php" class="active">Contact</a>
        </div>
        <div class="login">
            <button class="signup"><a href="Views/Auth/login.php">Connexion</a></button>
            <button><a href="Views/Auth/register.php">S'inscrire</a></button>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <h1>Nous Contacter</h1>
            <p>Avez-vous des questions? Nous serions heureux de vous aider.</p>
        </div>
    </header>

    <!-- Contact Information -->
    <h2 class="separator">
        Informations de Contact
    </h2>

    <div class="contact-info">
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-map'></i>
            </div>
            <h4>Adresse</h4>
            <p>123 Rue de l'XXXXXX <br> Dakar,Senegal , 14000 <p>
        </div>
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-phone'></i>
            </div>
            <h4>Téléphone</h4>
            <p>+221 33 XXX XXXX<br>+221 77 XXX XXXX</p>
        </div>
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-envelope'></i>
            </div>
            <h4>Email</h4>
            <p>support@educonnect.sn<br>info@educonnect.sn</p>
        </div>
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-time'></i>
            </div>
            <h4>Heures d'Ouverture</h4>
            <p>Lun - Ven: 8h00 - 18h00<br>Sam: 9h00 - 14h00</p>
        </div>
    </div>

    <!-- Contact Form -->
    <h2 class="separator">
        Formulaire de Contact
    </h2>

    <div class="contact-form-container">
        <?php if ($message): ?>
            <div class="form-message">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="contact-form">
            <div class="form-group">
                <label for="name">Nom Complet</label>
                <input type="text" id="name" name="name" placeholder="Votre nom" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Votre email" required>
            </div>

            <div class="form-group">
                <label for="subject">Sujet</label>
                <select id="subject" name="subject" required>
                    <option value="">Sélectionnez un sujet</option>
                    <option value="support">Support Technique</option>
                    <option value="billing">Facturation</option>
                    <option value="partnership">Partenariat</option>
                    <option value="feedback">Retour d'Expérience</option>
                    <option value="other">Autre</option>
                </select>
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" placeholder="Votre message..." rows="6" required></textarea>
            </div>

            <button type="submit" class="submit-btn">Envoyer le Message</button>
        </form>
    </div>

    <!-- FAQ Section -->
    <h2 class="separator">
        Questions Fréquemment Posées
    </h2>

    <div class="faq">
        <div class="faq-item">
            <div class="question">
                <h4>Comment puis-je créer un compte?</h4>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="answer">
                <p>Cliquez sur le bouton "S'inscrire" en haut de la page et remplissez le formulaire d'inscription avec vos informations personnelles.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="question">
                <h4>Est-ce que mes données sont sécurisées?</h4>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="answer">
                <p>Oui, nous utilisons le chiffrement SSL et les meilleures pratiques de sécurité pour protéger vos données personnelles.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="question">
                <h4>Quel est le coût de la plateforme?</h4>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="answer">
                <p>EduConnect offre des plans gratuits et premium. Consultez notre page de tarification pour plus de détails.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="question">
                <h4>Comment puis-je réinitialiser mon mot de passe?</h4>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="answer">
                <p>Cliquez sur "Mot de passe oublié" sur la page de connexion et suivez les instructions envoyées à votre email.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="question">
                <h4>Puis-je utiliser EduConnect sur mon téléphone?</h4>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="answer">
                <p>Oui, EduConnect est entièrement responsive et fonctionne sur tous les appareils: ordinateurs, tablettes et téléphones.</p>
            </div>
        </div>

        <div class="faq-item">
            <div class="question">
                <h4>Comment puis-je contacter le support?</h4>
                <i class='bx bx-chevron-down'></i>
            </div>
            <div class="answer">
                <p>Vous pouvez nous contacter par email, téléphone ou en utilisant le formulaire de contact ci-dessus. Notre équipe répond généralement dans les 24 heures.</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <h3>Plateforme de Gestion Éducative Complète</h3>
        <div class="right">
            <div class="links">
                <a href="#">Politique de Confidentialité</a>
                <a href="#">Conditions d'Utilisation</a>
                <a href="#">Support</a>
                <a href="contact.php">Nous Contacter</a>
            </div>
            <div class="social">
                <i class='bx bxl-instagram'></i>
                <i class='bx bxl-facebook-square'></i>
                <i class='bx bxl-twitter'></i>
                <i class='bx bxl-linkedin'></i>
            </div>
            <p>Copyright © 2026 EduConnect, Tous Droits Réservés.</p>
        </div>
    </footer>

    <script>
        // FAQ Accordion
        document.querySelectorAll('.faq-item .question').forEach(question => {
            question.addEventListener('click', function() {
                const item = this.parentElement;
                item.classList.toggle('active');
            });
        });
    </script>

    <script src="Assets/js/animations.js"></script>
</body>

</html>
