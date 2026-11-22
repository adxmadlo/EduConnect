@extends('layouts.app')

@section('titre', 'Réservations de salles')
@section('sous-titre', '· ' . $reservations->total() . ' demande' . ($reservations->total() > 1 ? 's' : ''))

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Réservation de salles</h1>
            <p class="page-sub">Demandez une salle libre ; l’administration confirme la réservation.</p>
        </div>
    </div>

    <section class="card" style="margin-bottom:16px">
        <div class="card__head">
            <h2 class="card__title"><i class="bi bi-calendar-plus" aria-hidden="true"></i>Nouvelle demande</h2>
        </div>
        <div class="card__body">
            <form method="POST" action="{{ route('reservations.store') }}">
                @csrf
                <div class="grid grid--2">
                    <div class="field">
                        <label for="salle_id">Salle *</label>
                        <select id="salle_id" name="salle_id" required>
                            <option value="">Choisir une salle</option>
                            @foreach ($salles as $salle)
                                <option value="{{ $salle->id }}" @selected((string) old('salle_id') === (string) $salle->id)>
                                    {{ $salle->nom }} — {{ $salle->type }} ({{ $salle->capacite }} places)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="participants">Participants *</label>
                        <input id="participants" name="participants" type="number" min="1" max="500" value="{{ old('participants', 1) }}" required>
                    </div>
                    <div class="field">
                        <label for="date_debut">Début *</label>
                        <input id="date_debut" name="date_debut" type="datetime-local" value="{{ old('date_debut') }}" required>
                    </div>
                    <div class="field">
                        <label for="date_fin">Fin *</label>
                        <input id="date_fin" name="date_fin" type="datetime-local" value="{{ old('date_fin') }}" required>
                    </div>
                    <div class="field" style="grid-column:1/-1">
                        <label for="objet">Objet *</label>
                        <input id="objet" name="objet" type="text" value="{{ old('objet') }}" maxlength="300"
                               placeholder="Ex. Réunion de projet, séance de mentorat…" required>
                    </div>
                </div>
                <button type="submit" class="btn btn--primary"><i class="bi bi-send" aria-hidden="true"></i>Envoyer la demande</button>
            </form>
        </div>
    </section>

    @include('reservations._disponibilite')

    <section class="card">
        <div class="card__head">
            <h2 class="card__title"><i class="bi bi-calendar-check" aria-hidden="true"></i>Mes demandes</h2>
            <form method="GET" action="{{ route('reservations.index') }}" class="field" style="min-width:190px;margin:0">
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
                    <strong>Aucune réservation</strong>
                    <span>Vos demandes de salle apparaîtront ici.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Code</th><th>Salle</th><th>Objet</th><th>Créneau</th><th>Participants</th><th>Statut</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($reservations as $reservation)
                                <tr>
                                    <td class="nowrap"><strong>{{ $reservation->code_resa }}</strong></td>
                                    <td class="nowrap">{{ $reservation->salle?->nom ?? '—' }}</td>
                                    <td>{{ $reservation->objet }}</td>
                                    <td class="nowrap">
                                        {{ $reservation->date_debut?->format('d/m/Y H:i') }}<br>
                                        <span class="muted" style="font-size:11px">→ {{ $reservation->date_fin?->format('d/m/Y H:i') }}</span>
                                    </td>
                                    <td>{{ $reservation->participants }}</td>
                                    <td>
                                        @php
                                            $variante = ['en_attente' => 'warning', 'confirmee' => 'success', 'refusee' => 'danger', 'annulee' => 'info'][$reservation->statut] ?? 'info';
                                        @endphp
                                        <span class="badge badge--{{ $variante }} badge--plain">{{ ucfirst(str_replace('_', ' ', $reservation->statut)) }}</span>
                                        @if ($reservation->notes_admin)
                                            <br><span class="muted" style="font-size:11px">{{ $reservation->notes_admin }}</span>
                                        @endif
                                    </td>
                                    <td class="nowrap">
                                        @if (in_array($reservation->statut, ['en_attente', 'refusee'], true))
                                            <form method="POST" action="{{ route('reservations.destroy', $reservation) }}" style="display:inline"
                                                  onsubmit="return confirm('Annuler cette demande ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--ghost btn--sm">Annuler</button>
                                            </form>
                                        @endif
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
