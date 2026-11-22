<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enseignant extends Model
{
    protected $table = 'enseignants';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Modules dont cet utilisateur est enseignant (table module_enseignants). */
    public static function modulesDe(User $user)
    {
        return Module::whereHas('enseignants', fn ($q) => $q->where('users.id', $user->id));
    }
}
