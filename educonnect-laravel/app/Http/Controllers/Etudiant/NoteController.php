<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $etudiant = $user->etudiant;

        if (! $etudiant) {
            return view('etudiant.notes', [
                'notes' => collect(), 'bulletins' => collect(), 'moyenne' => null,
                'annee' => config('educonnect.annee_acad'), 'etudiant' => null,
                'nbNotifs' => $user->notificationsEduconnect()->where('lu', false)->count(),
            ]);
        }

        $semestre = $request->integer('semestre') ?: null;
        $annee = config('educonnect.annee_acad');

        $notes = Note::with('module')
            ->where('etudiant_id', $etudiant->id)
            ->where('publie', true)
            ->where('annee_acad', $annee)
            ->when($semestre, fn ($q) => $q->where('semestre', $semestre))
            ->orderBy('semestre')
            ->orderByDesc('note_finale')
            ->get();

        return view('etudiant.notes', [
            'etudiant'  => $etudiant,
            'notes'     => $notes,
            'bulletins' => Bulletin::where('etudiant_id', $etudiant->id)->orderByDesc('semestre')->get(),
            'moyenne'   => $notes->isEmpty() ? null : round($notes->avg('note_finale'), 2),
            'semestre'  => $semestre,
            'annee'     => $annee,
            'nbNotifs'  => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
