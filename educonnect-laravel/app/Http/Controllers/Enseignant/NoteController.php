<?php

namespace App\Http\Controllers\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Saisie des notes : l'enseignant ne voit et ne modifie que ses propres modules.
 * note_finale est recalculée par Note::calculerNoteFinale() (règle LMD).
 */
class NoteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $moduleIds = Module::whereHas('enseignants', fn ($q) => $q->where('users.id', $user->id))->pluck('id');

        $moduleId = $request->integer('module');

        $notes = Note::with(['module', 'etudiant.user', 'etudiant.classe'])
            ->whereIn('module_id', $moduleIds)
            ->when($moduleId > 0, fn ($q) => $q->where('module_id', $moduleId))
            ->when($request->query('publication') === 'brouillon', fn ($q) => $q->where('publie', false))
            ->join('etudiants', 'etudiants.id', '=', 'notes.etudiant_id')
            ->join('users', 'users.id', '=', 'etudiants.user_id')
            ->orderBy('users.nom')
            ->orderBy('users.prenom')
            ->select('notes.*')
            ->paginate(25)
            ->withQueryString();

        return view('enseignant.notes', [
            'notes'    => $notes,
            'modules'  => Module::whereIn('id', $moduleIds)->orderBy('semestre')->orderBy('code')->get(),
            'moduleId' => $moduleId,
            'nbNotifs' => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }

    public function update(Request $request, Note $note)
    {
        $user = $request->user();

        $autorise = Module::whereKey($note->module_id)
            ->whereHas('enseignants', fn ($q) => $q->where('users.id', $user->id))
            ->exists();

        if (! $autorise) {
            abort(403, 'Ce module ne vous est pas attribué.');
        }

        $donnees = $request->validate([
            'note_devoir' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'note_examen' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'publie'      => ['nullable', 'boolean'],
        ], [
            'note_devoir.numeric' => 'La note de devoir doit être un nombre.',
            'note_devoir.max'     => 'La note de devoir ne peut pas dépasser 20.',
            'note_examen.numeric' => 'La note d\'examen doit être un nombre.',
            'note_examen.max'     => 'La note d\'examen ne peut pas dépasser 20.',
        ]);

        $note->note_devoir = $donnees['note_devoir'] ?? null;
        $note->note_examen = $donnees['note_examen'] ?? null;
        $note->module()->associate(Module::find($note->module_id));
        $note->calculerNoteFinale();

        if ($note->module->type_eval === '100' && $note->note_devoir !== null) {
            throw ValidationException::withMessages([
                'note_devoir' => 'Ce module est évalué à 100 % par l\'examen : la note de devoir doit rester vide.',
            ]);
        }

        $note->publie = (bool) ($donnees['publie'] ?? $note->publie);
        $note->publie_le = $note->publie ? ($note->publie_le ?? now()) : null;
        $note->modifiee_le = now();
        $note->saisie_par = $user->id;
        $note->save();

        return back()->with('succes', 'Note mise à jour.');
    }
}
