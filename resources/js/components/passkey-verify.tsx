import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    /**
     * Which side of the button the divider falls on. The divider names what is
     * on the far side of it, so a screen that offers the passkey first wants it
     * after the button, and one that offers it last wants it before.
     */
    separatorPlacement?: 'before' | 'after';
    className?: string;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
    separatorPlacement = 'after',
    className,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/');
        },
    });

    if (!isSupported) {
        return null;
    }

    const divider = (
        <div className="my-6 flex items-center gap-4 text-xs font-medium text-slate-400">
            <span className="h-px flex-1 bg-slate-200" />
            {separator ?? 'Or continue with email'}
            <span className="h-px flex-1 bg-slate-200" />
        </div>
    );

    return (
        <>
            {separatorPlacement === 'before' && divider}

            <div className="grid gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className={cn('w-full', className)}
                    onClick={verify}
                    disabled={isLoading}
                >
                    {isLoading ? <Spinner /> : <KeyRound className="h-4 w-4" />}
                    {isLoading
                        ? (loadingLabel ?? 'Authenticating...')
                        : (label ?? 'Sign in with a passkey')}
                </Button>
                {error && (
                    <InputError message={error} className="text-center" />
                )}
            </div>

            {separatorPlacement === 'after' && divider}
        </>
    );
}
