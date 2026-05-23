<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $action = trim((string) $request->query('action', ''));
        $entityType = trim((string) $request->query('entity_type', ''));
        $status = trim((string) $request->query('status', ''));

        $logs = AuditLog::query()
            ->when($action !== '', fn ($q) => $q->where('action', 'like', "%{$action}%"))
            ->when($entityType !== '', fn ($q) => $q->where('entity_type', 'like', "%{$entityType}%"))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', compact('logs', 'action', 'entityType', 'status'));
    }
}

