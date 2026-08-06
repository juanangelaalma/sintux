import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import InputError from '@/components/input-error';
import CompanyLayout from '@/layouts/company/company-layout';
import usersRoute from '@/routes/company/users';

type Branch = { id: number; name: string; code: string };
type Role = { id: number; name: string; slug: string; level: string };
type Member = {
    id: number;
    user_id: number;
    name: string;
    email: string;
    company_role: string;
    scope: string;
    branch_id: number | null;
    is_default: boolean;
    roles: string[];
};

type Props = {
    members: Member[];
    branches: Branch[];
    roles: Role[];
    canManageUsers: boolean;
};

export default function Index({ members, branches, roles, canManageUsers }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editMember, setEditMember] = useState<Member | null>(null);

    const { data, setData, post, put, delete: destroy, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        company_role: 'member',
        scope: 'branch',
        branch_id: null as number | null,
        roles: [] as number[],
    });

    const openCreate = () => {
        clearErrors();
        reset();
        setIsCreateOpen(true);
    };

    const openEdit = (member: Member) => {
        clearErrors();
        setEditMember(member);
        setData({
            name: member.name,
            email: member.email,
            password: '',
            company_role: member.company_role,
            scope: member.scope,
            branch_id: member.branch_id,
            roles: roles.filter(r => member.roles.includes(r.slug)).map(r => r.id),
        });
    };

    const closeModals = () => {
        setIsCreateOpen(false);
        setEditMember(null);
        reset();
    };

    const toggleRole = (id: number) => {
        setData('roles', data.roles.includes(id) ? data.roles.filter(r => r !== id) : [...data.roles, id]);
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(usersRoute.store.url(), {
            onSuccess: () => closeModals(),
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editMember) {
            put(usersRoute.update.url(editMember.id), {
                onSuccess: () => closeModals(),
            });
        }
    };

    const handleDelete = (member: Member) => {
        if (confirm(`Remove ${member.name} from this company?`)) {
            destroy(usersRoute.destroy.url(member.id));
        }
    };

    const roleName = (slug: string) => roles.find(r => r.slug === slug)?.name ?? slug;

    return (
        <>
            <Head title="Company Users" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">Company Users</h1>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage users and their branch-level roles.</p>
                    </div>
                    {canManageUsers && (
                        <button
                            onClick={openCreate}
                            className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
                        >
                            Add User
                        </button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead className="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Name</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Email</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Company Role</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Scope</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Branch</th>
                                <th className="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Roles</th>
                                {canManageUsers && (
                                    <th className="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider dark:text-gray-400">Actions</th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            {members.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No users yet. Click "Add User" to invite one.
                                    </td>
                                </tr>
                            ) : (
                                members.map(member => (
                                    <tr key={member.id}>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{member.name}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{member.email}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{member.company_role}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{member.scope}</td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {branches.find(b => b.id === member.branch_id)?.name ?? '-'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {member.roles.map(roleName).join(', ') || '-'}
                                        </td>
                                        {canManageUsers && (
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                                <button onClick={() => openEdit(member)} className="text-brand-500 hover:text-brand-600">Edit</button>
                                                <button onClick={() => handleDelete(member)} className="text-red-500 hover:text-red-600">Remove</button>
                                            </td>
                                        )}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {isCreateOpen && canManageUsers && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Add User</h3>
                        <form onSubmit={submitCreate} className="mt-4 space-y-4">
                            <UserFields data={data} setData={setData as any} branches={branches} roles={roles} errors={errors} toggleRole={toggleRole} />
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" onClick={closeModals} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                                <button type="submit" disabled={processing} className="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {editMember && canManageUsers && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Edit User</h3>
                        <form onSubmit={submitEdit} className="mt-4 space-y-4">
                            <UserFields data={data} setData={setData as any} branches={branches} roles={roles} errors={errors} toggleRole={toggleRole} editing />
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
    data: any;
    setData: any;
    branches: Branch[];
    roles: Role[];
    errors: any;
    toggleRole: (id: number) => void;
    editing?: boolean;
};

function UserFields({ data, setData, branches, roles, errors, toggleRole, editing }: FieldProps) {
    return (
        <>
            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                <input type="text" required value={data.name} onChange={e => setData('name', e.target.value)} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                <InputError message={errors.name} />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input type="email" required disabled={editing} value={data.email} onChange={e => setData('email', e.target.value)} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800" />
                <InputError message={errors.email} />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">{editing ? 'New Password (optional)' : 'Password'}</label>
                <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} placeholder="Minimum 8 characters" className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                <InputError message={errors.password} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Company Role</label>
                    <select value={data.company_role} onChange={e => setData('company_role', e.target.value)} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="admin">Admin</option>
                        <option value="member">Member</option>
                    </select>
                    <InputError message={errors.company_role} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Scope</label>
                    <select value={data.scope} onChange={e => setData('scope', e.target.value)} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="branch">Specific branch</option>
                        <option value="all">All branches</option>
                    </select>
                    <InputError message={errors.scope} />
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Home Branch</label>
                <select value={data.branch_id ?? ''} onChange={e => setData('branch_id', e.target.value ? Number(e.target.value) : null)} className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Select branch...</option>
                    {branches.map(b => (
                        <option key={b.id} value={b.id}>{b.name} ({b.code})</option>
                    ))}
                </select>
                <InputError message={errors.branch_id} />
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Branch Roles</label>
                <div className="mt-2 space-y-2">
                    {roles.filter(r => r.level === 'branch').map(role => (
                        <label key={role.id} className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.roles.includes(role.id)}
                                onChange={() => toggleRole(role.id)}
                                className="rounded border-gray-300"
                            />
                            {role.name}
                        </label>
                    ))}
                </div>
                <InputError message={errors.roles} />
            </div>
        </>
    );
}

Index.layout = (page: any) => <CompanyLayout>{page}</CompanyLayout>;
