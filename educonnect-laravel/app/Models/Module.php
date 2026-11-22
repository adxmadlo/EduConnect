<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'modules';

    protected $guarded = [];

    /** 30 % devoir + 70 % examen, ou 100 % examen. */
    public function evalueSeulementParExamen(): bool
    {
        return $this->type_eval === '100';
    }

    public function enseignants()
    {
        return $this->belongsToMany(User::class, 'module_enseignants', 'module_id', 'enseignant_id')
            ->withPivot('est_principal');
    }

    public function classes()
    {
        return $this->belongsToMany(Classe::class, 'module_classes')
            ->withPivot('annee_acad');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
