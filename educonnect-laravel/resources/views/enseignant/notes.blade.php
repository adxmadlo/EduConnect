@extends('layouts.app')

@section('titre', 'Saisie des notes')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Saisie des notes</h1>
            <p class="page-sub">Vous ne pouvez modifier que les notes de vos modules. La note finale est recalculée automatiquement.</p>
        </div>
    </div>

    <section class="card">
        <div class="card__head">
            <form method="GET" action="{{ route('enseignant.notes.index') }}" class="flex gap-8" style="flex-wrap:wrap">
                <div class="field">
                    <label for="module">Module</label>
                    <select id="module" name="module">
                        <option value="">Tous mes modules</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module->id }}" @selected($moduleId === $module->id)>
                                {{ $module->code }} — {{ $module->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="publication">Publication</label>
                    <select id="publication" name="publication">
                        <option value="">Toutes</option>
                        <option value="brouillon" @selected(request('publication') === 'brouillon')>Brouillons</option>
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
                    <span>Aucune note ne correspond à ce filtre.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Étudiant</th><th>Classe</th><th>Module</th>
                                <th class="num">Devoir /20</th><th class="num">Examen /20</th>
                                <th class="num">Finale</th><th>Publier</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notes as $note)
                                <tr>
                                    <td>
                                        <strong>{{ $note->etudiant?->user?->nomComplet() ?? '—' }}</strong><br>
                                        <span class="muted" style="font-size:11px">{{ $note->etudiant?->user?->numero_ucao }}</span>
                                    </td>
                                    <td>{{ $note->etudiant?->classe?->code ?? '—' }}</td>
                                    <td class="nowrap">{{ $note->module?->code ?? '—' }}</td>
                                    <td colspan="6" style="padding:0;border:0">
                                        <form method="POST" action="{{ route('enseignant.notes.update', $note) }}"
                                              class="flex gap-8" style="padding:8px 0;align-items:center;flex-wrap:wrap">
                                            @csrf
                                            @method('PATCH')
                                            <input name="note_devoir" type="number" step="0.25" min="0" max="20"
                                                   value="{{ $note->note_devoir !== null ? number_format($note->note_devoir, 2, '.', '') : '' }}"
                                                   aria-label="Note de devoir de {{ $note->etudiant?->user?->nomComplet() }}"
                                                   style="width:90px;padding:6px 8px;border:1px solid var(--border-strong);border-radius:8px"
                                                   @disabled($note->module?->type_eval === '100')>
                                            <input name="note_examen" type="number" step="0.25" min="0" max="20"
                                                   value="{{ $note->note_examen !== null ? number_format($note->note_examen, 2, '.', '') : '' }}"
                                                   aria-label="Note d’examen de {{ $note->etudiant?->user?->nomComplet() }}"
                                                   style="width:90px;padding:6px 8px;border:1px solid var(--border-strong);border-radius:8px">
                                            <strong class="nowrap" style="min-width:70px">
                                                {{ $note->note_finale !== null ? number_format($note->note_finale, 2, ',', '') : '—' }}/20
                                            </strong>
                                            <label class="nowrap" style="font-size:12px">
                                                <input type="checkbox" name="publie" value="1" @checked($note->publie)>
                                                Publier
                                            </label>
                                            <button type="submit" class="btn btn--ghost btn--sm">Enregistrer</button>
                                        </form>
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
