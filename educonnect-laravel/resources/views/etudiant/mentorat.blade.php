@extends('layouts.app')

@section('titre', 'Mentorat')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Mentorat</h1>
            <p class="page-sub">Sessions avec les étudiants mentors validés par l’établissement.</p>
        </div>
    </div>

    <div class="grid grid--2">
        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-calendar-check" aria-hidden="true"></i>Mes sessions</h2>
            </div>
            <div class="card__body card__body--flush">
                @if ($sessions->isEmpty())
                    <div class="empty">
                        <i class="bi bi-calendar-x" aria-hidden="true"></i>
                        <strong>Aucune session</strong>
                        <span>Les sessions demandées ou confirmées apparaîtront ici.</span>
                    </div>
                @else
                    <div class="list">
                        @foreach ($sessions as $session)
                            <div class="list__item">
                                <span class="list__icon list__icon--accent"><i class="bi bi-camera-video" aria-hidden="true"></i></span>
                                <div class="list__body">
                                    <div class="list__title">{{ $session->module?->nom ?? 'Session' }}</div>
                                    <div class="list__meta">
                                        {{ $session->mentor?->user?->nomComplet() ?? 'Mentor' }} ·
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
                <h2 class="card__title"><i class="bi bi-people" aria-hidden="true"></i>Mentors disponibles</h2>
            </div>
            <div class="card__body card__body--flush">
                @if ($mentors->isEmpty())
                    <div class="empty">
                        <i class="bi bi-person-x" aria-hidden="true"></i>
                        <strong>Aucun mentor actif</strong>
                        <span>Les mentors validés apparaîtront ici.</span>
                    </div>
                @else
                    <div class="list">
                        @foreach ($mentors as $mentor)
                            <div class="list__item">
                                <span class="avatar avatar--sm">{{ mb_strtoupper(mb_substr($mentor->user?->prenom ?? 'M', 0, 1) . mb_substr($mentor->user?->nom ?? '', 0, 1)) }}</span>
                                <div class="list__body">
                                    <div class="list__title">{{ $mentor->user?->nomComplet() ?? 'Mentor' }}</div>
                                    <div class="list__meta">
                                        {{ $mentor->modules->pluck('nom')->take(2)->join(' · ') ?: 'Modules non renseignés' }}
                                    </div>
                                </div>
                                <div class="list__aside nowrap">
                                    <span class="badge badge--success">{{ number_format($mentor->note_moyenne, 1, ',', '') }}/5</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
