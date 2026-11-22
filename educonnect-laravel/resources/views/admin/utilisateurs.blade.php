@extends('layouts.app')

@section('titre', 'Utilisateurs')
@section('sous-titre', '· ' . $utilisateurs->total() . ' comptes')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Utilisateurs</h1>
            <p class="page-sub">Comptes de l’établissement, tous rôles confondus.</p>
        </div>
    </div>

    <section class="card">
        <div class="card__head">
            <form method="GET" action="{{ route('admin.utilisateurs.index') }}" class="flex gap-8" style="flex-wrap:wrap">
                <div class="field">
                    <label for="q">Rechercher</label>
                    <input id="q" name="q" type="search" value="{{ $recherche }}" placeholder="Nom, prénom, e-mail">
                </div>
                <div class="field">
                    <label for="role">Rôle</label>
                    <select id="role" name="role">
                        <option value="">Tous</option>
                        @foreach (\App\Models\User::LIBELLES_ROLES as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($role === $valeur)>{{ $libelle }}</option>
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
            @if ($utilisateurs->isEmpty())
                <div class="empty">
                    <i class="bi bi-person-x" aria-hidden="true"></i>
                    <strong>Aucun compte</strong>
                    <span>Aucun utilisateur ne correspond à ces critères.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Utilisateur</th><th>Rôle</th><th>Numéro UCAO</th><th>Dernière connexion</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($utilisateurs as $utilisateur)
                                <tr>
                                    <td>
                                        <strong>{{ $utilisateur->nomComplet() }}</strong><br>
                                        <span class="muted" style="font-size:11px">{{ $utilisateur->email }}</span>
                                    </td>
                                    <td>{{ $utilisateur->libelleRole() }}</td>
                                    <td class="nowrap">{{ $utilisateur->numero_ucao ?: '—' }}</td>
                                    <td class="nowrap">{{ $utilisateur->dernier_login?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                                    <td>
                                        @if ($utilisateur->id === auth()->id())
                                            <span class="badge badge--info">Vous</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.utilisateurs.statut', $utilisateur) }}">
                                                @csrf
                                                @method('PATCH')
                                                <select name="statut" onchange="this.form.submit()" aria-label="Statut de {{ $utilisateur->nomComplet() }}">
                                                    @foreach (['actif', 'suspendu', 'en_attente', 'archive'] as $statut)
                                                        <option value="{{ $statut }}" @selected($utilisateur->statut === $statut)>
                                                            {{ ucfirst(str_replace('_', ' ', $statut)) }}
                                                        </option>
                                                    @endforeach
                                                </select>
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

        @include('partials.pagination', ['paginateur' => $utilisateurs])
    </section>
@endsection
