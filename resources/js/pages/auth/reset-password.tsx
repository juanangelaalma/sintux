import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import Button from '@/components/ui/button';
import FormField from '@/components/ui/form-field';
import TextInput from '@/components/ui/text-input';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(update.url(), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Reset password" />

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <FormField
                        label="Email"
                        htmlFor="email"
                        error={errors.email}
                    >
                        <TextInput
                            id="email"
                            type="email"
                            autoComplete="email"
                            value={data.email}
                            readOnly
                        />
                    </FormField>

                    <FormField
                        label="Password"
                        htmlFor="password"
                        error={errors.password}
                    >
                        <TextInput
                            id="password"
                            type="password"
                            required
                            autoComplete="new-password"
                            autoFocus
                            placeholder="Password"
                            passwordrules={passwordRules}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label="Confirm password"
                        htmlFor="password_confirmation"
                        error={errors.password_confirmation}
                    >
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            required
                            autoComplete="new-password"
                            placeholder="Confirm password"
                            passwordrules={passwordRules}
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />
                    </FormField>

                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="reset-password-button"
                        fullWidth
                    >
                        Reset password
                    </Button>
                </div>
            </form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Reset password',
    description: 'Please enter your new password below',
};
