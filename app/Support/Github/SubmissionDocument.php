<?php

namespace App\Support\Github;

use App\Enums\SubmissionAction;
use App\Enums\ToolKind;
use App\Enums\ToolStatus;
use App\Models\Tool;
use App\Models\ToolSubmission;
use Illuminate\Support\Str;

/**
 * A submission as the files it is asking the repository to end up with, so a
 * reviewer sees the diff before deciding rather than after.
 *
 * The tool it describes may not exist yet, so this projects rather than reads:
 * a create submission from its payload alone, a change from the tool it
 * targets with the payload laid over it.
 */
class SubmissionDocument
{
    public function __construct(private ToolSubmission $submission) {}

    public function directory(): string
    {
        return config('github.path').'/'.$this->toolUlid();
    }

    /**
     * @return array<string, string> path => contents
     */
    public function files(): array
    {
        $files = [$this->directory().'/tool.json' => $this->json()];
        $source = $this->source();

        if ($source !== null && $source !== '') {
            $files[$this->directory().'/source.'.$this->extension()] = $source;
        }

        return $files;
    }

    public function branch(): string
    {
        return 'submission/'.$this->submission->ulid;
    }

    /**
     * A tool's name, not its slug or its ULID: this is the line a reviewer
     * reads in a list of pull requests. Tool names are not personal data -
     * the rule about what stays out of the repository is about people.
     */
    public function title(): string
    {
        $what = ucfirst($this->submission->action->value);

        return "{$what} {$this->name()}";
    }

    private function name(): string
    {
        $name = $this->submission->payload['name'] ?? $this->tool()?->name;

        return is_string($name) && $name !== '' ? $name : $this->slug();
    }

    /**
     * Who asked and what for, by ULID. A reviewer reads the names in the
     * portal; the repository keeps no personal data.
     */
    public function body(): string
    {
        $submission = $this->submission;

        $lines = [
            "Requested-by: {$submission->user->ulid}",
            "Submission: {$submission->ulid}",
        ];

        if ($submission->toolRequest !== null) {
            $lines[] = "Answers-request: {$submission->toolRequest->ulid}";
        }

        if ($submission->note !== null && $submission->note !== '') {
            $lines[] = '';
            $lines[] = $submission->note;
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * The tool this would change, or null when it would create one.
     */
    private function tool(): ?Tool
    {
        return $this->submission->tool;
    }

    /**
     * Where the tool lives in the repository. A create has no tool yet, but
     * ApproveSubmission gives the one it publishes this submission's ULID, so
     * the path proposed here is the path it ends up at.
     */
    private function toolUlid(): string
    {
        $tool = $this->tool();

        return $tool === null ? $this->submission->ulid : $tool->ulid;
    }

    private function slug(): string
    {
        $tool = $this->tool();

        if ($tool !== null) {
            return $tool->slug;
        }

        return Str::slug((string) ($this->submission->payload['name'] ?? '')) ?: 'tool';
    }

    private function source(): ?string
    {
        return $this->submission->source() ?? $this->tool()?->source;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $config = $this->submission->config();

        if ($config !== []) {
            return $config;
        }

        $tool = $this->tool();

        return $tool === null ? [] : $tool->config;
    }

    private function json(): string
    {
        $tool = $this->tool();
        $payload = $this->submission->payload;
        $deprecating = $this->submission->action === SubmissionAction::Deprecate;
        $source = $this->source();

        return json_encode([
            'ulid' => $tool?->ulid,
            'slug' => $this->slug(),
            'kind' => $payload['kind'] ?? $tool?->kind->value,
            'name' => $payload['name'] ?? $tool?->name,
            'summary' => $payload['summary'] ?? $tool?->summary,
            'description' => $payload['description'] ?? $tool?->description,
            'icon' => $payload['icon'] ?? $tool?->icon,
            'accent' => $payload['accent'] ?? $tool?->accent,
            'status' => $deprecating ? ToolStatus::Deprecated->value : ToolStatus::Running->value,
            'categories' => $payload['categories'] ?? $tool?->categories() ?? [],
            'config' => $this->config(),
            'sourceHash' => $source === null ? null : hash('sha256', $source),
            'version' => $tool?->version,
            'owner' => $tool?->owner->ulid ?? $this->submission->user->ulid,
            'requestedBy' => $this->submission->user->ulid,
            'endorsedBy' => $this->submission->endorser?->ulid,
            'approvedBy' => $this->submission->reviewer?->ulid,
            'publishedAt' => $tool?->published_at?->toIso8601String(),
            'deprecatedAt' => $deprecating ? null : $tool?->deprecated_at?->toIso8601String(),
            'deletedAt' => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private function extension(): string
    {
        $runtime = $this->config()['runtime'] ?? null;

        if ($runtime === null) {
            $kind = $this->submission->payload['kind'] ?? $this->tool()?->kind->value;
            $runtime = $kind === ToolKind::Script->value ? 'php' : 'php';
        }

        return $runtime === 'shell' ? 'sh' : 'php';
    }
}
