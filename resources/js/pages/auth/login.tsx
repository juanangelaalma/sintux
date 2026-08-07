import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import Button from '@/components/ui/button';
import FormField from '@/components/ui/form-field';
import TextInput from '@/components/ui/text-input';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<
        Required<LoginForm>
    >({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Log in" />
            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
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
                            autoComplete="email"
                            placeholder="email@example.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        htmlFor="password"
                        error={errors.password}
                        label="Password"
                        labelAction={
                            canResetPassword ? (
                                <Link
                                    href={request()}
                                    className="text-sm text-brand-500 hover:text-brand-600"
                                >
                                    Forgot your password?
                                </Link>
                            ) : undefined
                        }
                    >
                        <TextInput
                            id="password"
                            type="password"
                            required
                            autoComplete="current-password"
                            placeholder="Password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="flex items-center gap-2">
                        <input
                            id="remember"
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                            className="rounded border-gray-300"
                        />
                        <label
                            htmlFor="remember"
                            className="text-sm text-gray-600 dark:text-gray-400"
                        >
                            Remember me
                        </label>
                    </div>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="login-button"
                        fullWidth
                    >
                        Log in
                    </Button>
                </div>
            </form>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
