@extends('layouts.app')

@section('titre', 'Mes notes')
@section('sous-titre', '· ' . $annee)

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Mes notes</h1>
            <p class="page-sub">
                Année académique {{ $annee }} ·
                moyenne des notes publiées :
                <strong>{{ $moyenne !== null ? number_format($moyenne, 2, ',', '') . '/20' : '—' }}</strong>
            </p>
        </div>
        <div class="segmented" role="group" aria-label="Filtrer par semestre">
            <a href="{{ route('etudiant.notes.index') }}"><button type="button" class="{{ $semestre ? '' : 'is-active' }}">Tout</button></a>
            <a href="{{ route('etudiant.notes.index', ['semestre' => 1]) }}"><button type="button" class="{{ $semestre === 1 ? 'is-active' : '' }}">Semestre 1</button></a>
            <a href="{{ route('etudiant.notes.index', ['semestre' => 2]) }}"><button type="button" class="{{ $semestre === 2 ? 'is-active' : '' }}">Semestre 2</button></a>
        </div>
    </div>

    @if (! $etudiant)
        <div class="alert alert--warning">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>Votre dossier étudiant n’est pas encore rattaché à une classe. Contactez l’administration.</div>
        </div>
    @else
        <div class="grid grid--aside" style="margin-bottom:16px">
            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><i class="bi bi-clipboard-data" aria-hidden="true"></i>Notes publiées</h2>
                </div>
                <div class="card__body card__body--flush">
                    @if ($notes->isEmpty())
                        <div class="empty">
                            <i class="bi bi-clipboard" aria-hidden="true"></i>
                            <strong>Aucune note publiée</strong>
                            <span>Vos notes apparaîtront dès leur publication par vos enseignants.</span>
                        </div>
                    @else
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Module</th><th>S.</th><th>Évaluation</th>
                                        <th class="num">Devoir</th><th class="num">Examen</th>
                                        <th class="num">Note</th><th>Mention</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($notes as $note)
                                        <tr>
                                            <td>
                                                <strong>{{ $note->module->nom }}</strong><br>
                                                <span class="muted" style="font-size:11px">
                                                    {{ $note->module->code }} · {{ $note->module->credits }} crédits
                                                </span>
                                            </td>
                                            <td>S{{ $note->semestre }}</td>
                                            <td class="nowrap">{{ $note->module->type_eval === '100' ? '100 % examen' : '30 / 70' }}</td>
                                            <td class="num">{{ $note->note_devoir !== null ? number_format($note->note_devoir, 2, ',', '') : '—' }}</td>
                                            <td class="num">{{ $note->note_examen !== null ? number_format($note->note_examen, 2, ',', '') : '—' }}</td>
                                            <td class="num"><strong>{{ number_format($note->note_finale, 2, ',', '') }}/20</strong></td>
                                            <td>
                                                <span class="badge badge--{{ $note->note_finale >= 14 ? 'success' : ($note->note_finale >= 10 ? 'info' : 'danger') }}">
                                                    {{ $note->mentionLongue() }}
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

            <aside class="card">
                <div class="card__head">
                    <h2 class="card__title"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>Bulletins</h2>
                </div>
                <div class="card__body">
                    @forelse ($bulletins as $bulletin)
                        <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                            <div class="flex-between">
                                <strong>Semestre {{ $bulletin->semestre }}</strong>
                                <span class="badge badge--{{ $bulletin->moyenne_sem >= 10 ? 'success' : 'danger' }}">
                                    {{ number_format($bulletin->moyenne_sem, 2, ',', '') }}/20
                                </span>
                            </div>
                            <div class="muted" style="font-size:12px;margin-top:4px">
                                Rang {{ $bulletin->rang ?? '—' }} ·
                                {{ $bulletin->credits_obtenus ?? 0 }} crédits ·
                                mention {{ $bulletin->mention ?? '—' }}
                            </div>
                        </div>
                    @empty
                        <div class="empty">
                            <i class="bi bi-file-earmark-x" aria-hidden="true"></i>
                            <strong>Aucun bulletin</strong>
                            <span>Vos bulletins seront générés après publication des notes.</span>
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>
    @endif
@endsection
