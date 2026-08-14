import { Head, Link, useForm } from '@inertiajs/react';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import type { StockRequest } from './types';

type Props = {
    stockRequest: StockRequest;
};

type ApproveItemRow = {
    product_variant_id: number;
    qty_approved: number;
};

type ApproveForm = {
    items: ApproveItemRow[];
};

export default function Show({ stockRequest }: Props) {
    const isPending = stockRequest.status === 'pending';

    const form = useForm<ApproveForm>({
        items:
            stockRequest.items?.map((item) => ({
                product_variant_id: item.product_variant_id,
                qty_approved: Number(item.qty_requested),
            })) ?? [],
    });

    const handleQtyApprovedChange = (index: number, value: number) => {
        const updated = [...form.data.items];
        updated[index] = { ...updated[index], qty_approved: value };
        form.setData('items', updated);
    };

    const handleRejectAll = () => {
        const rejectedItems = form.data.items.map((item) => ({
            ...item,
            qty_approved: 0,
        }));
        form.setData('items', rejectedItems);
        form.post(`/warehouse/stock-requests/${stockRequest.id}/approve`);
    };

    const handleSubmitApprove = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/warehouse/stock-requests/${stockRequest.id}/approve`);
    };

    const renderStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return (
                    <span className="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        PENDING
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        DISETUJUI SEPENUHNYA
                    </span>
                );
            case 'partially_approved':
                return (
                    <span className="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 ring-1 ring-inset ring-sky-600/20">
                        DISETUJUI SEBAGIAN
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">
                        DITOLAK
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {status.toUpperCase()}
                    </span>
                );
        }
    };

    return (
        <CompanyLayout>
            <Head title={`Detail Permintaan Stok #${stockRequest.id}`} />
            <div className="space-y-6">
                <PageHeader
                    title={`Permintaan Stok #${stockRequest.id}`}
                    description="Detail pengajuan pasokan stok dari cabang ke Gudang HQ."
                    actions={
                        <Link href="/warehouse/stock-requests">
                            <Button variant="secondary">Kembali ke Daftar</Button>
                        </Link>
                    }
                />

                {/* Summary Card */}
                <div className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4 mb-4">
                        <div>
                            <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Status Request</span>
                            <div className="mt-1">{renderStatusBadge(stockRequest.status)}</div>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Tanggal Pengajuan</span>
                            <p className="mt-1 text-sm font-semibold text-slate-900">
                                {stockRequest.requested_at
                                    ? new Date(stockRequest.requested_at).toLocaleDateString('id-ID', {
                                          day: '2-digit',
                                          month: 'long',
                                          year: 'numeric',
                                          hour: '2-digit',
                                          minute: '2-digit',
                                      })
                                    : '-'}
                            </p>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">Pemohon</span>
                            <p className="mt-1 text-sm font-semibold text-slate-900">
                                {stockRequest.requested_by_user?.name ?? '-'}
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="rounded-md bg-slate-50 p-4 border border-slate-200">
                            <span className="text-xs font-medium text-slate-500 uppercase">Gudang Peminta (Cabang)</span>
                            <p className="mt-1 text-base font-semibold text-slate-900">
                                {stockRequest.requesting_warehouse?.name ?? '-'}
                            </p>
                            <p className="text-xs text-slate-500">
                                Cabang: {stockRequest.requesting_warehouse?.branch?.name ?? '-'}
                            </p>
                        </div>

                        <div className="rounded-md bg-slate-50 p-4 border border-slate-200">
                            <span className="text-xs font-medium text-slate-500 uppercase">Gudang Tujuan (HQ)</span>
                            <p className="mt-1 text-base font-semibold text-slate-900">
                                {stockRequest.destination_warehouse?.name ?? '-'}
                            </p>
                            <p className="text-xs text-slate-500">
                                Cabang: {stockRequest.destination_warehouse?.branch?.name ?? '-'}
                            </p>
                        </div>
                    </div>

                    {stockRequest.note && (
                        <div className="mt-4 pt-4 border-t border-slate-200">
                            <span className="text-xs font-medium text-slate-500 uppercase">Catatan Peminta</span>
                            <p className="mt-1 text-sm text-slate-700">{stockRequest.note}</p>
                        </div>
                    )}
                </div>

                {/* Stock Transfer Draft Notice */}
                {stockRequest.transfer && (
                    <div className="rounded-lg bg-emerald-50 p-4 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <span className="text-xs font-bold text-emerald-800 uppercase tracking-wider">
                                Transfer Stok Draft Terbuat (#{stockRequest.transfer.id})
                            </span>
                            <p className="text-sm text-emerald-700">
                                Transfer stok otomatis dibuat dalam status{' '}
                                <strong className="font-semibold">{stockRequest.transfer.status.toUpperCase()}</strong>{' '}
                                dan siap diproses ke tahap Pengiriman (Ship).
                            </p>
                        </div>
                        <Link href={`/warehouse/stock-transfers/${stockRequest.transfer.id}`}>
                            <Button variant="primary">
                                Process Transfer Stok #{stockRequest.transfer.id}
                            </Button>
                        </Link>
                    </div>
                )}

                {/* Items & Approval Form */}
                <form onSubmit={handleSubmitApprove} className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200 space-y-4">
                    <h3 className="text-base font-semibold text-slate-900">Item Permintaan Stok</h3>

                    {form.errors.items && (
                        <p className="text-sm font-medium text-red-600 bg-red-50 p-3 rounded-md border border-red-200">
                            {form.errors.items}
                        </p>
                    )}

                    <div className="overflow-hidden rounded-md border border-slate-200">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase">
                                <tr>
                                    <th className="px-4 py-3">No</th>
                                    <th className="px-4 py-3">Varian Produk</th>
                                    <th className="px-4 py-3 text-right">Qty Diminta</th>
                                    <th className="px-4 py-3 text-right">Stok HQ Tersedia</th>
                                    <th className="px-4 py-3 text-right">Qty Disetujui</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 bg-white">
                                {stockRequest.items?.map((item, idx) => {
                                    const available = item.available_qty ?? 0;
                                    const requested = Number(item.qty_requested);
                                    const isStockLow = available < requested;

                                    return (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3 font-medium text-slate-500">{idx + 1}</td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900">
                                                    {item.product_variant?.product?.name ?? 'Produk'} - {item.product_variant?.variant_name}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    SKU: {item.product_variant?.sku}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {requested}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <span
                                                    className={`font-semibold ${
                                                        isStockLow ? 'text-amber-600' : 'text-emerald-600'
                                                    }`}
                                                >
                                                    {available}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right w-44">
                                                {isPending ? (
                                                    <TextInput
                                                        type="number"
                                                        min={0}
                                                        max={Math.min(requested, available)}
                                                        value={form.data.items[idx]?.qty_approved ?? 0}
                                                        onChange={(e) =>
                                                            handleQtyApprovedChange(idx, Number(e.target.value))
                                                        }
                                                    />
                                                ) : (
                                                    <span className="font-bold text-slate-900">
                                                        {item.qty_approved ?? 0}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {isPending && (
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-slate-200">
                            <Button
                                type="button"
                                variant="danger"
                                onClick={handleRejectAll}
                                disabled={form.processing}
                            >
                                Tolak Permintaan
                            </Button>

                            <div className="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    disabled={form.processing}
                                >
                                    {form.processing ? 'Memproses...' : 'Setujui Permintaan'}
                                </Button>
                            </div>
                        </div>
                    )}
                </form>
            </div>
        </CompanyLayout>
    );
}
