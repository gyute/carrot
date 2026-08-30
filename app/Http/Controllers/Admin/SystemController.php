<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SystemStatus;
use Inertia\Inertia;
use Inertia\Response;

class SystemController extends Controller
{
    public function index(SystemStatus $status): Response
    {
        return Inertia::render('admin/system/index', [
            'status' => $status->snapshot(),
        ]);
    }
}
