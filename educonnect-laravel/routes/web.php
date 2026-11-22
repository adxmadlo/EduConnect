<?php

use App\Http\Controllers\Admin\EtudiantController as AdminEtudiantController;
use App\Http\Controllers\Admin\NoteController as AdminNoteController;
use App\Http\Controllers\Admin\PaiementController as AdminPaiementController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\UtilisateurController as AdminUtilisateurController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Enseignant\ModuleController as EnseignantModuleController;
use App\Http\Controllers\Enseignant\NoteController as EnseignantNoteController;
use App\Http\Controllers\Etudiant\MentoratController as EtudiantMentoratController;
use App\Http\Controllers\Etudiant\NoteController as EtudiantNoteController;
use App\Http\Controllers\Etudiant\PaiementController as EtudiantPaiementController;
use App\Http\Controllers\Etudiant\PlanningController as EtudiantPlanningController;
use App\Http\Controllers\Mentor\SessionController as MentorSessionController;
use App\Http\Controllers\Parent\EnfantController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RessourceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes EduConnect
|--------------------------------------------------------------------------
| Les URL reprennent les noms français des anciennes pages (index.php,
| services.php, apropos.php, contact.php, Views/Auth/*) mais en routes
| nommées : plus aucun fichier PHP autonome.
*/

// ── Pages publiques ─────────────────────────────────────────────────
Route::get('/', [PublicController::class, 'accueil'])->name('accueil');
Route::get('/services', [PublicController::class, 'services'])->name('public.services');
Route::get('/a-propos', [PublicController::class, 'apropos'])->name('public.apropos');
Route::get('/contact', [PublicController::class, 'contact'])->name('public.contact');
Route::post('/contact', [PublicController::class, 'contactStore'])->name('public.contact.store');

// ── Authentification ────────────────────────────────────────────────
Route::get('/connexion', [LoginController::class, 'create'])->name('login');
Route::post('/connexion', [LoginController::class, 'store'])->name('login.store');
Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

Route::get('/inscription', [RegisterController::class, 'create'])->name('register');
Route::post('/inscription', [RegisterController::class, 'store'])->name('register.store');

// Tableau de bord : redirige vers la vue du rôle connecté
Route::get('/tableau-de-bord', [DashboardController::class, 'index'])
    ->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profil', [ProfilController::class, 'index'])->name('profil');
    Route::patch('/profil', [ProfilController::class, 'update'])->name('profil.update');
});

// ── Ressources pédagogiques et réservations (tous les acteurs) ──────
Route::middleware(['auth', 'role:etudiant,parent,enseignant,mentor,admin,chef_admin'])->group(function () {
    Route::get('/ressources', [RessourceController::class, 'index'])->name('ressources.index');
    Route::post('/ressources', [RessourceController::class, 'store'])->name('ressources.store');
    Route::get('/ressources/{ressource}/telecharger', [RessourceController::class, 'telecharger'])->name('ressources.telecharger');
    Route::delete('/ressources/{ressource}', [RessourceController::class, 'destroy'])->name('ressources.destroy');
});

Route::middleware(['auth', 'role:etudiant,enseignant,mentor,admin,chef_admin'])->group(function () {
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/occupation', [ReservationController::class, 'occupation'])->name('reservations.occupation');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
});

// ── Administration (admin + chef_admin) ──────────────────────────────
Route::middleware(['auth', 'role:admin,chef_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/etudiants', [AdminEtudiantController::class, 'index'])->name('etudiants.index');
    Route::get('/notes', [AdminNoteController::class, 'index'])->name('notes.index');
    Route::get('/paiements', [AdminPaiementController::class, 'index'])->name('paiements.index');
    Route::get('/utilisateurs', [AdminUtilisateurController::class, 'index'])->name('utilisateurs.index');
    Route::patch('/utilisateurs/{user}/statut', [AdminUtilisateurController::class, 'statut'])->name('utilisateurs.statut');
    Route::get('/reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
    Route::patch('/reservations/{reservation}/statut', [AdminReservationController::class, 'statut'])->name('reservations.statut');
});

// ── Enseignant ───────────────────────────────────────────────────────
Route::middleware(['auth', 'role:enseignant'])->prefix('enseignant')->name('enseignant.')->group(function () {
    Route::get('/modules', [EnseignantModuleController::class, 'index'])->name('modules.index');
    Route::get('/notes', [EnseignantNoteController::class, 'index'])->name('notes.index');
    Route::patch('/notes/{note}', [EnseignantNoteController::class, 'update'])->name('notes.update');
});

// ── Étudiant ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:etudiant'])->prefix('etudiant')->name('etudiant.')->group(function () {
    Route::get('/notes', [EtudiantNoteController::class, 'index'])->name('notes.index');
    Route::get('/emploi-du-temps', [EtudiantPlanningController::class, 'index'])->name('planning.index');
    Route::get('/paiements', [EtudiantPaiementController::class, 'index'])->name('paiements.index');
    Route::get('/mentorat', [EtudiantMentoratController::class, 'index'])->name('mentorat.index');
});

// ── Parent ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/enfants/{etudiant?}', [EnfantController::class, 'index'])->name('enfants.index');
});

// ── Mentor ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:mentor'])->prefix('mentor')->name('mentor.')->group(function () {
    Route::get('/sessions', [MentorSessionController::class, 'index'])->name('sessions.index');
    Route::patch('/sessions/{session}', [MentorSessionController::class, 'statut'])->name('sessions.statut');
});
