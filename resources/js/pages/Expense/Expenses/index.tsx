import { Button } from '@heroui/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, ReceiptText } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import HerouiDataTable from '@/components/tables/heroui-data-table';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDateDash } from '@/lib/format';
import ExpenseFilterBar from './expense-filter-bar';
import ExpenseSummaryCards from './expense-summary-cards';
import ExpenseStatusBadge from './status-badge';
import type {
    ExpenseFilters,
    ExpenseRow,
    ExpenseSummary,
    Paginated,
} from './types';

type Props = {
    expenses: Paginated<ExpenseRow>;
    summary: ExpenseSummary;
    statusCounts: { open: number; closed: number };
    filters: ExpenseFilters;
};

export default function ExpensesIndex({ expenses, summary, filters }: Props) {
    const { props } = usePage();
    const auth = (props.auth ?? {}) as {
        permissions?: string[];
    };
    const canCreate = (auth.permissions ?? []).includes('expense.create');

    // State reside di halaman, bukan di komponen: filter bar sepenuhnya
    // controlled supaya hanya ada satu sumber kebenaran untuk pencarian.
    const [search, setSearch] = useState(filters.search ?? '');
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => () => clearTimeout(timer.current), []);

    const handleSearch = (value: string) => {
        setSearch(value);
        clearTimeout(timer.current);
        timer.current = setTimeout(
            () => apply({ filter: filters.filter, search: value }),
            300,
        );
    };

    const apply = (next: { filter?: string; search?: string }) => {
        const params: Record<string, string> = {};

        if (next.filter) {
            params.filter = next.filter;
        }

        if (next.search) {
            params.search = next.search;
        }

        router.get(window.location.pathname, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const columns: DataTableColumn<ExpenseRow>[] = [
        {
            key: 'transaction_date',
            header: 'Tanggal',
            render: (row) => formatDateDash(row.transaction_date),
        },
        {
            key: 'number',
            header: 'Nomor',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/expenses/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    {row.number}
                </Link>
            ),
        },
        {
            key: 'category_label',
            header: 'Kategori',
            render: (row) => (
                <span className="text-accent">{row.category_label}</span>
            ),
        },
        {
            key: 'contact_name',
            header: 'Penerima',
            render: (row) => row.contact_name ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <ExpenseStatusBadge status={row.status} />,
        },
        {
            key: 'outstanding',
            header: 'Sisa tagihan (IDR)',
            align: 'right',
            render: (row) => formatCurrency(row.outstanding),
        },
        {
            key: 'grand_total',
            header: 'Total (IDR)',
            align: 'right',
            render: (row) => formatCurrency(row.grand_total),
        },
        {
            key: 'tags',
            header: 'Tags',
            render: (row) =>
                row.tags.length === 0 ? (
                    '-'
                ) : (
                    <span className="text-xs text-muted">
                        {row.tags.map((tag) => tag.name).join(', ')}
                    </span>
                ),
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Pengeluaran - Biaya" />

            <div className="w-full space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-widest text-muted uppercase">
                            Biaya
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold text-accent">
                            Pengeluaran
                        </h1>
                    </div>

                    {canCreate && (
                        <Link href="/expenses/create">
                            <Button variant="primary" className="gap-1.5">
                                <Plus className="size-4" />
                                Buat biaya baru
                            </Button>
                        </Link>
                    )}
                </div>

                <ExpenseSummaryCards
                    summary={summary}
                    activeFilter={filters.filter ?? ''}
                    onFilterChange={(filter) => apply({ filter, search })}
                />

                <section className="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="flex items-center gap-2 text-lg font-semibold text-foreground">
                            <ReceiptText className="size-5 text-muted" />
                            Daftar biaya
                        </h2>
                    </div>

                    <ExpenseFilterBar value={search} onChange={handleSearch} />

                    <HerouiDataTable<ExpenseRow>
                        columns={columns}
                        rows={expenses.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada biaya. Klik Buat biaya baru untuk mencatat pengeluaran pertama."
                        pagination={{
                            currentPage: expenses.current_page,
                            lastPage: expenses.last_page,
                            perPage: expenses.per_page,
                            total: expenses.total,
                        }}
                        onPageChange={(page) => {
                            const params: Record<string, string> = {
                                page: String(page),
                            };

                            if (filters.filter) {
                                params.filter = filters.filter;
                            }

                            if (search) {
                                params.search = search;
                            }

                            router.get(window.location.pathname, params, {
                                preserveState: true,
                                preserveScroll: true,
                            });
                        }}
                    />
                </section>
            </div>
        </CompanyLayout>
    );
}
