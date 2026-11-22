@extends('layouts.app')

@section('titre', 'Tableau de bord')
@section('sous-titre', '· ' . $annee)

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Bonjour, {{ $user->prenom }}</h1>
            <p class="page-sub">Suivi scolaire de {{ $enfants->count() }} enfant{{ $enfants->count() > 1 ? 's' : '' }} · {{ $annee }}</p>
        </div>
    </div>

    @if ($enfants->isEmpty())
        <div class="alert alert--warning">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>Aucun étudiant n’est rattaché à votre compte. Contactez l’administration pour établir le lien parent–étudiant.</div>
        </div>
    @else
        <div class="grid grid--2">
            @foreach ($enfants as $ligne)
                @php
                    $enfant = $ligne['etudiant'];
                    $frais = $ligne['frais'];
                @endphp
                <section class="card">
                    <div class="card__head">
                        <div>
                            <h2 class="card__title"><i class="bi bi-person-badge" aria-hidden="true"></i>{{ $enfant->user?->nomComplet() }}</h2>
                            <p class="card__hint">{{ $enfant->classe?->nom ?? 'Classe non assignée' }}</p>
                        </div>
                        <a class="btn btn--ghost btn--sm" href="{{ route('parent.enfants.index', $enfant) }}">Détail</a>
                    </div>
                    <div class="card__body">
                        <div class="flex-between" style="margin-bottom:10px">
                            <span class="muted">Moyenne publiée</span>
                            <strong>{{ $ligne['moyenne'] !== null ? number_format($ligne['moyenne'], 2, ',', '') . '/20' : '—' }}</strong>
                        </div>
                        <div class="flex-between" style="margin-bottom:10px">
                            <span class="muted">Crédits validés</span>
                            <strong>{{ $enfant->credits_obtenus }} / {{ $enfant->credits_requis }}</strong>
                        </div>
                        <div class="flex-between" style="margin-bottom:10px">
                            <span class="muted">Scolarité réglée</span>
                            <strong>{{ $frais?->pourcentagePaye() ?? 0 }} %</strong>
                        </div>
                        <div class="progress">
                            <div class="progress__bar {{ ($frais?->pourcentagePaye() ?? 0) >= 100 ? 'progress__bar--success' : '' }}"
                                 style="width:{{ $frais?->pourcentagePaye() ?? 0 }}%"></div>
                        </div>
                        @if ($frais && $frais->soldeRestant() > 0)
                            <div class="alert alert--warning" style="margin-top:14px">
                                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                                <span>Solde restant : {{ number_format($frais->soldeRestant(), 0, ',', ' ') }} FCFA</span>
                            </div>
                        @elseif ($frais)
                            <div class="alert alert--success" style="margin-top:14px">
                                <i class="bi bi-check-circle" aria-hidden="true"></i><span>Scolarité entièrement réglée</span>
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
