import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import InputError from '@/components/input-error';
import CompanyLayout from '@/layouts/company/company-layout';
import branchesRoute from '@/routes/company/branches';

type Branch = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
    is_headquarters: boolean;
};

type Props = {
    branches: Branch[];
};

export default function Index({ branches }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editBranch, setEditBranch] = useState<Branch | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        code: '',
        address: '',
        phone: '',
        is_active: true,
    });

    const openCreate = () => {
        clearErrors();
        reset();
        setIsCreateOpen(true);
    };

    const openEdit = (branch: Branch) => {
        clearErrors();
        setEditBranch(branch);
        setData({
            name: branch.name,
            code: branch.code,
            address: branch.address ?? '',
            phone: branch.phone ?? '',
            is_active: branch.is_active,
        });
    };

    const closeModals = () => {
        setIsCreateOpen(false);
        setEditBranch(null);
        reset();
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(branchesRoute.store.url(), {
            onSuccess: () => closeModals(),
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editBranch) {
            put(branchesRoute.update.url(editBranch.id), {
                onSuccess: () => closeModals(),
            });
        }
    };

    const toggleActive = (branch: Branch) => {
        router.put(branchesRoute.update.url(branch.id), {
            name: branch.name,
            code: branch.code,
            address: branch.address ?? '',
            phone: branch.phone ?? '',
            is_active: !branch.is_active,
        });
    };

    return (
        <>
            <Head title="Company Branches" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">Company Branches</h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage branches across your company.</p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
                    >
                        Add Branch
                    </button>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Name</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Code</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Address</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Phone</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Status</th>
                                <th className="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            {branches.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No branches yet. Click "Add Branch" to create one.
                                    </td>
                                </tr>
                            ) : (
                                branches.map(branch => (
                                    <tr key={branch.id}>
                                        <td className="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-white">
                                            <div className="flex items-center gap-2">
                                                {branch.name}
                                                {branch.is_headquarters && (
                                                    <span className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                                                        HQ
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">{branch.code}</td>
                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">{branch.address || '-'}</td>
                                        <td className="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">{branch.phone || '-'}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    branch.is_active
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                                }`}
                                            >
                                                {branch.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right text-sm font-medium whitespace-nowrap space-x-3">
                                            <button onClick={() => openEdit(branch)} className="text-brand-500 hover:text-brand-600">
                                                Edit
                                            </button>
                                            <button onClick={() => toggleActive(branch)} className="text-red-500 hover:text-red-600">
                                                {branch.is_active ? 'Deactivate' : 'Activate'}
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {isCreateOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Add Branch</h3>
                        <form onSubmit={submitCreate} className="mt-4 space-y-4">
                            <BranchFields data={data} setData={setData} errors={errors} />
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={closeModals} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                                <button type="submit" disabled={processing} className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {editBranch && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Edit Branch</h3>
                        <form onSubmit={submitEdit} className="mt-4 space-y-4">
                            <BranchFields data={data} setData={setData} errors={errors} />
                            <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={e => setData('is_active', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                Active
                            </label>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={closeModals} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                                <button type="submit" disabled={processing} className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

type FieldProps = {
    data: { name: string; code: string; address: string; phone: string };
    setData: (key: keyof FieldProps['data'], value: string) => void;
    errors: Partial<Record<'name' | 'code' | 'address' | 'phone' | 'is_active', string>>;
};

function BranchFields({ data, setData, errors }: FieldProps) {
    const inputClass =
        'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white';

    return (
        <>
            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                <input type="text" required value={data.name} onChange={e => setData('name', e.target.value)} className={inputClass} />
                <InputError message={errors.name} />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label>
                <input type="text" required value={data.code} onChange={e => setData('code', e.target.value)} className={inputClass} placeholder="e.g. JKT" />
                <InputError message={errors.code} />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                <input type="text" value={data.address} onChange={e => setData('address', e.target.value)} className={inputClass} />
                <InputError message={errors.address} />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                <input type="text" value={data.phone} onChange={e => setData('phone', e.target.value)} className={inputClass} />
                <InputError message={errors.phone} />
            </div>
        </>
    );
}

Index.layout = (page: any) => <CompanyLayout>{page}</CompanyLayout>;
