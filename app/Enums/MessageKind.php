<?php

namespace App\Enums;

enum MessageKind: string
{
    case SubmissionRequested = 'submission_requested';
    case SubmissionApproved = 'submission_approved';
    case SubmissionRejected = 'submission_rejected';
    case RequestFiled = 'request_filed';
    case RequestTriaged = 'request_triaged';
    case RequestDelivered = 'request_delivered';
    case Direct = 'direct';
}
