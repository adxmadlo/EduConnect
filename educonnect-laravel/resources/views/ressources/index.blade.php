@extends('layouts.app')

@section('titre', 'Ressources pédagogiques')
@section('sous-titre', '· ' . $ressources->total() . ' document' . ($ressources->total() > 1 ? 's' : ''))

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Ressources pédagogiques</h1>
            <p class="page-sub">Supports de cours, TD, TP et sujets d’examen partagés par les enseignants et les mentors.</p>
        </div>
    </div>

    @if ($peutDeposer)
        <section class="card" style="margin-bottom:16px">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>Déposer une ressource</h2>
            </div>
            <div class="card__body">
                <form method="POST" action="{{ route('ressources.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid--2">
                        <div class="field">
                            <label for="titre">Titre *</label>
                            <input id="titre" name="titre" type="text" value="{{ old('titre') }}" required maxlength="200">
                        </div>
                        <div class="field">
                            <label for="type">Type *</label>
                            <select id="type" name="type" required>
                                @foreach (\App\Models\RessourcePedagogique::TYPES as $valeur => $libelle)
                                    <option value="{{ $valeur }}" @selected(old('type') === $valeur)>{{ $libelle }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="module_id">Module</label>
                            <select id="module_id" name="module_id">
                                <option value="">Aucun module</option>
                                @foreach ($modules as $module)
                                    <option value="{{ $module->id }}" @selected((string) old('module_id') === (string) $module->id)>
                                        S{{ $module->semestre }} · {{ $module->code }} — {{ $module->nom }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="classe_id">Classe concernée</label>
                            <select id="classe_id" name="classe_id">
                                <option value="">Toutes les classes</option>
                                @foreach ($classes as $classe)
                                    <option value="{{ $classe->id }}" @selected((string) old('classe_id') === (string) $classe->id)>{{ $classe->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="fichier">Fichier (10 Mo max.)</label>
                            <input id="fichier" name="fichier" type="file">
                        </div>
                        <div class="field">
                            <label for="lien_url">Ou lien externe</label>
                            <input id="lien_url" name="lien_url" type="url" value="{{ old('lien_url') }}" placeholder="https://…">
                        </div>
                        <div class="field" style="grid-column:1/-1">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="2" maxlength="2000">{{ old('description') }}</textarea>
                        </div>
                        <div class="field">
                            <label for="visible">Visibilité</label>
                            <select id="visible" name="visible">
                                <option value="1" @selected(old('visible') !== '0')>Visible par les étudiants</option>
                                <option value="0" @selected(old('visible') === '0')>Masquée (brouillon)</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-8" style="margin-top:8px">
                        <button type="submit" class="btn btn--primary"><i class="bi bi-upload" aria-hidden="true"></i>Publier</button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <section class="card">
        <div class="card__head">
            <form method="GET" action="{{ route('ressources.index') }}" class="flex gap-8" style="flex-wrap:wrap">
                <div class="field">
                    <label for="q">Rechercher</label>
                    <input id="q" name="q" type="search" value="{{ $recherche }}" placeholder="Titre de la ressource">
                </div>
                <div class="field">
                    <label for="module">Module</label>
                    <select id="module" name="module">
                        <option value="">Tous</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}" @selected($moduleId === $module->id)>{{ $module->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="type-filtre">Type</label>
                    <select id="type-filtre" name="type">
                        <option value="">Tous</option>
                        @foreach (\App\Models\RessourcePedagogique::TYPES as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($type === $valeur)>{{ $libelle }}</option>
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
            @if ($ressources->isEmpty())
                <div class="empty">
                    <i class="bi bi-folder2-open" aria-hidden="true"></i>
                    <strong>Aucune ressource</strong>
                    <span>Les supports déposés apparaîtront ici.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Ressource</th><th>Module</th><th>Type</th><th>Déposée par</th><th>Date</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($ressources as $ressource)
                                <tr>
                                    <td>
                                        <strong>{{ $ressource->titre }}</strong>
                                        @if (! $ressource->visible)
                                            <span class="badge badge--warning" style="margin-left:6px">Brouillon</span>
                                        @endif
                                        @if ($ressource->description)
                                            <br><span class="muted" style="font-size:11px">{{ \Illuminate\Support\Str::limit($ressource->description, 90) }}</span>
                                        @endif
                                    </td>
                                    <td class="nowrap">{{ $ressource->module?->code ?? '—' }}</td>
                                    <td>{{ $ressource->libelleType() }}</td>
                                    <td>{{ $ressource->deposant?->nomComplet() ?? '—' }}</td>
                                    <td class="nowrap">{{ $ressource->created_at?->format('d/m/Y') }}</td>
                                    <td class="nowrap">
                                        @if ($ressource->estUnLien())
                                            <a class="btn btn--ghost btn--sm" href="{{ $ressource->lien_url }}" target="_blank" rel="noopener">
                                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>Ouvrir
                                            </a>
                                        @else
                                            <a class="btn btn--ghost btn--sm" href="{{ route('ressources.telecharger', $ressource) }}">
                                                <i class="bi bi-download" aria-hidden="true"></i>Télécharger
                                            </a>
                                        @endif
                                        @if (auth()->user()->estAdmin() || $ressource->deposant_id === auth()->id())
                                            <form method="POST" action="{{ route('ressources.destroy', $ressource) }}" style="display:inline"
                                                  onsubmit="return confirm('Supprimer cette ressource ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--ghost btn--sm" aria-label="Supprimer {{ $ressource->titre }}">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
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

        @include('partials.pagination', ['paginateur' => $ressources])
    </section>
@endsection
