<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('assets/css/stylemanus.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/educonnect-public.css') }}">
    <title>Contact - EduConnect</title>
</head>

<body>

    <!-- Navigation -->
    <nav>
        <a href="{{ route('accueil') }}" class="logo">
            <i class='bx bxs-graduation'></i>
            Edu <span>Connect</span>
        </a>
        <div class="links">
            <a href="{{ route('accueil') }}">Accueil</a>
            <a href="{{ route('public.services') }}">Services</a>
            <a href="{{ route('public.apropos') }}">À Propos</a>
            <a href="{{ route('public.contact') }}" class="active">Contact</a>
        </div>
        <div class="login">
            <button class="signup"><a href="{{ route('login') }}">Connexion</a></button>
            <button><a href="{{ route('register') }}">S'inscrire</a></button>
        </div>
    </nav>

    <!-- Héros : image de la bibliothèque à gauche, formulaire à droite -->
    <section class="contact-hero">
        <div class="contact-hero__media">
            <img src="{{ asset('assets/images/contact-bibliotheque.jpg') }}"
                 alt="Deux étudiants de l'UCAO révisant ensemble à la bibliothèque universitaire"
                 width="880" height="1184" loading="eager">
            <div class="contact-hero__caption">
                <i class='bx bx-book-reader' aria-hidden="true"></i>
                <div>
                    <strong>Bibliothèque UCAO</strong>
                    <span>Ouverte du lundi au vendredi, jusqu'à 17h30. Fermée le samedi.</span>
                </div>
            </div>
        </div>

        <div class="contact-hero__panel">
            <p class="contact-eyebrow">
                <i class='bx bxs-graduation' aria-hidden="true"></i>
                UCAO Saint-Michel · Dakar
            </p>
            <h1>Parlons de votre vie académique</h1>
            <p class="contact-lead">
                Une question sur les notes, la scolarité, le mentorat ou une réservation de salle ?
                Écrivez-nous : l'équipe répond sous 24 h ouvrées.
            </p>

            @if (session('contact_succes'))
                <div class="form-message">
                    <p>{{ session('contact_succes') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="form-message" style="border-color:#f5a0b0">
                    @foreach ($errors->all() as $erreur)
                        <p style="color:#c41e3a">{{ $erreur }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('public.contact.store') }}" class="contact-form">
                @csrf

                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nom Complet</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Votre nom" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Votre email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">Sujet</label>
                    <select id="subject" name="subject" required>
                        <option value="">Sélectionnez un sujet</option>
                        @foreach (['support' => 'Support Technique', 'billing' => 'Facturation', 'partnership' => 'Partenariat', 'feedback' => "Retour d'Expérience", 'other' => 'Autre'] as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(old('subject') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" placeholder="Votre message..." rows="5" required>{{ old('message') }}</textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class='bx bx-send' aria-hidden="true"></i>
                    Envoyer le Message
                </button>
            </form>
        </div>
    </section>

    <!-- Informations de contact -->
    <h2 class="separator">
        Informations de Contact
    </h2>

    <div class="contact-info">
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-map' aria-hidden="true"></i>
            </div>
            <h4>Adresse</h4>
            <p>123 Rue de l'XXXXXX <br> Dakar, Senegal, 14000</p>
        </div>
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-phone' aria-hidden="true"></i>
            </div>
            <h4>Téléphone</h4>
            <p>+221 33 XXX XXXX<br>+221 77 XXX XXXX</p>
        </div>
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-envelope' aria-hidden="true"></i>
            </div>
            <h4>Email</h4>
            <p>support@educonnect.sn<br>info@educonnect.sn</p>
        </div>
        <div class="info-item">
            <div class="icon">
                <i class='bx bx-time' aria-hidden="true"></i>
            </div>
            <h4>Heures d'Ouverture</h4>
            <p>Lun - Ven: 8h00 - 17h30<br>Sam: fermé</p>
        </div>
    </div>

    <!-- FAQ : questions repliées par défaut, ouvertes au clic -->
    <h2 class="separator">
        Questions Fréquemment Posées
    </h2>

    <div class="faq">
        @php
            $questions = [
                [
                    'q' => 'Comment puis-je créer un compte ?',
                    'r' => 'Cliquez sur le bouton « S\'inscrire » en haut de la page et remplissez le formulaire d\'inscription avec vos informations personnelles. Votre compte étudiant est validé par l\'administration avant la première connexion.',
                ],
                [
                    'q' => 'Est-ce que mes données sont sécurisées ?',
                    'r' => 'Oui. Les échanges sont chiffrés, les mots de passe sont stockés sous forme de hash et chaque page vérifie le rôle de l\'utilisateur avant d\'afficher la moindre donnée.',
                ],
                [
                    'q' => 'Quel est le coût de la plateforme ?',
                    'r' => 'L\'accès à EduConnect est inclus dans la scolarité de l\'UCAO Saint-Michel. Seuls les frais de scolarité et les services optionnels restent à votre charge.',
                ],
                [
                    'q' => 'Comment puis-je réinitialiser mon mot de passe ?',
                    'r' => 'Cliquez sur « Mot de passe oublié » sur la page de connexion et suivez les instructions envoyées à votre adresse email UCAO.',
                ],
                [
                    'q' => 'Puis-je utiliser EduConnect sur mon téléphone ?',
                    'r' => 'Oui, la plateforme est entièrement responsive et fonctionne sur ordinateurs, tablettes et téléphones, sans application à installer.',
                ],
                [
                    'q' => 'Comment puis-je contacter le support ?',
                    'r' => 'Utilisez le formulaire ci-dessus, écrivez à support@educonnect.sn ou appelez le secrétariat. Notre équipe répond généralement dans les 24 heures ouvrées.',
                ],
            ];
        @endphp

        @foreach ($questions as $index => $item)
            <div class="faq-item">
                <button type="button" class="question" aria-expanded="false" aria-controls="faq-reponse-{{ $index }}">
                    <span>{{ $item['q'] }}</span>
                    <i class='bx bx-chevron-down' aria-hidden="true"></i>
                </button>
                <div class="answer" id="faq-reponse-{{ $index }}" role="region">
                    <p>{{ $item['r'] }}</p>
                </div>
            </div>
        @endforeach
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

    <script>
        // FAQ : une seule réponse ouverte à la fois, fermeture au second clic
        document.querySelectorAll('.faq-item .question').forEach(question => {
            question.addEventListener('click', function () {
                const item = this.parentElement;
                const ouverte = item.classList.contains('active');

                document.querySelectorAll('.faq-item.active').forEach(autre => {
                    autre.classList.remove('active');
                    autre.querySelector('.question').setAttribute('aria-expanded', 'false');
                });

                if (!ouverte) {
                    item.classList.add('active');
                    this.setAttribute('aria-expanded', 'true');
                }
            });
        });
    </script>

    <script src="{{ asset('assets/js/animations.js') }}"></script>
</body>

</html>
