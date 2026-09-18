import { Button } from '@heroui/react';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatDate, formatQty } from '@/lib/format';

type PreviewItem = {
    supplier_barcode: string;
    product_name: string;
    sku: string;
    size?: string | null;
    uom_name?: string | null;
    color_raw?: string | null;
    qty_do: number | string;
    product_variant_id: number | null;
};

type Preview = {
    do_no: string;
    purchase_order_id?: number | null;
    customer?: string | null;
    po_number: string;
    header: {
        do_date?: string | null;
        cust_name?: string | null;
        driver?: string | null;
        nopol?: string | null;
        inv_no?: string | null;
    };
    items: PreviewItem[];
};

type Props = {
    preview: Preview;
};

export default function GrnsPreview({ preview }: Props) {
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const toggleExpand = (barcode: string) => {
        setExpanded((prev) => ({ ...prev, [barcode]: !prev[barcode] }));
    };

    const handleSave = () => {
        router.post('/purchasing/grns', {
            do_no: preview.do_no,
            purchase_order_id: preview.purchase_order_id ?? undefined,
            customer: preview.customer ?? undefined,
        });
    };

    // Kelompokkan baris warna per bundle barcode untuk tampilan.
    const bundles = new Map<string, PreviewItem[]>();

    for (const item of preview.items) {
        const group = bundles.get(item.supplier_barcode) ?? [];
        group.push(item);
        bundles.set(item.supplier_barcode, group);
    }

    const rows: DetailRow[] = [
        { label: 'No. DO Supplier', value: preview.do_no },
        { label: 'No. PO', value: preview.po_number },
        {
            label: 'Tanggal DO',
            value: preview.header.do_date
                ? formatDate(preview.header.do_date)
                : '-',
        },
        {
            label: 'Sopir / Nopol',
            value: `${preview.header.driver ?? '-'} / ${preview.header.nopol ?? '-'}`,
        },
    ];

    return (
        <CompanyLayout>
            <Head title={`Preview ${preview.do_no}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="GRN / Preview DO"
                    title={`Preview ${preview.do_no}`}
                    actions={
                        <>
                            <Link href="/purchasing/grns/create">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            <Button
                                type="button"
                                variant="primary"
                                onPress={handleSave}
                            >
                                Simpan sebagai Draft
                            </Button>
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status="preview"
                    statusLabel="Status"
                    rows={rows}
                    note="Data hanya pratinjau dari supplier — belum disimpan. Tekan Simpan sebagai Draft untuk mulai verifikasi."
                >
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm text-foreground">
                            <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                <tr>
                                    <th className="px-3 py-3">No</th>
                                    <th className="px-3 py-3">Barcode</th>
                                    <th className="px-3 py-3">Item Name</th>
                                    <th className="px-3 py-3">Pkg / Size</th>
                                    <th className="px-3 py-3 text-right">
                                        Qty
                                    </th>
                                    <th className="px-3 py-3">UoM</th>
                                    <th className="px-3 py-3">Master</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {[...bundles.entries()].map(
                                    ([barcode, group], index) => {
                                        const first = group[0];
                                        const unmapped = group.filter(
                                            (d) =>
                                                d.product_variant_id === null,
                                        ).length;
                                        const open = expanded[barcode] ?? false;
                                        const totalQty = group.reduce(
                                            (sum, d) =>
                                                sum + Number(d.qty_do || 0),
                                            0,
                                        );

                                        return [
                                            <tr
                                                key={barcode}
                                                className="cursor-pointer hover:bg-surface-secondary/60"
                                                onClick={() =>
                                                    toggleExpand(barcode)
                                                }
                                            >
                                                <td className="px-3 py-3 text-muted">
                                                    {index + 1}
                                                </td>
                                                <td className="px-3 py-3 font-mono text-xs font-semibold">
                                                    {barcode}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <p className="font-semibold">
                                                        {first.product_name}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted">
                                                        {first.sku}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3">
                                                    {first.size ?? '-'}
                                                </td>
                                                <td className="px-3 py-3 text-right font-bold">
                                                    {formatQty(totalQty)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {first.uom_name ?? '-'}
                                                </td>
                                                <td className="px-3 py-3 text-xs">
                                                    {unmapped === 0 ? (
                                                        <span className="font-semibold text-emerald-600">
                                                            OK
                                                        </span>
                                                    ) : (
                                                        <span className="font-semibold text-danger">
                                                            {unmapped} belum
                                                            mapping
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>,
                                            open && (
                                                <tr key={`${barcode}-detail`}>
                                                    <td
                                                        colSpan={7}
                                                        className="bg-surface-secondary/40 px-6 py-3"
                                                    >
                                                        <table className="w-full text-left text-xs text-foreground">
                                                            <thead className="text-[11px] font-bold text-muted uppercase">
                                                                <tr>
                                                                    <th className="py-2 pr-3">
                                                                        Warna
                                                                    </th>
                                                                    <th className="py-2 pr-3 text-right">
                                                                        Qty
                                                                    </th>
                                                                    <th className="py-2">
                                                                        Master
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody className="divide-y divide-border/40">
                                                                {group.map(
                                                                    (
                                                                        detail,
                                                                        i,
                                                                    ) => (
                                                                        <tr
                                                                            key={`${detail.sku}-${detail.color_raw}-${i}`}
                                                                        >
                                                                            <td className="py-2 pr-3 font-semibold">
                                                                                {detail.color_raw ||
                                                                                    '-'}
                                                                            </td>
                                                                            <td className="py-2 pr-3 text-right">
                                                                                {formatQty(
                                                                                    detail.qty_do,
                                                                                )}
                                                                            </td>
                                                                            <td className="py-2">
                                                                                {detail.product_variant_id !==
                                                                                null ? (
                                                                                    <span className="font-semibold text-emerald-600">
                                                                                        Termapping
                                                                                    </span>
                                                                                ) : (
                                                                                    <span className="font-semibold text-danger">
                                                                                        Belum
                                                                                        mapping
                                                                                    </span>
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            ),
                                        ];
                                    },
                                )}
                            </tbody>
                        </table>
                    </div>
                </PurchaseDocumentDetail>
            </div>
        </CompanyLayout>
    );
}
