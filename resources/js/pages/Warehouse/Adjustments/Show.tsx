import { Head, Link, useForm } from '@inertiajs/react';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import type { StockAdjustment } from './types';

type Props = {
    adjustment: StockAdjustment;
};

export default function AdjustmentsShow({ adjustment }: Props) {
    const { post, processing } = useForm();

    const handlePost = () => {
        if (
            confirm(
                `Apakah Anda yakin ingin memposting penyesuaian stok ${adjustment.adjustment_number}? Stok akan langsung diperbarui.`,
            )
        ) {
            post(`/warehouse/adjustments/${adjustment.id}/post`);
        }
    };

    const renderTypeBadge = (type: string) => {
        if (type === 'in') {
            return (
                <span className="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 ring-inset">
                    STOK MASUK (IN)
                </span>
            );
        }

        return (
            <span className="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 ring-inset">
                STOK KELUAR (OUT)
            </span>
        );
    };

    const renderStatusBadge = (status: string) => {
        if (status === 'posted') {
            return (
                <span className="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10 ring-inset">
                    POSTED
                </span>
            );
        }

        return (
            <span className="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                DRAFT
            </span>
        );
    };

    return (
        <CompanyLayout>
            <Head
                title={`Detail Penyesuaian - ${adjustment.adjustment_number}`}
            />

            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={`Detail Penyesuaian ${adjustment.adjustment_number}`}
                    description={`Detail rincian penyesuaian stok gudang ${adjustment.warehouse?.name ?? ''}`}
                    actions={
                        <div className="flex items-center gap-3">
                            <Link href="/warehouse/adjustments">
                                <Button variant="secondary">
                                    &larr; Kembali
                                </Button>
                            </Link>
                            {adjustment.status === 'draft' && (
                                <Button
                                    variant="primary"
                                    onClick={handlePost}
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Memposting...'
                                        : 'Posting Penyesuaian Stok'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4">
                        <div>
                            <span className="text-xs text-slate-500">
                                Nomor Penyesuaian
                            </span>
                            <h2 className="font-mono text-xl font-bold text-slate-900">
                                {adjustment.adjustment_number}
                            </h2>
                        </div>
                        <div className="flex items-center gap-2">
                            {renderTypeBadge(adjustment.type)}
                            {renderStatusBadge(adjustment.status)}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <span className="block text-xs font-medium text-slate-500">
                                Gudang
                            </span>
                            <span className="font-semibold text-slate-900">
                                {adjustment.warehouse?.name} (
                                {adjustment.warehouse?.branch?.name ?? '-'})
                            </span>
                        </div>
                        <div>
                            <span className="block text-xs font-medium text-slate-500">
                                Dibuat / Disesuaikan Oleh
                            </span>
                            <span className="font-semibold text-slate-900">
                                {adjustment.adjusted_by_user?.name ?? '-'}
                            </span>
                        </div>
                        <div>
                            <span className="block text-xs font-medium text-slate-500">
                                Waktu Diposting
                            </span>
                            <span className="font-semibold text-slate-900">
                                {adjustment.adjusted_at
                                    ? new Date(
                                          adjustment.adjusted_at,
                                      ).toLocaleDateString('id-ID', {
                                          day: '2-digit',
                                          month: 'short',
                                          year: 'numeric',
                                          hour: '2-digit',
                                          minute: '2-digit',
                                      })
                                    : 'Belum diposting'}
                            </span>
                        </div>
                    </div>

                    {adjustment.note && (
                        <div className="rounded-md bg-slate-50 p-3 text-sm text-slate-700">
                            <span className="font-semibold">Catatan: </span>
                            {adjustment.note}
                        </div>
                    )}

                    <div>
                        <h3 className="mb-3 text-base font-semibold text-slate-900">
                            Rincian Item
                        </h3>
                        <div className="overflow-x-auto rounded-lg border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 font-semibold text-slate-700">
                                    <tr>
                                        <th className="px-4 py-3 text-left">
                                            SKU
                                        </th>
                                        <th className="px-4 py-3 text-left">
                                            Nama Produk & Varian
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Qty
                                        </th>
                                        {adjustment.type === 'in' && (
                                            <th className="px-4 py-3 text-right">
                                                Biaya/Unit
                                            </th>
                                        )}
                                        <th className="px-4 py-3 text-left">
                                            Catatan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white">
                                    {adjustment.items?.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3 font-mono text-xs font-semibold text-slate-800">
                                                {item.product_variant?.sku}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-900">
                                                    {
                                                        item.product_variant
                                                            ?.product?.name
                                                    }
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    Varian:{' '}
                                                    {
                                                        item.product_variant
                                                            ?.variant_name
                                                    }
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold text-slate-900">
                                                {item.qty}{' '}
                                                {item.product_variant?.product
                                                    ?.uom?.code ?? ''}
                                            </td>
                                            {adjustment.type === 'in' && (
                                                <td className="px-4 py-3 text-right font-mono text-slate-700">
                                                    {item.unit_cost !== null
                                                        ? `Rp ${Number(item.unit_cost).toLocaleString('id-ID')}`
                                                        : '-'}
                                                </td>
                                            )}
                                            <td className="px-4 py-3 text-xs text-slate-600">
                                                {item.note ?? '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </CompanyLayout>
    );
}
