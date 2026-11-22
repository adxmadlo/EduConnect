@extends('layouts.app')

@section('titre', 'Tableau de bord')
@section('sous-titre', '· ' . $annee)

@push('head')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@endpush

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Bonjour, {{ $user->prenom }}</h1>
            <p class="page-sub">Vue d’ensemble de l’établissement · année académique {{ $annee }}</p>
        </div>
        <div class="segmented" role="group" aria-label="Raccourcis">
            <a href="{{ route('admin.etudiants.index') }}"><button type="button">Étudiants</button></a>
            <a href="{{ route('admin.paiements.index') }}"><button type="button">Paiements</button></a>
        </div>
    </div>

    <section class="stat-grid" aria-label="Indicateurs clés">
        <article class="stat stat--accent">
            <div class="stat__top">
                <span class="stat__label">Étudiants inscrits</span>
                <span class="stat__icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $nbEtudiants }}</div>
            <div class="stat__meta">{{ $nbClasses }} classes · {{ $nbModules }} modules</div>
        </article>

        <article class="stat">
            <div class="stat__top">
                <span class="stat__label">Enseignants</span>
                <span class="stat__icon"><i class="bi bi-person-workspace" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $nbEnseignants }}</div>
            <div class="stat__meta">Comptes actifs de l’établissement</div>
        </article>

        <article class="stat">
            <div class="stat__top">
                <span class="stat__label">Taux de recouvrement</span>
                <span class="stat__icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $tauxRecouvrement }}<small>%</small></div>
            <div class="progress" style="margin-top:6px">
                <div class="progress__bar" style="width:{{ $tauxRecouvrement }}%"></div>
            </div>
            <div class="stat__meta">
                {{ number_format($montantEncaisse, 0, ',', ' ') }} FCFA sur
                {{ number_format($montantAttendu, 0, ',', ' ') }} FCFA attendus
            </div>
        </article>

        <article class="stat">
            <div class="stat__top">
                <span class="stat__label">Notes publiées</span>
                <span class="stat__icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $nbNotesPubliees }}<small>/{{ $nbNotes }}</small></div>
            <div class="stat__meta">Semestres 1 et 2 confondus</div>
        </article>
    </section>

    <div class="grid grid--main" style="margin-bottom:16px">
        <section class="card">
            <div class="card__head">
                <div>
                    <h2 class="card__title"><i class="bi bi-bar-chart" aria-hidden="true"></i>Effectifs par classe</h2>
                    <p class="card__hint">Étudiants rattachés, toutes promotions</p>
                </div>
            </div>
            <div class="card__body">
                <div class="chart-wrap">
                    <canvas id="chartEffectifs"></canvas>
                    <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucune classe enregistrée</span></div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Soldes à relancer</h2>
            </div>
            <div class="card__body card__body--flush">
                @if ($impayes->isEmpty())
                    <div class="empty">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        <strong>Aucun impayé</strong>
                        <span>Toutes les scolarités sont réglées.</span>
                    </div>
                @else
                    <div class="list">
                        @foreach ($impayes as $frais)
                            <div class="list__item">
                                <span class="list__icon list__icon--warning"><i class="bi bi-person" aria-hidden="true"></i></span>
                                <div class="list__body">
                                    <div class="list__title">{{ $frais->etudiant?->user?->nomComplet() ?? 'Étudiant inconnu' }}</div>
                                    <div class="list__meta">{{ $frais->etudiant?->classe?->code ?? '—' }} · plan {{ $frais->plan }}</div>
                                </div>
                                <div class="list__aside nowrap">
                                    <strong>{{ number_format($frais->soldeRestant(), 0, ',', ' ') }}</strong> FCFA
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    <div class="grid grid--2">
        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-credit-card" aria-hidden="true"></i>Derniers paiements</h2>
                <a class="btn btn--ghost btn--sm" href="{{ route('admin.paiements.index') }}">Tout voir</a>
            </div>
            <div class="card__body card__body--flush">
                @if ($paiementsRecents->isEmpty())
                    <div class="empty">
                        <i class="bi bi-credit-card" aria-hidden="true"></i>
                        <strong>Aucun paiement</strong>
                        <span>Les encaissements apparaîtront ici.</span>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Reçu</th><th>Étudiant</th><th>Moyen</th><th class="num">Montant</th><th>Statut</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($paiementsRecents as $paiement)
                                    <tr>
                                        <td class="nowrap">{{ $paiement->recu_numero }}</td>
                                        <td>{{ $paiement->frais?->etudiant?->user?->nomComplet() ?? '—' }}</td>
                                        <td>{{ $paiement->libelleMoyen() }}</td>
                                        <td class="num nowrap">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</td>
                                        <td>
                                            <span class="badge badge--{{ $paiement->statut === 'confirme' ? 'success' : ($paiement->statut === 'en_attente' ? 'warning' : 'danger') }}">
                                                {{ $paiement->libelleStatut() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-buildings" aria-hidden="true"></i>Répartition des effectifs</h2>
            </div>
            <div class="card__body">
                @forelse ($repartition as $classe)
                    <div style="margin-bottom:12px">
                        <div class="flex-between" style="font-size:13px">
                            <span><strong>{{ $classe->code }}</strong> — {{ $classe->filiere }}</span>
                            <span class="muted">{{ $classe->etudiants_count }} / {{ $classe->capacite }}</span>
                        </div>
                        <div class="progress" style="margin-top:6px">
                            <div class="progress__bar" style="width:{{ $classe->capacite > 0 ? min(100, round($classe->etudiants_count / $classe->capacite * 100)) : 0 }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-buildings" aria-hidden="true"></i>
                        <strong>Aucune classe</strong>
                        <span>Créez des classes pour répartir les étudiants.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/educonnect-charts.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            EduCharts.bar('chartEffectifs', {
                labels: @json($repartition->pluck('code')),
                values: @json($repartition->pluck('etudiants_count')),
                label: 'Étudiants',
                format: 'number',
                aria: 'Nombre d’étudiants par classe'
            });
        });
    </script>
@endpush
