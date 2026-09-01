<?php

namespace App\Support\Github;

use App\Models\Tool;

/**
 * A tool as the files that stand for it in the repository.
 *
 * People are written as ULIDs, never names, and the department is left out
 * entirely. Git only ever adds: a name committed once cannot be taken back,
 * which would quietly undo retiring a person rather than deleting them. The
 * portal resolves the ULIDs when it shows the history.
 */
class ToolDocument
{
    public function __construct(private Tool $tool) {}

    public function directory(): string
    {
        return config('github.path').'/'.$this->tool->ulid;
    }

    /**
     * @return array<string, string> path => contents
     */
    public function files(): array
    {
        $files = [$this->directory().'/tool.json' => $this->json()];

        if ($this->tool->source !== null && $this->tool->source !== '') {
            $files[$this->directory().'/source.'.$this->extension()] = $this->tool->source;
        }

        return $files;
    }

    /**
     * What the commit says happened, and on whose behalf - by ULID, so the
     * message stays readable without naming anybody.
     */
    public function message(): string
    {
        $tool = $this->tool;
        $what = match (true) {
            $tool->trashed() => 'Delete',
            $tool->deprecated_at !== null => 'Deprecate',
            default => 'Publish',
        };

        $subject = "{$what} {$tool->slug}".($tool->version === null ? '' : " v{$tool->version}");

        $trailers = array_filter([
            'Requested-by' => $tool->requester?->ulid,
            'Endorsed-by' => $tool->endorser?->ulid,
            'Approved-by' => $tool->approver?->ulid,
        ]);

        if ($trailers === []) {
            return $subject."\n";
        }

        $body = implode("\n", array_map(
            fn (string $key, string $ulid): string => "{$key}: {$ulid}",
            array_keys($trailers),
            $trailers,
        ));

        return $subject."\n\n".$body."\n";
    }

    private function json(): string
    {
        $tool = $this->tool;

        return json_encode([
            'ulid' => $tool->ulid,
            'slug' => $tool->slug,
            'kind' => $tool->kind->value,
            'name' => $tool->name,
            'summary' => $tool->summary,
            'description' => $tool->description,
            'icon' => $tool->icon,
            'accent' => $tool->accent,
            'status' => $tool->status->value,
            'categories' => $tool->categories(),
            'config' => $tool->config,
            'sourceHash' => $tool->source_hash,
            'version' => $tool->version,
            'owner' => $tool->owner?->ulid,
            'requestedBy' => $tool->requester?->ulid,
            'endorsedBy' => $tool->endorser?->ulid,
            'approvedBy' => $tool->approver?->ulid,
            'publishedAt' => $tool->published_at?->toIso8601String(),
            'deprecatedAt' => $tool->deprecated_at?->toIso8601String(),
            'deletedAt' => $tool->deleted_at?->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private function extension(): string
    {
        return ($this->tool->config['runtime'] ?? 'php') === 'shell' ? 'sh' : 'php';
    }
}
