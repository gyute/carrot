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
import {
    portalFieldClasses,
    portalLabelClasses,
    portalOutlineClasses,
    portalSubmitClasses,
} from '@/lib/portal-form';
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
                className={portalOutlineClasses}
            />

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className={portalLabelClasses}
                            >
                                パスワード
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="パスワード"
                                className={portalFieldClasses}
                                autoComplete="current-password"
                                autoFocus
                            />

                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center">
                            <Button
                                className={portalSubmitClasses}
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
