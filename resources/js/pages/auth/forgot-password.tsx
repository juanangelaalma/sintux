import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import Button from '@/components/ui/button';
import FormField from '@/components/ui/form-field';
import TextInput from '@/components/ui/text-input';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(email.url());
    };

    return (
        <>
            <Head title="Forgot password" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <FormField
                        label="Email address"
                        htmlFor="email"
                        error={errors.email}
                    >
                        <TextInput
                            id="email"
                            type="email"
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder="email@example.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>

                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="email-password-reset-link-button"
                        fullWidth
                    >
                        Email password reset link
                    </Button>
                </div>
            </form>

            <div className="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                <span>Or, return to </span>
                <Link
                    href={login()}
                    className="text-brand-500 hover:text-brand-600"
                >
                    log in
                </Link>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot password',
    description: 'Enter your email to receive a password reset link',
};
