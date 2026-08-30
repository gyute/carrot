<?php

namespace App\Enums;

enum MessageKind: string
{
    case SubmissionRequested = 'submission_requested';
    case SubmissionApproved = 'submission_approved';
    case SubmissionRejected = 'submission_rejected';
    case Direct = 'direct';
}
