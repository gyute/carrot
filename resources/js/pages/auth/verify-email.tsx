// Components
import { Form, Head } from '@inertiajs/react';
import PortalLink from '@/components/portal-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="メールアドレスの確認" />

            {status === 'verification-link-sent' && (
                <div className="mb-6 border-l-4 border-sky-500 bg-sky-50 px-4 py-3 text-sm font-medium text-sky-800">
                    ご登録のメールアドレスに新しい確認リンクをお送りしました。
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            className="h-12 w-full rounded-none bg-sky-600 text-base font-bold text-white shadow-none hover:bg-sky-700"
                        >
                            {processing && <Spinner />}
                            確認メールを再送する
                        </Button>

                        <PortalLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            ログアウト
                        </PortalLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'メールアドレスの確認',
    description:
        'お送りしたメールのリンクをクリックして、確認を完了してください。',
};
