<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Etudiant;
use App\Models\Module;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Paiement;
use App\Models\SessionMentorat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord : une vue par rôle.
 * Aucune valeur n'est codée en dur : tout provient des tables (aucune donnée de démo en dur).
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return match (true) {
            $user->estAdmin()    => $this->admin($user),
            $user->role === 'enseignant' => $this->enseignant($user),
            $user->role === 'etudiant'   => $this->etudiant($user),
            $user->role === 'parent'     => $this->parent($user),
            $user->role === 'mentor'     => $this->mentor($user),
            default => abort(403, 'Rôle inconnu.'),
        };
    }

    private function admin(User $user)
    {
        $annee = config('educonnect.annee_acad');

        $montantAttendu = (float) DB::table('frais_scolarite')->where('annee_acad', $annee)->sum('montant_total');
        $montantEncaisse = (float) DB::table('paiements')->where('statut', 'confirme')->sum('montant');

        $impayes = \App\Models\FraisScolarite::with(['etudiant.user', 'etudiant.classe'])
            ->where('annee_acad', $annee)
            ->get()
            ->filter(fn ($frais) => $frais->soldeRestant() > 0)
            ->sortByDesc(fn ($frais) => $frais->soldeRestant())
            ->take(5)
            ->values();

        return view('dashboards.admin', [
            'user'             => $user,
            'annee'            => $annee,
            'nbEtudiants'      => Etudiant::count(),
            'nbEnseignants'    => User::where('role', 'enseignant')->count(),
            'nbClasses'        => Classe::count(),
            'nbModules'        => Module::count(),
            'nbNotesPubliees'  => Note::where('publie', true)->count(),
            'nbNotes'          => Note::count(),
            'montantAttendu'   => $montantAttendu,
            'montantEncaisse'  => $montantEncaisse,
            'tauxRecouvrement' => $montantAttendu > 0 ? (int) round($montantEncaisse / $montantAttendu * 100) : 0,
            'impayes'          => $impayes,
            'paiementsRecents' => Paiement::with(['payeur', 'frais.etudiant.user'])->latest()->limit(5)->get(),
            'repartition'      => Classe::withCount('etudiants')->orderBy('code')->get(),
            'nbNotifs'         => Notification::where('user_id', $user->id)->where('lu', false)->count(),
        ]);
    }

    private function etudiant(User $user)
    {
        $annee = config('educonnect.annee_acad');
        $etudiant = $user->etudiant?->load('classe');

        if (! $etudiant) {
            return view('dashboards.etudiant', [
                'user' => $user, 'annee' => $annee, 'etudiant' => null,
                'nbNotifs' => Notification::where('user_id', $user->id)->where('lu', false)->count(),
            ]);
        }

        $notes = Note::with('module')
            ->where('etudiant_id', $etudiant->id)
            ->where('annee_acad', $annee)
            ->where('publie', true)
            ->whereNotNull('note_finale')
            ->orderByDesc('note_finale')
            ->get();

        $frais = $etudiant->fraisCourants($annee);
        $montantPaye = $frais?->montantPaye() ?? 0;
        $montantTotal = $frais?->montant_total ?? 0;

        return view('dashboards.etudiant', [
            'user'            => $user,
            'annee'           => $annee,
            'etudiant'        => $etudiant,
            'notes'           => $notes,
            'moyenne'         => $notes->isEmpty() ? null : round($notes->avg('note_finale'), 2),
            'modulesValides'  => $notes->where('note_finale', '>=', config('educonnect.seuil_validation'))->count(),
            'frais'           => $frais,
            'montantPaye'     => (float) $montantPaye,
            'montantTotal'    => (float) $montantTotal,
            'solde'           => $frais?->soldeRestant() ?? 0,
            'pourcentagePaye' => $frais?->pourcentagePaye() ?? 0,
            'creditsRequis'   => max(1, (int) $etudiant->credits_requis),
            'evolution'       => Note::with('module')
                ->where('etudiant_id', $etudiant->id)
                ->where('publie', true)
                ->whereNotNull('note_finale')
                ->orderBy('saisie_le')
                ->limit(12)
                ->get(),
            'prochainsCours'  => EmploiDuTemps::with(['module', 'salle'])
                ->where('classe_id', $etudiant->classe_id)
                ->where('date_debut', '>=', now())
                ->orderBy('date_debut')
                ->limit(5)
                ->get(),
            'sessions'        => SessionMentorat::with(['module', 'mentor.user'])
                ->where('etudiant_id', $etudiant->id)
                ->where('statut', 'confirmee')
                ->where('date_session', '>=', now()->toDateString())
                ->orderBy('date_session')
                ->limit(3)
                ->get(),
            'nbNotifs'        => Notification::where('user_id', $user->id)->where('lu', false)->count(),
        ]);
    }

    private function enseignant(User $user)
    {
        $modules = Module::whereHas('enseignants', fn ($q) => $q->where('users.id', $user->id))
            ->withCount('notes')
            ->orderBy('semestre')
            ->orderBy('code')
            ->get();

        $moduleIds = $modules->pluck('id');

        $classes = Classe::whereHas('modules', fn ($q) => $q->whereIn('modules.id', $moduleIds))->count();

        return view('dashboards.enseignant', [
            'user'            => $user,
            'modules'         => $modules,
            'nbModules'       => $modules->count(),
            'nbClasses'       => $classes,
            'nbNotes'         => Note::whereIn('module_id', $moduleIds)->count(),
            'nbPubliees'      => Note::whereIn('module_id', $moduleIds)->where('publie', true)->count(),
            'dernieresNotes'  => Note::with(['module', 'etudiant.user'])
                ->whereIn('module_id', $moduleIds)
                ->latest('saisie_le')
                ->limit(6)
                ->get(),
            'prochainsCours'  => EmploiDuTemps::with(['module', 'salle', 'classe'])
                ->where('enseignant_id', $user->id)
                ->where('date_debut', '>=', now())
                ->orderBy('date_debut')
                ->limit(5)
                ->get(),
            'nbNotifs'        => Notification::where('user_id', $user->id)->where('lu', false)->count(),
        ]);
    }

    private function parent(User $user)
    {
        $annee = config('educonnect.annee_acad');

        $enfants = Etudiant::with(['user', 'classe'])
            ->where('parent_id', $user->id)
            ->get()
            ->map(fn (Etudiant $enfant) => [
                'etudiant' => $enfant,
                'moyenne'  => $enfant->moyennePubliee($annee),
                'frais'    => $enfant->fraisCourants($annee),
            ]);

        return view('dashboards.parent', [
            'user'     => $user,
            'annee'    => $annee,
            'enfants'  => $enfants,
            'nbNotifs' => Notification::where('user_id', $user->id)->where('lu', false)->count(),
        ]);
    }

    private function mentor(User $user)
    {
        $mentor = $user->mentor?->load('modules');

        $sessions = $mentor
            ? SessionMentorat::with(['etudiant.user', 'etudiant.classe', 'module'])
                ->where('mentor_id', $mentor->id)
                ->orderBy('date_session')
                ->limit(10)
                ->get()
            : collect();

        return view('dashboards.mentor', [
            'user'     => $user,
            'mentor'   => $mentor,
            'sessions' => $sessions,
            'nbNotifs' => Notification::where('user_id', $user->id)->where('lu', false)->count(),
        ]);
    }
}
