import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import AdminLayout from '@/layouts/admin/admin-layout';
import rolesRoute from '@/routes/admin/roles';

type Permission = { id: number; slug: string; module: string };
type Role = { id: number; name: string; slug: string; level: string; permissions: Permission[] };

type Props = {
    roles: Role[];
    permissions: Permission[];
};

export default function Index({ roles, permissions }: Props) {
    const [openRole, setOpenRole] = useState<Role | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        permissions: [] as number[],
    });

    const modules = [...new Set(permissions.map(p => p.module))].sort();

    const openEditor = (role: Role) => {
        setOpenRole(role);
        setData('permissions', role.permissions.map(p => p.id));
    };

    const toggle = (id: number) => {
        setData('permissions', data.permissions.includes(id) ? data.permissions.filter(p => p !== id) : [...data.permissions, id]);
    };

    const toggleModule = (module: string, ids: number[]) => {
        const allSelected = ids.every(id => data.permissions.includes(id));

        if (allSelected) {
            setData('permissions', data.permissions.filter(p => !ids.includes(p)));
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

    return (
        <>
            <Head title="Role Permissions" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">Role Permissions</h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Define which permissions each role can grant.</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Role</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Level</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Slug</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Permissions</th>
                                <th className="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            {roles.map(role => (
                                <tr key={role.id}>
                                    <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{role.name}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{role.level}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{role.slug}</td>
                                    <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{role.permissions.length} permissions</td>
                                    <td className="px-6 py-4 text-right text-sm font-medium">
                                        <button onClick={() => openEditor(role)} className="text-brand-500 hover:text-brand-600">Edit</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {openRole && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <div className="flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Edit {openRole.name}</h3>
                            <button onClick={() => setOpenRole(null)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">Close</button>
                        </div>

                        <form onSubmit={submit} className="mt-4 flex flex-1 flex-col">
                            <div className="flex-1 space-y-4 overflow-y-auto">
                                {modules.map(module => {
                                    const modulePerms = permissions.filter(p => p.module === module);
                                    const ids = modulePerms.map(p => p.id);

                                    return (
                                        <div key={module} className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={ids.every(id => data.permissions.includes(id))}
                                                    onChange={() => toggleModule(module, ids)}
                                                    className="rounded border-gray-300"
                                                />
                                                <span className="text-sm font-semibold text-gray-900 dark:text-white">{module}</span>
                                            </label>
                                            <div className="mt-3 grid grid-cols-2 gap-2">
                                                {modulePerms.map(p => (
                                                    <label key={p.id} className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                        <input
                                                            type="checkbox"
                                                            checked={data.permissions.includes(p.id)}
                                                            onChange={() => toggle(p.id)}
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

                            {errors.permissions && <p className="mt-2 text-sm text-red-500">{errors.permissions}</p>}

                            <div className="mt-4 flex justify-end gap-2">
                                <button type="button" onClick={() => setOpenRole(null)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                                <button type="submit" disabled={processing} className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}

Index.layout = (page: any) => <AdminLayout>{page}</AdminLayout>;
