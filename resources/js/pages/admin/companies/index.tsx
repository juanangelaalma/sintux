import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import InputError from '@/components/input-error';
import AdminLayout from '@/layouts/admin/admin-layout';
import companiesRoute from '@/routes/admin/companies';

type Company = {
    id: string;
    name: string;
    schema_name: string;
    is_active: boolean;
    created_at: string;
};

type Props = {
    companies: Company[];
};

export default function Index({ companies }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editCompany, setEditCompany] = useState<Company | null>(null);

    const { data, setData, post, put, delete: destroy, processing, errors, reset, clearErrors } = useForm({
        id: '',
        name: '',
        schema_name: '',
        is_active: true,
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    const openCreate = () => {
        clearErrors();
        reset();
        setIsCreateOpen(true);
    };

    const openEdit = (company: Company) => {
        clearErrors();
        setEditCompany(company);
        setData({
            id: company.id,
            name: company.name,
            schema_name: company.schema_name,
            is_active: company.is_active,
        });
    };

    const closeModals = () => {
        setIsCreateOpen(false);
        setEditCompany(null);
        reset();
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(companiesRoute.store.url(), {
            onSuccess: () => closeModals(),
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editCompany) {
            put(companiesRoute.update.url(editCompany.id), {
                onSuccess: () => closeModals(),
            });
        }
    };

    const handleDelete = (id: string) => {
        if (confirm('Are you sure you want to delete this company and its database? This action is IRREVERSIBLE.')) {
            destroy(companiesRoute.destroy.url(id));
        }
    };

    const handleAutoSchema = (val: string) => {
        setData(prev => ({
            ...prev,
            id: val,
            schema_name: val ? `company_${val.toLowerCase().replace(/[^a-z0-9]/g, '_')}` : ''
        }));
    };

    return (
        <>
            <Head title="Companies Management" />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">Tenant Companies</h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage tenant companies, databases, and schemas.</p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
                    >
                        Create Company
                    </button>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">ID</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Name</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Schema Name</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Created At</th>
                                <th className="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            {companies.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No companies found. Click "Create Company" to add one.
                                    </td>
                                </tr>
                            ) : (
                                companies.map((company) => (
                                    <tr key={company.id}>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{company.id}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{company.name}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><code>{company.schema_name}</code></td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${company.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'}`}>
                                                {company.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{new Date(company.created_at).toLocaleDateString()}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                            <button
                                                onClick={() => openEdit(company)}
                                                className="text-brand-500 hover:text-brand-600"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => handleDelete(company.id)}
                                                className="text-red-500 hover:text-red-600"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Create Modal */}
            {isCreateOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Create Company</h3>
                        <form onSubmit={submitCreate} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Company Slug/ID</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. acme-corp"
                                    value={data.id}
                                    onChange={(e) => handleAutoSchema(e.target.value)}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError message={errors.id} />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Company Name</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. Acme Corporation"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Database Schema Name</label>
                                <input
                                    type="text"
                                    required
                                    readOnly
                                    value={data.schema_name}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                                />
                                <InputError message={errors.schema_name} />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    id="is_active"
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                <label htmlFor="is_active" className="text-sm text-gray-600 dark:text-gray-400">Active Tenant</label>
                            </div>

                            <hr className="border-gray-200 dark:border-gray-800" />
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">Admin Credentials</h4>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Admin Name</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="e.g. John Doe"
                                    value={data.admin_name}
                                    onChange={(e) => setData('admin_name', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError message={errors.admin_name} />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Admin Email</label>
                                <input
                                    type="email"
                                    required
                                    placeholder="e.g. admin@acme.com"
                                    value={data.admin_email}
                                    onChange={(e) => setData('admin_email', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError message={errors.admin_email} />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Admin Password</label>
                                <input
                                    type="password"
                                    required
                                    placeholder="Minimum 8 characters"
                                    value={data.admin_password}
                                    onChange={(e) => setData('admin_password', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError message={errors.admin_password} />
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={closeModals}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                                >
                                    Create
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Edit Modal */}
            {editCompany && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Edit Company</h3>
                        <form onSubmit={submitEdit} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Company Name</label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    id="edit_is_active"
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                <label htmlFor="edit_is_active" className="text-sm text-gray-600 dark:text-gray-400">Active Tenant</label>
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={closeModals}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                                >
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

Index.layout = (page: any) => <AdminLayout>{page}</AdminLayout>;
