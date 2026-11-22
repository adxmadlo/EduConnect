<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploiDuTemps extends Model
{
    protected $table = 'emplois_du_temps';

    protected $guarded = [];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin'   => 'datetime',
    ];

    public const CRENEAUX = [
        'cours1'     => '08:00 – 11:00',
        'cours2'     => '11:15 – 13:00',
        'cours3'     => '14:00 – 16:45',
        'revision'   => 'Révisions',
        'examen'     => 'Examen',
        'rattrapage' => 'Rattrapage',
    ];

    public function classe()
    {
        return $this->belongsTo(Classe::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function salle()
    {
        return $this->belongsTo(Salle::class);
    }

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function libelleCreneau(): string
    {
        return self::CRENEAUX[$this->type_creneau] ?? '';
    }
}
