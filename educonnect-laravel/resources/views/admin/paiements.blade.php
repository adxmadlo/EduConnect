@extends('layouts.app')

@section('titre', 'Paiements')
@section('sous-titre', '· ' . $nbConfirmes . ' confirmés')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Paiements</h1>
            <p class="page-sub">Encaissements Wave, Orange Money, carte, virement et espèces.</p>
        </div>
    </div>

    <section class="stat-grid" aria-label="Indicateurs de trésorerie">
        <article class="stat stat--accent">
            <div class="stat__top">
                <span class="stat__label">Total confirmé</span>
                <span class="stat__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value" style="font-size:22px">{{ number_format($totalConfirme, 0, ',', ' ') }}<small> FCFA</small></div>
            <div class="stat__meta">{{ $nbConfirmes }} paiements reçus</div>
        </article>

        <article class="stat">
            <div class="stat__top">
                <span class="stat__label">En attente</span>
                <span class="stat__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
            </div>
            <div class="stat__value" style="font-size:22px">{{ number_format($totalAttente, 0, ',', ' ') }}<small> FCFA</small></div>
            <div class="stat__meta">À confirmer par le prestataire</div>
        </article>

        @foreach ($parMoyen->take(2) as $ligne)
            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">{{ \App\Models\Paiement::LIBELLES_MOYENS[$ligne->moyen] ?? $ligne->moyen }}</span>
                    <span class="stat__icon"><i class="bi bi-phone" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value" style="font-size:22px">{{ number_format($ligne->total, 0, ',', ' ') }}<small> FCFA</small></div>
                <div class="stat__meta">{{ $ligne->nb }} paiement{{ $ligne->nb > 1 ? 's' : '' }}</div>
            </article>
        @endforeach
    </section>

    <section class="card">
        <div class="card__head">
            <form method="GET" action="{{ route('admin.paiements.index') }}" class="flex gap-8" style="flex-wrap:wrap">
                <div class="field">
                    <label for="statut">Statut</label>
                    <select id="statut" name="statut">
                        <option value="">Tous</option>
                        @foreach (\App\Models\Paiement::LIBELLES_STATUTS as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="moyen">Moyen</label>
                    <select id="moyen" name="moyen">
                        <option value="">Tous</option>
                        @foreach (\App\Models\Paiement::LIBELLES_MOYENS as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected($moyen === $valeur)>{{ $libelle }}</option>
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
            @if ($paiements->isEmpty())
                <div class="empty">
                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                    <strong>Aucun paiement</strong>
                    <span>Aucun encaissement ne correspond à ces critères.</span>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Reçu</th><th>Étudiant</th><th>Payeur</th><th>Moyen</th>
                                <th class="num">Montant</th><th>Statut</th><th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paiements as $paiement)
                                <tr>
                                    <td class="nowrap">{{ $paiement->recu_numero }}</td>
                                    <td>
                                        {{ $paiement->frais?->etudiant?->user?->nomComplet() ?? '—' }}<br>
                                        <span class="muted" style="font-size:11px">{{ $paiement->frais?->etudiant?->classe?->code ?? '—' }}</span>
                                    </td>
                                    <td>{{ $paiement->payeur?->nomComplet() ?? '—' }}</td>
                                    <td>{{ $paiement->libelleMoyen() }}</td>
                                    <td class="num nowrap">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</td>
                                    <td>
                                        <span class="badge badge--{{ $paiement->statut === 'confirme' ? 'success' : ($paiement->statut === 'en_attente' ? 'warning' : 'danger') }}">
                                            {{ $paiement->libelleStatut() }}
                                        </span>
                                    </td>
                                    <td class="nowrap">{{ $paiement->created_at?->format('d/m/Y') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @include('partials.pagination', ['paginateur' => $paiements])
    </section>
@endsection
