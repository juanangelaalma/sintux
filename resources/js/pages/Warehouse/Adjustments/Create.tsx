import { Head, useForm, Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';

type Warehouse = {
    id: number;
    name: string;
    code: string;
    branch?: { name: string };
};

type ProductVariant = {
    id: number;
    sku: string;
    variant_name: string;
    product?: { name: string; code: string };
};

type Props = {
    warehouses: Warehouse[];
    productVariants: ProductVariant[];
};

type AdjustmentItemForm = {
    product_variant_id: number | '';
    qty: number | '';
    unit_cost: number | '';
    note: string;
};

export default function AdjustmentsCreate({
    warehouses,
    productVariants,
}: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        warehouse_id: number | '';
        type: 'in' | 'out';
        note: string;
        items: AdjustmentItemForm[];
    }>({
        warehouse_id: warehouses.length > 0 ? warehouses[0].id : '',
        type: 'in',
        note: '',
        items: [
            {
                product_variant_id: '',
                qty: '',
                unit_cost: '',
                note: '',
            },
        ],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { product_variant_id: '', qty: '', unit_cost: '', note: '' },
        ]);
    };

    const removeItem = (index: number) => {
        if (data.items.length === 1) return;
        const newItems = [...data.items];
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const updateItem = (
        index: number,
        field: keyof AdjustmentItemForm,
        value: any,
    ) => {
        const newItems = [...data.items];
        newItems[index] = { ...newItems[index], [field]: value };
        setData('items', newItems);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/warehouse/adjustments');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Penyesuaian Stok" />

            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title="Buat Penyesuaian Stok Baru"
                    description="Input penyesuaian stok masuk (Opname Tambah) atau stok keluar (Opname Kurang)."
                    actions={
                        <Link href="/warehouse/adjustments">
                            <Button variant="secondary">&larr; Batal</Button>
                        </Link>
                    }
                />

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-slate-700">
                                Gudang <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={data.warehouse_id}
                                onChange={(e) =>
                                    setData(
                                        'warehouse_id',
                                        Number(e.target.value),
                                    )
                                }
                                className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="" disabled>
                                    Pilih Gudang
                                </option>
                                {warehouses.map((w) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name} ({w.branch?.name ?? '-'})
                                    </option>
                                ))}
                            </select>
                            {errors.warehouse_id && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {errors.warehouse_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700">
                                Tipe Penyesuaian{' '}
                                <span className="text-rose-500">*</span>
                            </label>
                            <select
                                value={data.type}
                                onChange={(e) =>
                                    setData(
                                        'type',
                                        e.target.value as 'in' | 'out',
                                    )
                                }
                                className="mt-1 block w-full rounded-md border-slate-300 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="in">
                                    Stok Masuk (IN - Tambah Stok & FIFO Layer)
                                </option>
                                <option value="out">
                                    Stok Keluar (OUT - Kurang Stok via FIFO)
                                </option>
                            </select>
                            {errors.type && (
                                <p className="mt-1 text-xs text-rose-600">
                                    {errors.type}
                                </p>
                            )}
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-slate-700">
                            Catatan / Alasan
                        </label>
                        <textarea
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            rows={2}
                            placeholder="Contoh: Stok opname bulanan, barang rusak, penyesuaian awal..."
                            className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        {errors.note && (
                            <p className="mt-1 text-xs text-rose-600">
                                {errors.note}
                            </p>
                        )}
                    </div>

                    <div className="border-t border-slate-200 pt-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-base font-semibold text-slate-900">
                                Daftar Item Barang
                            </h3>
                            <Button
                                type="button"
                                variant="secondary"
                                className="px-3 py-1.5 text-xs"
                                onClick={addItem}
                            >
                                + Tambah Item
                            </Button>
                        </div>

                        {errors.items && (
                            <p className="mb-4 text-xs text-rose-600">
                                {errors.items}
                            </p>
                        )}

                        <div className="space-y-4">
                            {data.items.map((item, index) => (
                                <div
                                    key={index}
                                    className="flex flex-col gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-start"
                                >
                                    <div className="flex-1">
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            Varian Produk #{index + 1}
                                        </label>
                                        <select
                                            value={item.product_variant_id}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'product_variant_id',
                                                    Number(e.target.value),
                                                )
                                            }
                                            className="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            required
                                        >
                                            <option value="" disabled>
                                                Pilih Varian Produk
                                            </option>
                                            {productVariants.map((v) => (
                                                <option key={v.id} value={v.id}>
                                                    [{v.sku}] {v.product?.name}{' '}
                                                    - {v.variant_name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="w-full sm:w-28">
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            Qty
                                        </label>
                                        <input
                                            type="number"
                                            step="any"
                                            min="0.0001"
                                            value={item.qty}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'qty',
                                                    e.target.value === ''
                                                        ? ''
                                                        : Number(
                                                              e.target.value,
                                                          ),
                                                )
                                            }
                                            className="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="0"
                                            required
                                        />
                                    </div>

                                    {data.type === 'in' && (
                                        <div className="w-full sm:w-36">
                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                Biaya/Unit (Rp)
                                            </label>
                                            <input
                                                type="number"
                                                step="any"
                                                min="0"
                                                value={item.unit_cost}
                                                onChange={(e) =>
                                                    updateItem(
                                                        index,
                                                        'unit_cost',
                                                        e.target.value === ''
                                                            ? ''
                                                            : Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                    )
                                                }
                                                className="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0"
                                            />
                                        </div>
                                    )}

                                    <div className="flex-1">
                                        <label className="mb-1 block text-xs font-medium text-slate-600">
                                            Catatan Item
                                        </label>
                                        <input
                                            type="text"
                                            value={item.note}
                                            onChange={(e) =>
                                                updateItem(
                                                    index,
                                                    'note',
                                                    e.target.value,
                                                )
                                            }
                                            className="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Opsional..."
                                        />
                                    </div>

                                    {data.items.length > 1 && (
                                        <div className="pt-6">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeItem(index)
                                                }
                                                className="text-xs font-semibold text-rose-600 hover:text-rose-800"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-slate-200 pt-6">
                        <Link href="/warehouse/adjustments">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={processing}
                        >
                            {processing
                                ? 'Menyimpan...'
                                : 'Simpan Draft Penyesuaian'}
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
