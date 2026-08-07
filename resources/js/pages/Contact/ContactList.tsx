import { Head, Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { contactLabels } from './types';
import type { Contact, ContactType } from './types';

type Props = {
    contacts: Contact[];
    type: ContactType;
};

export default function ContactList({ contacts, type }: Props) {
    const labels = contactLabels[type];

    const handleDelete = (contact: Contact) => {
        if (confirm(`Remove ${contact.name} from contacts?`)) {
            router.delete(`/company/contacts/${type}/${contact.id}`);
        }
    };

    const columns: DataTableColumn<Contact>[] = [
        {
            key: 'name',
            header: 'Name',
            render: (contact) => contact.name,
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        {
            key: 'email',
            header: 'Email',
            render: (contact) => contact.email ?? '-',
        },
        {
            key: 'phone',
            header: 'Phone',
            render: (contact) => contact.mobile_phone ?? contact.telephone ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (contact) => (
                <span
                    className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                        contact.is_active
                            ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                            : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                    }`}
                >
                    {contact.is_active ? 'Active' : 'Inactive'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right' as const,
            cellClassName: 'font-medium',
            render: (contact: Contact) => (
                <div className="space-x-3">
                    <Link
                        href={`/company/contacts/${type}/${contact.id}/edit`}
                        className="text-brand-500 hover:text-brand-600"
                    >
                        Edit
                    </Link>
                    <button
                        onClick={() => handleDelete(contact)}
                        className="text-red-500 hover:text-red-600"
                    >
                        Remove
                    </button>
                </div>
            ),
        },
    ];

    return (
        <CompanyLayout>
            <Head title={labels.plural} />

            <div className="space-y-6">
                <PageHeader
                    title={labels.plural}
                    description={labels.description}
                    actions={
                        <Link href={`/company/contacts/${type}/create`}>
                            <Button>Add {labels.singular}</Button>
                        </Link>
                    }
                />
                <DataTable
                    columns={columns}
                    rows={contacts}
                    getRowKey={(contact) => contact.id}
                    emptyMessage={`No ${type} yet. Click "Add ${labels.singular}" to create one.`}
                />
            </div>
        </CompanyLayout>
    );
}
