<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve or reject. A rejection has to say why; an approval may.
 */
class ReviewSubmissionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rejecting = $this->routeIs('admin.approvals.reject');

        return [
            'comment' => [$rejecting ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['comment' => 'コメント'];
    }
}
