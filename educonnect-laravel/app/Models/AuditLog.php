<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    protected $guarded = [];

    protected $casts = ['detail' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
