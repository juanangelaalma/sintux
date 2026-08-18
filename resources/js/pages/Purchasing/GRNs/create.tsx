import { Head, useForm, Link } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

type Branch = { id: number; name: string; code: string };
type Warehouse = { id: number; name: string };
type Supplier = { id: number; name: string };
type Variant = { id: number; product_name: string; sku: string; uom_name?: string };

type ItemRow = {
    purchase_order_item_id?: number;
    product_variant_id: number;
    description?: string;
    qty_received: number;
};

type Props = {
    branches: Branch[];
    suppliers: Supplier[];
    productVariants: Variant[];
    warehouses?: Warehouse[];
};

export default function GoodsReceiptsCreate({ branches, suppliers, productVariants }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        purchase_order_id: number | string;
        warehouse_id: number | string;
        receipt_date: string;
        note: string;
        items: ItemRow[];
    }>({
        branch_id: branches[0]?.id ?? '',
        supplier_id: suppliers[0]?.id ?? '',
        purchase_order_id: '',
        warehouse_id: '',
        receipt_date: new Date().toISOString().split('T')[0],
        note: '',
        items: [{ product_variant_id: productVariants[0]?.id ?? 0, description: '', qty_received: 1 }],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { product_variant_id: productVariants[0]?.id ?? 0, description: '', qty_received: 1 },
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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/grns');
    };

    return (
        <CompanyLayout>
            <Head title="Input Penerimaan Barang" />
            <div className="space-y-6">
                {/* Top Bar Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian</p>
                        <h1 className="text-2xl font-bold text-slate-900">Input Penerimaan Barang (GRN)</h1>
                    </div>
                    <div className="flex items-center gap-4">
                        <select className="rounded-lg border-slate-300 py-1.5 px-3 text-sm font-medium text-slate-700 bg-white">
                            <option value="grn">Penerimaan Barang (GRN)</option>
                            <option value="po">Pesanan Pembelian</option>
                        </select>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    {/* Header Fields Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-slate-700">Supplier *</label>
                            <select
                                value={data.supplier_id}
                                onChange={(e) => setData('supplier_id', Number(e.target.value))}
                                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="">Pilih supplier</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                            {errors.supplier_id && <p className="text-xs text-rose-600 mt-1">{errors.supplier_id}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700">ID Purchase Order *</label>
                            <input
                                type="number"
                                value={data.purchase_order_id}
                                onChange={(e) => setData('purchase_order_id', Number(e.target.value))}
                                placeholder="ID PO"
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.purchase_order_id && <p className="text-xs text-rose-600 mt-1">{errors.purchase_order_id}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700">Tgl. Penerimaan *</label>
                            <input
                                type="date"
                                value={data.receipt_date}
                                onChange={(e) => setData('receipt_date', e.target.value)}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.receipt_date && <p className="text-xs text-rose-600 mt-1">{errors.receipt_date}</p>}
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">No Transaksi ⚙</label>
                                <input
                                    type="text"
                                    placeholder="[Auto]"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Cabang</label>
                                <select
                                    value={data.branch_id}
                                    onChange={(e) => setData('branch_id', Number(e.target.value))}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {branches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.name} ({b.code})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Gudang Tujuan *</label>
                                <input
                                    type="number"
                                    value={data.warehouse_id}
                                    onChange={(e) => setData('warehouse_id', Number(e.target.value))}
                                    placeholder="ID Warehouse"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                {errors.warehouse_id && <p className="text-xs text-rose-600 mt-1">{errors.warehouse_id}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Table Item Section */}
                    <div className="border border-slate-200 rounded-lg overflow-hidden">
                        <table className="w-full text-left text-sm text-slate-600">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-700 border-b border-slate-200">
                                <tr>
                                    <th className="py-2.5 px-3 w-1/3">Produk Diterima</th>
                                    <th className="py-2.5 px-3">Deskripsi</th>
                                    <th className="py-2.5 px-3 w-36">Kuantitas Diterima</th>
                                    <th className="py-2.5 px-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {data.items.map((row, idx) => (
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
                                                placeholder="Deskripsi item"
                                                className="w-full rounded-md border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                            />
                                        </td>
                                        <td className="p-2">
                                            <input
                                                type="number"
                                                min="1"
                                                value={row.qty_received}
                                                onChange={(e) => updateItem(idx, 'qty_received', Number(e.target.value))}
                                                className="w-full rounded-md border-slate-300 text-xs text-right focus:border-indigo-500 focus:ring-indigo-500"
                                            />
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
                                + Tambah Data
                            </Button>
                        </div>
                    </div>

                    {/* Footer Details Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-200">
                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Catatan Penerimaan</label>
                                <textarea
                                    value={data.note}
                                    onChange={(e) => setData('note', e.target.value)}
                                    rows={2}
                                    placeholder="Catatan kondisi fisik barang"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <Link href="/purchasing/grns">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" variant="primary" disabled={processing}>
                            Simpan Penerimaan Barang
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}