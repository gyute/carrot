import { Form, Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import PortalLink from '@/components/portal-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const SAVED_USERNAME_KEY = 'carrot.portal.username';

/** Read the login ID the browser remembered for this device, if any. */
function readSavedUsername(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    try {
        return window.localStorage.getItem(SAVED_USERNAME_KEY) ?? '';
    } catch {
        return '';
    }
}

const fieldClasses =
    'h-12 rounded-none border-0 bg-slate-100 px-4 text-base text-slate-800 shadow-none placeholder:text-slate-400 focus-visible:bg-white focus-visible:ring-2 focus-visible:ring-sky-500';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const [username, setUsername] = useState(readSavedUsername);
    const [saveUsername, setSaveUsername] = useState(
        () => readSavedUsername() !== '',
    );

    useEffect(() => {
        if (!saveUsername) {
            window.localStorage.removeItem(SAVED_USERNAME_KEY);

            return;
        }

        if (username !== '') {
            window.localStorage.setItem(SAVED_USERNAME_KEY, username);
        }
    }, [saveUsername, username]);

    return (
        <>
            <Head title="ログイン" />

            {status && (
                <div className="mb-6 border-l-4 border-sky-500 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-800">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-3 sm:grid-cols-[1fr_9rem]">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="username" className="sr-only">
                                    ログインID
                                </Label>
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    value={username}
                                    onChange={(event) =>
                                        setUsername(event.target.value)
                                    }
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    placeholder="ログインID"
                                    className={fieldClasses}
                                />

                                <Label htmlFor="password" className="sr-only">
                                    パスワード
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="パスワード"
                                    className={fieldClasses}
                                />
                            </div>

                            <Button
                                type="submit"
                                tabIndex={3}
                                disabled={processing}
                                data-test="login-button"
                                className="h-14 w-full rounded-none bg-sky-600 text-lg font-bold text-white shadow-none hover:bg-sky-700 sm:h-auto"
                            >
                                {processing && <Spinner />}
                                ログイン
                            </Button>
                        </div>

                        <InputError message={errors.username} />
                        <InputError message={errors.password} />

                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm sm:pr-[9.75rem]">
                            <div className="flex items-center gap-4">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="save-username"
                                        checked={saveUsername}
                                        onCheckedChange={(checked) =>
                                            setSaveUsername(checked === true)
                                        }
                                        tabIndex={4}
                                        className="rounded-none border-slate-400 data-[state=checked]:border-sky-600 data-[state=checked]:bg-sky-600"
                                    />
                                    <Label
                                        htmlFor="save-username"
                                        className="font-normal text-slate-600"
                                    >
                                        IDを保存
                                    </Label>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={5}
                                        className="rounded-none border-slate-400 data-[state=checked]:border-sky-600 data-[state=checked]:bg-sky-600"
                                    />
                                    <Label
                                        htmlFor="remember"
                                        className="font-normal text-slate-600"
                                    >
                                        ログイン状態を保持
                                    </Label>
                                </div>
                            </div>

                            {canResetPassword && (
                                <PortalLink href={request()} tabIndex={6}>
                                    パスワードをお忘れの方
                                </PortalLink>
                            )}
                        </div>
                    </>
                )}
            </Form>

            <div className="mt-8 border-t border-slate-200 pt-8">
                <PasskeyVerify
                    label="パスキーでログイン"
                    loadingLabel="認証中..."
                    separator="または"
                    className="h-11 rounded-none border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900"
                />

                <p className="text-center text-sm text-slate-500">
                    アカウントをお持ちでない方は{' '}
                    <PortalLink href={register()} tabIndex={7}>
                        新規登録
                    </PortalLink>
                </p>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Login',
    description: 'CARROT へようこそ。',
    wide: true,
};
