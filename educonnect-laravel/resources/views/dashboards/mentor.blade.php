@extends('layouts.app')

@section('titre', 'Tableau de bord')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Bonjour, {{ $user->prenom }}</h1>
            <p class="page-sub">Espace mentor · {{ $mentor?->actif ? 'profil validé' : 'profil en cours de validation' }}</p>
        </div>
        <a class="btn btn--primary" href="{{ route('mentor.sessions.index') }}">
            <i class="bi bi-camera-video" aria-hidden="true"></i>Mes sessions
        </a>
    </div>

    @if (! $mentor)
        <div class="alert alert--warning">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>Votre profil mentor n’est pas encore créé. Contactez l’administration pour finaliser les 4 étapes de validation.</div>
        </div>
    @else
        <section class="stat-grid" aria-label="Indicateurs clés">
            <article class="stat stat--accent">
                <div class="stat__top">
                    <span class="stat__label">Note moyenne</span>
                    <span class="stat__icon"><i class="bi bi-star" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ number_format($mentor->note_moyenne, 1, ',', '') }}<small>/5</small></div>
                <div class="stat__meta">{{ (int) round($mentor->taux_satisfaction * 100) }} % de satisfaction</div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Sessions animées</span>
                    <span class="stat__icon"><i class="bi bi-camera-video" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ $mentor->nb_sessions }}</div>
                <div class="stat__meta">Depuis l’ouverture du mentorat</div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Étudiants suivis</span>
                    <span class="stat__icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ $mentor->nb_etudiants }}</div>
                <div class="stat__meta">Sur l’année académique en cours</div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Modules de mentorat</span>
                    <span class="stat__icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ $mentor->modules->count() }}</div>
                <div class="stat__meta">Validés par les enseignants</div>
            </article>
        </section>

        <div class="grid grid--2">
            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><i class="bi bi-calendar3" aria-hidden="true"></i>Sessions à venir</h2>
                </div>
                <div class="card__body card__body--flush">
                    @if ($sessions->isEmpty())
                        <div class="empty">
                            <i class="bi bi-calendar-x" aria-hidden="true"></i>
                            <strong>Aucune session</strong>
                            <span>Les demandes des étudiants apparaîtront ici.</span>
                        </div>
                    @else
                        <div class="list">
                            @foreach ($sessions as $session)
                                <div class="list__item">
                                    <span class="list__icon list__icon--accent"><i class="bi bi-person" aria-hidden="true"></i></span>
                                    <div class="list__body">
                                        <div class="list__title">{{ $session->etudiant?->user?->nomComplet() ?? 'Étudiant' }}</div>
                                        <div class="list__meta">
                                            {{ $session->module?->nom ?? '—' }} ·
                                            {{ $session->date_session->format('d/m/Y') }} à {{ substr($session->heure_debut, 0, 5) }}
                                        </div>
                                    </div>
                                    <span class="badge badge--{{ $session->statut === 'confirmee' ? 'success' : ($session->statut === 'demandee' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($session->statut) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><i class="bi bi-journal-check" aria-hidden="true"></i>Mes modules validés</h2>
                </div>
                <div class="card__body">
                    @forelse ($mentor->modules as $module)
                        <div class="flex-between" style="padding:8px 0;border-bottom:1px solid var(--border)">
                            <div>
                                <strong>{{ $module->nom }}</strong><br>
                                <span class="muted" style="font-size:12px">{{ $module->code }}</span>
                            </div>
                            <span class="badge badge--success">{{ number_format($module->pivot->note_obtenue, 2, ',', '') }}/20</span>
                        </div>
                    @empty
                        <div class="empty">
                            <i class="bi bi-journal-x" aria-hidden="true"></i>
                            <strong>Aucun module validé</strong>
                            <span>Vos modules de mentorat seront affichés après validation.</span>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
@endsection
