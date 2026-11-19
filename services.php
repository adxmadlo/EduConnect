<?php
/**
 * EduConnect - Services
 * Detailed service descriptions and features
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="Assets/css/stylemanus.css">
    <title>Services - EduConnect</title>
</head>

<body>

    <!-- Navigation -->
    <nav>
        <a href="index.php" class="logo">
            <i class='bx bxs-graduation'></i>
            Edu<span>Connect</span>
        </a>
        <div class="links">
            <a href="index.php">Accueil</a>
            <a href="services.php" class="active">Services</a>
            <a href="apropos.php">À Propos</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="login">
            <button class="signup"><a href="Views/Auth/login.php">Connexion</a></button>
            <button><a href="Views/Auth/register.php">S'inscrire</a></button>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <h1>Nos Services</h1>
            <p>Découvrez toutes les fonctionnalités d'EduConnect</p>
        </div>
    </header>

    <!-- Main Services -->
    <h2 class="separator">
        Services Principaux
    </h2>

    <div class="services-main">
        <div class="service-card" id="mentoring">
            <div class="service-icon">
                <i class='bx bx-user-check'></i>
            </div>
            <h3>Mentorat Académique</h3>
            <p>Connectez-vous avec des mentors expérimentés pour améliorer vos performances académiques et obtenir des conseils personnalisés.</p>
            <div class="features-list">
                <ul>
                    <li><i class='bx bx-check'></i> Matching automatique avec des mentors</li>
                    <li><i class='bx bx-check'></i> Sessions de mentorat en ligne</li>
                    <li><i class='bx bx-check'></i> Suivi des progrès</li>
                    <li><i class='bx bx-check'></i> Ressources d'apprentissage</li>
                    <li><i class='bx bx-check'></i> Certificats de mentorat</li>
                </ul>
            </div>
        </div>

        <div class="service-card" id="timetable">
            <div class="service-icon">
                <i class='bx bx-calendar'></i>
            </div>
            <h3>Emploi du Temps Intelligent</h3>
            <p>Consultez votre emploi du temps, recevez des rappels de cours et synchronisez vos événements avec votre calendrier personnel.</p>
            <div class="features-list">
                <ul>
                    <li><i class='bx bx-check'></i> Vue calendrier complète</li>
                    <li><i class='bx bx-check'></i> Notifications de cours</li>
                    <li><i class='bx bx-check'></i> Synchronisation avec Google Calendar</li>
                    <li><i class='bx bx-check'></i> Changements d'horaire en temps réel</li>
                    <li><i class='bx bx-check'></i> Planification des études</li>
                </ul>
            </div>
        </div>

        <div class="service-card" id="rooms">
            <div class="service-icon">
                <i class='bx bx-door-open'></i>
            </div>
            <h3>Réservation de Salles</h3>
            <p>Réservez facilement les salles de classe disponibles pour vos projets de groupe et vos études collaboratives.</p>
            <div class="features-list">
                <ul>
                    <li><i class='bx bx-check'></i> 36 salles disponibles</li>
                    <li><i class='bx bx-check'></i> Réservation en temps réel</li>
                    <li><i class='bx bx-check'></i> Affichage de la disponibilité</li>
                    <li><i class='bx bx-check'></i> Confirmations instantanées</li>
                    <li><i class='bx bx-check'></i> Historique des réservations</li>
                </ul>
            </div>
        </div>

        <div class="service-card" id="notifications">
            <div class="service-icon">
                <i class='bx bx-bell'></i>
            </div>
            <h3>Notifications en Temps Réel</h3>
            <p>Restez informé de tous les événements importants : résultats d'examen, changements d'horaire, messages des enseignants.</p>
            <div class="features-list">
                <ul>
                    <li><i class='bx bx-check'></i> Notifications personnalisées</li>
                    <li><i class='bx bx-check'></i> Alertes d'examen</li>
                    <li><i class='bx bx-check'></i> Messages des enseignants</li>
                    <li><i class='bx bx-check'></i> Rappels de paiement</li>
                    <li><i class='bx bx-check'></i> Notifications mobiles</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Additional Services -->
    <h2 class="separator">
        Services Additionnels
    </h2>

    <div class="additional-services">
        <div class="service-item">
            <div class="header">
                <i class='bx bx-book-open'></i>
                <h5>Gestion des Cours</h5>
            </div>
            <br>
            <p>Accédez à tous vos cours, consultez les horaires, téléchargez les matériaux pédagogiques et suivez votre progression académique en temps réel.</p>
        </div>

        <div class="service-item">
            <div class="header">
                <i class='bx bx-bar-chart-alt-2'></i>
                <h5>Suivi des Notes</h5>
            </div>
            <br>
            <p>Consultez vos notes en temps réel, suivez votre moyenne générale et recevez des alertes sur vos performances académiques.</p>
        </div>

        <div class="service-item">
            <div class="header">
                <i class='bx bx-credit-card'></i>
                <h5>Paiements Sécurisés</h5>
            </div>
            <br>
            <p>Effectuez vos paiements de scolarité de manière sécurisée via Wave, Orange Money ou carte bancaire. Recevez des reçus instantanés.</p>
        </div>

        <div class="service-item">
            <div class="header">
                <i class='bx bx-message-square-detail'></i>
                <h5>Communication</h5>
            </div>
            <br>
            <p>Communiquez facilement avec vos enseignants, vos camarades de classe et l'administration via la plateforme intégrée.</p>
        </div>

        <div class="service-item">
            <div class="header">
                <i class='bx bx-file'></i>
                <h5>Gestion des Documents</h5>
            </div>
            <br>
            <p>Stockez et organisez tous vos documents académiques, certificats et relevés de notes en un seul endroit sécurisé.</p>
        </div>

        <div class="service-item">
            <div class="header">
                <i class='bx bx-chart'></i>
                <br>
                <h5>Analyses et Rapports</h5>
            </div>
            <br>
            <p>Obtenez des analyses détaillées de vos performances académiques et des rapports personnalisés pour améliorer vos résultats.</p>
        </div>
    </div>

    <!-- Pricing Plans -->
    <h2 class="separator">
        Plans de Tarification
    </h2>

    <div class="pricing-plans">
        <div class="plan">
            <div class="plan-header">
                <h3>Plan Gratuit</h3>
                <p class="price">Gratuit</p>
            </div>
            <ul class="plan-features">
                <li><i class='bx bx-check'></i> Accès aux cours</li>
                <li><i class='bx bx-check'></i> Suivi des notes</li>
                <li><i class='bx bx-check'></i> Emploi du temps</li>
                <li><i class='bx bx-check'></i> Mentorat (limité)</li>
                <li><i class='bx bx-x'></i> Réservation de salles</li>
                <li><i class='bx bx-x'></i> Support prioritaire</li>
            </ul>
            <button class="plan-btn">Commencer</button>
        </div>

        <div class="plan featured">
            <div class="badge">Populaire</div>
            <div class="plan-header">
                <h3>Plan Étudiant</h3>
                <p class="price">5,000 CFA<span>/mois</span></p>
            </div>
            <ul class="plan-features">
                <li><i class='bx bx-check'></i> Accès complet aux cours</li>
                <li><i class='bx bx-check'></i> Suivi des notes détaillé</li>
                <li><i class='bx bx-check'></i> Mentorat académique</li>
                <li><i class='bx bx-check'></i> Réservation de salles</li>
                <li><i class='bx bx-check'></i> Paiements sécurisés</li>
                <li><i class='bx bx-check'></i> Support prioritaire</li>
            </ul>
            <button class="plan-btn featured-btn">S'abonner</button>
        </div>

        <div class="plan">
            <div class="plan-header">
                <h3>Plan Enseignant</h3>
                <p class="price">10,000 CFA<span>/mois</span></p>
            </div>
            <ul class="plan-features">
                <li><i class='bx bx-check'></i> Gestion complète des cours</li>
                <li><i class='bx bx-check'></i> Gestion des notes</li>
                <li><i class='bx bx-check'></i> Communication avec étudiants</li>
                <li><i class='bx bx-check'></i> Analyses de classe</li>
                <li><i class='bx bx-check'></i> Ressources pédagogiques</li>
                <li><i class='bx bx-check'></i> Support dédié</li>
            </ul>
            <button class="plan-btn">S'abonner</button>
        </div>
    </div>

    <!-- Comparison Table -->
    <h2 class="separator">
        Comparaison des Plans
    </h2>

    <div class="comparison-table">
        <table>
            <thead>
                <tr>
                    <th>Fonctionnalité</th>
                    <th>Gratuit</th>
                    <th>Étudiant</th>
                    <th>Enseignant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Accès aux cours</td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                </tr>
                <tr>
                    <td>Suivi des notes</td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                </tr>
                <tr>
                    <td>Mentorat académique</td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                </tr>
                <tr>
                    <td>Réservation de salles</td>
                    <td><i class='bx bx-x-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                </tr>
                <tr>
                    <td>Paiements sécurisés</td>
                    <td><i class='bx bx-x-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                </tr>
                <tr>
                    <td>Support prioritaire</td>
                    <td><i class='bx bx-x-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                    <td><i class='bx bxs-check-circle'></i></td>
                </tr>
            </tbody>
        </table>
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
