<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    protected $table = 'bulletins';

    protected $guarded = [];

    protected $casts = [
        'moyenne_sem' => 'float',
        'genere_le'   => 'datetime',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }
}
