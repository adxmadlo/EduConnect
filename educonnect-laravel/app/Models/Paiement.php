<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    protected $table = 'paiements';

    protected $guarded = [];

    protected $casts = [
        'montant'          => 'float',
        'wave_fee'         => 'float',
        'callback_payload' => 'array',
        'confirmed_at'     => 'datetime',
    ];

    public const LIBELLES_MOYENS = [
        'wave'         => 'Wave',
        'orange_money' => 'Orange Money',
        'carte'        => 'Carte bancaire',
        'virement'     => 'Virement',
        'especes'      => 'Espèces',
    ];

    public const LIBELLES_STATUTS = [
        'en_attente' => 'En attente',
        'confirme'   => 'Confirmé',
        'echec'      => 'Échec',
        'rembourse'  => 'Remboursé',
    ];

    public function frais()
    {
        return $this->belongsTo(FraisScolarite::class, 'frais_id');
    }

    public function payeur()
    {
        return $this->belongsTo(User::class, 'payeur_id');
    }

    public function libelleMoyen(): string
    {
        return self::LIBELLES_MOYENS[$this->moyen] ?? $this->moyen;
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES_STATUTS[$this->statut] ?? $this->statut;
    }
}
