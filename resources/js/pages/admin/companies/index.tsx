import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import CompaniesTable from '@/components/tables/companies-table';
import type { Company } from '@/components/tables/companies-table';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import Modal from '@/components/ui/modal';
import PageHeader from '@/components/ui/page-header';
import TextInput from '@/components/ui/text-input';
import AdminLayout from '@/layouts/admin/admin-layout';
import companiesRoute from '@/routes/admin/companies';

type Props = {
    companies: Company[];
};

export default function Index({ companies }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editCompany, setEditCompany] = useState<Company | null>(null);

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({
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
        if (
            confirm(
                'Are you sure you want to delete this company and its database? This action is IRREVERSIBLE.',
            )
        ) {
            destroy(companiesRoute.destroy.url(id));
        }
    };

    const handleAutoSchema = (val: string) => {
        setData((prev) => ({
            ...prev,
            id: val,
            schema_name: val
                ? `company_${val.toLowerCase().replace(/[^a-z0-9]/g, '_')}`
                : '',
        }));
    };

    return (
        <>
            <Head title="Companies Management" />

            <div className="space-y-6">
                <PageHeader
                    title="Tenant Companies"
                    description="Manage tenant companies, databases, and schemas."
                    actions={
                        <Button onClick={openCreate}>Create Company</Button>
                    }
                />

                <CompaniesTable
                    companies={companies}
                    onDelete={handleDelete}
                    onEdit={openEdit}
                />
            </div>

            {isCreateOpen && (
                <Modal title="Create Company">
                    <form onSubmit={submitCreate} className="mt-4 space-y-4">
                        <FormField label="Company Slug/ID" error={errors.id}>
                            <TextInput
                                type="text"
                                required
                                placeholder="e.g. acme-corp"
                                value={data.id}
                                onChange={(e) =>
                                    handleAutoSchema(e.target.value)
                                }
                            />
                        </FormField>

                        <FormField label="Company Name" error={errors.name}>
                            <TextInput
                                type="text"
                                required
                                placeholder="e.g. Acme Corporation"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Database Schema Name"
                            error={errors.schema_name}
                        >
                            <TextInput
                                type="text"
                                required
                                readOnly
                                value={data.schema_name}
                                className="bg-gray-50 dark:bg-gray-800 dark:text-gray-400"
                            />
                        </FormField>

                        <div className="flex items-center gap-2">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData('is_active', e.target.checked)
                                }
                                className="rounded border-gray-300"
                            />
                            <label
                                htmlFor="is_active"
                                className="text-sm text-gray-600 dark:text-gray-400"
                            >
                                Active Tenant
                            </label>
                        </div>

                        <hr className="border-gray-200 dark:border-gray-800" />
                        <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                            Admin Credentials
                        </h4>

                        <FormField label="Admin Name" error={errors.admin_name}>
                            <TextInput
                                type="text"
                                required
                                placeholder="e.g. John Doe"
                                value={data.admin_name}
                                onChange={(e) =>
                                    setData('admin_name', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Admin Email"
                            error={errors.admin_email}
                        >
                            <TextInput
                                type="email"
                                required
                                placeholder="e.g. admin@acme.com"
                                value={data.admin_email}
                                onChange={(e) =>
                                    setData('admin_email', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Admin Password"
                            error={errors.admin_password}
                        >
                            <TextInput
                                type="password"
                                required
                                placeholder="Minimum 8 characters"
                                value={data.admin_password}
                                onChange={(e) =>
                                    setData('admin_password', e.target.value)
                                }
                            />
                        </FormField>

                        <FormActions
                            onCancel={closeModals}
                            submitLabel="Create"
                            processing={processing}
                        />
                    </form>
                </Modal>
            )}

            {editCompany && (
                <Modal title="Edit Company">
                    <form onSubmit={submitEdit} className="mt-4 space-y-4">
                        <FormField label="Company Name" error={errors.name}>
                            <TextInput
                                type="text"
                                required
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                        </FormField>

                        <div className="flex items-center gap-2">
                            <input
                                id="edit_is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData('is_active', e.target.checked)
                                }
                                className="rounded border-gray-300"
                            />
                            <label
                                htmlFor="edit_is_active"
                                className="text-sm text-gray-600 dark:text-gray-400"
                            >
                                Active Tenant
                            </label>
                        </div>

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

Index.layout = (page: any) => <AdminLayout>{page}</AdminLayout>;
