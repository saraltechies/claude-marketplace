<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Add app-specific stats as 'Label' => value (e.g. 'Orders' => Order::count()).
        // Each entry renders as a card on the dashboard.
        return view('admin.dashboard', [
            'stats' => [
                'Admins' => Admin::count(),
                'Actions today' => ActivityLog::whereDate('created_at', today())->count(),
            ],
            'recentActivity' => ActivityLog::with('admin')->latest()->limit(8)->get(),
        ]);
    }
}
