import { Form, Head } from '@inertiajs/react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="パスワードの確認" />

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                label="パスキーで確認"
                loadingLabel="確認中..."
                separator="またはパスワードで確認"
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className="text-xs font-bold tracking-wide text-slate-500"
                            >
                                パスワード
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="パスワード"
                                className="h-12 rounded-none border-0 bg-slate-100 px-4 text-base text-slate-800 shadow-none placeholder:text-slate-400 focus-visible:bg-white focus-visible:ring-2 focus-visible:ring-sky-500"
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className="h-12 w-full rounded-none bg-sky-600 text-base font-bold text-white shadow-none hover:bg-sky-700"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                確認
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'パスワードの確認',
    description:
        'セキュリティ保護された領域です。続けるにはパスワードをもう一度入力してください。',
};
