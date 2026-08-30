<?php

namespace App\Http\Requests\Tools;

use App\Enums\ToolKind;
use App\Models\Tool;
use App\Support\Departments;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a submission's payload. The rules depend on the kind: a link needs
 * a URL, an embed an external https URL, a script a runtime and source.
 *
 * Used for both new requests and edits of a draft. For an update request on an
 * existing tool only the behaviour fields (config, source) are taken; the
 * display fields are edited on the tool itself.
 */
class ToolSubmissionRequest extends FormRequest
{
    /** Largest script accepted, in bytes. */
    public const MAX_SOURCE_BYTES = 65536;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kind = ToolKind::tryFrom((string) $this->input('kind'));
        $script = $kind === ToolKind::Script;
        $needsUrl = in_array($kind, [ToolKind::Link, ToolKind::Embed], true);

        return [
            'kind' => ['required', Rule::enum(ToolKind::class)],
            'name' => ['required', 'string', 'max:60'],
            'summary' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['required', Rule::in(Tool::ICONS)],
            'accent' => ['required', Rule::in(Tool::ACCENTS)],
            'department' => Departments::rules(),
            'categories' => ['array', 'max:5'],
            'categories.*' => ['string', 'max:30', 'distinct'],
            'note' => ['nullable', 'string', 'max:2000'],

            'config' => ['required', 'array'],
            'config.url' => [
                Rule::requiredIf($needsUrl),
                Rule::excludeIf(! $needsUrl),
                'string',
                'max:2000',
                $kind === ToolKind::Embed ? $this->externalHttps(...) : $this->linkTarget(...),
            ],
            'config.runtime' => [Rule::requiredIf($script), Rule::excludeIf(! $script), Rule::in(['php', 'shell'])],
            'config.timeout_sec' => [Rule::requiredIf($script), Rule::excludeIf(! $script), 'integer', 'min:1', 'max:'.config('sandbox.timeout_max', 120)],
            'config.memory_mb' => [Rule::requiredIf($script), Rule::excludeIf(! $script), 'integer', 'min:32', 'max:'.config('sandbox.memory_max', 512)],
            'config.network' => [Rule::excludeIf(! $script), 'nullable', Rule::in(['none', 'internet'])],
            'config.inputs' => [Rule::excludeIf(! $script), 'array', 'max:10'],
            'config.inputs.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,31}$/', 'distinct'],
            'config.inputs.*.label' => ['required', 'string', 'max:40'],
            'config.inputs.*.type' => ['required', Rule::in(['text', 'number', 'select'])],
            'config.inputs.*.required' => ['boolean'],
            'config.inputs.*.options' => ['required_if:config.inputs.*.type,select', 'array', 'max:20'],
            'config.inputs.*.options.*' => ['string', 'max:60'],
            'source' => [Rule::requiredIf($script), Rule::excludeIf(! $script), 'string', 'max:'.self::MAX_SOURCE_BYTES],
        ];
    }

    /**
     * A link may point inside the portal (a path) or at an https site.
     */
    private function linkTarget(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('URL の形式が正しくありません。');

            return;
        }

        $internal = str_starts_with($value, '/') && ! str_starts_with($value, '//');

        if ($internal || $this->isHttps($value)) {
            return;
        }

        $fail('URL は https:// か、ポータル内のパス（/ で始まる）で指定してください。');
    }

    /**
     * Only external https origins may be framed: framing our own origin would
     * hand the embedded page our DOM.
     */
    private function externalHttps(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isHttps($value)) {
            $fail('埋め込み先は https:// の URL を指定してください。');

            return;
        }

        $own = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (parse_url($value, PHP_URL_HOST) === $own) {
            $fail('ポータル自身のページは埋め込めません。');
        }
    }

    private function isHttps(string $value): bool
    {
        return str_starts_with($value, 'https://')
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && is_string(parse_url($value, PHP_URL_HOST));
    }

    /**
     * The validated payload in the shape stored on the submission.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'kind' => $data['kind'],
            'name' => $data['name'],
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'],
            'accent' => $data['accent'],
            'department' => $data['department'] ?? null,
            'categories' => array_values($data['categories'] ?? []),
            'config' => $this->normalisedConfig($data['config']),
            'source' => $data['source'] ?? null,
        ];
    }

    /**
     * The behaviour fields only, for an update request on a published tool.
     *
     * @return array{config: array<string, mixed>, source: string|null}
     */
    public function behaviourPayload(): array
    {
        $data = $this->validated();

        return [
            'config' => $this->normalisedConfig($data['config']),
            'source' => $data['source'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalisedConfig(array $config): array
    {
        if (isset($config['inputs'])) {
            $config['inputs'] = array_values(array_map(fn (array $input): array => [
                'key' => $input['key'],
                'label' => $input['label'],
                'type' => $input['type'],
                'required' => (bool) ($input['required'] ?? false),
                'options' => $input['type'] === 'select' ? array_values($input['options'] ?? []) : null,
            ], $config['inputs']));
        }

        if (isset($config['timeout_sec'])) {
            $config['timeout_sec'] = (int) $config['timeout_sec'];
        }

        if (isset($config['memory_mb'])) {
            $config['memory_mb'] = (int) $config['memory_mb'];
            $config['network'] = ($config['network'] ?? 'none') === 'internet' ? 'internet' : 'none';
        }

        return $config;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => '種類',
            'name' => 'ツール名',
            'summary' => '概要',
            'description' => '説明',
            'icon' => 'アイコン',
            'accent' => 'カラー',
            'department' => '所属',
            'categories' => 'カテゴリ',
            'categories.*' => 'カテゴリ',
            'note' => '申請メモ',
            'config.url' => 'URL',
            'config.runtime' => 'ランタイム',
            'config.timeout_sec' => 'タイムアウト',
            'config.memory_mb' => 'メモリ上限',
            'config.network' => 'ネットワーク',
            'config.inputs' => '入力項目',
            'config.inputs.*.key' => '入力キー',
            'config.inputs.*.label' => '入力ラベル',
            'config.inputs.*.type' => '入力タイプ',
            'config.inputs.*.options' => '選択肢',
            'source' => 'ソースコード',
        ];
    }
}
