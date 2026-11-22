<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mentor extends Model
{
    protected $table = 'mentors';

    protected $guarded = [];

    protected $casts = [
        'note_moyenne'      => 'float',
        'taux_satisfaction' => 'float',
        'score_quiz'        => 'float',
        'etape_temoignage'  => 'boolean',
        'etape_notes'       => 'boolean',
        'etape_entretien'   => 'boolean',
        'etape_quiz'        => 'boolean',
        'actif'             => 'boolean',
        'quiz_passe_le'     => 'datetime',
        'valide_le'         => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'mentor_modules')
            ->withPivot('note_obtenue', 'temoignage_txt');
    }

    public function sessions()
    {
        return $this->hasMany(SessionMentorat::class);
    }
}
