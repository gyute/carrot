// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import PortalLink from '@/components/portal-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    portalFieldClasses,
    portalLabelClasses,
    portalSubmitClasses,
} from '@/lib/portal-form';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="パスワード再設定" />

            {status && (
                <div className="mb-6 border-l-4 border-sky-500 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-800">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className={portalLabelClasses}
                                >
                                    ご登録のメールアドレス
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="yamada@example.com"
                                    className={portalFieldClasses}
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className={portalSubmitClasses}
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    パスワード再設定メールを送る
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-slate-500">
                    <span>パスワードを思い出しましたか？</span>
                    <PortalLink href={login()}>ログイン</PortalLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'パスワード再設定',
    description: 'ご登録のメールアドレスに再設定リンクをお送りします。',
};
