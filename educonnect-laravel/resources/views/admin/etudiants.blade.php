@extends('layouts.app')

@section('titre', 'Étudiants')
@section('sous-titre', '· ' . $etudiants->total() . ' inscrits')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Étudiants</h1>
            <p class="page-sub">Dossiers étudiants rattachés aux classes de l’établissement.</p>
        </div>
    </div>

    <section class="card">
        <div class="card__head">
            <form method="GET" action="{{ route('admin.etudiants.index') }}" class="flex gap-8" style="flex-wrap:wrap">
                <div class="field">
                    <label for="q">Rechercher</label>
                    <input id="q" name="q" type="search" value="{{ $recherche }}" placeholder="Nom, e-mail, numéro UCAO">
                </div>
                <div class="field">
                    <label for="classe">Classe</label>
                    <select id="classe" name="classe">
                        <option value="">Toutes</option>
                        @foreach ($classes as $classe)
                            <option value="{{ $classe->id }}" @selected($classeId === $classe->id)>{{ $classe->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn--primary btn--sm">Filtrer</button>
                </div>
            </form>
        </div>

        <div class="card__body card__body--flush">
            @if ($etudiants->isEmpty())
                <div class="empty">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <strong>Aucun étudiant</strong>
                    <span>Aucun dossier ne correspond à ces critères.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Numéro</th><th>Étudiant</th><th>Classe</th>
                                <th class="num">Crédits</th><th>Statut académique</th><th>Compte</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($etudiants as $etudiant)
                                <tr>
                                    <td class="nowrap">{{ $etudiant->user?->numero_ucao ?? '—' }}</td>
                                    <td>
                                        <strong>{{ $etudiant->user?->nomComplet() ?? '—' }}</strong><br>
                                        <span class="muted" style="font-size:11px">{{ $etudiant->user?->email }}</span>
                                    </td>
                                    <td>{{ $etudiant->classe?->code ?? '—' }}</td>
                                    <td class="num">{{ $etudiant->credits_obtenus }} / {{ $etudiant->credits_requis }}</td>
                                    <td>
                                        <span class="badge badge--{{ $etudiant->statut_academique === 'regulier' ? 'success' : ($etudiant->statut_academique === 'probation' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($etudiant->statut_academique) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge--{{ $etudiant->user?->statut === 'actif' ? 'success' : 'warning' }}">
                                            {{ ucfirst($etudiant->user?->statut ?? '—') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @include('partials.pagination', ['paginateur' => $etudiants])
    </section>
@endsection
