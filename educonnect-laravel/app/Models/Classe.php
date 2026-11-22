<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    protected $table = 'classes';

    protected $guarded = [];

    public function etudiants()
    {
        return $this->hasMany(Etudiant::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'module_classes')
            ->withPivot('annee_acad');
    }

    public function emploisDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class);
    }
}
