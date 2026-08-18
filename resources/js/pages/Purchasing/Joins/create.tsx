import { Head, useForm, Link } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

type Branch = { id: number; name: string; code: string };

type ItemRow = {
    purchase_invoice_id: number;
    supplier_id: number;
    invoice_number: string;
    supplier_name: string;
    invoice_total: number;
};

type Props = {
    branches: Branch[];
};

export default function JoinPurchaseInvoicesCreate({ branches }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        join_date: string;
        note: string;
        items: ItemRow[];
    }>({
        branch_id: branches[0]?.id ?? '',
        join_date: new Date().toISOString().split('T')[0],
        note: '',
        items: [
            {
                purchase_invoice_id: 1,
                supplier_id: 1,
                invoice_number: 'INV-001',
                supplier_name: 'PT. Behaestex',
                invoice_total: 500000,
            },
        ],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            {
                purchase_invoice_id: 2,
                supplier_id: 1,
                invoice_number: 'INV-002',
                supplier_name: 'PT. Behaestex',
                invoice_total: 300000,
            },
        ]);
    };

    const removeItem = (index: number) => {
        if (data.items.length === 1) {
            return;
        }

        const next = [...data.items];
        next.splice(index, 1);
        setData('items', next);
    };

    const totalAmount = data.items.reduce((sum, item) => sum + Number(item.invoice_total || 0), 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/joins');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Tukar Faktur" />
            <div className="space-y-6">
                {/* Top Bar Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian</p>
                        <h1 className="text-2xl font-bold text-slate-900">Buat Tukar Faktur (Join Invoice)</h1>
                    </div>
                    <div className="text-right">
                        <p className="text-xs text-slate-500 font-medium">Total Konsolidasi</p>
                        <p className="text-2xl font-black text-slate-900">
                            Rp{totalAmount.toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    {/* Header Fields Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-slate-700">Cabang *</label>
                            <select
                                value={data.branch_id}
                                onChange={(e) => setData('branch_id', Number(e.target.value))}
                                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                {branches.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name} ({b.code})
                                    </option>
                                ))}
                            </select>
                            {errors.branch_id && <p className="text-xs text-rose-600 mt-1">{errors.branch_id}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700">Tgl. Tukar Faktur *</label>
                            <input
                                type="date"
                                value={data.join_date}
                                onChange={(e) => setData('join_date', e.target.value)}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.join_date && <p className="text-xs text-rose-600 mt-1">{errors.join_date}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700">No Transaksi ⚙</label>
                            <input
                                type="text"
                                placeholder="[Auto]"
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700">Catatan Konsolidasi</label>
                        <textarea
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            rows={2}
                            placeholder="Catatan tukar faktur"
                            className="mt-1 block w-full rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    {/* Table Item Section */}
                    <div className="border border-slate-200 rounded-lg overflow-hidden">
                        <table className="w-full text-left text-sm text-slate-600">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-700 border-b border-slate-200">
                                <tr>
                                    <th className="py-2.5 px-3">No. Faktur</th>
                                    <th className="py-2.5 px-3">Supplier</th>
                                    <th className="py-2.5 px-3 text-right">Nilai Faktur</th>
                                    <th className="py-2.5 px-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {data.items.map((row, idx) => (
                                    <tr key={idx} className="hover:bg-slate-50/50">
                                        <td className="p-2 font-semibold text-xs text-slate-900">{row.invoice_number}</td>
                                        <td className="p-2 text-xs text-slate-600">{row.supplier_name}</td>
                                        <td className="p-2 text-right font-medium text-xs text-slate-900">
                                            Rp{Number(row.invoice_total || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                                        </td>
                                        <td className="p-2 text-center">
                                            {data.items.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() => removeItem(idx)}
                                                    className="text-slate-400 hover:text-rose-600"
                                                >
                                                    ✕
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="p-3 bg-slate-50 border-t border-slate-200">
                            <Button type="button" variant="secondary" onClick={addItem} className="text-xs">
                                + Tambah Faktur
                            </Button>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <Link href="/purchasing/joins">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" variant="primary" disabled={processing}>
                            Simpan Tukar Faktur
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}