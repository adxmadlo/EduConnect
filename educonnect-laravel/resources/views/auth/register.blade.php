@extends('layouts.auth')

@section('titre', 'Inscription')

@section('classe-container', 'active')

@section('inscription')
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="brand-logo"><i class='bx bxs-graduation'></i> EduConnect</div>
        <h1>Créer un compte</h1>
        <p class="subtitle">Rejoignez des milliers d'étudiants</p>
        @if ($errors->any())
            <div style="background:#fde8ec;border:1px solid #f5a0b0;color:#C41E3A;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px">
                {{ $errors->first() }}
            </div>
        @endif
        <div class="input-group"><i class='bx bx-user'></i><input type="text" name="prenom" value="{{ old('prenom') }}" placeholder="Prénom" required></div>
        <div class="input-group"><i class='bx bx-user'></i><input type="text" name="nom" value="{{ old('nom') }}" placeholder="Nom" required></div>
        <div class="input-group"><i class='bx bx-envelope'></i><input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required></div>
        <div class="input-group">
            <i class='bx bx-book'></i>
            <select name="classe_id" required>
                <option value="">Choisir une classe</option>
                @foreach ($classes as $classe)
                    <option value="{{ $classe->id }}" @selected((string) old('classe_id') === (string) $classe->id)>{{ $classe->code }} — {{ $classe->nom }}</option>
                @endforeach
            </select>
        </div>
        <div class="input-group"><i class='bx bx-lock-alt'></i><input type="password" name="password" placeholder="Mot de passe (8 caractères min.)" required></div>
        <button type="submit" class="btn-auth"><i class='bx bx-user-plus'></i> S'inscrire</button>
    </form>
@endsection

@section('connexion')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="brand-logo"><i class='bx bxs-graduation'></i> EduConnect</div>
        <h1>Se connecter</h1>
        <p class="subtitle">Bon retour parmi nous !</p>
        <div class="input-group"><i class='bx bx-envelope'></i><input type="email" name="email" placeholder="Email" required></div>
        <div class="input-group"><i class='bx bx-lock-alt'></i><input type="password" name="password" placeholder="Mot de passe" required></div>
        <button type="submit" class="btn-auth"><i class='bx bx-log-in'></i> Se connecter</button>
    </form>
@endsection
