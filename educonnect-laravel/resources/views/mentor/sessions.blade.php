@extends('layouts.app')

@section('titre', 'Mes sessions')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Mes sessions de mentorat</h1>
            <p class="page-sub">Confirmez, terminez ou annulez les sessions demandées par les étudiants.</p>
        </div>
    </div>

    <section class="card">
        <div class="card__head">
            <h2 class="card__title"><i class="bi bi-camera-video" aria-hidden="true"></i>Toutes les sessions</h2>
        </div>
        <div class="card__body card__body--flush">
            @if ($sessions->isEmpty())
                <div class="empty">
                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                    <strong>Aucune session</strong>
                    <span>Les demandes des étudiants apparaîtront ici.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Étudiant</th><th>Classe</th><th>Module</th><th>Date</th>
                                <th>Type</th><th>Statut</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr>
                                    <td>{{ $session->etudiant?->user?->nomComplet() ?? '—' }}</td>
                                    <td>{{ $session->etudiant?->classe?->code ?? '—' }}</td>
                                    <td>{{ $session->module?->nom ?? '—' }}</td>
                                    <td class="nowrap">
                                        {{ $session->date_session->format('d/m/Y') }}<br>
                                        <span class="muted" style="font-size:11px">
                                            {{ substr($session->heure_debut, 0, 5) }} – {{ substr($session->heure_fin, 0, 5) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge--plain badge--info">
                                            {{ $session->type_session === 'en_ligne' ? 'En ligne' : 'Présentiel' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $session->statut === 'confirmee' ? 'success' : ($session->statut === 'demandee' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($session->statut) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if (in_array($session->statut, ['demandee', 'confirmee'], true))
                                            <form method="POST" action="{{ route('mentor.sessions.statut', $session) }}" class="flex gap-8">
                                                @csrf
                                                @method('PATCH')
                                                <select name="statut" aria-label="Nouveau statut de la session">
                                                    <option value="confirmee">Confirmer</option>
                                                    <option value="terminee">Terminer</option>
                                                    <option value="annulee">Annuler</option>
                                                </select>
                                                <button type="submit" class="btn btn--ghost btn--sm">Valider</button>
                                            </form>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
