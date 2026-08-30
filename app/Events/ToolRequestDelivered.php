<?php

namespace App\Events;

use App\Models\ToolRequest;
use Illuminate\Foundation\Events\Dispatchable;

class ToolRequestDelivered
{
    use Dispatchable;

    public function __construct(public ToolRequest $toolRequest) {}
}
