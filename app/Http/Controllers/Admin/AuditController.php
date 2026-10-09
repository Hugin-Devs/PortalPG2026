<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class AuditController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->latest()
            ->paginate(50);

        return view('admin.audit.index', compact('logs'));
    }
}
