<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $guarded = [];

    protected $casts = [
        'lu'        => 'boolean',
        'envoye_le' => 'datetime',
        'lu_le'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
