<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $table = 'user_preferences';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'notif_email' => 'boolean',
        'notif_sms'   => 'boolean',
        'notif_inapp' => 'boolean',
        'updated_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
