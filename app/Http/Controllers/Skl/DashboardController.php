<?php

namespace App\Http\Controllers\Skl;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('skl/dashboard-page');
    }
}
