<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('assets/css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/educonnect-legacy.css') }}">
    <title>@yield('titre', 'Connexion') | EduConnect</title>
    <script>document.documentElement.classList.add('legacy-ui');</script>
</head>
<body>
<nav>
    <a href="{{ route('accueil') }}" class="logo"><i class='bx bxs-graduation'></i>Edu<span>Connect</span></a>
    <div class="links">
        <a href="{{ route('accueil') }}">Accueil</a>
        <a href="{{ route('public.services') }}">Services</a>
        <a href="{{ route('public.apropos') }}">À Propos</a>
        <a href="{{ route('public.contact') }}">Contact</a>
    </div>
    <div class="login">
        <button class="signup"><a href="{{ route('login') }}">Connexion</a></button>
        <button><a href="{{ route('register') }}">S'inscrire</a></button>
    </div>
</nav>

<div class="auth-wrapper">
<div class="container @yield('classe-container')" id="container">
    <!-- Sign Up -->
    <div class="form-container sign-up">
        @yield('inscription')
    </div>
    <!-- Sign In -->
    <div class="form-container sign-in">
        @yield('connexion')
    </div>
    <!-- Toggle -->
    <div class="toggle-container"><div class="toggle">
        <div class="toggle-panel toggle-left">
            <i class='bx bxs-graduation toggle-icon'></i><h1>Bon retour !</h1>
            <p>Connectez-vous pour accéder à votre espace académique personnel.</p>
            <div class="toggle-features">
                <div class="feature"><i class='bx bx-check-circle'></i> Notes en temps réel</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Paiements Wave &amp; Orange Money</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Mentorat personnalisé</div>
            </div>
            <button type="button" class="btn-toggle" id="login">Se connecter</button>
        </div>
        <div class="toggle-panel toggle-right">
            <i class='bx bxs-graduation toggle-icon'></i><h1>Rejoignez-nous !</h1>
            <p>Créez votre compte et transformez votre expérience dès aujourd'hui.</p>
            <div class="toggle-features">
                <div class="feature"><i class='bx bx-check-circle'></i> Salles UCAO en ligne</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Bulletins gratuits</div>
                <div class="feature"><i class='bx bx-check-circle'></i> Notifications push</div>
            </div>
            <button type="button" class="btn-toggle" id="register">S'inscrire</button>
        </div>
    </div></div>
</div>
</div>

<script>
    const conteneur = document.getElementById('container');
    const urlConnexion = @json(route('login'));
    const urlInscription = @json(route('register'));

    document.getElementById('register').addEventListener('click', () => {
        if (conteneur.classList.contains('active')) {
            window.location.href = urlInscription;
        } else {
            conteneur.classList.add('active');
            history.replaceState(null, '', urlInscription);
        }
    });

    document.getElementById('login').addEventListener('click', () => {
        if (!conteneur.classList.contains('active')) {
            window.location.href = urlConnexion;
        } else {
            conteneur.classList.remove('active');
            history.replaceState(null, '', urlConnexion);
        }
    });
</script>
<script src="{{ asset('assets/js/animations.js') }}"></script>
<script src="{{ asset('assets/js/educonnect-legacy.js') }}"></script>
</body>
</html>
