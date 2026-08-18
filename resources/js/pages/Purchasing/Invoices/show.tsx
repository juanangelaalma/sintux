import { Head, Link, useForm } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    qty: number;
    unit_price: number;
    line_total: number;
};

type PurchaseInvoice = {
    id: number;
    number: string;
    status: string;
    invoice_date: string;
    due_date?: string;
    note?: string;
    subtotal: number;
    tax_amount: number;
    total: number;
    items: Item[];
};

type Props = {
    purchaseInvoice: PurchaseInvoice;
};

export default function PurchaseInvoicesShow({ purchaseInvoice }: Props) {
    const { post: postApprove, processing: approving } = useForm({});

    const handleApprove = () => {
        if (confirm('Setujui Faktur Pembelian ini (3-way match validation)?')) {
            postApprove(`/purchasing/invoices/${purchaseInvoice.id}/approve`);
        }
    };

    return (
        <CompanyLayout>
            <Head title={`Faktur #${purchaseInvoice.number}`} />
            <div className="space-y-6 max-w-5xl">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian / Detail Faktur</p>
                        <h1 className="text-2xl font-bold text-slate-900">Purchase Invoice #{purchaseInvoice.number}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/purchasing/invoices">
                            <Button variant="secondary">Kembali</Button>
                        </Link>
                        {purchaseInvoice.status === 'draft' && (
                            <Button variant="primary" onClick={handleApprove} disabled={approving}>
                                Setujui Faktur (3-Way Match)
                            </Button>
                        )}
                    </div>
                </div>

                <div className="bg-white p-8 rounded-xl border border-slate-200 shadow-sm space-y-6">
                    <div className="flex justify-between items-start border-b border-slate-100 pb-6">
                        <div>
                            <span className="text-xs uppercase font-semibold text-slate-400">Status Faktur</span>
                            <div className="mt-1">
                                <span className="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-700/10 uppercase">
                                    {purchaseInvoice.status}
                                </span>
                            </div>
                        </div>
                        <div className="text-right space-y-1 text-sm">
                            <p className="text-slate-500">
                                Tgl Faktur: <span className="font-semibold text-slate-900">{purchaseInvoice.invoice_date}</span>
                            </p>
                            <p className="text-slate-500">
                                Jatuh Tempo: <span className="font-semibold text-slate-900">{purchaseInvoice.due_date || '-'}</span>
                            </p>
                        </div>
                    </div>

                    {purchaseInvoice.note && (
                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 text-sm">
                            <p className="text-xs font-bold text-slate-700 uppercase">Catatan</p>
                            <p className="text-slate-600 mt-1">{purchaseInvoice.note}</p>
                        </div>
                    )}

                    <div>
                        <h3 className="text-sm font-bold text-slate-900 mb-3">Item Tagihan</h3>
                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            <table className="w-full text-sm text-left text-slate-600">
                                <thead className="text-xs uppercase bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Produk</th>
                                        <th className="py-3 px-4">SKU</th>
                                        <th className="py-3 px-4 text-right">Qty</th>
                                        <th className="py-3 px-4 text-right">Harga Satuan</th>
                                        <th className="py-3 px-4 text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {purchaseInvoice.items.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/50">
                                            <td className="py-3 px-4 font-semibold text-slate-900">{item.product_name}</td>
                                            <td className="py-3 px-4 text-xs font-mono text-slate-500">{item.sku}</td>
                                            <td className="py-3 px-4 text-right font-medium">{item.qty}</td>
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
                                <span>Rp{Number(purchaseInvoice.subtotal).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex justify-between font-bold text-slate-900 border-t border-slate-200 pt-2 text-lg">
                                <span>Total Tagihan</span>
                                <span>Rp{Number(purchaseInvoice.total).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CompanyLayout>
    );
}