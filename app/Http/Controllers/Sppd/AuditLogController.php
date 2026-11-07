<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Models\Sppd\LogAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('sppd/audit-log/index', [
            'items' => LogAudit::query()->with('user')->latest()->paginate($this->perPage($request)),
        ]);
    }
}
