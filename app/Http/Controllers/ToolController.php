<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ToolController extends Controller
{
    /**
     * Show the catalog of in-house tools.
     */
    public function index(): Response
    {
        return Inertia::render('tools/index');
    }
}
