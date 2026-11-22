<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Module;
use App\Models\Salle;
use Illuminate\Database\Seeder;

/**
 * Référentiels repris de educonnect_final.sql :
 * 3 classes, 25 salles UCAO, 21 modules (L1 S1 + S2).
 */
class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $annee = config('educonnect.annee_acad');

        $classes = [
            ['code' => 'LIG1-1', 'nom' => 'Licence Informatique de Gestion 1 — Groupe 1', 'filiere' => 'Informatique de Gestion', 'annee_etude' => 1, 'capacite' => 40],
            ['code' => 'LIG1-2', 'nom' => 'Licence Informatique de Gestion 1 — Groupe 2', 'filiere' => 'Informatique de Gestion', 'annee_etude' => 1, 'capacite' => 40],
            ['code' => 'LIG2-1', 'nom' => 'Licence Informatique de Gestion 2 — Groupe 1', 'filiere' => 'Informatique de Gestion', 'annee_etude' => 2, 'capacite' => 40],
        ];

        foreach ($classes as $classe) {
            Classe::updateOrCreate(['code' => $classe['code']], $classe + ['annee_acad' => $annee]);
        }

        // Salles UCAO (1er → 6e étage) — mêmes noms et capacités que le schéma d'origine
        $salles = [
            ['UCAO-1-1', 1, 'cours', 35, ['Tableau', 'Projecteur', 'Wi-Fi']],
            ['UCAO-1-2', 1, 'cours', 35, ['Tableau', 'Projecteur', 'Wi-Fi']],
            ['UCAO-1-3', 1, 'cours', 30, ['Tableau', 'Wi-Fi']],
            ['UCAO-1-4', 1, 'cours', 30, ['Tableau', 'Wi-Fi']],
            ['UCAO-1-5', 1, 'cours', 30, ['Tableau', 'Wi-Fi']],
            ['UCAO-2-1', 2, 'cours', 35, ['Tableau', 'Projecteur', 'Wi-Fi']],
            ['UCAO-2-2', 2, 'cours', 35, ['Tableau', 'Projecteur', 'Wi-Fi']],
            ['UCAO-2-3', 2, 'cours', 30, ['Tableau', 'Wi-Fi']],
            ['UCAO-2-4', 2, 'informatique', 25, ['PC×25', 'Projecteur', 'Wi-Fi']],
            ['UCAO-2-5', 2, 'reunion', 15, ['Écran TV', 'Wi-Fi', 'Table ronde']],
            ['UCAO-3-1', 3, 'cours', 40, ['Tableau', 'Projecteur', 'Wi-Fi', 'Climatisation']],
            ['UCAO-3-2', 3, 'cours', 40, ['Tableau', 'Projecteur', 'Wi-Fi', 'Climatisation']],
            ['UCAO-3-3', 3, 'informatique', 30, ['PC×30', 'Projecteur', 'Wi-Fi']],
            ['UCAO-4-1', 4, 'cours', 45, ['Tableau', 'Projecteur', 'Wi-Fi', 'Micro']],
            ['UCAO-4-2', 4, 'cours', 45, ['Tableau', 'Projecteur', 'Wi-Fi', 'Micro']],
            ['UCAO-4-3', 4, 'cours', 35, ['Tableau', 'Projecteur', 'Wi-Fi']],
            ['UCAO-4-4', 4, 'informatique', 28, ['PC×28', 'Projecteur', 'Wi-Fi']],
            ['UCAO-4-5', 4, 'reunion', 12, ['Écran TV', 'Wi-Fi']],
            ['UCAO-5-1', 5, 'cours', 50, ['Tableau', 'Projecteur', 'Wi-Fi', 'Micro', 'Climatisation']],
            ['UCAO-5-2', 5, 'cours', 50, ['Tableau', 'Projecteur', 'Wi-Fi', 'Micro', 'Climatisation']],
            ['UCAO-5-3', 5, 'cours', 35, ['Tableau', 'Projecteur', 'Wi-Fi']],
            ['UCAO-5-4', 5, 'informatique', 30, ['PC×30', 'Projecteur', 'Wi-Fi']],
            ['UCAO-5-5', 5, 'labo', 20, ['PC×20', 'Matériel réseau', 'Wi-Fi']],
            ['UCAO-6-1', 6, 'amphi', 120, ['Micro', 'Projecteur', 'Climatisation', 'Gradins']],
            ['UCAO-6-2', 6, 'amphi', 100, ['Micro', 'Projecteur', 'Climatisation', 'Gradins']],
        ];

        foreach ($salles as [$nom, $etage, $type, $capacite, $equipements]) {
            Salle::updateOrCreate(['nom' => $nom], [
                'batiment'    => 'UCAO',
                'etage'       => $etage,
                'type'        => $type,
                'capacite'    => $capacite,
                'equipements' => $equipements,
                'actif'       => true,
            ]);
        }

        $modules = [
            // L1 — Semestre 1
            ['IG-L1S1-01', 'Architecture des Ordinateurs', 1, '30_70', 3, 2],
            ['IG-L1S1-02', 'Anglais Technique', 1, '100', 2, 1],
            ['IG-L1S1-03', 'Algorithme 1', 1, '30_70', 3, 3],
            ['IG-L1S1-04', "Système d'Exploitation", 1, '30_70', 3, 2],
            ['IG-L1S1-05', 'Programmation 1 (C#)', 1, '30_70', 4, 3],
            ['IG-L1S1-06', 'Mathématiques Générales', 1, '100', 3, 2],
            ['IG-L1S1-07', 'Mathématiques Financières', 1, '100', 2, 2],
            ['IG-L1S1-08', 'Comptabilité Générale', 1, '30_70', 3, 2],
            ['IG-L1S1-09', 'Économie', 1, '30_70', 2, 1],
            ['IG-L1S1-10', "Technique d'Expression en Langue Française", 1, '100', 2, 1],
            ['IG-L1S1-11', 'Atelier Maintenance', 1, '30_70', 2, 2],
            // L1 — Semestre 2
            ['IG-L1S2-01', 'Probabilités', 2, '100', 2, 2],
            ['IG-L1S2-02', 'Statistiques', 2, '100', 2, 2],
            ['IG-L1S2-03', 'Algorithme 2', 2, '30_70', 3, 3],
            ['IG-L1S2-04', 'Programmation 2 (C#)', 2, '30_70', 4, 3],
            ['IG-L1S2-05', "Économie d'Entreprise", 2, '30_70', 2, 1],
            ['IG-L1S2-06', 'Initiation au Management', 2, '30_70', 2, 1],
            ['IG-L1S2-07', 'Site Web Statiques (HTML/CSS/JS)', 2, '30_70', 3, 2],
            ['IG-L1S2-08', 'Framework Frontend (Bootstrap/jQuery)', 2, '30_70', 3, 2],
            ['IG-L1S2-09', 'SGBDR', 2, '30_70', 3, 3],
            ['IG-L1S2-10', 'MERISE', 2, '30_70', 2, 2],
        ];

        foreach ($modules as [$code, $nom, $semestre, $typeEval, $credits, $coefficient]) {
            Module::updateOrCreate(['code' => $code], [
                'nom'         => $nom,
                'filiere'     => 'Informatique de Gestion',
                'annee_etude' => 1,
                'semestre'    => $semestre,
                'type_eval'   => $typeEval,
                'credits'     => $credits,
                'coefficient' => $coefficient,
            ]);
        }

        // Les modules L1 sont suivis par les deux groupes de L1
        $modulesL1 = Module::where('annee_etude', 1)->get();
        $classesL1 = Classe::where('annee_etude', 1)->get();

        foreach ($classesL1 as $classe) {
            foreach ($modulesL1 as $module) {
                $module->classes()->syncWithoutDetaching([
                    $classe->id => ['annee_acad' => $annee],
                ]);
            }
        }
    }
}
