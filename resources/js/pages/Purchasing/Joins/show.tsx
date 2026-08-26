import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PurchaseConfirmDialog from '@/components/purchasing/purchase-confirm-dialog';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';

type Item = {
    id: number;
    purchase_invoice_id: number;
    invoice_number: string;
    supplier_name: string;
    invoice_total: number;
};

type JoinPurchaseInvoice = {
    id: number;
    number: string;
    status: string;
    join_date: string;
    note?: string;
    total_amount: number;
    items: Item[];
};

type Props = {
    joinPurchaseInvoice: JoinPurchaseInvoice;
};

export default function JoinPurchaseInvoicesShow({
    joinPurchaseInvoice,
}: Props) {
    const [confirmReady, setConfirmReady] = useState(false);
    const { post: postReady, processing: markingReady } = useForm({});

    const handleReady = () => {
        postReady(`/purchasing/joins/${joinPurchaseInvoice.id}/ready`);
    };

    const rows: DetailRow[] = [
        {
            label: 'Tanggal konsolidasi',
            value: formatDate(joinPurchaseInvoice.join_date),
        },
    ];

    return (
        <CompanyLayout>
            <Head title={`Tukar Faktur #${joinPurchaseInvoice.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Tukar Faktur"
                    title={`Join Invoice #${joinPurchaseInvoice.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/joins">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            {joinPurchaseInvoice.status === 'draft' && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onPress={() => setConfirmReady(true)}
                                >
                                    Tandai Siap (Ready)
                                </Button>
                            )}
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={joinPurchaseInvoice.status}
                    statusLabel="Status Konsolidasi"
                    rows={rows}
                    note={joinPurchaseInvoice.note}
                >
                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Faktur Tergabung
                        </h3>
                        <div className="overflow-hidden rounded-lg border border-border">
                            <table className="w-full text-left text-sm text-foreground">
                                <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Nomor Faktur
                                        </th>
                                        <th className="px-4 py-3">Pemasok</th>
                                        <th className="px-4 py-3 text-right">
                                            Nilai Faktur
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {joinPurchaseInvoice.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-surface-secondary/60"
                                        >
                                            <td className="px-4 py-3 font-semibold text-foreground">
                                                {item.invoice_number}
                                            </td>
                                            <td className="px-4 py-3 text-muted">
                                                {item.supplier_name}
                                            </td>
                                            <td className="px-4 py-3 text-right font-bold text-foreground">
                                                {formatCurrency(
                                                    item.invoice_total,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="flex justify-end border-t border-border/60 pt-4">
                        <div className="w-72 space-y-2 text-sm">
                            <div className="flex justify-between text-lg font-bold text-foreground">
                                <span>Total Konsolidasi</span>
                                <span>
                                    {formatCurrency(
                                        joinPurchaseInvoice.total_amount,
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>
                </PurchaseDocumentDetail>
            </div>

            <PurchaseConfirmDialog
                open={confirmReady}
                title="Tandai Tukar Faktur sebagai Siap?"
                description="Tukar Faktur ini akan ditandai SIAP (ready) untuk diproses pembayaran."
                confirmLabel="Tandai Siap"
                processing={markingReady}
                onConfirm={handleReady}
                onClose={() => setConfirmReady(false)}
            />
        </CompanyLayout>
    );
}
