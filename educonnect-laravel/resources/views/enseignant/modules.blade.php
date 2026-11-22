@extends('layouts.app')

@section('titre', 'Mes modules')
@section('sous-titre', '· ' . $modules->count())

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Mes modules</h1>
            <p class="page-sub">Modules dont vous êtes enseignant cette année.</p>
        </div>
        <a class="btn btn--primary" href="{{ route('enseignant.notes.index') }}">
            <i class="bi bi-pencil-square" aria-hidden="true"></i>Saisir des notes
        </a>
    </div>

    @if ($modules->isEmpty())
        <div class="card">
            <div class="card__body">
                <div class="empty">
                    <i class="bi bi-journal-x" aria-hidden="true"></i>
                    <strong>Aucun module attribué</strong>
                    <span>Contactez l’administration pour l’attribution de vos modules.</span>
                </div>
            </div>
        </div>
    @else
        <div class="grid grid--2">
            @foreach ($modules as $module)
                <section class="card">
                    <div class="card__head">
                        <div>
                            <h2 class="card__title"><i class="bi bi-book" aria-hidden="true"></i>{{ $module->nom }}</h2>
                            <p class="card__hint">
                                {{ $module->code }} · S{{ $module->semestre }} ·
                                {{ $module->type_eval === '100' ? '100 % examen' : '30 % devoir + 70 % examen' }}
                            </p>
                        </div>
                        <span class="badge badge--plain badge--info">{{ $module->credits }} crédits</span>
                    </div>
                    <div class="card__body">
                        <div class="flex-between" style="margin-bottom:8px">
                            <span class="muted">Coefficient</span>
                            <strong>{{ $module->coefficient }}</strong>
                        </div>
                        <div class="flex-between" style="margin-bottom:8px">
                            <span class="muted">Notes saisies</span>
                            <strong>{{ $module->notes_count }}</strong>
                        </div>
                        <div class="flex-between" style="margin-bottom:8px">
                            <span class="muted">Notes publiées</span>
                            <strong>{{ $module->notes_publiees_count }}</strong>
                        </div>
                        <div class="flex-between" style="margin-bottom:8px">
                            <span class="muted">Classes</span>
                            <strong>{{ $module->classes->pluck('code')->join(', ') ?: '—' }}</strong>
                        </div>
                        <a class="btn btn--ghost btn--sm" style="margin-top:10px"
                           href="{{ route('enseignant.notes.index', ['module' => $module->id]) }}">
                            Voir les notes de ce module
                        </a>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
