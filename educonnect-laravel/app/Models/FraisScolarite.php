<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraisScolarite extends Model
{
    protected $table = 'frais_scolarite';

    protected $guarded = [];

    protected $casts = [
        'montant_total' => 'float',
        'date_echeance' => 'date',
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'frais_id');
    }

    /** Somme réellement encaissée (paiements confirmés uniquement). */
    public function montantPaye(): float
    {
        return (float) $this->paiements()->where('statut', 'confirme')->sum('montant');
    }

    public function soldeRestant(): float
    {
        return max(0, $this->montant_total - $this->montantPaye());
    }

    public function pourcentagePaye(): int
    {
        return $this->montant_total > 0
            ? (int) round($this->montantPaye() / $this->montant_total * 100)
            : 0;
    }
}
