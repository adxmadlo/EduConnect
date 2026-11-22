@php
    $u = auth()->user();
    $roleCss = $u->estAdmin() ? 'admin' : $u->role;
    $initiales = $u->initiales();
    $nbNotifsNav = $nbNotifs ?? 0;

    $liens = match (true) {
        $u->estAdmin() => [
            ['route' => 'dashboard', 'icone' => 'grid-1x2', 'libelle' => 'Tableau de bord'],
            ['label' => 'Scolarité'],
            ['route' => 'admin.etudiants.index', 'icone' => 'people', 'libelle' => 'Étudiants'],
            ['route' => 'admin.notes.index', 'icone' => 'clipboard-data', 'libelle' => 'Notes'],
            ['route' => 'admin.paiements.index', 'icone' => 'credit-card', 'libelle' => 'Paiements'],
            ['label' => 'Vie scolaire'],
            ['route' => 'ressources.index', 'icone' => 'folder2-open', 'libelle' => 'Ressources'],
            ['route' => 'admin.reservations.index', 'icone' => 'calendar-check', 'libelle' => 'Réservations de salles'],
            ['label' => 'Administration'],
            ['route' => 'admin.utilisateurs.index', 'icone' => 'person-gear', 'libelle' => 'Utilisateurs'],
        ],
        $u->role === 'enseignant' => [
            ['route' => 'dashboard', 'icone' => 'grid-1x2', 'libelle' => 'Tableau de bord'],
            ['label' => 'Enseignement'],
            ['route' => 'enseignant.modules.index', 'icone' => 'journal-bookmark', 'libelle' => 'Mes modules'],
            ['route' => 'enseignant.notes.index', 'icone' => 'clipboard-data', 'libelle' => 'Saisie des notes'],
            ['label' => 'Ressources'],
            ['route' => 'ressources.index', 'icone' => 'folder2-open', 'libelle' => 'Ressources pédagogiques'],
            ['route' => 'reservations.index', 'icone' => 'calendar-check', 'libelle' => 'Réservations de salles'],
        ],
        $u->role === 'etudiant' => [
            ['route' => 'dashboard', 'icone' => 'grid-1x2', 'libelle' => 'Tableau de bord'],
            ['label' => 'Scolarité'],
            ['route' => 'etudiant.notes.index', 'icone' => 'clipboard-data', 'libelle' => 'Mes notes'],
            ['route' => 'etudiant.planning.index', 'icone' => 'calendar3', 'libelle' => 'Emploi du temps'],
            ['label' => 'Ressources'],
            ['route' => 'ressources.index', 'icone' => 'folder2-open', 'libelle' => 'Ressources pédagogiques'],
            ['route' => 'reservations.index', 'icone' => 'calendar-check', 'libelle' => 'Salles'],
            ['label' => 'Accompagnement'],
            ['route' => 'etudiant.mentorat.index', 'icone' => 'people', 'libelle' => 'Mentorat'],
            ['route' => 'etudiant.paiements.index', 'icone' => 'credit-card', 'libelle' => 'Paiements'],
        ],
        $u->role === 'parent' => [
            ['route' => 'dashboard', 'icone' => 'grid-1x2', 'libelle' => 'Tableau de bord'],
            ['label' => 'Suivi'],
            ['route' => 'parent.enfants.index', 'icone' => 'person-heart', 'libelle' => 'Mes enfants'],
            ['route' => 'ressources.index', 'icone' => 'folder2-open', 'libelle' => 'Ressources pédagogiques'],
        ],
        $u->role === 'mentor' => [
            ['route' => 'dashboard', 'icone' => 'grid-1x2', 'libelle' => 'Tableau de bord'],
            ['label' => 'Mentorat'],
            ['route' => 'mentor.sessions.index', 'icone' => 'camera-video', 'libelle' => 'Mes sessions'],
            ['route' => 'ressources.index', 'icone' => 'folder2-open', 'libelle' => 'Ressources pédagogiques'],
            ['route' => 'reservations.index', 'icone' => 'calendar-check', 'libelle' => 'Réservations de salles'],
        ],
        default => [['route' => 'dashboard', 'icone' => 'grid-1x2', 'libelle' => 'Tableau de bord']],
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titre', 'EduConnect') · EduConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/educonnect-ui.css') }}">
    @stack('head')
</head>
<body class="role-{{ $roleCss }}">

<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <div class="sidebar__mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></div>
        <div>
            <div class="sidebar__name">Edu<span>Connect</span></div>
            <div class="sidebar__role">{{ $u->libelleRole() }}</div>
        </div>
    </div>

    <nav class="sidebar__nav" aria-label="Navigation principale">
        @foreach ($liens as $lien)
            @if (isset($lien['label']))
                <div class="sidebar__label">{{ $lien['label'] }}</div>
            @else
                <a class="nav-link {{ request()->routeIs($lien['route']) ? 'is-active' : '' }}"
                   href="{{ route($lien['route']) }}"
                   @if (request()->routeIs($lien['route'])) aria-current="page" @endif>
                    <i class="bi bi-{{ $lien['icone'] }}" aria-hidden="true"></i>{{ $lien['libelle'] }}
                </a>
            @endif
        @endforeach
    </nav>

    <div class="sidebar__foot">
        <a class="sidebar__user" href="{{ route('profil') }}">
            <span class="avatar avatar--sm">{{ $initiales }}</span>
            <span class="sidebar__user-meta">
                <span class="sidebar__user-name">{{ $u->nomComplet() }}</span>
                <span class="sidebar__user-role">{{ $u->numero_ucao ?: $u->libelleRole() }}</span>
            </span>
        </a>
        <div class="flex" style="padding:8px 4px 0">
            <a class="nav-link" href="{{ route('profil') }}" style="padding:6px 8px;font-size:12px">
                <i class="bi bi-gear" aria-hidden="true"></i>Paramètres
            </a>
            <form method="POST" action="{{ route('logout') }}" style="margin-left:auto">
                @csrf
                <button type="submit" class="nav-link" style="padding:6px 8px;font-size:12px;background:none;border:0;cursor:pointer">
                    <i class="bi bi-box-arrow-left" aria-hidden="true"></i>Quitter
                </button>
            </form>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<main class="main">
    <header class="topbar">
        <button class="icon-btn nav-toggle" type="button" onclick="EduUI.toggleNav()" aria-label="Ouvrir la navigation">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="topbar__title">@yield('titre', 'Tableau de bord') <span>@yield('sous-titre')</span></div>
        <div class="topbar__actions">
            <a class="icon-btn icon-btn--bordered bell" href="{{ route('profil') }}" aria-label="Notifications">
                <i class="bi bi-bell" aria-hidden="true"></i>
                @if ($nbNotifsNav > 0)<span class="bell__dot"></span>@endif
            </a>
            <a class="icon-btn icon-btn--bordered" href="{{ route('profil') }}" aria-label="Mon profil">
                <span class="avatar avatar--sm" style="background:var(--accent)">{{ $initiales }}</span>
            </a>
        </div>
    </header>

    <div class="page">
        @if (session('succes'))
            <div class="alert alert--success" style="margin-bottom:16px">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <div>{{ session('succes') }}</div>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert--danger" style="margin-bottom:16px">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <div>
                    @foreach ($errors->all() as $erreur)
                        <div>{{ $erreur }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @yield('contenu')
    </div>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="{{ asset('assets/js/educonnect-ui.js') }}"></script>
@stack('scripts')
</body>
</html>
