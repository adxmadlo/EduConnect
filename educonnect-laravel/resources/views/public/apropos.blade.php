<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('assets/css/stylemanus.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/educonnect-public.css') }}">
    <title>À Propos - EduConnect</title>
</head>

<body>

    <!-- Navigation -->
    <nav>
        <a href="{{ route('accueil') }}" class="logo">
            <i class='bx bxs-graduation'></i>
            Edu<span>Connect</span>
        </a>
        <div class="links">
            <a href="{{ route('accueil') }}">Accueil</a>
            <a href="{{ route('public.services') }}">Services</a>
            <a href="{{ route('public.apropos') }}" class="active">À Propos</a>
            <a href="{{ route('public.contact') }}">Contact</a>
        </div>
        <div class="login">
            <button class="signup"><a href="{{ route('login') }}">Connexion</a></button>
            <button><a href="{{ route('register') }}">S'inscrire</a></button>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <h1>À Propos d'EduConnect</h1>
            <p>Découvrez notre mission, notre vision et l'équipe derrière la plateforme</p>
        </div>
    </header>

    <!-- Mission & Vision Section -->
    <h2 class="separator">
        Notre Mission et Vision
    </h2>

    <div class="mission-vision">
        <div class="item">
            <div class="icon">
                <i class='bx bx-target-lock'></i>
            </div>
            <h3>Notre Mission</h3>
            <p>Fournir une plateforme de gestion éducative complète, accessible et intuitive qui empowere les étudiants, les enseignants et les administrateurs à atteindre l'excellence académique et administrative.</p>
        </div>
        <div class="item">
            <div class="icon">
                <i class='bx bx-rocket'></i>
            </div>
            <h3>Notre Vision</h3>
            <p>Transformer l'éducation en Afrique en créant un écosystème numérique où chaque étudiant a accès aux outils nécessaires pour réussir, peu importe son contexte socio-économique.</p>
        </div>
    </div>

    <!-- Values Section -->
    <h2 class="separator">
        Nos Valeurs
    </h2>

    <div class="values">
        <div class="item">
            <div class="header">
                <i class='bx bx-heart'></i>
                <h5>Excellence</h5>
            </div>
            <p>Nous nous engageons à fournir des solutions de haute qualité qui répondent aux plus hauts standards de l'industrie.</p>
        </div>
        <div class="item">
            <div class="header">
                <i class='bx bx-shield-alt-2'></i>
                <h5>Intégrité</h5>
            </div>
            <p>La transparence et l'honnêteté sont au cœur de tout ce que nous faisons. Nous respectons la confidentialité de nos utilisateurs.</p>
        </div>
        <div class="item">
            <div class="header">
                <i class='bx bx-group'></i>
                <h5>Collaboration</h5>
            </div>
            <p>Nous croyons au pouvoir de la collaboration et du travail d'équipe pour créer des solutions innovantes.</p>
        </div>
        <div class="item">
            <div class="header">
                <i class='bx bx-bulb'></i>
                <h5>Innovation</h5>
            </div>
            <p>Nous cherchons constamment à innover et à améliorer notre plateforme en fonction des retours de nos utilisateurs.</p>
        </div>
    </div>

    <!-- History Section -->
    <h2 class="separator">
        Notre Histoire
    </h2>

    <div class="history">
        <div class="timeline-item">
            <div class="year">Decembre 2024</div>
            <div class="content">
                <h4>Rencontre de l'ensemble des membres de l'equipe actuelle</h4>
                <p>L'équipe projet se constitue autour d'un constat simple : la gestion académique de l'UCAO repose encore sur des fichiers dispersés.</p>
            </div>
        </div>
        
        <div class="timeline-item">
            <div class="year">Janvier 2026</div>
            <div class="content">
                <h4>Formation de notre Équipe</h4>
                <p>Répartition des rôles, définition du périmètre et premiers maquettes des espaces étudiant, parent et administration.</p>
            </div>
        </div>
        <div class="timeline-item">
            <div class="year">Février 2026</div>
            <div class="content">
                <h4>Phase de Conception</h4>
                <p>Modélisation de la base de données (21 tables) et choix techniques : PHP, MySQL, Chart.js pour les restitutions.</p>
            </div>
        </div>
        <div class="timeline-item">
            <div class="year">Mars<br> 2026</div>
            <div class="content">
                <h4>Debut de la conception du projet</h4>
                <p>Développement des cinq espaces, des paiements Wave / Orange Money et des bulletins.</p>
            </div>
        </div>
        <div class="timeline-item">
            <div class="year">Avril<br> 2026</div>
            <div class="content">
                <h4>Debut de la conception du projet</h4>
                <p>Tests, corrections et préparation de la démonstration devant le jury.</p>
            </div>
        </div>

    </div>

    <!-- Team Section -->
    <h2 class="separator">
        Notre Équipe
    </h2>

    <div class="team">
        <div class="team-member">
            @php $photo = 'assets/images/equipe/mme-bilong.jpg'; @endphp
            <div class="team-photo">
                @if (file_exists(public_path($photo)))
                    <img src="{{ asset($photo) }}" alt="Portrait de Mme Bilong" width="84" height="84" loading="lazy">
                @else
                    <span aria-hidden="true">MB</span>
                @endif
            </div>
            <div class="info">
                <h4>Mme Bilong</h4>
                <p>Directrice Générale</p>
                <p class="bio">Pilote la vision du projet et la relation avec l'administration de l'UCAO.</p>
            </div>
        </div>
        <div class="team-member">
            @php $photo = 'assets/images/equipe/sidaty-diedhiou.jpg'; @endphp
            <div class="team-photo">
                @if (file_exists(public_path($photo)))
                    <img src="{{ asset($photo) }}" alt="Portrait de Sidaty Diedhiou" width="84" height="84" loading="lazy">
                @else
                    <span aria-hidden="true">SD</span>
                @endif
            </div>
            <div class="info">
                <h4>Sidaty Diedhiou</h4>
                <p>Responsable Back-End et Chef de Projet</p>
                <p class="bio">Coordonne l'équipe et développe la logique serveur, les requêtes et la sécurité des accès.</p>
            </div>
        </div>
        <div class="team-member">
            @php $photo = 'assets/images/equipe/abdourahmane-cisse.jpg'; @endphp
            <div class="team-photo">
                @if (file_exists(public_path($photo)))
                    <img src="{{ asset($photo) }}" alt="Portrait de Abdourahmane Cisse" width="84" height="84" loading="lazy">
                @else
                    <span aria-hidden="true">AC</span>
                @endif
            </div>
            <div class="info">
                <h4>Abdourahmane Cisse</h4>
                <p>Responsable Base de Donnees</p>
                <p class="bio">Conçoit le schéma relationnel, les contraintes et les jeux de données de démonstration.</p>
            </div>
        </div>
        <br>
        <div class="team-member">
            @php $photo = 'assets/images/equipe/seynabou-cisse.jpg'; @endphp
            <div class="team-photo">
                @if (file_exists(public_path($photo)))
                    <img src="{{ asset($photo) }}" alt="Portrait de Seynabou Cisse" width="84" height="84" loading="lazy">
                @else
                    <span aria-hidden="true">SC</span>
                @endif
            </div>
            <div class="info">
                <h4>Seynabou Cisse</h4>
                <p>Responsable de la Co-Coordination du Projet et de la Comptabilite</p>
                <p class="bio">Suit le planning, le budget et la coordination entre les pôles du projet.</p>
            </div>
        </div>
         <div class="team-member">
            @php $photo = 'assets/images/equipe/adama-diallo.jpg'; @endphp
            <div class="team-photo">
                @if (file_exists(public_path($photo)))
                    <img src="{{ asset($photo) }}" alt="Portrait de Adama Diallo" width="84" height="84" loading="lazy">
                @else
                    <span aria-hidden="true">AD</span>
                @endif
            </div>
            <div class="info">
                <h4>Adama Diallo</h4>
                <p>Responsable Front-End</p>
                <p class="bio">Intègre les interfaces, les composants partagés et les graphiques du tableau de bord.</p>
            </div>
        </div>
         <div class="team-member">
            @php $photo = 'assets/images/equipe/riradjim-dannodje-gloria.jpg'; @endphp
            <div class="team-photo">
                @if (file_exists(public_path($photo)))
                    <img src="{{ asset($photo) }}" alt="Portrait de Riradjim Dannodje Gloria" width="84" height="84" loading="lazy">
                @else
                    <span aria-hidden="true">RD</span>
                @endif
            </div>
            <div class="info">
                <h4>Riradjim Dannodje Gloria</h4>
                <p>UX/UI Designer et Responsable de la Co-Coordination </p>
                <p class="bio">Définit les parcours par rôle, la charte visuelle et l'accessibilité des écrans.</p>
            </div>
        </div>

    </div>

    <!-- Why Choose Us -->
    <h2 class="separator">
        Pourquoi Choisir EduConnect?
    </h2>

    <div class="why-choose">
        <div class="item">
            <div class="number">01</div>
            <h4>Plateforme Complète</h4>
            <p>Tout ce dont vous avez besoin pour gérer votre vie académique en un seul endroit.</p>
        </div>
        <div class="item">
            <div class="number">02</div>
            <h4>Sécurité Garantie</h4>
            <p>Vos données sont protégées avec les plus hauts standards de sécurité et de confidentialité.</p>
        </div>
        <div class="item">
            <div class="number">03</div>
            <h4>Support 24/7</h4>
            <p>Notre équipe de support est toujours disponible pour vous aider en cas de problème.</p>
        </div>
        <div class="item">
            <div class="number">04</div>
            <h4>Innovations Constantes</h4>
            <p>Nous ajoutons régulièrement de nouvelles fonctionnalités basées sur vos retours.</p>
        </div>
        <div class="item">
            <div class="number">05</div>
            <h4>Accessibilité</h4>
            <p>EduConnect est conçu pour être accessible à tous, peu importe le contexte technologique.</p>
        </div>
        <div class="item">
            <div class="number">06</div>
            <h4>Tarification Abordable</h4>
            <p>Des plans flexibles et abordables pour tous les budgets et tous les besoins.</p>
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
                <a href="{{ route('public.contact') }}">Nous Contacter</a>
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

    <script src="{{ asset('assets/js/animations.js') }}"></script>
</body>

</html>