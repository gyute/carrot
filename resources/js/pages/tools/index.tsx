import { Head } from '@inertiajs/react';
import { PackageOpen } from 'lucide-react';
import ToolsNav from '@/components/tools-nav';

export default function ToolsIndex() {
    return (
        <>
            <Head title="ツール" />

            <ToolsNav />

            <div className="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                <h1 className="text-xl font-bold text-slate-800">ツール</h1>
                <p className="text-sm text-slate-500">
                    社内で使われている業務ツールをここに集約します。
                </p>
            </div>

            <div className="mt-6 flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-white/60 px-6 py-16 text-center">
                <PackageOpen className="size-8 text-slate-300" />
                <p className="text-sm font-medium text-slate-600">
                    まだツールがありません。
                </p>
            </div>
        </>
    );
}
