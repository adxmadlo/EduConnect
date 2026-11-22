@extends('layouts.app')

@section('titre', 'Réservations de salles')
@section('sous-titre', '· ' . $enAttente . ' en attente')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Réservations de salles</h1>
            <p class="page-sub">Demandes déposées par les étudiants, enseignants et mentors.</p>
        </div>
    </div>

    @if ($enAttente > 0)
        <div class="alert alert--warning" style="margin-bottom:16px">
            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
            <div>{{ $enAttente }} demande{{ $enAttente > 1 ? 's' : '' }} attend{{ $enAttente > 1 ? 'ent' : '' }} une décision.</div>
        </div>
    @endif

    @include('reservations._disponibilite')

    <section class="card">
        <div class="card__head">
            <h2 class="card__title"><i class="bi bi-calendar-week" aria-hidden="true"></i>Toutes les demandes</h2>
            <form method="GET" action="{{ route('admin.reservations.index') }}" class="field" style="min-width:190px;margin:0">
                <label for="filtre-statut">Statut</label>
                <select id="filtre-statut" name="statut" onchange="this.form.submit()" aria-label="Filtrer par statut">
                    <option value="">Tous les statuts</option>
                    @foreach (['en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'refusee' => 'Refusée', 'annulee' => 'Annulée'] as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="card__body card__body--flush">
            @if ($reservations->isEmpty())
                <div class="empty">
                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                    <strong>Aucune demande</strong>
                    <span>Aucune réservation ne correspond à ce filtre.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Code</th><th>Demandeur</th><th>Salle</th><th>Objet</th><th>Créneau</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($reservations as $reservation)
                                <tr>
                                    <td class="nowrap"><strong>{{ $reservation->code_resa }}</strong></td>
                                    <td>
                                        {{ $reservation->demandeur?->nomComplet() ?? '—' }}
                                        <br><span class="muted" style="font-size:11px">{{ $reservation->demandeur?->libelleRole() }}</span>
                                    </td>
                                    <td class="nowrap">{{ $reservation->salle?->nom ?? '—' }}</td>
                                    <td>
                                        {{ $reservation->objet }}
                                        <br><span class="muted" style="font-size:11px">{{ $reservation->participants }} participant{{ $reservation->participants > 1 ? 's' : '' }}</span>
                                    </td>
                                    <td class="nowrap">
                                        {{ $reservation->date_debut?->format('d/m/Y H:i') }}<br>
                                        <span class="muted" style="font-size:11px">→ {{ $reservation->date_fin?->format('d/m/Y H:i') }}</span>
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.reservations.statut', $reservation) }}"
                                              class="flex gap-8" style="flex-wrap:wrap;align-items:flex-end">
                                            @csrf
                                            @method('PATCH')
                                            <div class="field" style="min-width:140px">
                                                <label for="statut-{{ $reservation->id }}">Statut</label>
                                                <select id="statut-{{ $reservation->id }}" name="statut">
                                                    @foreach (['en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'refusee' => 'Refusée', 'annulee' => 'Annulée'] as $valeur => $libelle)
                                                        <option value="{{ $valeur }}" @selected($reservation->statut === $valeur)>{{ $libelle }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="field" style="min-width:160px">
                                                <label for="notes-{{ $reservation->id }}">Motif</label>
                                                <input id="notes-{{ $reservation->id }}" name="notes_admin" type="text"
                                                       value="{{ $reservation->notes_admin }}" placeholder="Facultatif" maxlength="500">
                                            </div>
                                            <button type="submit" class="btn btn--primary btn--sm">Valider</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @include('partials.pagination', ['paginateur' => $reservations])
    </section>
@endsection
