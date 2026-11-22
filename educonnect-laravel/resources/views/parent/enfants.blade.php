@extends('layouts.app')

@section('titre', 'Suivi de mes enfants')
@section('sous-titre', '· ' . $annee)

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Suivi de mes enfants</h1>
            <p class="page-sub">Notes publiées et situation de scolarité, année {{ $annee }}.</p>
        </div>
    </div>

    @if ($enfants->isEmpty())
        <div class="alert alert--warning">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>Aucun étudiant n’est rattaché à votre compte. Contactez l’administration pour établir le lien parent–étudiant.</div>
        </div>
    @else
        @if ($enfants->count() > 1)
            <div class="segmented" role="group" aria-label="Choisir un enfant" style="margin-bottom:16px">
                @foreach ($enfants as $enfant)
                    <a href="{{ route('parent.enfants.index', $enfant) }}">
                        <button type="button" class="{{ $selection?->id === $enfant->id ? 'is-active' : '' }}">
                            {{ $enfant->user?->prenom }} {{ $enfant->user?->nom }}
                        </button>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($selection)
            <section class="stat-grid" aria-label="Situation de l’enfant">
                <article class="stat stat--accent">
                    <div class="stat__top">
                        <span class="stat__label">Moyenne publiée</span>
                        <span class="stat__icon"><i class="bi bi-graph-up" aria-hidden="true"></i></span>
                    </div>
                    <div class="stat__value">{{ $moyenne !== null ? number_format($moyenne, 2, ',', '') : '—' }}<small>/20</small></div>
                    <div class="stat__meta">{{ $notes->count() }} note{{ $notes->count() > 1 ? 's' : '' }} publiée{{ $notes->count() > 1 ? 's' : '' }}</div>
                </article>

                <article class="stat">
                    <div class="stat__top">
                        <span class="stat__label">Classe</span>
                        <span class="stat__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
                    </div>
                    <div class="stat__value" style="font-size:20px">{{ $selection->classe?->code ?? '—' }}</div>
                    <div class="stat__meta">{{ $selection->classe?->nom ?? 'Classe non assignée' }}</div>
                </article>

                <article class="stat">
                    <div class="stat__top">
                        <span class="stat__label">Crédits validés</span>
                        <span class="stat__icon"><i class="bi bi-award" aria-hidden="true"></i></span>
                    </div>
                    <div class="stat__value">{{ $selection->credits_obtenus }}<small>/{{ $selection->credits_requis }}</small></div>
                    <div class="stat__meta">Statut : {{ ucfirst($selection->statut_academique) }}</div>
                </article>

                <article class="stat">
                    <div class="stat__top">
                        <span class="stat__label">Scolarité</span>
                        <span class="stat__icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                    </div>
                    <div class="stat__value">{{ $frais?->pourcentagePaye() ?? 0 }}<small>%</small></div>
                    <div class="stat__meta">
                        Reste {{ number_format($frais?->soldeRestant() ?? 0, 0, ',', ' ') }} FCFA
                    </div>
                </article>
            </section>

            <div class="grid grid--2">
                <section class="card">
                    <div class="card__head">
                        <h2 class="card__title"><i class="bi bi-clipboard-data" aria-hidden="true"></i>Notes publiées</h2>
                    </div>
                    <div class="card__body card__body--flush">
                        @if ($notes->isEmpty())
                            <div class="empty">
                                <i class="bi bi-clipboard" aria-hidden="true"></i>
                                <strong>Aucune note publiée</strong>
                                <span>Les notes apparaîtront dès leur publication.</span>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table class="table">
                                    <thead><tr><th>Module</th><th>S.</th><th class="num">Note</th><th>Mention</th></tr></thead>
                                    <tbody>
                                        @foreach ($notes as $note)
                                            <tr>
                                                <td>
                                                    <strong>{{ $note->module->nom }}</strong><br>
                                                    <span class="muted" style="font-size:11px">{{ $note->module->code }}</span>
                                                </td>
                                                <td>S{{ $note->semestre }}</td>
                                                <td class="num">{{ number_format($note->note_finale, 2, ',', '') }}/20</td>
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

                <section class="card">
                    <div class="card__head">
                        <h2 class="card__title"><i class="bi bi-credit-card" aria-hidden="true"></i>Paiements</h2>
                    </div>
                    <div class="card__body card__body--flush">
                        @if ($paiements->isEmpty())
                            <div class="empty">
                                <i class="bi bi-credit-card" aria-hidden="true"></i>
                                <strong>Aucun paiement</strong>
                                <span>Les règlements enregistrés apparaîtront ici.</span>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table class="table">
                                    <thead><tr><th>Reçu</th><th>Moyen</th><th class="num">Montant</th><th>Statut</th></tr></thead>
                                    <tbody>
                                        @foreach ($paiements as $paiement)
                                            <tr>
                                                <td class="nowrap">{{ $paiement->recu_numero }}</td>
                                                <td>{{ $paiement->libelleMoyen() }}</td>
                                                <td class="num nowrap">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</td>
                                                <td>
                                                    <span class="badge badge--{{ $paiement->statut === 'confirme' ? 'success' : ($paiement->statut === 'en_attente' ? 'warning' : 'danger') }}">
                                                        {{ $paiement->libelleStatut() }}
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
            </div>
        @endif
    @endif
@endsection
