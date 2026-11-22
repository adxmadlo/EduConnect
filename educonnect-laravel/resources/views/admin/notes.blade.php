@extends('layouts.app')

@section('titre', 'Notes')
@section('sous-titre', '· ' . $nbPubliees . '/' . $nbTotal . ' publiées')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Notes</h1>
            <p class="page-sub">Notes LMD : 30 % devoir + 70 % examen, ou 100 % examen selon le module.</p>
        </div>
    </div>

    <section class="card">
        <div class="card__head">
            <form method="GET" action="{{ route('admin.notes.index') }}" class="flex gap-8" style="flex-wrap:wrap">
                <div class="field">
                    <label for="module">Module</label>
                    <select id="module" name="module">
                        <option value="">Tous</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}" @selected($moduleId === $module->id)>
                                {{ $module->code }} — {{ $module->nom }}
                            </option>
                        @endforeach
                    </select>
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
                <div class="field">
                    <label for="publication">Publication</label>
                    <select id="publication" name="publication">
                        <option value="">Toutes</option>
                        <option value="publiees" @selected($publication === 'publiees')>Publiées</option>
                        <option value="brouillon" @selected($publication === 'brouillon')>Brouillons</option>
                    </select>
                </div>
                <div class="field" style="justify-content:flex-end">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn--primary btn--sm">Filtrer</button>
                </div>
            </form>
        </div>

        <div class="card__body card__body--flush">
            @if ($notes->isEmpty())
                <div class="empty">
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                    <strong>Aucune note</strong>
                    <span>Aucune note ne correspond à ces critères.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Étudiant</th><th>Classe</th><th>Module</th><th>S.</th>
                                <th class="num">Devoir</th><th class="num">Examen</th><th class="num">Finale</th>
                                <th>Mention</th><th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notes as $note)
                                <tr>
                                    <td>{{ $note->etudiant?->user?->nomComplet() ?? '—' }}</td>
                                    <td>{{ $note->etudiant?->classe?->code ?? '—' }}</td>
                                    <td class="nowrap">{{ $note->module?->code ?? '—' }}</td>
                                    <td>S{{ $note->semestre }}</td>
                                    <td class="num">{{ $note->note_devoir !== null ? number_format($note->note_devoir, 2, ',', '') : '—' }}</td>
                                    <td class="num">{{ $note->note_examen !== null ? number_format($note->note_examen, 2, ',', '') : '—' }}</td>
                                    <td class="num"><strong>{{ $note->note_finale !== null ? number_format($note->note_finale, 2, ',', '') : '—' }}</strong></td>
                                    <td>
                                        @if ($note->note_finale !== null)
                                            <span class="badge badge--{{ $note->note_finale >= 14 ? 'success' : ($note->note_finale >= 10 ? 'info' : 'danger') }}">
                                                {{ $note->mentionLongue() }}
                                            </span>
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
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

        @include('partials.pagination', ['paginateur' => $notes])
    </section>
@endsection
