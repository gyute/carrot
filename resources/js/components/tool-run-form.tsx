import { useForm } from '@inertiajs/react';
import { Play } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ToolInput } from '@/types/tools';

type Props = {
    inputs: ToolInput[];
    action: string;
    label?: string;
};

/**
 * Renders the inputs a script tool declared and posts them to `action`.
 * Shared by the tool page (a normal run) and the approval page (a test run).
 */
export default function ToolRunForm({
    inputs,
    action,
    label = '実行する',
}: Props) {
    const form = useForm<{ inputs: Record<string, string> }>({
        inputs: Object.fromEntries(
            inputs.map((input) => [
                input.key,
                input.type === 'select' ? (input.options?.[0] ?? '') : '',
            ]),
        ),
    });
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(action, { preserveScroll: true });
            }}
            className="grid gap-4"
        >
            {inputs.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2">
                    {inputs.map((input) => (
                        <div key={input.key} className="grid gap-1.5">
                            <Label htmlFor={`input-${input.key}`}>
                                {input.label}
                                {input.required && (
                                    <span className="ml-1 text-rose-500">
                                        *
                                    </span>
                                )}
                            </Label>
                            {input.type === 'select' ? (
                                <select
                                    id={`input-${input.key}`}
                                    value={form.data.inputs[input.key]}
                                    onChange={(e) =>
                                        form.setData('inputs', {
                                            ...form.data.inputs,
                                            [input.key]: e.target.value,
                                        })
                                    }
                                    className="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm shadow-xs"
                                >
                                    {(input.options ?? []).map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <Input
                                    id={`input-${input.key}`}
                                    type={
                                        input.type === 'number'
                                            ? 'number'
                                            : 'text'
                                    }
                                    value={form.data.inputs[input.key]}
                                    onChange={(e) =>
                                        form.setData('inputs', {
                                            ...form.data.inputs,
                                            [input.key]: e.target.value,
                                        })
                                    }
                                    className="bg-white"
                                />
                            )}
                            <InputError message={errors[input.key]} />
                        </div>
                    ))}
                </div>
            )}

            <div className="flex items-center gap-3">
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="bg-sky-700 text-white hover:bg-sky-800"
                >
                    <Play className="size-4" />
                    {form.processing ? '開始しています…' : label}
                </Button>
                <span className="text-xs text-slate-500">
                    隔離環境で実行します。ネットワークなし・書き込み不可。
                </span>
            </div>
        </form>
    );
}
