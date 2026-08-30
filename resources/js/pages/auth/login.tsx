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
import {
    portalCheckboxClasses,
    portalFieldClasses,
    portalOutlineClasses,
    portalSubmitClasses,
} from '@/lib/portal-form';
import { cn } from '@/lib/utils';
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
                <div className="mb-6 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-800">
                    {status}
                </div>
            )}

            <Form {...store.form()} resetOnSuccess={['password']}>
                {({ processing, errors }) => (
                    /*
                     * One grid holds the whole form so the rows line up on
                     * their own: the fields and the submit button share row 1,
                     * and the options row below is placed back in column 1, so
                     * its left and right edges match the fields above without
                     * a hand-measured padding.
                     */
                    <div className="grid gap-3 sm:grid-cols-[1fr_10rem]">
                        <div className="grid content-start gap-3">
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
                                className={portalFieldClasses}
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
                                className={portalFieldClasses}
                            />

                            <InputError message={errors.username} />
                            <InputError message={errors.password} />
                        </div>

                        <Button
                            type="submit"
                            tabIndex={3}
                            disabled={processing}
                            data-test="login-button"
                            className={cn(
                                portalSubmitClasses,
                                'h-14 text-lg sm:h-auto',
                            )}
                        >
                            {processing && <Spinner />}
                            ログイン
                        </Button>

                        <div className="mt-3 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 text-sm sm:col-start-1">
                            <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="save-username"
                                        checked={saveUsername}
                                        onCheckedChange={(checked) =>
                                            setSaveUsername(checked === true)
                                        }
                                        tabIndex={4}
                                        className={portalCheckboxClasses}
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
                                        className={portalCheckboxClasses}
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
                    </div>
                )}
            </Form>

            {/*
             * flex, not plain flow: the divider carries its own margin and
             * would otherwise collapse into this wrapper's.
             */}
            <div className="flex flex-col">
                <PasskeyVerify
                    label="パスキーでログイン"
                    loadingLabel="認証中..."
                    separator="または"
                    separatorPlacement="before"
                    className={portalOutlineClasses}
                />

                <p className="mt-6 text-center text-sm text-slate-500">
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
