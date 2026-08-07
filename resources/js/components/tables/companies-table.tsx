import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';

export type Company = {
    id: string;
    name: string;
    schema_name: string;
    is_active: boolean;
    created_at: string;
};

type CompaniesTableProps = {
    companies: Company[];
    onDelete: (id: string) => void;
    onEdit: (company: Company) => void;
};

export default function CompaniesTable({
    companies,
    onDelete,
    onEdit,
}: CompaniesTableProps) {
    const columns: DataTableColumn<Company>[] = [
        {
            key: 'id',
            header: 'ID',
            render: (company) => company.id,
            cellClassName:
                'font-medium text-gray-800 sm:px-6 dark:text-white/90',
        },
        {
            key: 'name',
            header: 'Name',
            render: (company) => company.name,
            cellClassName: 'font-medium text-gray-800 dark:text-white/90',
        },
        {
            key: 'schema_name',
            header: 'Schema Name',
            render: (company) => <code>{company.schema_name}</code>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (company) => (
                <span
                    className={`inline-flex items-center justify-center rounded-full px-2.5 py-0.5 text-theme-xs font-medium ${
                        company.is_active
                            ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'
                            : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500'
                    }`}
                >
                    {company.is_active ? 'Active' : 'Inactive'}
                </span>
            ),
        },
        {
            key: 'created_at',
            header: 'Created At',
            render: (company) =>
                new Date(company.created_at).toLocaleDateString(),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            cellClassName: 'font-medium sm:px-6',
            render: (company) => (
                <div className="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => onEdit(company)}
                        className="text-brand-500 transition-colors hover:text-brand-600 dark:text-brand-400"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        onClick={() => onDelete(company.id)}
                        className="text-error-500 transition-colors hover:text-error-600"
                    >
                        Delete
                    </button>
                </div>
            ),
        },
    ];

    return (
        <DataTable
            columns={columns}
            emptyMessage={
                <>
                    No companies found. Click &quot;Create Company&quot; to add
                    one.
                </>
            }
            getRowKey={(company) => company.id}
            rows={companies}
        />
    );
}
