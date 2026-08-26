import { Button } from '@heroui/react';
import { Head, Link } from '@inertiajs/react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    qty_requested: number;
    unit_price?: number;
    line_total?: number;
};

type PurchaseRequest = {
    id: number;
    number: string;
    status: string;
    request_date: string;
    required_date?: string;
    note?: string;
    items: Item[];
};

type Props = {
    purchaseRequest: PurchaseRequest;
    approval?: ApprovalStatusProps | null;
};

export default function PurchaseRequestsShow({
    purchaseRequest,
    approval,
}: Props) {
    const rows: DetailRow[] = [
        {
            label: 'Tanggal permintaan',
            value: formatDate(purchaseRequest.request_date),
        },
        {
            label: 'Tanggal dibutuhkan',
            value: formatDate(purchaseRequest.required_date),
        },
    ];

    return (
        <CompanyLayout>
            <Head title={`Permintaan #${purchaseRequest.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Permintaan"
                    title={`Purchase Request #${purchaseRequest.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/requests">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            <ApprovalHeaderControls
                                approval={approval}
                                documentTitle={purchaseRequest.number}
                            />
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={purchaseRequest.status}
                    statusLabel="Status Permintaan"
                    rows={rows}
                    note={purchaseRequest.note}
                >
                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Item Permintaan
                        </h3>
                        <div className="overflow-hidden rounded-lg border border-border">
                            <table className="w-full text-left text-sm text-foreground">
                                <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                    <tr>
                                        <th className="px-4 py-3">Produk</th>
                                        <th className="px-4 py-3">SKU</th>
                                        <th className="px-4 py-3 text-right">
                                            Qty
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Est. Harga Satuan
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Estimasi Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {purchaseRequest.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-surface-secondary/60"
                                        >
                                            <td className="px-4 py-3 font-semibold text-foreground">
                                                {item.product_name}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-muted">
                                                {item.sku}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">
                                                {item.qty_requested}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {item.unit_price != null &&
                                                item.unit_price !== 0
                                                    ? formatCurrency(
                                                          item.unit_price,
                                                      )
                                                    : '-'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-bold text-foreground">
                                                {item.line_total != null &&
                                                item.line_total !== 0
                                                    ? formatCurrency(
                                                          item.line_total,
                                                      )
                                                    : '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </PurchaseDocumentDetail>
            </div>
        </CompanyLayout>
    );
}
