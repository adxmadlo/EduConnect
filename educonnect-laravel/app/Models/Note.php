<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Note LMD : note_finale = examen si type 100, sinon 30 % devoir + 70 % examen.
 * Le calcul est centralisé ici (remplace les triggers MySQL de l'ancien schéma).
 */
class Note extends Model
{
    protected $table = 'notes';

    protected $guarded = [];

    protected $casts = [
        'note_devoir' => 'float',
        'note_examen' => 'float',
        'note_finale' => 'float',
        'publie'      => 'boolean',
        'publie_le'   => 'datetime',
        'saisie_le'   => 'datetime',
        'modifiee_le' => 'datetime',
    ];

    /** Calcule et affecte note_finale selon le type d'évaluation du module. */
    public function calculerNoteFinale(): void
    {
        $type = $this->module?->type_eval;

        if ($type === '100') {
            $this->note_finale = $this->note_examen;
            return;
        }

        if ($this->note_devoir === null || $this->note_examen === null) {
            $this->note_finale = null;
            return;
        }

        $this->note_finale = round($this->note_devoir * 0.30 + $this->note_examen * 0.70, 2);
    }

    public function mention(): string
    {
        $note = (float) $this->note_finale;

        return match (true) {
            $note >= 16 => 'TB',
            $note >= 14 => 'B',
            $note >= 12 => 'AB',
            $note >= 10 => 'P',
            default     => 'R',
        };
    }

    public function mentionLongue(): string
    {
        return [
            'TB' => 'Très bien', 'B' => 'Bien', 'AB' => 'Assez bien',
            'P'  => 'Passable', 'R' => 'Insuffisant',
        ][$this->mention()] ?? '—';
    }

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }
}
