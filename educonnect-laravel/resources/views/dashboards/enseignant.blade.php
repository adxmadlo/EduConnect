@extends('layouts.app')

@section('titre', 'Tableau de bord')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Bonjour, {{ $user->prenom }}</h1>
            <p class="page-sub">{{ $user->enseignant?->grade ?? 'Enseignant' }} · {{ $user->enseignant?->departement ?? 'Département non renseigné' }}</p>
        </div>
        <a class="btn btn--primary" href="{{ route('enseignant.notes.index') }}">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>Saisir des notes
        </a>
    </div>

    <section class="stat-grid" aria-label="Indicateurs clés">
        <article class="stat stat--accent">
            <div class="stat__top">
                <span class="stat__label">Modules enseignés</span>
                <span class="stat__icon"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $nbModules }}</div>
            <div class="stat__meta">{{ $nbClasses }} classe{{ $nbClasses > 1 ? 's' : '' }} concernée{{ $nbClasses > 1 ? 's' : '' }}</div>
        </article>

        <article class="stat">
            <div class="stat__top">
                <span class="stat__label">Notes saisies</span>
                <span class="stat__icon"><i class="bi bi-clipboard-data" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $nbNotes }}</div>
            <div class="stat__meta">Toutes sessions confondues</div>
        </article>

        <article class="stat">
            <div class="stat__top">
                <span class="stat__label">Notes publiées</span>
                <span class="stat__icon"><i class="bi bi-send-check" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $nbPubliees }}<small>/{{ $nbNotes }}</small></div>
            <div class="stat__meta">Visibles par les étudiants</div>
        </article>

        <article class="stat">
            <div class="stat__top">
                <span class="stat__label">Prochains cours</span>
                <span class="stat__icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value">{{ $prochainsCours->count() }}</div>
            <div class="stat__meta">Créneaux à venir cette semaine</div>
        </article>
    </section>

    <div class="grid grid--2">
        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-journal-bookmark" aria-hidden="true"></i>Mes modules</h2>
                <a class="btn btn--ghost btn--sm" href="{{ route('enseignant.modules.index') }}">Détail</a>
            </div>
            <div class="card__body card__body--flush">
                @if ($modules->isEmpty())
                    <div class="empty">
                        <i class="bi bi-journal-x" aria-hidden="true"></i>
                        <strong>Aucun module attribué</strong>
                        <span>Contactez l’administration pour l’attribution de vos modules.</span>
                    </div>
                @else
                    <div class="list">
                        @foreach ($modules->take(6) as $module)
                            <div class="list__item">
                                <span class="list__icon list__icon--accent"><i class="bi bi-book" aria-hidden="true"></i></span>
                                <div class="list__body">
                                    <div class="list__title">{{ $module->nom }}</div>
                                    <div class="list__meta">{{ $module->code }} · S{{ $module->semestre }} · {{ $module->notes_count }} note{{ $module->notes_count > 1 ? 's' : '' }}</div>
                                </div>
                                <span class="badge badge--plain badge--info">{{ $module->credits }} crédits</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-clock-history" aria-hidden="true"></i>Dernières notes saisies</h2>
            </div>
            <div class="card__body card__body--flush">
                @if ($dernieresNotes->isEmpty())
                    <div class="empty">
                        <i class="bi bi-clipboard" aria-hidden="true"></i>
                        <strong>Aucune note saisie</strong>
                        <span>Commencez par saisir les notes d’un module.</span>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Étudiant</th><th>Module</th><th class="num">Note</th><th>Statut</th></tr></thead>
                            <tbody>
                                @foreach ($dernieresNotes as $note)
                                    <tr>
                                        <td>{{ $note->etudiant?->user?->nomComplet() ?? '—' }}</td>
                                        <td class="nowrap">{{ $note->module?->code }}</td>
                                        <td class="num">{{ number_format($note->note_finale, 2, ',', '') }}/20</td>
                                        <td>
                                            <span class="badge badge--{{ $note->publie ? 'success' : 'warning' }}">
                                                {{ $note->publie ? 'Publiée' : 'Brouillon' }}
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
    </div>

    <section class="card" style="margin-top:16px">
        <div class="card__head">
            <h2 class="card__title"><i class="bi bi-calendar3" aria-hidden="true"></i>Mes prochains créneaux</h2>
        </div>
        <div class="card__body card__body--flush">
            @if ($prochainsCours->isEmpty())
                <div class="empty">
                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                    <strong>Aucun créneau planifié</strong>
                    <span>Vos prochains cours apparaîtront ici dès leur programmation.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Créneau</th><th>Module</th><th>Classe</th><th>Salle</th></tr></thead>
                        <tbody>
                            @foreach ($prochainsCours as $cours)
                                <tr>
                                    <td class="nowrap">{{ $cours->date_debut->format('d/m/Y') }}</td>
                                    <td class="nowrap">{{ $cours->libelleCreneau() }}</td>
                                    <td>{{ $cours->module?->nom ?? $cours->titre ?? '—' }}</td>
                                    <td>{{ $cours->classe?->code ?? '—' }}</td>
                                    <td>{{ $cours->salle?->nom ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
