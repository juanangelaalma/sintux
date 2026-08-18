import { Head, Link, useForm } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    qty_received: number;
};

type GoodsReceipt = {
    id: number;
    number: string;
    purchase_order_id: number;
    warehouse_id: number;
    status: string;
    receipt_date: string;
    note?: string;
    items: Item[];
};

type Props = {
    goodsReceipt: GoodsReceipt;
};

export default function GoodsReceiptsShow({ goodsReceipt }: Props) {
    const { post: postPost, processing: posting } = useForm({});

    const handlePost = () => {
        if (confirm('POST Penerimaan Barang ini? Stok akan dimasukkan ke gudang dan status PO diperbarui.')) {
            postPost(`/purchasing/grns/${goodsReceipt.id}/post`);
        }
    };

    return (
        <CompanyLayout>
            <Head title={`GRN #${goodsReceipt.number}`} />
            <div className="space-y-6 max-w-5xl">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian / Detail Penerimaan</p>
                        <h1 className="text-2xl font-bold text-slate-900">Goods Receipt #{goodsReceipt.number}</h1>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/purchasing/grns">
                            <Button variant="secondary">Kembali</Button>
                        </Link>
                        {goodsReceipt.status === 'draft' && (
                            <Button variant="primary" onClick={handlePost} disabled={posting}>
                                Posting Stok ke Gudang
                            </Button>
                        )}
                    </div>
                </div>

                <div className="bg-white p-8 rounded-xl border border-slate-200 shadow-sm space-y-6">
                    <div className="flex justify-between items-start border-b border-slate-100 pb-6">
                        <div>
                            <span className="text-xs uppercase font-semibold text-slate-400">Status GRN</span>
                            <div className="mt-1">
                                <span className="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-700/10 uppercase">
                                    {goodsReceipt.status}
                                </span>
                            </div>
                        </div>
                        <div className="text-right space-y-1 text-sm">
                            <p className="text-slate-500">
                                Tgl Terima: <span className="font-semibold text-slate-900">{goodsReceipt.receipt_date}</span>
                            </p>
                            <p className="text-slate-500">
                                ID PO: <span className="font-semibold text-slate-900">#{goodsReceipt.purchase_order_id}</span>
                            </p>
                        </div>
                    </div>

                    {goodsReceipt.note && (
                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 text-sm">
                            <p className="text-xs font-bold text-slate-700 uppercase">Catatan</p>
                            <p className="text-slate-600 mt-1">{goodsReceipt.note}</p>
                        </div>
                    )}

                    <div>
                        <h3 className="text-sm font-bold text-slate-900 mb-3">Item Diterima</h3>
                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            <table className="w-full text-sm text-left text-slate-600">
                                <thead className="text-xs uppercase bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                                    <tr>
                                        <th className="py-3 px-4">Produk</th>
                                        <th className="py-3 px-4">SKU</th>
                                        <th className="py-3 px-4 text-right">Qty Diterima</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {goodsReceipt.items.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/50">
                                            <td className="py-3 px-4 font-semibold text-slate-900">{item.product_name}</td>
                                            <td className="py-3 px-4 text-xs font-mono text-slate-500">{item.sku}</td>
                                            <td className="py-3 px-4 text-right font-bold text-slate-900">{item.qty_received}</td>
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