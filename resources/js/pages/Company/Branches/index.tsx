import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import Modal from '@/components/ui/modal';
import PageHeader from '@/components/ui/page-header';
import TextInput from '@/components/ui/text-input';
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

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
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

    const columns: DataTableColumn<Branch>[] = [
        {
            key: 'name',
            header: 'Name',
            cellClassName: 'font-medium text-gray-900 dark:text-white',
            render: (branch) => (
                <div className="flex items-center gap-2">
                    {branch.name}
                    {branch.is_headquarters && (
                        <span className="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                            HQ
                        </span>
                    )}
                </div>
            ),
        },
        { key: 'code', header: 'Code', render: (branch) => branch.code },
        {
            key: 'address',
            header: 'Address',
            render: (branch) => branch.address || '-',
        },
        {
            key: 'phone',
            header: 'Phone',
            render: (branch) => branch.phone || '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (branch) => (
                <span
                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${branch.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'}`}
                >
                    {branch.is_active ? 'Active' : 'Inactive'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            cellClassName: 'font-medium',
            render: (branch) => (
                <div className="space-x-3">
                    <button
                        onClick={() => openEdit(branch)}
                        className="text-brand-500 hover:text-brand-600"
                    >
                        Edit
                    </button>
                    <button
                        onClick={() => toggleActive(branch)}
                        className="text-red-500 hover:text-red-600"
                    >
                        {branch.is_active ? 'Deactivate' : 'Activate'}
                    </button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Company Branches" />

            <div className="space-y-6">
                <PageHeader
                    title="Company Branches"
                    description="Manage branches across your company."
                    actions={<Button onClick={openCreate}>Add Branch</Button>}
                />

                <DataTable
                    columns={columns}
                    rows={branches}
                    getRowKey={(branch) => branch.id}
                    emptyMessage={
                        'No branches yet. Click "Add Branch" to create one.'
                    }
                />
            </div>

            {isCreateOpen && (
                <Modal title="Add Branch" maxWidth="lg">
                    <form onSubmit={submitCreate} className="mt-4 space-y-4">
                        <BranchFields
                            data={data}
                            setData={setData}
                            errors={errors}
                        />
                        <FormActions
                            onCancel={closeModals}
                            submitLabel="Create"
                            processing={processing}
                        />
                    </form>
                </Modal>
            )}

            {editBranch && (
                <Modal title="Edit Branch" maxWidth="lg">
                    <form onSubmit={submitEdit} className="mt-4 space-y-4">
                        <BranchFields
                            data={data}
                            setData={setData}
                            errors={errors}
                        />
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData('is_active', e.target.checked)
                                }
                                className="rounded border-gray-300"
                            />
                            Active
                        </label>
                        <FormActions
                            onCancel={closeModals}
                            processing={processing}
                        />
                    </form>
                </Modal>
            )}
        </>
    );
}

type FieldProps = {
    data: { name: string; code: string; address: string; phone: string };
    setData: (key: keyof FieldProps['data'], value: string) => void;
    errors: Partial<
        Record<'name' | 'code' | 'address' | 'phone' | 'is_active', string>
    >;
};

function BranchFields({ data, setData, errors }: FieldProps) {
    return (
        <>
            <FormField label="Name" error={errors.name}>
                <TextInput
                    type="text"
                    required
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
            </FormField>

            <FormField label="Code" error={errors.code}>
                <TextInput
                    type="text"
                    required
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    placeholder="e.g. JKT"
                />
            </FormField>

            <FormField label="Address" error={errors.address}>
                <TextInput
                    type="text"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                />
            </FormField>

            <FormField label="Phone" error={errors.phone}>
                <TextInput
                    type="text"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                />
            </FormField>
        </>
    );
}

Index.layout = (page: any) => <CompanyLayout>{page}</CompanyLayout>;
