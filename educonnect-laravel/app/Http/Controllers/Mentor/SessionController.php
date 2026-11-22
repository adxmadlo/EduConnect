<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\SessionMentorat;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $mentor = $user->mentor?->load('modules');

        return view('mentor.sessions', [
            'mentor'   => $mentor,
            'sessions' => $mentor
                ? SessionMentorat::with(['etudiant.user', 'etudiant.classe', 'module'])
                    ->where('mentor_id', $mentor->id)
                    ->orderBy('date_session')
                    ->get()
                : collect(),
            'nbNotifs' => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }

    public function statut(Request $request, SessionMentorat $session)
    {
        $mentor = $request->user()->mentor;

        if (! $mentor || $session->mentor_id !== $mentor->id) {
            abort(403, 'Cette session ne vous appartient pas.');
        }

        $donnees = $request->validate([
            'statut' => ['required', 'in:confirmee,annulee,terminee'],
        ], [
            'statut.in' => 'Statut de session inconnu.',
        ]);

        $session->update(['statut' => $donnees['statut']]);

        return back()->with('succes', 'Session mise à jour.');
    }
}
