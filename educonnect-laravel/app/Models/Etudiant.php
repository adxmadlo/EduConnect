<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    protected $table = 'etudiants';

    protected $guarded = [];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function frais()
    {
        return $this->hasMany(FraisScolarite::class);
    }

    /** Frais de l'année académique courante (ou du dernier enregistré). */
    public function fraisCourants(?string $annee = null)
    {
        $annee ??= config('educonnect.annee_acad');

        return $this->frais()->where('annee_acad', $annee)->first()
            ?? $this->frais()->latest('annee_acad')->first();
    }

    /** Moyenne sur 20 des notes publiées (null si aucune note). */
    public function moyennePubliee(?string $annee = null): ?float
    {
        $annee ??= config('educonnect.annee_acad');

        $notes = $this->notes()
            ->where('publie', true)
            ->where('annee_acad', $annee)
            ->whereNotNull('note_finale')
            ->pluck('note_finale');

        return $notes->isEmpty() ? null : round($notes->avg(), 2);
    }
}
