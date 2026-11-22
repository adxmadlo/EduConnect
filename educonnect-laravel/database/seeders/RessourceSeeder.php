<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\RessourcePedagogique;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ressources pédagogiques de démonstration : un petit fichier texte est écrit
 * dans storage/app/private/ressources pour que le téléchargement soit réel.
 */
class RessourceSeeder extends Seeder
{
    public function run(): void
    {
        $annee = config('educonnect.annee_acad');
        $deposants = User::whereIn('role', ['enseignant', 'mentor'])->orderBy('id')->get();

        if ($deposants->isEmpty()) {
            return;
        }

        $modeles = [
            ['IG-L1S1-03', 'cours', 'Algorithme 1 — support complet', 'Structures de contrôle, boucles, tableaux et exercices corrigés du semestre 1.'],
            ['IG-L1S1-03', 'td', 'Algorithme 1 — série de TD n°3', 'Dix exercices sur les boucles et les tableaux, avec corrections.'],
            ['IG-L1S1-05', 'tp', 'Programmation 1 (C#) — TP guidé', 'Mise en pratique : variables, conditions, boucles et méthodes en C#.'],
            ['IG-L1S2-09', 'cours', 'SGBDR — modèle relationnel', 'Normalisation, algèbre relationnelle et requêtes SQL de base.'],
            ['IG-L1S2-09', 'examen', 'SGBDR — sujet d’examen S2', 'Sujet type avec barème, durée 2 heures.'],
            ['IG-L1S1-06', 'cours', 'Mathématiques générales — fiches de révision', 'Fiches synthétiques : suites, limites et dérivées.'],
            ['IG-L1S2-07', 'lien', 'Site Web Statiques — ressources HTML/CSS', 'Sélection de références en ligne pour le module.'],
        ];

        foreach ($modeles as $index => [$code, $type, $titre, $description]) {
            $module = Module::where('code', $code)->first();
            $deposant = $deposants[$index % $deposants->count()];

            $chemin = null;
            $nomFichier = null;

            if ($type !== 'lien') {
                $nomFichier = Str::slug($titre) . '.txt';
                $chemin = 'ressources/demo-' . ($index + 1) . '.txt';

                Storage::disk('local')->put($chemin, implode("\n", [
                    'EduConnect — ressource pédagogique de démonstration',
                    'Année académique : ' . $annee,
                    'Titre : ' . $titre,
                    'Module : ' . ($module?->nom ?? '—'),
                    'Déposée par : ' . $deposant->nomComplet(),
                    '',
                    $description,
                ]));
            }

            RessourcePedagogique::updateOrCreate(
                ['titre' => $titre, 'annee_acad' => $annee],
                [
                    'module_id'    => $module?->id,
                    'classe_id'    => null,
                    'deposant_id'  => $deposant->id,
                    'description'  => $description,
                    'type'         => $type,
                    'fichier_path' => $chemin,
                    'fichier_nom'  => $nomFichier,
                    'lien_url'     => $type === 'lien' ? 'https://developer.mozilla.org/fr/docs/Web/HTML' : null,
                    'visible'      => true,
                ]
            );
        }
    }
}
