<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $table = 'stages';

    protected $guarded = [];

    protected $casts = [
        'date_debut'    => 'date',
        'date_fin'      => 'date',
        'score_profil'  => 'float',
        'admin_valide'  => 'boolean',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }
}
