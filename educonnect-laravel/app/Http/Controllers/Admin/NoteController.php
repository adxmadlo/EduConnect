<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Module;
use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $moduleId = $request->integer('module');
        $classeId = $request->integer('classe');
        $publication = $request->query('publication');

        $notes = Note::with(['module', 'etudiant.user', 'etudiant.classe', 'auteur'])
            ->when($moduleId > 0, fn ($q) => $q->where('module_id', $moduleId))
            ->when($classeId > 0, fn ($q) => $q->whereHas('etudiant', fn ($e) => $e->where('classe_id', $classeId)))
            ->when($publication === 'publiees', fn ($q) => $q->where('publie', true))
            ->when($publication === 'brouillon', fn ($q) => $q->where('publie', false))
            ->latest('saisie_le')
            ->paginate(25)
            ->withQueryString();

        return view('admin.notes', [
            'notes'       => $notes,
            'modules'     => Module::orderBy('semestre')->orderBy('code')->get(),
            'classes'     => Classe::orderBy('code')->get(),
            'moduleId'    => $moduleId,
            'classeId'    => $classeId,
            'publication' => $publication,
            'nbPubliees'  => Note::where('publie', true)->count(),
            'nbTotal'     => Note::count(),
            'nbNotifs'    => $request->user()->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
