<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['admin_id', 'action', 'description', 'ip_address'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
