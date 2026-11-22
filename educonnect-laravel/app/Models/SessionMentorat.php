<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionMentorat extends Model
{
    protected $table = 'sessions_mentorat';

    protected $guarded = [];

    protected $casts = [
        'date_session' => 'date',
    ];

    public function mentor()
    {
        return $this->belongsTo(Mentor::class);
    }

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function salle()
    {
        return $this->belongsTo(Salle::class);
    }
}
