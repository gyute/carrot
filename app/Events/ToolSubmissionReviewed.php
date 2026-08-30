<?php

namespace App\Events;

use App\Models\ToolSubmission;
use Illuminate\Foundation\Events\Dispatchable;

class ToolSubmissionReviewed
{
    use Dispatchable;

    public function __construct(public ToolSubmission $submission) {}
}
