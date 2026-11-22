<?php

namespace App\Http\Controllers\Enseignant;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $modules = Module::whereHas('enseignants', fn ($q) => $q->where('users.id', $user->id))
            ->with(['classes', 'enseignants'])
            ->withCount(['notes', 'notes as notes_publiees_count' => fn ($q) => $q->where('publie', true)])
            ->orderBy('semestre')
            ->orderBy('code')
            ->get();

        return view('enseignant.modules', [
            'modules'  => $modules,
            'nbNotifs' => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
