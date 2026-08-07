import { Form, Head, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import Modal from '@/components/ui/modal';
import SectionHeader from '@/components/ui/section-header';
import TextInput from '@/components/ui/text-input';
import SettingsLayout from '@/layouts/settings/layout';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;
    const passwordInput = useRef<HTMLInputElement>(null);
    const [showDeleteModal, setShowDeleteModal] = useState(false);

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <SectionHeader
                    title="Profile"
                    description="Update your name and email address"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                className="grid gap-2"
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <TextInput
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                    defaultValue={auth.user.name}
                                />
                            </FormField>

                            <FormField
                                className="grid gap-2"
                                label="Email address"
                                htmlFor="email"
                                error={errors.email}
                            >
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                    defaultValue={auth.user.email}
                                />
                            </FormField>

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-6">
                <SectionHeader
                    title="Delete account"
                    description="Delete your account and all of its resources"
                />
                <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p className="font-medium">Warning</p>
                        <p className="text-sm">
                            Please proceed with caution, this cannot be undone.
                        </p>
                    </div>
                    <Button
                        type="button"
                        data-test="delete-user-button"
                        variant="danger"
                        onClick={() => setShowDeleteModal(true)}
                    >
                        Delete account
                    </Button>
                </div>
            </div>

            {showDeleteModal && (
                <Modal
                    title="Are you sure you want to delete your account?"
                    description={
                        <>
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted. Please
                            enter your password to confirm you would like to
                            permanently delete your account.
                        </>
                    }
                >
                    <Form
                        {...ProfileController.destroy.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                        className="mt-4 space-y-4"
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <>
                                <FormField
                                    label="Password"
                                    labelClassName="sr-only"
                                    htmlFor="delete-password"
                                    error={errors.password}
                                >
                                    <TextInput
                                        id="delete-password"
                                        name="password"
                                        type="password"
                                        ref={passwordInput}
                                        placeholder="Password"
                                        autoComplete="current-password"
                                        className="mt-0"
                                    />
                                </FormField>
                                <FormActions
                                    danger
                                    submitLabel="Delete account"
                                    processing={processing}
                                    submitDataTest="confirm-delete-user-button"
                                    onCancel={() => {
                                        resetAndClearErrors();
                                        setShowDeleteModal(false);
                                    }}
                                />
                            </>
                        )}
                    </Form>
                </Modal>
            )}
        </>
    );
}

Profile.layout = (page: any) => <SettingsLayout>{page}</SettingsLayout>;
