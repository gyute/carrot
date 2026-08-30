<?php

namespace App\Events;

use App\Models\ToolRequest;
use Illuminate\Foundation\Events\Dispatchable;

class ToolRequestSubmitted
{
    use Dispatchable;

    public function __construct(public ToolRequest $toolRequest) {}
}
