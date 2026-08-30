<?php

namespace App\Http\Requests\Admin;

use App\Enums\ToolRequestPriority;
use App\Enums\UserRole;
use App\Models\Tool;
use App\Models\ToolRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The development team's decision on a request. Declining and marking a
 * duplicate have to say what happened; accepting may.
 */
class ReviewToolRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $declining = $this->routeIs('admin.requests.decline');
        $duplicating = $this->routeIs('admin.requests.duplicate');
        $delivering = $this->routeIs('admin.requests.deliver');

        // A request cannot be a duplicate of itself.
        $current = $this->route('toolRequest');
        $original = Rule::exists(ToolRequest::class, 'ulid');

        if ($current instanceof ToolRequest) {
            $original->whereNot('ulid', $current->ulid);
        }

        return [
            'comment' => [$declining ? 'required' : 'nullable', 'string', 'max:2000'],
            'priority' => ['nullable', Rule::enum(ToolRequestPriority::class)],
            // The form sends a ULID, never the login ID: an identity
            // provider owns that once SSO lands. Kept to the development
            // team, matching User::scopeDevelopmentTeam.
            'assignee' => [
                'nullable',
                'string',
                Rule::exists(User::class, 'ulid')->where('role', UserRole::Admin->value),
            ],
            'duplicate_of' => [
                Rule::requiredIf($duplicating),
                Rule::excludeIf(! $duplicating),
                'string',
                $original,
            ],
            'tool' => [
                Rule::requiredIf($delivering),
                Rule::excludeIf(! $delivering),
                'string',
                Rule::exists(Tool::class, 'ulid')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'comment' => 'コメント',
            'priority' => '優先度',
            'assignee' => '担当者',
            'duplicate_of' => '重複元の依頼',
            'tool' => '公開したツール',
        ];
    }
}
