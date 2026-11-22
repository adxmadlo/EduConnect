<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EduConnect — plateforme de gestion éducative de l'UCAO Saint-Michel : cours, notes, paiements mobile money, mentorat et suivi parental.">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('assets/css/stylemanus.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/educonnect-public.css') }}">
    <title>EduConnect — Plateforme de gestion éducative</title>
</head>

<body>

    <nav>
        <a href="{{ route('accueil') }}" class="logo">
            <i class='bx bxs-graduation'></i>
            Edu<span>Connect</span>
        </a>
        <div class="links">
            <a href="{{ route('accueil') }}" class="active">Accueil</a>
            <a href="{{ route('public.services') }}">Services</a>
            <a href="{{ route('public.apropos') }}">À Propos</a>
            <a href="{{ route('public.contact') }}">Contact</a>
        </div>
        <div class="login">
            <button class="signup"><a href="{{ route('login') }}">Connexion</a></button>
            <button><a href="{{ route('register') }}">S'inscrire</a></button>
        </div>
    </nav>

    <header>
        <div class="left">
            <h1>La vie académique de l'UCAO, <span>en un seul endroit</span></h1>
            <p>Notes, emploi du temps, scolarité et mentorat réunis dans un espace unique pour les étudiants,
                les enseignants, les parents et l'administration. Chaque rôle voit exactement ce qui le concerne.</p>
            <a href="{{ route('login') }}">
                <i class='bx bx-log-in-circle'></i>
                <span>Accéder à mon espace</span>
            </a>
        </div>

        <div class="hero-visual" aria-hidden="true">
            <div class="hv-bar"><i></i><i></i><i></i></div>
            <div class="hv-grid">
                <div class="hv-card"><small>Moyenne générale</small><b>14,50 / 20</b></div>
                <div class="hv-card"><small>Scolarité réglée</small><b>75 %</b></div>
            </div>
            <div class="hv-bars">
                <span style="height:46%"></span><span style="height:64%"></span><span style="height:38%"></span>
                <span style="height:82%"></span><span style="height:57%"></span><span style="height:94%"></span>
                <span style="height:68%"></span>
            </div>
            <div class="hv-note"><i class='bx bx-check-circle'></i> Notes publiées et paiements synchronisés</div>
        </div>
    </header>

    <h2 class="separator">Fonctionnalités principales</h2>

    <div class="features">
        <div class="item">
            <div class="header">
                <i class='bx bx-book-open'></i>
                <h5>Gestion des cours</h5>
            </div>
            <p>Cours, emplois du temps et supports pédagogiques par classe et par module, mis à jour par les enseignants.</p>
        </div>
        <div class="item">
            <div class="header">
                <i class='bx bx-bar-chart-alt-2'></i>
                <h5>Suivi des notes</h5>
            </div>
            <p>Notes par module, moyennes pondérées, mentions et rang, avec bulletin téléchargeable par semestre.</p>
        </div>
        <div class="item">
            <div class="header">
                <i class='bx bx-credit-card'></i>
                <h5>Paiements sécurisés</h5>
            </div>
            <p>Règlement de la scolarité par Wave, Orange Money ou carte, avec reçu numérique et suivi du solde restant.</p>
        </div>
    </div>

    <h2 class="separator">Un espace par rôle</h2>

    <div class="features">
        <div class="item">
            <div class="header"><i class='bx bx-user'></i><h5>Étudiant</h5></div>
            <p>Résultats, progression des crédits, prochains cours, échéances de scolarité et sessions de mentorat.</p>
        </div>
        <div class="item">
            <div class="header"><i class='bx bx-group'></i><h5>Parent</h5></div>
            <p>Suivi de chaque enfant : notes, assiduité, paiements et messages avec l'équipe pédagogique.</p>
        </div>
        <div class="item">
            <div class="header"><i class='bx bx-chalkboard'></i><h5>Enseignant</h5></div>
            <p>Saisie des notes, publication des résultats, ressources de cours et échanges avec les étudiants.</p>
        </div>
        <div class="item">
            <div class="header"><i class='bx bx-star'></i><h5>Mentor</h5></div>
            <p>Demandes de mentorat, planning des séances et suivi de la progression des étudiants accompagnés.</p>
        </div>
        <div class="item">
            <div class="header"><i class='bx bx-shield-quarter'></i><h5>Administration</h5></div>
            <p>Utilisateurs, classes, salles, recouvrement, rapports et file d'attente de validation des comptes.</p>
        </div>
    </div>

    <h2 class="separator">Nos services</h2>

    <div class="services-preview">
        <div class="category">
            <a href="{{ route('public.services') }}#mentoring">Mentorat</a>
            <a href="{{ route('public.services') }}#timetable">Emploi du temps</a>
            <a href="{{ route('public.services') }}#rooms">Réservation de salles</a>
            <a href="{{ route('public.services') }}#notifications">Notifications</a>
        </div>

        <div class="services-list">
            <div class="item">
                <div class="icon-box"><i class='bx bx-user-check'></i></div>
                <div class="info">
                    <h5>Mentorat académique</h5>
                    <p>Des étudiants avancés accompagnent leurs camarades sur les modules difficiles, séance par séance.</p>
                </div>
            </div>
            <div class="item">
                <div class="icon-box"><i class='bx bx-calendar'></i></div>
                <div class="info">
                    <h5>Emploi du temps</h5>
                    <p>Cours, salles et enseignants de la semaine, consultables depuis un téléphone.</p>
                </div>
            </div>
            <div class="item">
                <div class="icon-box"><i class='bx bx-door-open'></i></div>
                <div class="info">
                    <h5>Réservation de salles</h5>
                    <p>Réservez une salle pour un travail de groupe et suivez la validation par l'administration.</p>
                </div>
            </div>
            <div class="item">
                <div class="icon-box"><i class='bx bx-bell'></i></div>
                <div class="info">
                    <h5>Notifications</h5>
                    <p>Résultats publiés, changement d'horaire, échéance de paiement : l'information arrive au bon moment.</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="separator">La plateforme en bref</h2>

    <div class="stats">
        <div class="stat-item">
            <i class='bx bx-group' aria-hidden="true"></i>
            <h3>5 rôles</h3>
            <p>Un espace dédié</p>
        </div>
        <div class="stat-item">
            <i class='bx bx-mobile-alt' aria-hidden="true"></i>
            <h3>Wave · OM</h3>
            <p>Paiement mobile money</p>
        </div>
        <div class="stat-item">
            <i class='bx bx-medal' aria-hidden="true"></i>
            <h3>LMD</h3>
            <p>Notes, crédits, mentions</p>
        </div>
        <div class="stat-item stat-item--logo">
            <span class="ucao-logo"><i class='bx bxs-graduation' aria-hidden="true"></i>UCAO</span>
            <h3>Saint-Michel</h3>
            <p>Dakar, Sénégal</p>
        </div>
    </div>

    <h2 class="separator">Comment ça marche</h2>

    <div class="testimonials">
        <div class="item">
            <div class="stars"><i class='bx bx-log-in-circle'></i></div>
            <p><strong>1. Connectez-vous</strong><br>Un identifiant UCAO suffit. Chaque compte est rattaché à un rôle et à ses données.</p>
        </div>
        <div class="item">
            <div class="stars"><i class='bx bx-line-chart'></i></div>
            <p><strong>2. Suivez votre parcours</strong><br>Notes, crédits, emploi du temps et scolarité sont mis à jour par l'administration et les enseignants.</p>
        </div>
        <div class="item">
            <div class="stars"><i class='bx bx-check-shield'></i></div>
            <p><strong>3. Agissez au bon moment</strong><br>Payez une échéance, réservez une salle ou demandez un mentorat, sans passer par le secrétariat.</p>
        </div>
    </div>

    <div class="cta-section">
        <h2>Prêt à utiliser EduConnect ?</h2>
        <p>Étudiants, parents, enseignants et mentors disposent déjà d'un compte UCAO.</p>
        <a href="{{ route('login') }}" class="cta-button">Se connecter</a>
    </div>

    <footer>
        <h3>EduConnect<br><span style="font-size:12px;color:var(--ink-3);font-weight:400">Plateforme de gestion éducative — UCAO Saint-Michel</span></h3>
        <div class="right">
            <div class="links">
                <a href="{{ route('public.services') }}">Services</a>
                <a href="{{ route('public.apropos') }}">À propos</a>
                <a href="{{ route('public.contact') }}">Nous contacter</a>
                <a href="{{ route('login') }}">Connexion</a>
            </div>
            <div class="social">
                <i class='bx bxl-instagram'></i>
                <i class='bx bxl-facebook-square'></i>
                <i class='bx bxl-linkedin'></i>
            </div>
            <p>Copyright © 2026 EduConnect, Tous Droits Réservés.</p>
        </div>
    </footer>

    <script src="{{ asset('assets/js/animations.js') }}"></script>
</body>

</html>
