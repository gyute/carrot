import { Form, Head, Link } from '@inertiajs/react';
import { ChevronLeft, Play } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { index as tools } from '@/routes/tools';
import { jobs, store } from '@/routes/tools/exports';

type Definition = {
    key: string;
    label: string;
    description: string;
};

export default function ExportCreate({
    definitions,
}: {
    definitions: Definition[];
}) {
    const [selected, setSelected] = useState(definitions[0]?.key ?? '');

    return (
        <>
            <Head title="データエクスポート" />

            <Link
                href={tools()}
                className="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-sky-700"
            >
                <ChevronLeft className="size-4" />
                ツール一覧
            </Link>

            <div className="mt-4 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h1 className="text-xl font-bold text-slate-800">
                    データエクスポート
                </h1>
                <Link
                    href={jobs()}
                    className="ml-auto text-sm font-medium text-sky-700 underline decoration-sky-300 underline-offset-4"
                >
                    バッチ一覧へ
                </Link>
            </div>
            <p className="mt-1 text-sm text-slate-500">
                出力する内容を選んで実行すると、バックグラウンドで CSV
                を作成します。完了後はバッチ一覧からダウンロードできます。
            </p>

            <Form
                {...store.form()}
                className="mt-8 rounded-md border border-slate-200 bg-white p-6 shadow-sm"
            >
                {({ errors, processing }) => (
                    <>
                        <fieldset>
                            <legend className="text-sm font-bold text-slate-700">
                                出力する内容
                            </legend>

                            <div className="mt-4 grid gap-3">
                                {definitions.map((definition) => (
                                    <label
                                        key={definition.key}
                                        className={`flex cursor-pointer gap-3 rounded-md border p-4 transition ${
                                            selected === definition.key
                                                ? 'border-sky-500 bg-sky-50/60'
                                                : 'border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="definition"
                                            value={definition.key}
                                            checked={
                                                selected === definition.key
                                            }
                                            onChange={() =>
                                                setSelected(definition.key)
                                            }
                                            className="mt-1 size-4 accent-sky-600"
                                        />
                                        <span>
                                            <span className="block text-sm font-bold text-slate-800">
                                                {definition.label}
                                            </span>
                                            <span className="mt-1 block text-sm text-slate-500">
                                                {definition.description}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>

                            <InputError
                                message={errors.definition}
                                className="mt-2"
                            />
                        </fieldset>

                        <Button
                            type="submit"
                            disabled={processing || definitions.length === 0}
                            className="mt-6"
                            data-test="run-export-button"
                        >
                            <Play className="size-4" />
                            エクスポートを実行
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
