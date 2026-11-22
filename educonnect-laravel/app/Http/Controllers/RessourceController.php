<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Module;
use App\Models\RessourcePedagogique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ressources pédagogiques : consultation par tous les acteurs, dépôt réservé
 * aux enseignants, mentors et à l'administration.
 */
class RessourceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $requete = RessourcePedagogique::with(['module', 'classe', 'deposant'])->latest();

        if ($user->role === 'etudiant') {
            $classeId = $user->etudiant?->classe_id;
            $requete->where('visible', true)
                ->where(fn ($q) => $q->whereNull('classe_id')->orWhere('classe_id', $classeId));
        } elseif ($user->role === 'enseignant') {
            $modules = Module::whereHas('enseignants', fn ($q) => $q->where('users.id', $user->id))->pluck('id');
            $requete->where(fn ($q) => $q
                ->where('deposant_id', $user->id)
                ->orWhereIn('module_id', $modules)
                ->orWhereNull('module_id'));
        } elseif ($user->role === 'mentor') {
            $requete->where('visible', true);
        }

        $moduleId = $request->integer('module');
        $type = $request->query('type');
        $recherche = trim((string) $request->query('q'));

        $requete
            ->when($moduleId > 0, fn ($q) => $q->where('module_id', $moduleId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($recherche !== '', fn ($q) => $q->where('titre', 'like', "%{$recherche}%"));

        return view('ressources.index', [
            'ressources' => $requete->paginate(15)->withQueryString(),
            'modules'    => Module::orderBy('semestre')->orderBy('code')->get(),
            'classes'    => Classe::orderBy('code')->get(),
            'moduleId'   => $moduleId,
            'type'       => $type,
            'recherche'  => $recherche,
            'peutDeposer' => $this->peutDeposer($user),
            'nbNotifs'   => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        abort_unless($this->peutDeposer($user), 403, 'Vous n’êtes pas autorisé à déposer une ressource.');

        $donnees = $request->validate([
            'titre'       => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type'        => ['required', 'in:' . implode(',', array_keys(RessourcePedagogique::TYPES))],
            'module_id'   => ['nullable', 'integer', 'exists:modules,id'],
            'classe_id'   => ['nullable', 'integer', 'exists:classes,id'],
            'lien_url'    => ['nullable', 'url', 'max:255'],
            'fichier'     => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,txt,png,jpg,jpeg'],
            'visible'     => ['nullable', 'boolean'],
        ], [
            'titre.required'  => 'Le titre de la ressource est obligatoire.',
            'titre.min'       => 'Le titre doit contenir au moins 3 caractères.',
            'type.required'   => 'Veuillez choisir un type de ressource.',
            'type.in'         => 'Ce type de ressource n’est pas reconnu.',
            'module_id.exists' => 'Ce module n’existe pas.',
            'classe_id.exists' => 'Cette classe n’existe pas.',
            'lien_url.url'    => 'Le lien doit être une adresse complète (http:// ou https://).',
            'fichier.max'     => 'Le fichier ne doit pas dépasser 10 Mo.',
            'fichier.mimes'   => 'Format non accepté (PDF, Word, Excel, PowerPoint, ZIP, TXT ou image).',
        ]);

        if (empty($donnees['module_id']) && $request->file('fichier') === null && empty($donnees['lien_url'])) {
            return back()
                ->withInput()
                ->withErrors(['fichier' => 'Joignez un fichier ou indiquez un lien vers la ressource.']);
        }

        if (! empty($donnees['module_id']) && $user->role === 'enseignant') {
            $autorise = Module::whereKey($donnees['module_id'])
                ->whereHas('enseignants', fn ($q) => $q->where('users.id', $user->id))
                ->exists();

            if (! $autorise) {
                return back()->withInput()->withErrors(['module_id' => 'Ce module ne vous est pas attribué.']);
            }
        }

        $chemin = null;
        $nomFichier = null;

        if ($fichier = $request->file('fichier')) {
            $chemin = $fichier->store('ressources', 'local');
            $nomFichier = $fichier->getClientOriginalName();
        }

        RessourcePedagogique::create([
            'module_id'     => $donnees['module_id'] ?? null,
            'classe_id'     => $donnees['classe_id'] ?? null,
            'deposant_id'   => $user->id,
            'titre'         => $donnees['titre'],
            'description'   => $donnees['description'] ?? null,
            'type'          => $donnees['type'],
            'fichier_path'  => $chemin,
            'fichier_nom'   => $nomFichier,
            'lien_url'      => $donnees['lien_url'] ?? null,
            'annee_acad'    => config('educonnect.annee_acad'),
            'visible'       => (bool) ($donnees['visible'] ?? true),
        ]);

        return back()->with('succes', 'Ressource « ' . $donnees['titre'] . ' » déposée.');
    }

    public function destroy(Request $request, RessourcePedagogique $ressource)
    {
        $user = $request->user();
        $proprietaire = $ressource->deposant_id === $user->id;

        abort_unless($proprietaire || $user->estAdmin(), 403, 'Cette ressource ne vous appartient pas.');

        if ($ressource->fichier_path) {
            Storage::disk('local')->delete($ressource->fichier_path);
        }

        $titre = $ressource->titre;
        $ressource->delete();

        return back()->with('succes', 'Ressource « ' . $titre . ' » supprimée.');
    }

    /** Téléchargement du fichier (compteur incrémenté). */
    public function telecharger(Request $request, RessourcePedagogique $ressource)
    {
        abort_if($ressource->fichier_path === null, 404, 'Cette ressource est un lien externe.');
        abort_unless(Storage::disk('local')->exists($ressource->fichier_path), 404, 'Fichier introuvable.');

        $ressource->increment('telechargements');

        return Storage::disk('local')->download(
            $ressource->fichier_path,
            $ressource->fichier_nom ?: Str::slug($ressource->titre) . '.' . pathinfo($ressource->fichier_path, PATHINFO_EXTENSION)
        );
    }

    private function peutDeposer($user): bool
    {
        return $user->estAdmin() || in_array($user->role, ['enseignant', 'mentor'], true);
    }
}
