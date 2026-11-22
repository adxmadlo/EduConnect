<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salle extends Model
{
    protected $table = 'salles';

    protected $guarded = [];

    protected $casts = [
        'equipements' => 'array',
        'actif'       => 'boolean',
    ];

    public function emploisDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class);
    }
}
