<?php

namespace App\Events;

use App\Models\ToolSubmission;
use Illuminate\Foundation\Events\Dispatchable;

class ToolSubmissionSubmitted
{
    use Dispatchable;

    public function __construct(public ToolSubmission $submission) {}
}
