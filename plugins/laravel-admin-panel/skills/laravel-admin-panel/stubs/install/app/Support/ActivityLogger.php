<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Record an admin action. Call this explicitly at the end of every
     * mutating admin action (create/update/toggle/delete/login).
     */
    public static function log(string $action, string $description): void
    {
        ActivityLog::create([
            'admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}
