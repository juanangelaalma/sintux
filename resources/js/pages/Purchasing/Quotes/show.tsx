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

type PurchaseQuote = {
    id: number;
    number: string;
    status: string;
    quote_date: string;
    valid_until?: string;
    note?: string;
    subtotal: number;
    tax_amount: number;
    total: number;
    items: Item[];
};

type Props = {
    purchaseQuote: PurchaseQuote;
};

export default function PurchaseQuotesShow({ purchaseQuote }: Props) {
    const { post: postSend, processing: sending } = useForm({});
    const { post: postAccept, processing: accepting } = useForm({});
    const { post: postCancel, processing: cancelling } = useForm({});

    const handleSend = () => {
        if (confirm('Tandai penawaran ini sebagai Dikirim ke pemasok?')) {
            postSend(`/purchasing/quotes/${purchaseQuote.id}/send`);
        }
    };

    const handleAccept = () => {
        if (confirm('Terima penawaran harga ini?')) {
            postAccept(`/purchasing/quotes/${purchaseQuote.id}/accept`);
        }
    };

    const handleCancel = () => {
        if (confirm('Batalkan penawaran harga ini?')) {
            postCancel(`/purchasing/quotes/${purchaseQuote.id}/cancel`);
        }
    };

    return (
        <CompanyLayout>
            <Head title={`Quote #${purchaseQuote.number}`} />
            <div className="space-y-6 max-w-5xl">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian / Detail Penawaran</p>
                        <h1 className="text-2xl font-bold text-slate-900">Purchase Quote #{purchaseQuote.number}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/purchasing/quotes">
                            <Button variant="secondary">Kembali</Button>
                        </Link>
                        {purchaseQuote.status === 'draft' && (
                            <>
                                <Button variant="primary" onClick={handleSend} disabled={sending}>
                                    Kirim Penawaran
                                </Button>
                                <Button variant="danger" onClick={handleCancel} disabled={cancelling}>
                                    Batalkan
                                </Button>
                            </>
                        )}
                        {purchaseQuote.status === 'sent' && (
                            <>
                                <Button variant="primary" onClick={handleAccept} disabled={accepting}>
                                    Terima Penawaran
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
                            <span className="text-xs uppercase font-semibold text-slate-400">Status Penawaran</span>
                            <div className="mt-1">
                                <span className="inline-flex items-center rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700 ring-1 ring-inset ring-sky-700/10 uppercase">
                                    {purchaseQuote.status}
                                </span>
                            </div>
                        </div>
                        <div className="text-right space-y-1 text-sm">
                            <p className="text-slate-500">
                                Tgl Penawaran: <span className="font-semibold text-slate-900">{purchaseQuote.quote_date}</span>
                            </p>
                            <p className="text-slate-500">
                                Berlaku Hingga: <span className="font-semibold text-slate-900">{purchaseQuote.valid_until || '-'}</span>
                            </p>
                        </div>
                    </div>

                    {purchaseQuote.note && (
                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 text-sm">
                            <p className="text-xs font-bold text-slate-700 uppercase">Catatan</p>
                            <p className="text-slate-600 mt-1">{purchaseQuote.note}</p>
                        </div>
                    )}

                    <div>
                        <h3 className="text-sm font-bold text-slate-900 mb-3">Item Penawaran</h3>
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
                                    {purchaseQuote.items.map((item) => (
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
                                <span>Rp{Number(purchaseQuote.subtotal).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex justify-between font-bold text-slate-900 border-t border-slate-200 pt-2 text-lg">
                                <span>Total Penawaran</span>
                                <span>Rp{Number(purchaseQuote.total).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CompanyLayout>
    );
}