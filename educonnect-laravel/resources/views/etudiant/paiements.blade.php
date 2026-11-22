@extends('layouts.app')

@section('titre', 'Paiements')
@section('sous-titre', '· ' . $annee)

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Ma scolarité</h1>
            <p class="page-sub">Frais de scolarité {{ $annee }} et historique des règlements.</p>
        </div>
    </div>

    @if (! $frais)
        <div class="alert alert--warning">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div>Aucun frais de scolarité n’est enregistré pour votre dossier. Contactez l’administration.</div>
        </div>
    @else
        <section class="stat-grid" aria-label="Situation de scolarité">
            <article class="stat stat--accent">
                <div class="stat__top">
                    <span class="stat__label">Montant annuel</span>
                    <span class="stat__icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value" style="font-size:22px">{{ number_format($frais->montant_total, 0, ',', ' ') }}<small> FCFA</small></div>
                <div class="stat__meta">Plan {{ $frais->plan }} · échéance {{ $frais->date_echeance?->format('d/m/Y') ?? '—' }}</div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Déjà réglé</span>
                    <span class="stat__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value" style="font-size:22px">{{ number_format($montantPaye, 0, ',', ' ') }}<small> FCFA</small></div>
                <div class="progress" style="margin-top:6px">
                    <div class="progress__bar" style="width:{{ $pourcentage }}%"></div>
                </div>
            </article>

            <article class="stat">
                <div class="stat__top">
                    <span class="stat__label">Reste à payer</span>
                    <span class="stat__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
                </div>
                <div class="stat__value" style="font-size:22px">{{ number_format($solde, 0, ',', ' ') }}<small> FCFA</small></div>
                <div class="stat__meta">Statut du dossier : {{ ucfirst(str_replace('_', ' ', $frais->statut)) }}</div>
            </article>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-clock-history" aria-hidden="true"></i>Historique des paiements</h2>
            </div>
            <div class="card__body card__body--flush">
                @if ($paiements->isEmpty())
                    <div class="empty">
                        <i class="bi bi-credit-card" aria-hidden="true"></i>
                        <strong>Aucun paiement</strong>
                        <span>Vos règlements apparaîtront ici dès leur enregistrement.</span>
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Reçu</th><th>Moyen</th><th class="num">Montant</th><th>Statut</th><th>Date</th></tr>
                            </thead>
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
                                        <td class="nowrap">{{ $paiement->created_at?->format('d/m/Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>

        @if ($solde > 0)
            <div class="alert alert--info" style="margin-top:16px">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <div>
                    Le règlement en ligne (Wave / Orange Money) sera branché sur les services de paiement :
                    les webhooks existants de l’ancienne API restent à porter dans Laravel.
                </div>
            </div>
        @endif
    @endif
@endsection
