import { Head, router, useForm } from '@inertiajs/react';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import type { ProductVariant, Warehouse } from './types';

type Props = {
    requestingWarehouses: Warehouse[];
    destinationWarehouses: Warehouse[];
    productVariants: ProductVariant[];
};

type ItemRow = {
    product_variant_id: number;
    qty_requested: number;
};

type StockRequestForm = {
    requesting_warehouse_id: number;
    destination_warehouse_id: number;
    note: string;
    items: ItemRow[];
};

export default function Create({
    requestingWarehouses,
    destinationWarehouses,
    productVariants,
}: Props) {
    const form = useForm<StockRequestForm>({
        requesting_warehouse_id: requestingWarehouses[0]?.id ?? 0,
        destination_warehouse_id: destinationWarehouses[0]?.id ?? 0,
        note: '',
        items: [
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                qty_requested: 1,
            },
        ],
    });

    const handleAddItem = () => {
        form.setData('items', [
            ...form.data.items,
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                qty_requested: 1,
            },
        ]);
    };

    const handleRemoveItem = (index: number) => {
        if (form.data.items.length <= 1) return;
        const updated = [...form.data.items];
        updated.splice(index, 1);
        form.setData('items', updated);
    };

    const handleItemChange = (
        index: number,
        field: keyof ItemRow,
        value: number,
    ) => {
        const updated = [...form.data.items];
        updated[index] = { ...updated[index], [field]: value };
        form.setData('items', updated);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/warehouse/stock-requests');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Permintaan Stok" />
            <div className="space-y-6">
                <PageHeader
                    title="Buat Permintaan Stok"
                    description="Form pengajuan pasokan stok baru dari cabang ke Gudang Utama (HQ)."
                />

                <form
                    onSubmit={submit}
                    className="max-w-4xl space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200"
                >
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <FormField
                            label="Gudang Peminta"
                            error={form.errors.requesting_warehouse_id}
                            required
                        >
                            <SelectInput
                                value={form.data.requesting_warehouse_id}
                                onChange={(e) =>
                                    form.setData(
                                        'requesting_warehouse_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                <option value={0}>Pilih Gudang Peminta</option>
                                {requestingWarehouses.map((wh) => (
                                    <option key={wh.id} value={wh.id}>
                                        {wh.name} ({wh.branch?.name ?? 'Cabang'}
                                        )
                                    </option>
                                ))}
                            </SelectInput>
                        </FormField>

                        <FormField
                            label="Gudang Tujuan (HQ)"
                            error={form.errors.destination_warehouse_id}
                            required
                        >
                            <SelectInput
                                value={form.data.destination_warehouse_id}
                                onChange={(e) =>
                                    form.setData(
                                        'destination_warehouse_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                <option value={0}>Pilih Gudang HQ</option>
                                {destinationWarehouses.map((wh) => (
                                    <option key={wh.id} value={wh.id}>
                                        {wh.name} ({wh.branch?.name ?? 'HQ'})
                                    </option>
                                ))}
                            </SelectInput>
                        </FormField>
                    </div>

                    <FormField
                        label="Catatan / Alasan Permintaan"
                        error={form.errors.note}
                    >
                        <textarea
                            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none"
                            placeholder="Contoh: Stok barang fisik di cabang B sudah menipis..."
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            rows={3}
                        />
                    </FormField>

                    <div className="space-y-4 pt-2">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold text-slate-900">
                                Daftar Barang yang Diminta
                            </h3>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={handleAddItem}
                            >
                                + Tambah Barang
                            </Button>
                        </div>

                        {form.errors.items && (
                            <p className="text-sm font-medium text-red-600">
                                {form.errors.items}
                            </p>
                        )}

                        <div className="divide-y divide-slate-200 rounded-md border border-slate-200">
                            {form.data.items.map((item, idx) => (
                                <div
                                    key={idx}
                                    className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center"
                                >
                                    <div className="flex-1">
                                        <label className="mb-1 block text-xs font-medium text-slate-500">
                                            Varian Produk #{idx + 1}
                                        </label>
                                        <SelectInput
                                            value={item.product_variant_id}
                                            onChange={(e) =>
                                                handleItemChange(
                                                    idx,
                                                    'product_variant_id',
                                                    Number(e.target.value),
                                                )
                                            }
                                        >
                                            <option value={0}>
                                                Pilih Varian Produk
                                            </option>
                                            {productVariants.map((v) => (
                                                <option key={v.id} value={v.id}>
                                                    {v.product?.name ??
                                                        'Produk'}{' '}
                                                    - {v.variant_name} ({v.sku})
                                                </option>
                                            ))}
                                        </SelectInput>
                                        {form.errors[
                                            `items.${idx}.product_variant_id` as keyof typeof form.errors
                                        ] && (
                                            <p className="mt-1 text-xs text-red-600">
                                                {
                                                    form.errors[
                                                        `items.${idx}.product_variant_id` as keyof typeof form.errors
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>

                                    <div className="w-full sm:w-36">
                                        <label className="mb-1 block text-xs font-medium text-slate-500">
                                            Jumlah (Qty)
                                        </label>
                                        <TextInput
                                            type="number"
                                            min={1}
                                            value={item.qty_requested}
                                            onChange={(e) =>
                                                handleItemChange(
                                                    idx,
                                                    'qty_requested',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                        {form.errors[
                                            `items.${idx}.qty_requested` as keyof typeof form.errors
                                        ] && (
                                            <p className="mt-1 text-xs text-red-600">
                                                {
                                                    form.errors[
                                                        `items.${idx}.qty_requested` as keyof typeof form.errors
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>

                                    <div className="sm:pt-5">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            onClick={() =>
                                                handleRemoveItem(idx)
                                            }
                                            disabled={
                                                form.data.items.length <= 1
                                            }
                                        >
                                            Hapus
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <FormActions
                        onCancel={() => router.get('/warehouse/stock-requests')}
                        submitLabel="Kirim Permintaan Stok"
                        processing={form.processing}
                    />
                </form>
            </div>
        </CompanyLayout>
    );
}
