<?php

namespace App\Actions\Tools;

use App\Enums\ToolRunStatus;
use App\Jobs\RunToolJob;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Validates the inputs against the tool's own input schema, records the run
 * and queues it. The same path serves a normal run on a tool and an admin's
 * test run on a submission.
 */
class StartToolRun
{
    /**
     * @param  array<string, mixed>  $inputs
     */
    public function forTool(Tool $tool, User $user, array $inputs): ToolRun
    {
        return $this->start($user, $tool, null, $tool->config, (string) $tool->source, $inputs);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function forSubmission(ToolSubmission $submission, User $user, array $inputs): ToolRun
    {
        return $this->start($user, null, $submission, $submission->config(), (string) $submission->source(), $inputs);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $inputs
     */
    private function start(User $user, ?Tool $tool, ?ToolSubmission $submission, array $config, string $source, array $inputs): ToolRun
    {
        $validated = $this->validateInputs($config, $inputs);

        $run = ToolRun::query()->create([
            'tool_id' => $tool?->id,
            'tool_submission_id' => $submission?->id,
            'user_id' => $user->id,
            'runtime' => (string) ($config['runtime'] ?? 'php'),
            'source_hash' => hash('sha256', $source),
            'status' => ToolRunStatus::Queued,
            'inputs' => $validated,
        ]);

        RunToolJob::dispatch($run);

        return $run;
    }

    /**
     * Builds validation rules from the tool's declared inputs. Unknown keys
     * are dropped, so a script only ever sees what it asked for.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateInputs(array $config, array $inputs): array
    {
        $rules = [];
        $attributes = [];

        foreach ((array) ($config['inputs'] ?? []) as $input) {
            if (! is_array($input) || ! is_string($input['key'] ?? null)) {
                continue;
            }

            $key = $input['key'];
            $rule = [($input['required'] ?? false) ? 'required' : 'nullable'];

            $rule[] = match ($input['type'] ?? 'text') {
                'number' => 'numeric',
                'select' => 'in:'.implode(',', array_map(
                    fn (string $option): string => str_replace(',', '\,', $option),
                    array_filter((array) ($input['options'] ?? []), 'is_string'),
                )),
                default => 'string',
            };
            $rule[] = 'max:1000';

            $rules[$key] = $rule;
            $attributes[$key] = (string) ($input['label'] ?? $key);
        }

        /** @var Validator $validator */
        $validator = ValidatorFactory::make($inputs, $rules, [], $attributes);

        return $validator->validate();
    }
}
