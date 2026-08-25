import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PortalLink from '@/components/portal-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

const fieldClasses =
    'h-12 rounded-none border-0 bg-slate-100 px-4 text-base text-slate-800 shadow-none placeholder:text-slate-400 focus-visible:bg-white focus-visible:ring-2 focus-visible:ring-sky-500';

const labelClasses = 'text-xs font-bold tracking-wide text-slate-500';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="新規登録" />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="name" className={labelClasses}>
                                    氏名
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    name="name"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder="アリスキャロット"
                                    className={fieldClasses}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid content-start gap-2">
                                <Label
                                    htmlFor="username"
                                    className={labelClasses}
                                >
                                    ログインID
                                </Label>
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    required
                                    tabIndex={2}
                                    autoComplete="username"
                                    placeholder="carrot"
                                    className={fieldClasses}
                                />
                                <p className="text-xs text-slate-400">
                                    半角英小文字ではじまる4〜20文字（数字・-・_
                                    が使えます）
                                </p>
                                <InputError message={errors.username} />
                            </div>

                            <div className="grid content-start gap-2 sm:col-span-2">
                                <Label htmlFor="email" className={labelClasses}>
                                    メールアドレス
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    tabIndex={3}
                                    autoComplete="email"
                                    placeholder="carrot@example.com"
                                    className={fieldClasses}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid content-start gap-2">
                                <Label
                                    htmlFor="password"
                                    className={labelClasses}
                                >
                                    パスワード
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    placeholder="パスワード"
                                    passwordrules={passwordRules}
                                    className={fieldClasses}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid content-start gap-2">
                                <Label
                                    htmlFor="password_confirmation"
                                    className={labelClasses}
                                >
                                    パスワード（確認）
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    placeholder="パスワード（確認）"
                                    passwordrules={passwordRules}
                                    className={fieldClasses}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            tabIndex={6}
                            data-test="register-user-button"
                            className="h-14 w-full rounded-none bg-sky-600 text-lg font-bold text-white shadow-none hover:bg-sky-700"
                        >
                            {processing && <Spinner />}
                            登録する
                        </Button>
                    </>
                )}
            </Form>

            <p className="mt-8 border-t border-slate-200 pt-6 text-center text-sm text-slate-500">
                すでにアカウントをお持ちの方は{' '}
                <PortalLink href={login()} tabIndex={7}>
                    ログイン
                </PortalLink>
            </p>
        </>
    );
}

Register.layout = {
    title: 'Sign up',
    description: 'CARROT で使用するアカウントを作成します。',
    wide: true,
};
