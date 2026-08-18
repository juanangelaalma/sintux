import { Head, Link, useForm } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    qty_ordered: number;
    qty_received: number;
    unit_price: number;
    line_total: number;
};

type PurchaseOrder = {
    id: number;
    number: string;
    status: string;
    order_date: string;
    expected_date?: string;
    note?: string;
    subtotal: number;
    tax_amount: number;
    total: number;
    items: Item[];
};

type Props = {
    purchaseOrder: PurchaseOrder;
};

export default function PurchaseOrdersShow({ purchaseOrder }: Props) {
    const { post: postApprove, processing: approving } = useForm({});
    const { post: postSend, processing: sending } = useForm({});
    const { post: postCancel, processing: cancelling } = useForm({});

    const handleApprove = () => {
        if (confirm('Setujui Purchase Order ini?')) {
            postApprove(`/purchasing/orders/${purchaseOrder.id}/approve`);
        }
    };

    const handleSend = () => {
        if (confirm('Tandai PO ini sebagai Dikirim ke pemasok?')) {
            postSend(`/purchasing/orders/${purchaseOrder.id}/send`);
        }
    };

    const handleCancel = () => {
        if (confirm('Batalkan Purchase Order ini?')) {
            postCancel(`/purchasing/orders/${purchaseOrder.id}/cancel`);
        }
    };

    return (
        <CompanyLayout>
            <Head title={`PO #${purchaseOrder.number}`} />
            <div className="space-y-6 max-w-5xl">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian / Detail PO</p>
                        <h1 className="text-2xl font-bold text-slate-900">Purchase Order #{purchaseOrder.number}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/purchasing/orders">
                            <Button variant="secondary">Kembali</Button>
                        </Link>
                        {purchaseOrder.status === 'pending' && (
                            <>
                                <Button variant="primary" onClick={handleApprove} disabled={approving}>
                                    Setujui PO
                                </Button>
                                <Button variant="danger" onClick={handleCancel} disabled={cancelling}>
                                    Batalkan
                                </Button>
                            </>
                        )}
                        {purchaseOrder.status === 'approved' && (
                            <>
                                <Button variant="primary" onClick={handleSend} disabled={sending}>
                                    Kirim ke Supplier
                                </Button>
                                <Button variant="danger" onClick={handleCancel} disabled={cancelling}>
                                    Batalkan
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="bg-white p-8 rounded-xl border border-slate-200 shadow-sm space-y-6">
                    <div className="flex justify-between items-start border-b border-slate-100 pb-6">
                        <div>
                            <span className="text-xs uppercase font-semibold text-slate-400">Status Dokumen</span>
                            <div className="mt-1">
                                <span className="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-700/10 uppercase">
                                    {purchaseOrder.status}
                                </span>
                            </div>
                        </div>
                        <div className="text-right space-y-1 text-sm">
                            <p className="text-slate-500">
                                Tanggal Order: <span className="font-semibold text-slate-900">{purchaseOrder.order_date}</span>
                            </p>
                            <p className="text-slate-500">
                                Tanggal Jatuh Tempo: <span className="font-semibold text-slate-900">{purchaseOrder.expected_date || '-'}</span>
                            </p>
                        </div>
                    </div>

                    {purchaseOrder.note && (
                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 text-sm">
                            <p className="text-xs font-bold text-slate-700 uppercase">Catatan</p>
                            <p className="text-slate-600 mt-1">{purchaseOrder.note}</p>
                        </div>
                    )}

                    <div>
                        <h3 className="text-sm font-bold text-slate-900 mb-3">Item Pesanan</h3>
                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            <table className="w-full text-sm text-left text-slate-600">
                                <thead className="text-xs uppercase bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Produk</th>
                                        <th className="py-3 px-4">SKU</th>
                                        <th className="py-3 px-4 text-right">Dipesan</th>
                                        <th className="py-3 px-4 text-right">Diterima</th>
                                        <th className="py-3 px-4 text-right">Harga Satuan</th>
                                        <th className="py-3 px-4 text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {purchaseOrder.items.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/50">
                                            <td className="py-3 px-4 font-semibold text-slate-900">{item.product_name}</td>
                                            <td className="py-3 px-4 text-xs font-mono text-slate-500">{item.sku}</td>
                                            <td className="py-3 px-4 text-right font-medium">{item.qty_ordered}</td>
                                            <td className="py-3 px-4 text-right font-medium">{item.qty_received}</td>
                                            <td className="py-3 px-4 text-right">
                                                Rp{Number(item.unit_price).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                                            </td>
                                            <td className="py-3 px-4 text-right font-bold text-slate-900">
                                                Rp{Number(item.line_total).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="flex justify-end pt-4 border-t border-slate-100">
                        <div className="w-72 space-y-2 text-sm">
                            <div className="flex justify-between text-slate-600">
                                <span>Subtotal</span>
                                <span>Rp{Number(purchaseOrder.subtotal).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex justify-between font-bold text-slate-900 border-t border-slate-200 pt-2 text-lg">
                                <span>Total</span>
                                <span>Rp{Number(purchaseOrder.total).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CompanyLayout>
    );
}