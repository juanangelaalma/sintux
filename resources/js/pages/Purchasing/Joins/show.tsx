import { Head, Link, useForm } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

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

export default function JoinPurchaseInvoicesShow({ joinPurchaseInvoice }: Props) {
    const { post: postReady, processing: markingReady } = useForm({});

    const handleReady = () => {
        if (confirm('Tandai Tukar Faktur ini sebagai SIAP (ready) untuk pembayaran?')) {
            postReady(`/purchasing/joins/${joinPurchaseInvoice.id}/ready`);
        }
    };

    return (
        <CompanyLayout>
            <Head title={`Tukar Faktur #${joinPurchaseInvoice.number}`} />
            <div className="space-y-6 max-w-5xl">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian / Detail Tukar Faktur</p>
                        <h1 className="text-2xl font-bold text-slate-900">Join Invoice #{joinPurchaseInvoice.number}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/purchasing/joins">
                            <Button variant="secondary">Kembali</Button>
                        </Link>
                        {joinPurchaseInvoice.status === 'draft' && (
                            <Button variant="primary" onClick={handleReady} disabled={markingReady}>
                                Tandai Siap (Ready)
                            </Button>
                        )}
                    </div>
                </div>

                <div className="bg-white p-8 rounded-xl border border-slate-200 shadow-sm space-y-6">
                    <div className="flex justify-between items-start border-b border-slate-100 pb-6">
                        <div>
                            <span className="text-xs uppercase font-semibold text-slate-400">Status Konsolidasi</span>
                            <div className="mt-1">
                                <span className="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-700/10 uppercase">
                                    {joinPurchaseInvoice.status}
                                </span>
                            </div>
                        </div>
                        <div className="text-right space-y-1 text-sm">
                            <p className="text-slate-500">
                                Tgl Konsolidasi: <span className="font-semibold text-slate-900">{joinPurchaseInvoice.join_date}</span>
                            </p>
                        </div>
                    </div>

                    {joinPurchaseInvoice.note && (
                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 text-sm">
                            <p className="text-xs font-bold text-slate-700 uppercase">Catatan</p>
                            <p className="text-slate-600 mt-1">{joinPurchaseInvoice.note}</p>
                        </div>
                    )}

                    <div>
                        <h3 className="text-sm font-bold text-slate-900 mb-3">Faktur Tergabung</h3>
                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            <table className="w-full text-sm text-left text-slate-600">
                                <thead className="text-xs uppercase bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">No. Faktur</th>
                                        <th className="py-3 px-4">Supplier</th>
                                        <th className="py-3 px-4 text-right">Nilai Faktur</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {joinPurchaseInvoice.items.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/50">
                                            <td className="py-3 px-4 font-semibold text-slate-900">{item.invoice_number}</td>
                                            <td className="py-3 px-4 text-slate-600">{item.supplier_name}</td>
                                            <td className="py-3 px-4 text-right font-bold text-slate-900">
                                                Rp{Number(item.invoice_total).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="flex justify-end pt-4 border-t border-slate-100">
                        <div className="w-72 space-y-2 text-sm">
                            <div className="flex justify-between font-bold text-slate-900 text-lg">
                                <span>Total Konsolidasi</span>
                                <span>Rp{Number(joinPurchaseInvoice.total_amount).toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CompanyLayout>
    );
}