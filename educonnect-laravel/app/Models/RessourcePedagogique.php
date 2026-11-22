<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RessourcePedagogique extends Model
{
    protected $table = 'ressources_pedagogiques';

    protected $guarded = [];

    public const TYPES = [
        'cours'   => 'Support de cours',
        'td'      => 'Travaux dirigés',
        'tp'      => 'Travaux pratiques',
        'examen'  => 'Sujet d’examen',
        'lien'    => 'Lien externe',
        'autre'   => 'Autre',
    ];

    protected $casts = [
        'visible'         => 'boolean',
        'telechargements' => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function deposant()
    {
        return $this->belongsTo(User::class, 'deposant_id');
    }

    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function estUnLien(): bool
    {
        return $this->fichier_path === null && $this->lien_url !== null;
    }
}
