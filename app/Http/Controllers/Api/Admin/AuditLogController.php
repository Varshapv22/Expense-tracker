<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        return AuditLog::with('admin:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(30);
    }
}
