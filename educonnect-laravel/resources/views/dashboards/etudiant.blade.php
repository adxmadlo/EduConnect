@extends('layouts.app')

@section('titre', 'Tableau de bord')
@section('sous-titre', '· ' . ($etudiant?->classe?->code ?? '—'))

@push('head')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@endpush

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Bonjour, {{ $user->prenom }}</h1>
            <p class="page-sub">
                Année académique {{ $annee }} ·
                {{ $etudiant?->classe?->nom ?? 'Classe non assignée' }}
            </p>
        </div>
    </div>

    @if (! $etudiant)
        <div class="alert alert--warning">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>Votre dossier étudiant n’est pas encore rattaché à une classe. Contactez l’administration pour activer vos notes et votre scolarité.</div>
        </div>
    @else
        <section class="stat-grid" aria-label="Indicateurs clés">
            <article class="stat stat--accent">
                <div class="stat__top">
                    <span class="stat__label">Moyenne générale</span>
                    <span class="stat__icon"><i class="bi bi-graph-up" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ $moyenne !== null ? number_format($moyenne, 2, ',', '') : '—' }}<small>/20</small></div>
                <div class="stat__meta">{{ $notes->count() }} note{{ $notes->count() > 1 ? 's' : '' }} publiée{{ $notes->count() > 1 ? 's' : '' }}</div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Crédits validés</span>
                    <span class="stat__icon"><i class="bi bi-award" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ $etudiant->credits_obtenus }}<small>/{{ $creditsRequis }}</small></div>
                <div class="progress" style="margin-top:2px">
                    <div class="progress__bar" style="width:{{ min(100, (int) round($etudiant->credits_obtenus / $creditsRequis * 100)) }}%"></div>
                </div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Scolarité réglée</span>
                    <span class="stat__icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ $pourcentagePaye }}<small>%</small></div>
                <div class="stat__meta">
                    {{ number_format($montantPaye, 0, ',', ' ') }} FCFA sur
                    {{ number_format($montantTotal, 0, ',', ' ') }} FCFA
                </div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Modules validés</span>
                    <span class="stat__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value">{{ $modulesValides }}<small>/{{ $notes->count() }}</small></div>
                <div class="stat__meta">Seuil de validation : 10/20</div>
            </article>
        </section>

        <div class="grid grid--main" style="margin-bottom:16px">
            <section class="card">
                <div class="card__head">
                    <div>
                        <h2 class="card__title"><i class="bi bi-activity" aria-hidden="true"></i>Évolution de mes notes</h2>
                        <p class="card__hint">Notes publiées, dans l’ordre de saisie par les enseignants</p>
                    </div>
                </div>
                <div class="card__body">
                    <div class="chart-wrap">
                        <canvas id="chartEvolution"></canvas>
                        <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucune note publiée pour le moment</span></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><i class="bi bi-pie-chart" aria-hidden="true"></i>Ma scolarité</h2>
                </div>
                <div class="card__body">
                    <div class="donut-wrap">
                        <canvas id="chartScolarite"></canvas>
                        <div class="chart-empty"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Aucun frais enregistré</span></div>
                    </div>
                    <div class="legend">
                        <span class="legend__item"><span class="legend__dot" style="background:#15803d"></span>Payé · {{ number_format($montantPaye, 0, ',', ' ') }} FCFA</span>
                        <span class="legend__item"><span class="legend__dot" style="background:#e3e8ef"></span>Reste · {{ number_format($solde, 0, ',', ' ') }} FCFA</span>
                    </div>
                    @if ($solde > 0)
                        <a class="btn btn--primary" href="{{ route('etudiant.paiements.index') }}" style="width:100%;margin-top:14px">
                            <i class="bi bi-credit-card" aria-hidden="true"></i>Régler {{ number_format($solde, 0, ',', ' ') }} FCFA
                        </a>
                    @else
                        <div class="alert alert--success" style="margin-top:14px">
                            <i class="bi bi-check-circle" aria-hidden="true"></i><span>Scolarité entièrement réglée</span>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-clipboard-data" aria-hidden="true"></i>Mes notes par module</h2>
                <a class="btn btn--ghost btn--sm" href="{{ route('etudiant.notes.index') }}">Tout voir</a>
            </div>
            <div class="card__body card__body--flush">
                @if ($notes->isEmpty())
                    <div class="empty">
                        <i class="bi bi-clipboard" aria-hidden="true"></i>
                        <strong>Aucune note publiée</strong>
                        <span>Vos notes apparaîtront dès leur publication par vos enseignants.</span>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Module</th><th>Semestre</th><th class="num">Note</th><th>Mention</th></tr></thead>
                            <tbody>
                                @foreach ($notes->take(6) as $note)
                                    @php
                                        $tonalite = match (true) {
                                            $note->note_finale >= 14 => 'success',
                                            $note->note_finale >= 10 => 'info',
                                            default                  => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $note->module->nom }}</strong><br>
                                            <span class="muted" style="font-size:11px">{{ $note->module->code }}</span>
                                        </td>
                                        <td>S{{ $note->semestre }}</td>
                                        <td class="num">{{ number_format($note->note_finale, 2, ',', '') }}/20</td>
                                        <td><span class="badge badge--{{ $tonalite }}">{{ $note->mentionLongue() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <div class="grid grid--2" style="margin-top:16px">
            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><i class="bi bi-calendar3" aria-hidden="true"></i>Prochains cours</h2>
                    <a class="btn btn--ghost btn--sm" href="{{ route('etudiant.planning.index') }}">Emploi du temps</a>
                </div>
                <div class="card__body card__body--flush">
                    @if ($prochainsCours->isEmpty())
                        <div class="empty">
                            <i class="bi bi-calendar-x" aria-hidden="true"></i>
                            <strong>Aucun cours planifié</strong>
                            <span>Les prochains créneaux seront publiés par l’administration.</span>
                        </div>
                    @else
                        <div class="list">
                            @foreach ($prochainsCours as $cours)
                                <div class="list__item">
                                    <span class="list__icon list__icon--accent"><i class="bi bi-book" aria-hidden="true"></i></span>
                                    <div class="list__body">
                                        <div class="list__title">{{ $cours->module?->nom ?? $cours->titre ?? 'Cours' }}</div>
                                        <div class="list__meta">
                                            {{ $cours->date_debut->locale('fr')->isoFormat('dddd D MMMM') }} ·
                                            {{ $cours->libelleCreneau() }}
                                        </div>
                                    </div>
                                    <div class="list__aside muted" style="font-size:11px">{{ $cours->salle?->nom ?? '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><i class="bi bi-people" aria-hidden="true"></i>Mentorat à venir</h2>
                    <a class="btn btn--ghost btn--sm" href="{{ route('etudiant.mentorat.index') }}">Gérer</a>
                </div>
                <div class="card__body card__body--flush">
                    @if ($sessions->isEmpty())
                        <div class="empty">
                            <i class="bi bi-calendar-check" aria-hidden="true"></i>
                            <strong>Aucune session confirmée</strong>
                            <span>Réservez un créneau avec un mentor depuis l’espace Mentorat.</span>
                        </div>
                    @else
                        <div class="list">
                            @foreach ($sessions as $session)
                                <div class="list__item">
                                    <span class="list__icon list__icon--success"><i class="bi bi-camera-video" aria-hidden="true"></i></span>
                                    <div class="list__body">
                                        <div class="list__title">{{ $session->module?->nom ?? 'Session' }}</div>
                                        <div class="list__meta">
                                            {{ $session->mentor?->user?->nomComplet() ?? 'Mentor' }} ·
                                            {{ $session->date_session->format('d/m/Y') }} à {{ substr($session->heure_debut, 0, 5) }}
                                        </div>
                                    </div>
                                    <span class="badge badge--info badge--plain">
                                        {{ $session->type_session === 'en_ligne' ? 'En ligne' : 'Présentiel' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/educonnect-charts.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if ($etudiant)
            EduCharts.line('chartEvolution', {
                labels: @json($evolution->map(fn ($note) => optional($note->saisie_le)->format('d/m'))),
                values: @json($evolution->pluck('note_finale')),
                label: 'Note',
                format: 'score',
                max: 20,
                color: '#326ea9',
                aria: 'Évolution de vos notes publiées sur 20'
            });

            EduCharts.doughnut('chartScolarite', {
                labels: ['Payé', 'Reste à payer'],
                values: [{{ (float) $montantPaye }}, {{ (float) $solde }}],
                colors: ['#15803d', '#e3e8ef'],
                format: 'money',
                aria: 'Part de la scolarité déjà réglée',
                empty: 'Aucun frais de scolarité enregistré',
                center: { value: '{{ $pourcentagePaye }}%', label: 'réglé' }
            });
            @endif
        });
    </script>
@endpush
