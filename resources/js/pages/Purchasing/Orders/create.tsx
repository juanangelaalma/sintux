import { Head, useForm, Link } from '@inertiajs/react';
import Button from '@/components/ui/button';
import CompanyLayout from '@/layouts/company/company-layout';

type Branch = { id: number; name: string; code: string };
type Warehouse = { id: number; name: string };
type Supplier = { id: number; name: string };
type Variant = { id: number; product_name: string; sku: string; uom_name?: string };

type ItemRow = {
    product_variant_id: number;
    description?: string;
    qty_ordered: number;
    unit_price: number;
};

type Props = {
    branches: Branch[];
    suppliers: Supplier[];
    productVariants: Variant[];
    // NOTE: opsional dulu — belum dikirim dari backend, ditambahkan agar field
    // "Gudang" (terpisah dari Cabang) punya sumber data saat mulai di-wire.
    warehouses?: Warehouse[];
};

export default function PurchaseOrdersCreate({ branches, suppliers, productVariants, warehouses = [] }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        source_request_id?: number | string;
        source_quote_id?: number | string;
        order_date: string;
        expected_date: string;
        note: string;
        items: ItemRow[];
    }>({
        branch_id: branches[0]?.id ?? '',
        supplier_id: suppliers[0]?.id ?? '',
        source_request_id: '',
        source_quote_id: '',
        order_date: new Date().toISOString().split('T')[0],
        expected_date: '',
        note: '',
        items: [{ product_variant_id: productVariants[0]?.id ?? 0, description: '', qty_ordered: 1, unit_price: 0 }],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { product_variant_id: productVariants[0]?.id ?? 0, description: '', qty_ordered: 1, unit_price: 0 },
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
        return data.items.reduce((sum, item) => sum + Number(item.qty_ordered || 0) * Number(item.unit_price || 0), 0);
    };

    const subtotal = calculateSubtotal();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/orders');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Pesanan Pembelian" />
            <div className="space-y-6">
                {/* Top Bar Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
                    <div>
                        <p className="text-xs text-indigo-600 font-semibold uppercase tracking-wider">Pembelian</p>
                        <h1 className="text-2xl font-bold text-slate-900">Buat Pesanan Pembelian</h1>
                    </div>
                    <div className="flex items-center gap-4">
                        <select className="rounded-lg border-slate-300 py-1.5 px-3 text-sm font-medium text-slate-700 bg-white">
                            <option value="po">Pesanan Pembelian</option>
                            <option value="pr">Permintaan Pembelian</option>
                            <option value="quote">Penawaran Harga</option>
                            <option value="inv">Faktur Pembelian</option>
                        </select>
                        <div className="text-right">
                            <p className="text-xs text-slate-500 font-medium">Total</p>
                            <p className="text-2xl font-black text-slate-900">
                                Rp{subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                            </p>
                        </div>
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
                            <label className="block text-xs font-semibold text-slate-700">Email</label>
                            <input
                                type="email"
                                placeholder="e.g. john@example.com"
                                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div className="flex items-center pt-6">
                            <label className="inline-flex items-center text-xs font-medium text-slate-600 cursor-pointer">
                                <input type="checkbox" className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                                <span className="ml-2">Info pengiriman</span>
                            </label>
                        </div>

                        <div className="md:col-span-2">
                            <label className="block text-xs font-semibold text-slate-700">Alamat Supplier</label>
                            <textarea
                                rows={2}
                                placeholder="Terisi otomatis setelah supplier dipilih"
                                className="mt-1 block w-full rounded-lg border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Tgl. transaksi *</label>
                                <input
                                    type="date"
                                    value={data.order_date}
                                    onChange={(e) => setData('order_date', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Tgl. jatuh tempo</label>
                                <input
                                    type="date"
                                    value={data.expected_date}
                                    onChange={(e) => setData('expected_date', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Syarat pembayaran</label>
                                <select className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="net30">Net 30</option>
                                    <option value="cod">COD</option>
                                    <option value="net15">Net 15</option>
                                </select>
                            </div>
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
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Nomor referensi supplier</label>
                                <input
                                    type="text"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Tag</label>
                                <input
                                    type="text"
                                    placeholder="Pilih tag"
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
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Gudang</label>
                                <select className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Pilih gudang</option>
                                    {warehouses.map((w) => (
                                        <option key={w.id} value={w.id}>
                                            {w.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Mata Uang</label>
                                <select className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="IDR">IDR - Rupiah</option>
                                    <option value="USD">USD - US Dollar</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end pt-2">
                        <label className="inline-flex items-center text-xs font-medium text-slate-600 cursor-pointer">
                            <input type="checkbox" className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            <span className="ml-2">Harga termasuk pajak</span>
                        </label>
                    </div>

                    {/* Table Item Section */}
                    <div className="border border-slate-200 rounded-lg overflow-hidden">
                        <table className="w-full text-left text-sm text-slate-600">
                            <thead className="bg-slate-50 text-xs font-semibold uppercase text-slate-700 border-b border-slate-200">
                                <tr>
                                    <th className="py-2.5 px-3 w-1/4">Produk</th>
                                    <th className="py-2.5 px-3">Deskripsi</th>
                                    <th className="py-2.5 px-3 w-24">Kuantitas</th>
                                    <th className="py-2.5 px-3 w-20">Unit</th>
                                    <th className="py-2.5 px-3 w-36">Harga satuan</th>
                                    <th className="py-2.5 px-3 w-28">Diskon</th>
                                    <th className="py-2.5 px-3 w-28">Pajak</th>
                                    <th className="py-2.5 px-3 w-36 text-right">Jumlah</th>
                                    <th className="py-2.5 px-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {data.items.map((row, idx) => {
                                    const selectedVariant = productVariants.find((v) => v.id === row.product_variant_id);
                                    const lineTotal = Number(row.qty_ordered || 0) * Number(row.unit_price || 0);

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
                                                    placeholder="Deskripsi item"
                                                    className="w-full rounded-md border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={row.qty_ordered}
                                                    onChange={(e) => updateItem(idx, 'qty_ordered', Number(e.target.value))}
                                                    className="w-full rounded-md border-slate-300 text-xs text-right focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-2 text-xs text-slate-500">{selectedVariant?.uom_name || 'PCS'}</td>
                                            <td className="p-2">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    value={row.unit_price}
                                                    onChange={(e) => updateItem(idx, 'unit_price', Number(e.target.value))}
                                                    className="w-full rounded-md border-slate-300 text-xs text-right focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    type="text"
                                                    placeholder="mis. 10% atau 50+20"
                                                    className="w-full rounded-md border-slate-300 text-xs text-right focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-2">
                                                <select className="w-full rounded-md border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">Tanpa Pajak</option>
                                                    <option value="ppn11">PPN 11%</option>
                                                </select>
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

                    {/* Footer Details Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-200">
                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Pesan</label>
                                <textarea
                                    value={data.note}
                                    onChange={(e) => setData('note', e.target.value)}
                                    rows={2}
                                    placeholder="Pesan untuk supplier"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Memo</label>
                                <textarea
                                    rows={2}
                                    placeholder="Memo internal"
                                    className="mt-1 block w-full rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-700">Lampiran</label>
                                <div className="mt-1 flex justify-center rounded-lg border border-dashed border-slate-300 px-6 py-4">
                                    <div className="text-center">
                                        <Button type="button" variant="secondary" className="text-xs">
                                            Choose file
                                        </Button>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            atau tarik & lepas file di sini (max 10 MB)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Summary Totals Right */}
                        <div className="space-y-3 rounded-xl bg-slate-50 p-4 border border-slate-200 text-sm">
                            <div className="flex justify-between text-slate-600">
                                <span>Subtotal</span>
                                <span>Rp{subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex items-center justify-between text-slate-600 border-t border-slate-200 pt-2">
                                <label htmlFor="down_payment">Uang Muka</label>
                                <input
                                    id="down_payment"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    className="w-32 rounded-md border-slate-300 text-xs text-right focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div className="flex justify-between font-bold text-slate-900 border-t border-slate-200 pt-2 text-base">
                                <span>Total</span>
                                <span>Rp{subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                            <div className="flex justify-between font-semibold text-slate-700 border-t border-slate-200 pt-2">
                                <span>Sisa tagihan</span>
                                <span>Rp{subtotal.toLocaleString('id-ID', { minimumFractionDigits: 2 })}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <Link href="/purchasing/orders">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" variant="primary" disabled={processing}>
                            Buat Pesanan Pembelian
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}