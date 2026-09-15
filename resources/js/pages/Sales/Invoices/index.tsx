import { Button, Input, Label, ListBox, Select } from '@heroui/react';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import HerouiDataTable from '@/components/tables/heroui-data-table';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';
import SalesStatusBadge from './status-badge';

type SalesInvoiceRow = {
    id: number;
    number: string;
    customer_name: string;
    status: string;
    invoice_date: string;
    due_date?: string | null;
    total: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    salesInvoices: Paginated<SalesInvoiceRow>;
    filters: {
        search?: string;
        status?: string;
    };
};

export default function SalesInvoicesIndex({ salesInvoices, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters = (nextSearch: string, nextStatus: string) => {
        const params: Record<string, string> = {};

        if (nextSearch) {
            params.search = nextSearch;
        }

        if (nextStatus) {
            params.status = nextStatus;
        }

        router.get('/sales/invoices', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const columns: DataTableColumn<SalesInvoiceRow>[] = [
        {
            key: 'invoice_date',
            header: 'Tanggal',
            render: (row) => formatDate(row.invoice_date),
        },
        {
            key: 'number',
            header: 'No.',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/sales/invoices/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    Faktur #{row.number}
                </Link>
            ),
        },
        {
            key: 'customer_name',
            header: 'Pelanggan',
            render: (row) => (
                <span className="font-medium">{row.customer_name}</span>
            ),
        },
        {
            key: 'due_date',
            header: 'Tgl. jatuh tempo',
            render: (row) => formatDate(row.due_date),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <SalesStatusBadge status={row.status} />,
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            render: (row) => formatCurrency(row.total || 0),
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Penjualan - Faktur Penjualan" />
            <div className="w-full space-y-6">
                <div>
                    <p className="text-xs font-semibold tracking-widest text-muted uppercase">
                        HOME &gt; PENJUALAN
                    </p>
                    <PageHeader
                        title="Penjualan"
                        actions={
                            <Link href="/sales/invoices/create">
                                <Button variant="primary">
                                    Buat Faktur Penjualan
                                </Button>
                            </Link>
                        }
                    />
                </div>

                <div className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <Label className="block text-xs font-semibold text-foreground">
                            Cari nomor / pelanggan
                        </Label>
                        <Input
                            className="mt-1"
                            placeholder="Ketik lalu Enter..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    applyFilters(search, status);
                                }
                            }}
                        />
                    </div>
                    <div className="w-full sm:w-56">
                        <Label className="block text-xs font-semibold text-foreground">
                            Status
                        </Label>
                        <Select
                            fullWidth
                            placeholder="Semua status"
                            value={status}
                            onChange={(val) => {
                                const next = String(val ?? '');
                                setStatus(next);
                                applyFilters(search, next);
                            }}
                        >
                            <Select.Trigger className="mt-1">
                                <Select.Value />
                                <Select.Indicator />
                            </Select.Trigger>
                            <Select.Popover>
                                <ListBox>
                                    <ListBox.Item
                                        id="pending"
                                        textValue="Menunggu"
                                    >
                                        Menunggu
                                    </ListBox.Item>
                                    <ListBox.Item
                                        id="approved"
                                        textValue="Disetujui"
                                    >
                                        Disetujui
                                    </ListBox.Item>
                                    <ListBox.Item
                                        id="cancelled"
                                        textValue="Dibatalkan"
                                    >
                                        Dibatalkan
                                    </ListBox.Item>
                                </ListBox>
                            </Select.Popover>
                        </Select>
                    </div>
                    <Button
                        variant="secondary"
                        onPress={() => applyFilters(search, status)}
                    >
                        Cari
                    </Button>
                </div>

                <HerouiDataTable<SalesInvoiceRow>
                    columns={columns}
                    rows={salesInvoices.data}
                    getRowKey={(row) => row.id}
                    emptyMessage="Belum ada data Faktur Penjualan."
                    pagination={{
                        currentPage: salesInvoices.current_page,
                        lastPage: salesInvoices.last_page,
                        perPage: salesInvoices.per_page,
                        total: salesInvoices.total,
                    }}
                    onPageChange={(page) => {
                        const params: Record<string, string> = {
                            ...(search ? { search } : {}),
                            ...(status ? { status } : {}),
                            page: String(page),
                        };
                        router.get('/sales/invoices', params, {
                            preserveState: true,
                            preserveScroll: true,
                        });
                    }}
                />
            </div>
        </CompanyLayout>
    );
}
