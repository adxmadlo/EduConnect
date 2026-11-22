@extends('layouts.app')

@section('titre', 'Mon profil')

@section('contenu')
    <div class="page-head">
        <div>
            <h1 class="page-title">Mon profil</h1>
            <p class="page-sub">Informations du compte et sécurité.</p>
        </div>
    </div>

    <div class="grid grid--2">
        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-person-badge" aria-hidden="true"></i>Compte</h2>
            </div>
            <div class="card__body">
                <div class="flex-between" style="padding:6px 0;border-bottom:1px solid var(--border)">
                    <span class="muted">Nom complet</span><strong>{{ $user->nomComplet() }}</strong>
                </div>
                <div class="flex-between" style="padding:6px 0;border-bottom:1px solid var(--border)">
                    <span class="muted">Rôle</span><strong>{{ $user->libelleRole() }}</strong>
                </div>
                <div class="flex-between" style="padding:6px 0;border-bottom:1px solid var(--border)">
                    <span class="muted">Numéro UCAO</span><strong>{{ $user->numero_ucao ?: '—' }}</strong>
                </div>
                <div class="flex-between" style="padding:6px 0;border-bottom:1px solid var(--border)">
                    <span class="muted">E-mail</span><strong>{{ $user->email }}</strong>
                </div>
                <div class="flex-between" style="padding:6px 0">
                    <span class="muted">Dernière connexion</span>
                    <strong>{{ $user->dernier_login?->format('d/m/Y à H:i') ?? '—' }}</strong>
                </div>
                @if ($user->preferences)
                    <div class="muted" style="font-size:12px;margin-top:14px">
                        Notifications : e-mail {{ $user->preferences->notif_email ? 'activé' : 'désactivé' }} ·
                        SMS {{ $user->preferences->notif_sms ? 'activé' : 'désactivé' }} ·
                        in-app {{ $user->preferences->notif_inapp ? 'activé' : 'désactivé' }}
                    </div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <h2 class="card__title"><i class="bi bi-shield-lock" aria-hidden="true"></i>Coordonnées et mot de passe</h2>
            </div>
            <div class="card__body">
                <form method="POST" action="{{ route('profil.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="field" style="margin-bottom:14px">
                        <label for="telephone">Téléphone</label>
                        <input id="telephone" name="telephone" type="tel" maxlength="20"
                               value="{{ old('telephone', $user->telephone) }}">
                    </div>

                    @if ($user->etudiant)
                        <div class="field" style="margin-bottom:14px">
                            <label for="adresse">Adresse</label>
                            <input id="adresse" name="adresse" type="text" maxlength="300"
                                   value="{{ old('adresse', $user->etudiant->adresse) }}">
                        </div>
                    @endif

                    <div class="field" style="margin-bottom:14px">
                        <label for="mot_de_passe_actuel">Mot de passe actuel</label>
                        <input id="mot_de_passe_actuel" name="mot_de_passe_actuel" type="password" autocomplete="current-password">
                    </div>

                    <div class="field" style="margin-bottom:14px">
                        <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
                        <input id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" type="password" autocomplete="new-password">
                    </div>

                    <div class="field" style="margin-bottom:16px">
                        <label for="nouveau_mot_de_passe_confirmation">Confirmation</label>
                        <input id="nouveau_mot_de_passe_confirmation" name="nouveau_mot_de_passe_confirmation" type="password" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn--primary">Enregistrer les modifications</button>
                </form>
            </div>
        </section>
    </div>
@endsection
