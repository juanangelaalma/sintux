import { Head, useForm, Link } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

type Branch = { id: number; name: string; code: string };
type Supplier = { id: number; name: string };
type Variant = { id: number; product_name: string; sku: string; uom_name?: string };

type ItemRow = {
    product_variant_id: number;
    description?: string;
    qty_requested: number;
    unit_price?: number;
};

type Props = {
    branches: Branch[];
    suppliers: Supplier[];
    productVariants: Variant[];
};

export default function PurchaseRequestsCreate({ branches, suppliers, productVariants }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        request_date: string;
        expected_date: string;
        note: string;
        items: ItemRow[];
    }>({
        branch_id: branches[0]?.id ?? '',
        supplier_id: '',
        request_date: new Date().toISOString().split('T')[0],
        expected_date: '',
        note: '',
        items: [{ product_variant_id: productVariants[0]?.id ?? 0, description: '', qty_requested: 1, unit_price: 0 }],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { product_variant_id: productVariants[0]?.id ?? 0, description: '', qty_requested: 1, unit_price: 0 },
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

    const updateItem = (index: number, field: keyof ItemRow, value: string | number) => {
        const next = [...data.items];
        next[index] = { ...next[index], [field]: value };
        setData('items', next);
    };

    const calculateSubtotal = () => {
        return data.items.reduce((sum, item) => sum + Number(item.qty_requested || 0) * Number(item.unit_price || 0), 0);
    };

    const subtotal = calculateSubtotal();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/requests');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Permintaan Pembelian" />
            <div className="space-y-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian</p>
                        <h1 className="text-2xl font-bold text-slate-900">Buat Permintaan Pembelian</h1>
                    </div>
                    <div className="flex items-center gap-4">
                        <select className="rounded-lg border-slate-300 py-1.5 px-3 text-sm font-medium text-slate-700 bg-white">
                            <option value="pr">Permintaan Pembelian</option>
                            <option value="quote">Penawaran Harga</option>
                            <option value="po">Pesanan Pembelian</option>
                        </select>
                        <div className="text-right">
                            <p className="text-xs text-slate-500 font-medium">Estimasi Total</p>
                            <p className="text-2xl font-black text-slate-900">
                                Rp{subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
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
                            <label className="block text-xs font-semibold text-slate-700">Pemasok (Opsional)</label>
                            <select
                                value={data.supplier_id}
                                onChange={(e) => setData('supplier_id', e.target.value ? Number(e.target.value) : '')}
                                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="">Pilih supplier</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700">Tgl. Permintaan *</label>
                            <input
                                type="date"
                                value={data.request_date}
                                onChange={(e) => setData('request_date', e.target.value)}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.request_date && <p className="text-xs text-rose-600 mt-1">{errors.request_date}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700">Catatan / Alasan Permintaan</label>
                        <textarea
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            rows={2}
                            placeholder="Alasan pengajuan stok"
                            className="mt-1 block w-full rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div className="border border-slate-200 rounded-lg overflow-hidden">
                        <table className="w-full text-left text-sm text-slate-600">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-700 border-b border-slate-200">
                                <tr>
                                    <th className="py-2.5 px-3 w-1/3">Produk</th>
                                    <th className="py-2.5 px-3">Deskripsi</th>
                                    <th className="py-2.5 px-3 w-28">Kuantitas</th>
                                    <th className="py-2.5 px-3 w-20">Unit</th>
                                    <th className="py-2.5 px-3 w-36">Est. Harga Satuan</th>
                                    <th className="py-2.5 px-3 w-36 text-right">Estimasi Jumlah</th>
                                    <th className="py-2.5 px-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {data.items.map((row, idx) => {
                                    const selectedVariant = productVariants.find((v) => v.id === row.product_variant_id);
                                    const lineTotal = Number(row.qty_requested || 0) * Number(row.unit_price || 0);

                                    return (
                                        <tr key={idx} className="hover:bg-slate-50/50">
                                            <td className="p-2">
                                                <select
                                                    value={row.product_variant_id}
                                                    onChange={(e) => updateItem(idx, 'product_variant_id', Number(e.target.value))}
                                                    className="w-full rounded-md border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                                >
                                                    {productVariants.map((v) => (
                                                        <option key={v.id} value={v.id}>
                                                            {v.product_name} ({v.sku})
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    type="text"
                                                    value={row.description || ''}
                                                    onChange={(e) => updateItem(idx, 'description', e.target.value)}
                                                    placeholder="Spesifikasi / catatan"
                                                    className="w-full rounded-md border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={row.qty_requested}
                                                    onChange={(e) => updateItem(idx, 'qty_requested', Number(e.target.value))}
                                                    className="w-full rounded-md border-slate-300 text-xs text-right focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-2 text-xs text-slate-500">{selectedVariant?.uom_name || 'PCS'}</td>
                                            <td className="p-2">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={row.unit_price || ''}
                                                    onChange={(e) => updateItem(idx, 'unit_price', Number(e.target.value))}
                                                    className="w-full rounded-md border-slate-300 text-xs text-right focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-2 text-right font-medium text-xs text-slate-900">
                                                Rp{lineTotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })}
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
                                    );
                                })}
                            </tbody>
                        </table>
                        <div className="p-3 bg-slate-50 border-t border-slate-200">
                            <Button type="button" variant="secondary" onClick={addItem} className="text-xs">
                                + Tambah Data
                            </Button>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <Link href="/purchasing/requests">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" variant="primary" disabled={processing}>
                            Simpan Purchase Request
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}