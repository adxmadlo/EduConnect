<?php
/**
 * EduConnect - Homepage
 * Modern educational platform for student management
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="Assets/css/stylemanus.css">
    <title>EduConnect - Plateforme de Gestion Éducative</title>
</head>

<body>

    <!-- Navigation -->
    <nav>
        <a href="index.php" class="logo">
            <i class='bx bxs-graduation'></i>
            Edu<span>Connect</span>
        </a>
        <div class="links">
            <a href="index.php" class="active">Accueil</a>
            <a href="services.php">Services</a>
            <a href="apropos.php">À Propos</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="login">
            <button class="signup"><a href="Views/Auth/login.php">Connexion</a></button>
            <button><a href="Views/Auth/register.php">S'inscrire</a></button>
        </div>
    </nav>

    <!-- Hero Header -->
    <header>
        <div class="left">
            <h1>Transformez votre <span>Expérience Éducative</span></h1>
            <p>Lorem, ipsum dolor sit amet consectetur adipisicing elit. Ea cum beatae amet sint incidunt! Alias explicabo tempora praesentium. Repellendus at temporibus perferendis voluptatibus nisi doloribus quisquam libero, dolor laborum ut!</p>
            <a href="Views.Auth.register.php">
                <i class='bx bx-play-circle'></i>
                <span>Se Connecter A Mon Compte</span>
            </a>
        </div>
        <!-- image a mettre plus tard -->
        <img src="Assets/images/hero.png" alt="EduConnect Hero">
    </header>

    <!-- Features Section -->
    <h2 class="separator">
        Fonctionnalités Principales
    </h2>

    <div class="features">
        <div class="item">
            <div class="header">
                <i class='bx bx-book-open'></i>
                <h5>Gestion des Cours</h5>
            </div>
            <p>Accédez à tous vos cours, consultez les horaires, téléchargez les matériaux pédagogiques et suivez votre progression académique.</p>
        </div>
        <div class="item">
            <div class="header">
                <i class='bx bx-bar-chart-alt-2'></i>
                <h5>Suivi des Notes</h5>
            </div>
            <p>Consultez vos notes en temps réel, suivez votre moyenne générale et recevez des alertes sur vos performances académiques.</p>
        </div>
        <div class="item">
            <div class="header">
                <i class='bx bx-credit-card'></i>
                <h5>Paiements Sécurisés</h5>
            </div>
            <p>Effectuez vos paiements de scolarité de manière sécurisée via Wave, Orange Money ou carte bancaire. Recevez des reçus instantanés.</p>
        </div>
    </div>

    <!-- Services Preview -->
    <h2 class="separator">
        Nos Services
    </h2>

    <div class="services-preview">
        <div class="category">
            <a href="services.php#mentoring">Mentorat</a>
            <a href="services.php#timetable">Emploi du Temps</a>
            <a href="services.php#rooms">Réservation de Salles</a>
            <a href="services.php#notifications">Notifications</a>
        </div>

        <div class="services-list">
            <div class="item">
                <div class="icon-box">
                    <i class='bx bx-user-check'></i>
                </div>
                <div class="info">
                    <h5>Mentorat Académique</h5>
                    <p>Connectez-vous avec des mentors expérimentés pour améliorer vos performances académiques et obtenir des conseils personnalisés.</p>
                </div>
            </div>
            <div class="item">
                <div class="icon-box">
                    <i class='bx bx-calendar'></i>
                </div>
                <div class="info">
                    <h5>Emploi du Temps Intelligent</h5>
                    <p>Consultez votre emploi du temps, recevez des rappels de cours et synchronisez vos événements avec votre calendrier personnel.</p>
                </div>
            </div>
            <div class="item">
                <div class="icon-box">
                    <i class='bx bx-door-open'></i>
                </div>
                <div class="info">
                    <h5>Réservation de Salles</h5>
                    <p>Réservez facilement les salles de classe disponibles pour vos projets de groupe et vos études collaboratives.</p>
                </div>
            </div>
            <div class="item">
                <div class="icon-box">
                    <i class='bx bx-bell'></i>
                </div>
                <div class="info">
                    <h5>Notifications en Temps Réel</h5>
                    <p>Restez informé de tous les événements importants : résultats d'examen, changements d'horaire, messages des enseignants.</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Statistics Section -->
    <h2 class="separator">
        Statistiques
    </h2>

    <div class="stats">
        <div class="stat-item">
            <h3>5,000+</h3>
            <p>Étudiants Actifs</p>
        </div>
        <div class="stat-item">
            <h3>200+</h3>
            <p>Enseignants</p>
        </div>
        <div class="stat-item">
            <h3>50+</h3>
            <p>Programmes d'Études</p>
        </div>
        <div class="stat-item">
            <h3>99.9%</h3>
            <p>Disponibilité</p>
        </div>
    </div>

    <!-- Testimonials Section -->
    <h2 class="separator">
        Témoignages
    </h2>

    <div class="testimonials">
        <div class="item">
            <div class="stars">
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
            </div>
                       <p>" Lorem ipsum, dolor sit amet consectetur adipisicing elit. Aliquid dignissimos fuga doloremque "</p>
            <div class="author">
                <img src="assets/avatar-1.png" alt="Aminata Diallo">
                <div>
                    <h5>Aminata Diallo</h5>
                    <p>Étudiante L2 Informatique</p>
                </div>
            </div>
        </div>
        <div class="item">
            <div class="stars">
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
            </div>
            <p>" Lorem ipsum, dolor sit amet consectetur adipisicing elit. Aliquid dignissimos fuga doloremque "</p>
            <div class="author">
                <img src="assets/avatar-2.png" alt="Ousmane Sow">
                <div>
                    <h5>Ousmane Sow</h5>
                    <p>Étudiant M1 Génie Logiciel</p>
                </div>
            </div>
        </div>
        <div class="item">
            <div class="stars">
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
                <i class='bx bxs-star'></i>
            </div>
            <p>" Lorem ipsum, dolor sit amet consectetur adipisicing elit. Aliquid dignissimos fuga doloremque "</p>
            <div class="author">
                <img src="assets/avatar-3.png" alt="Dr. Mamadou Ba">
                <div>
                    <h5>Dr. Mamadou Ba</h5>
                    <p>Professeur de Mathématiques</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="cta-section">
        <h2>Prêt à Transformer Votre Expérience Éducative?</h2>
        <p>Rejoignez des milliers d'étudiants et d'enseignants qui utilisent EduConnect pour réussir.</p>
        <a href="register.php" class="cta-button">S'inscrire Gratuitement</a>
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

    <script src="Assets/js/animations.js"></script>
</body>

</html>
