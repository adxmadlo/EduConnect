<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Module;
use App\Models\Salle;
use Illuminate\Http\Request;

/** Pages publiques reprises de index.php, services.php, apropos.php et contact.php. */
class PublicController extends Controller
{
    public function accueil()
    {
        return view('public.accueil', [
            'nbClasses' => Classe::count(),
            'nbModules' => Module::count(),
            'nbSalles'  => Salle::where('actif', true)->count(),
            'annee'     => config('educonnect.annee_acad'),
        ]);
    }

    public function services()
    {
        return view('public.services', [
            'annee' => config('educonnect.annee_acad'),
        ]);
    }

    public function apropos()
    {
        return view('public.apropos');
    }

    public function contact()
    {
        return view('public.contact');
    }

    public function contactStore(Request $request)
    {
        $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:120'],
            'email'   => ['required', 'email', 'max:190'],
            'subject' => ['required', 'in:support,billing,partnership,feedback,other'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'name.required'    => 'Veuillez indiquer votre nom complet.',
            'email.required'   => 'Veuillez indiquer votre adresse e-mail.',
            'email.email'      => 'Cette adresse e-mail n’est pas valide.',
            'subject.required' => 'Veuillez choisir un sujet.',
            'subject.in'       => 'Ce sujet n’est pas reconnu.',
            'message.required' => 'Veuillez écrire votre message.',
            'message.min'      => 'Votre message doit contenir au moins 10 caractères.',
        ]);

        return redirect()
            ->route('public.contact')
            ->with('contact_succes', 'Merci ' . $request->input('name') . ', votre message a bien été pris en compte. Notre équipe vous répond sous 24 heures.');
    }
}
