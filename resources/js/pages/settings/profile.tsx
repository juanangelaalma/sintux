import { Form, Head, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
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
                <header>
                    <h2 className="mb-0.5 text-base font-medium">Profile</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Update your name and email address</p>
                </header>

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                <input
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                    defaultValue={auth.user.name}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError className="mt-2" message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                    defaultValue={auth.user.email}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError className="mt-2" message={errors.email} />
                            </div>

                            <div className="flex items-center gap-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    data-test="update-profile-button"
                                    className="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                                >
                                    Save
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-6">
                <header>
                    <h2 className="mb-0.5 text-base font-medium">Delete account</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Delete your account and all of its resources</p>
                </header>
                <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p className="font-medium">Warning</p>
                        <p className="text-sm">Please proceed with caution, this cannot be undone.</p>
                    </div>
                    <button
                        type="button"
                        data-test="delete-user-button"
                        className="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700"
                        onClick={() => setShowDeleteModal(true)}
                    >
                        Delete account
                    </button>
                </div>
            </div>

            {showDeleteModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Are you sure you want to delete your account?
                        </h3>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Once your account is deleted, all of its resources and data will also be permanently deleted.
                            Please enter your password to confirm you would like to permanently delete your account.
                        </p>
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
                                    <div>
                                        <label htmlFor="delete-password" className="sr-only">Password</label>
                                        <input
                                            id="delete-password"
                                            name="password"
                                            type="password"
                                            ref={passwordInput}
                                            placeholder="Password"
                                            autoComplete="current-password"
                                            className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            className="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                            onClick={() => {
                                                resetAndClearErrors();
                                                setShowDeleteModal(false);
                                            }}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            data-test="confirm-delete-user-button"
                                            className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                                        >
                                            Delete account
                                        </button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            )}
        </>
    );
}

Profile.layout = (page: any) => <SettingsLayout>{page}</SettingsLayout>;
