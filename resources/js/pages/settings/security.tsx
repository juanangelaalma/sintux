import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Button from '@/components/ui/button';
import FormField from '@/components/ui/form-field';
import SectionHeader from '@/components/ui/section-header';
import TextInput from '@/components/ui/text-input';
import SettingsLayout from '@/layouts/settings/layout';

type Props = {
    passwordRules: string;
};

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            <div className="space-y-6">
                <SectionHeader
                    title="Update password"
                    description="Ensure your account is using a long, random password to stay secure"
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField
                                className="grid gap-2"
                                label="Current password"
                                htmlFor="current_password"
                                error={errors.current_password}
                            >
                                <TextInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    placeholder="Current password"
                                />
                            </FormField>

                            <FormField
                                className="grid gap-2"
                                label="New password"
                                htmlFor="password"
                                error={errors.password}
                            >
                                <TextInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder="New password"
                                    passwordrules={props.passwordRules}
                                />
                            </FormField>

                            <FormField
                                className="grid gap-2"
                                label="Confirm password"
                                htmlFor="password_confirmation"
                                error={errors.password_confirmation}
                            >
                                <TextInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                    passwordrules={props.passwordRules}
                                />
                            </FormField>

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Security.layout = (page: any) => <SettingsLayout>{page}</SettingsLayout>;
