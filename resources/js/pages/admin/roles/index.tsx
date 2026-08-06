import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import FormActions from '@/components/ui/form-actions';
import Modal from '@/components/ui/modal';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin/admin-layout';
import rolesRoute from '@/routes/admin/roles';

type Permission = { id: number; slug: string; module: string };
type Role = {
    id: number;
    name: string;
    slug: string;
    level: string;
    permissions: Permission[];
};

type Props = {
    roles: Role[];
    permissions: Permission[];
};

export default function Index({ roles, permissions }: Props) {
    const [openRole, setOpenRole] = useState<Role | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        permissions: [] as number[],
    });

    const modules = [...new Set(permissions.map((p) => p.module))].sort();

    const openEditor = (role: Role) => {
        setOpenRole(role);
        setData(
            'permissions',
            role.permissions.map((p) => p.id),
        );
    };

    const toggle = (id: number) => {
        setData(
            'permissions',
            data.permissions.includes(id)
                ? data.permissions.filter((p) => p !== id)
                : [...data.permissions, id],
        );
    };

    const toggleModule = (module: string, ids: number[]) => {
        const allSelected = ids.every((id) => data.permissions.includes(id));

        if (allSelected) {
            setData(
                'permissions',
                data.permissions.filter((p) => !ids.includes(p)),
            );
        } else {
            setData('permissions', [...new Set([...data.permissions, ...ids])]);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (openRole) {
            put(rolesRoute.update.url(openRole.id), {
                preserveScroll: true,
                onSuccess: () => setOpenRole(null),
            });
        }
    };

    const columns: DataTableColumn<Role>[] = [
        {
            key: 'role',
            header: 'Role',
            render: (role) => role.name,
            cellClassName:
                'font-medium text-gray-800 sm:px-6 dark:text-white/90',
        },
        {
            key: 'level',
            header: 'Level',
            render: (role) => role.level,
        },
        {
            key: 'slug',
            header: 'Slug',
            render: (role) => <code>{role.slug}</code>,
        },
        {
            key: 'permissions',
            header: 'Permissions',
            render: (role) => `${role.permissions.length} permissions`,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            cellClassName: 'font-medium sm:px-6',
            render: (role) => (
                <button
                    type="button"
                    onClick={() => openEditor(role)}
                    className="text-brand-500 transition-colors hover:text-brand-600 dark:text-brand-400"
                >
                    Edit
                </button>
            ),
        },
    ];

    return (
        <>
            <Head title="Role Permissions" />

            <div className="space-y-6">
                <PageHeader
                    title="Role Permissions"
                    description="Define which permissions each role can grant."
                />

                <DataTable
                    columns={columns}
                    emptyMessage="No roles found."
                    getRowKey={(role) => role.id}
                    rows={roles}
                />
            </div>

            {openRole && (
                <Modal
                    title={`Edit ${openRole.name}`}
                    maxWidth="2xl"
                    onClose={() => setOpenRole(null)}
                    className="flex max-h-[85vh] flex-col"
                >
                    <form
                        onSubmit={submit}
                        className="mt-4 flex flex-1 flex-col"
                    >
                        <div className="flex-1 space-y-4 overflow-y-auto">
                            {modules.map((module) => {
                                const modulePerms = permissions.filter(
                                    (p) => p.module === module,
                                );
                                const ids = modulePerms.map((p) => p.id);

                                return (
                                    <div
                                        key={module}
                                        className="rounded-lg border border-gray-200 p-4 dark:border-gray-800"
                                    >
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={ids.every((id) =>
                                                    data.permissions.includes(
                                                        id,
                                                    ),
                                                )}
                                                onChange={() =>
                                                    toggleModule(module, ids)
                                                }
                                                className="rounded border-gray-300"
                                            />
                                            <span className="text-sm font-semibold text-gray-900 dark:text-white">
                                                {module}
                                            </span>
                                        </label>
                                        <div className="mt-3 grid grid-cols-2 gap-2">
                                            {modulePerms.map((p) => (
                                                <label
                                                    key={p.id}
                                                    className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={data.permissions.includes(
                                                            p.id,
                                                        )}
                                                        onChange={() =>
                                                            toggle(p.id)
                                                        }
                                                        className="rounded border-gray-300"
                                                    />
                                                    {p.slug}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {errors.permissions && (
                            <p className="mt-2 text-sm text-red-500">
                                {errors.permissions}
                            </p>
                        )}

                        <FormActions
                            className="mt-4"
                            onCancel={() => setOpenRole(null)}
                            processing={processing}
                        />
                    </form>
                </Modal>
            )}
        </>
    );
}

Index.layout = (page: any) => <AdminLayout>{page}</AdminLayout>;
