import { Head, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import Modal from '@/components/ui/modal';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
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

export default function Index({
    members,
    branches,
    roles,
    canManageUsers,
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editMember, setEditMember] = useState<Member | null>(null);

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
            roles: roles
                .filter((r) => member.roles.includes(r.slug))
                .map((r) => r.id),
        });
    };

    const closeModals = () => {
        setIsCreateOpen(false);
        setEditMember(null);
        reset();
    };

    const toggleRole = (id: number) => {
        setData(
            'roles',
            data.roles.includes(id)
                ? data.roles.filter((r) => r !== id)
                : [...data.roles, id],
        );
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

    const roleName = (slug: string) =>
        roles.find((r) => r.slug === slug)?.name ?? slug;

    const columns: DataTableColumn<Member>[] = [
        {
            key: 'name',
            header: 'Name',
            render: (member) => member.name,
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        { key: 'email', header: 'Email', render: (member) => member.email },
        {
            key: 'company-role',
            header: 'Company Role',
            render: (member) => member.company_role,
        },
        { key: 'scope', header: 'Scope', render: (member) => member.scope },
        {
            key: 'branch',
            header: 'Branch',
            render: (member) =>
                branches.find((b) => b.id === member.branch_id)?.name ?? '-',
        },
        {
            key: 'roles',
            header: 'Roles',
            render: (member) => member.roles.map(roleName).join(', ') || '-',
        },
        ...(canManageUsers
            ? [
                  {
                      key: 'actions',
                      header: 'Actions',
                      align: 'right' as const,
                      cellClassName: 'font-medium',
                      render: (member: Member) => (
                          <div className="space-x-3">
                              <button
                                  onClick={() => openEdit(member)}
                                  className="text-brand-500 hover:text-brand-600"
                              >
                                  Edit
                              </button>
                              <button
                                  onClick={() => handleDelete(member)}
                                  className="text-red-500 hover:text-red-600"
                              >
                                  Remove
                              </button>
                          </div>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title="Company Users" />

            <div className="space-y-6">
                <PageHeader
                    title="Company Users"
                    description="Manage users and their branch-level roles."
                    actions={
                        canManageUsers ? (
                            <Button onClick={openCreate}>Add User</Button>
                        ) : undefined
                    }
                />
                <DataTable
                    columns={columns}
                    rows={members}
                    getRowKey={(member) => member.id}
                    emptyMessage={
                        canManageUsers
                            ? 'No users yet. Click "Add User" to invite one.'
                            : 'No users found.'
                    }
                />
            </div>

            {isCreateOpen && canManageUsers && (
                <Modal title="Add User" maxWidth="lg">
                    <form onSubmit={submitCreate} className="mt-4 space-y-4">
                        <UserFields
                            data={data}
                            setData={setData as any}
                            branches={branches}
                            roles={roles}
                            errors={errors}
                            toggleRole={toggleRole}
                        />
                        <FormActions
                            onCancel={closeModals}
                            submitLabel="Create"
                            processing={processing}
                        />
                    </form>
                </Modal>
            )}

            {editMember && canManageUsers && (
                <Modal title="Edit User" maxWidth="lg">
                    <form onSubmit={submitEdit} className="mt-4 space-y-4">
                        <UserFields
                            data={data}
                            setData={setData as any}
                            branches={branches}
                            roles={roles}
                            errors={errors}
                            toggleRole={toggleRole}
                            editing
                        />
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
    data: any;
    setData: any;
    branches: Branch[];
    roles: Role[];
    errors: any;
    toggleRole: (id: number) => void;
    editing?: boolean;
};

function UserFields({
    data,
    setData,
    branches,
    roles,
    errors,
    toggleRole,
    editing,
}: FieldProps) {
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

            <FormField label="Email" error={errors.email}>
                <TextInput
                    type="email"
                    required
                    disabled={editing}
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                />
            </FormField>

            <FormField
                label={editing ? 'New Password (optional)' : 'Password'}
                error={errors.password}
            >
                <TextInput
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    placeholder="Minimum 8 characters"
                />
            </FormField>

            <div className="grid grid-cols-2 gap-4">
                <FormField label="Company Role" error={errors.company_role}>
                    <SelectInput
                        value={data.company_role}
                        onChange={(e) =>
                            setData('company_role', e.target.value)
                        }
                    >
                        <option value="admin">Admin</option>
                        <option value="member">Member</option>
                    </SelectInput>
                </FormField>

                <FormField label="Branch Scope" error={errors.scope}>
                    <SelectInput
                        value={data.scope}
                        onChange={(e) => setData('scope', e.target.value)}
                    >
                        <option value="branch">Specific branch</option>
                        <option value="all">All branches</option>
                    </SelectInput>
                </FormField>
            </div>

            <FormField label="Home Branch" error={errors.branch_id}>
                <SelectInput
                    value={data.branch_id ?? ''}
                    onChange={(e) =>
                        setData(
                            'branch_id',
                            e.target.value ? Number(e.target.value) : null,
                        )
                    }
                >
                    <option value="">Select branch...</option>
                    {branches.map((b) => (
                        <option key={b.id} value={b.id}>
                            {b.name} ({b.code})
                        </option>
                    ))}
                </SelectInput>
            </FormField>

            <FormField label="Branch Roles" error={errors.roles}>
                <div className="mt-2 space-y-2">
                    {roles
                        .filter((r) => r.level === 'branch')
                        .map((role) => (
                            <label
                                key={role.id}
                                className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
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
            </FormField>
        </>
    );
}

Index.layout = (page: any) => <CompanyLayout>{page}</CompanyLayout>;
