@extends('layouts.app')

@section('titre', 'Emploi du temps')
@section('sous-titre', '· ' . ($etudiant?->classe?->code ?? '—'))

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Emploi du temps</h1>
            <p class="page-sub">
                {{ $etudiant?->classe?->nom ?? 'Classe non assignée' }} · année {{ $annee }} ·
                semaine du {{ $debutSemaine->locale('fr')->isoFormat('D MMMM') }}
                au {{ $finSemaine->locale('fr')->isoFormat('D MMMM YYYY') }}
            </p>
        </div>
    </div>

    {{-- Examens de la semaine : bloc dédié, distinct des cours --}}
    <section class="card" style="margin-bottom:16px">
        <div class="card__head">
            <h2 class="card__title"><i class="bi bi-journal-check" aria-hidden="true"></i>Semaine d’examens</h2>
            <span class="muted">{{ $examens->count() }} examen{{ $examens->count() > 1 ? 's' : '' }} programmé{{ $examens->count() > 1 ? 's' : '' }}</span>
        </div>

        @if ($examens->isEmpty())
            <div class="card__body">
                <div class="empty">
                    <i class="bi bi-journal" aria-hidden="true"></i>
                    <strong>Aucun examen cette semaine</strong>
                    <span>Les convocations sont publiées par l’administration au fil de l’année.</span>
                </div>
            </div>
        @else
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Date</th><th>Créneau</th><th>Module</th><th>Salle</th><th>Enseignant</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($examens as $examen)
                                <tr>
                                    <td class="nowrap">
                                        <strong>{{ $examen->date_debut->locale('fr')->isoFormat('ddd D MMM') }}</strong>
                                    </td>
                                    <td class="nowrap">{{ $examen->libelleCreneau() }}</td>
                                    <td>{{ $examen->module?->nom ?? $examen->titre ?? '—' }}</td>
                                    <td>{{ $examen->salle?->nom ?? '—' }}</td>
                                    <td>{{ $examen->enseignant?->nomComplet() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    @if ($cours->isEmpty())
        <section class="card">
            <div class="card__body">
                <div class="empty">
                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                    <strong>Aucun cours planifié</strong>
                    <span>Les créneaux de votre classe seront publiés par l’administration.</span>
                </div>
            </div>
        </section>
    @else
        @foreach ($cours as $jour => $creneaux)
            <section class="card" style="margin-bottom:16px">
                <div class="card__head">
                    <h2 class="card__title">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        {{ \Illuminate\Support\Carbon::parse($jour)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </h2>
                    <span class="muted">{{ $creneaux->count() }} créneau{{ $creneaux->count() > 1 ? 'x' : '' }}</span>
                </div>
                <div class="card__body card__body--flush">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Créneau</th><th>Module</th><th>Enseignant</th><th>Salle</th><th>Type</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($creneaux as $seance)
                                    <tr>
                                        <td class="nowrap">{{ $seance->libelleCreneau() }}</td>
                                        <td><strong>{{ $seance->module?->nom ?? $seance->titre ?? '—' }}</strong></td>
                                        <td>{{ $seance->enseignant?->nomComplet() ?? '—' }}</td>
                                        <td>{{ $seance->salle?->nom ?? '—' }}</td>
                                        <td>
                                            <span class="badge badge--{{ $seance->type_creneau === 'examen' ? 'danger' : 'info' }} badge--plain">
                                                {{ ucfirst($seance->type_creneau) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endforeach
    @endif
@endsection
