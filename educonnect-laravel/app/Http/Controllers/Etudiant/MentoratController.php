<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Mentor;
use App\Models\SessionMentorat;
use Illuminate\Http\Request;

class MentoratController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $etudiant = $user->etudiant;

        return view('etudiant.mentorat', [
            'etudiant' => $etudiant,
            'sessions' => $etudiant
                ? SessionMentorat::with(['module', 'mentor.user'])
                    ->where('etudiant_id', $etudiant->id)
                    ->orderBy('date_session')
                    ->get()
                : collect(),
            'mentors'  => Mentor::with(['user', 'modules'])
                ->where('actif', true)
                ->orderByDesc('note_moyenne')
                ->get(),
            'nbNotifs' => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
