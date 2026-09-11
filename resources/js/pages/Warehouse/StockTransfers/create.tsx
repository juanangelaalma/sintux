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
    sourceWarehouses: Warehouse[];
    destinationWarehouses: Warehouse[];
    productVariants: ProductVariant[];
};

type ItemRow = {
    product_variant_id: number;
    qty: number;
};

type DirectTransferForm = {
    from_warehouse_id: number;
    to_warehouse_id: number;
    items: ItemRow[];
};

export default function Create({
    sourceWarehouses,
    destinationWarehouses,
    productVariants,
}: Props) {
    const form = useForm<DirectTransferForm>({
        from_warehouse_id: sourceWarehouses[0]?.id ?? 0,
        to_warehouse_id: destinationWarehouses[0]?.id ?? 0,
        items: [
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                qty: 1,
            },
        ],
    });

    const selectedSource = sourceWarehouses.find(
        (wh) => wh.id === form.data.from_warehouse_id,
    );
    const isHqSource = selectedSource?.branch?.is_headquarters ?? false;

    const handleAddItem = () => {
        form.setData('items', [
            ...form.data.items,
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                qty: 1,
            },
        ]);
    };

    const handleRemoveItem = (index: number) => {
        if (form.data.items.length <= 1) {
            return;
        }

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
        form.post('/warehouse/stock-transfers');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Transfer Stok Langsung" />
            <div className="space-y-6">
                <PageHeader
                    title="Buat Transfer Stok Langsung"
                    description="Kirim stok antar gudang tanpa stock request. Transfer dari gudang HQ langsung draft, dari cabang lain menunggu persetujuan HO."
                />

                <form
                    onSubmit={submit}
                    className="max-w-4xl space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200"
                >
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <FormField
                            label="Gudang Asal (Pengirim)"
                            error={form.errors.from_warehouse_id}
                            required
                        >
                            <SelectInput
                                value={form.data.from_warehouse_id}
                                onChange={(e) =>
                                    form.setData(
                                        'from_warehouse_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                <option value={0}>Pilih Gudang Asal</option>
                                {sourceWarehouses.map((wh) => (
                                    <option key={wh.id} value={wh.id}>
                                        {wh.name} ({wh.branch?.name ?? 'Cabang'}
                                        )
                                    </option>
                                ))}
                            </SelectInput>
                        </FormField>

                        <FormField
                            label="Gudang Tujuan (Penerima)"
                            error={form.errors.to_warehouse_id}
                            required
                        >
                            <SelectInput
                                value={form.data.to_warehouse_id}
                                onChange={(e) =>
                                    form.setData(
                                        'to_warehouse_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                <option value={0}>Pilih Gudang Tujuan</option>
                                {destinationWarehouses.map((wh) => (
                                    <option key={wh.id} value={wh.id}>
                                        {wh.name} ({wh.branch?.name ?? 'Cabang'}
                                        )
                                    </option>
                                ))}
                            </SelectInput>
                        </FormField>
                    </div>

                    {form.data.from_warehouse_id > 0 && (
                        <div
                            className={`rounded-lg p-3 text-sm ring-1 ${
                                isHqSource
                                    ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                    : 'bg-amber-50 text-amber-800 ring-amber-200'
                            }`}
                        >
                            {isHqSource
                                ? 'Gudang asal milik HQ: transfer langsung berstatus draft dan siap dikirim.'
                                : 'Gudang asal bukan HQ: transfer berstatus menunggu persetujuan HO sebelum bisa dikirim.'}
                        </div>
                    )}

                    <div className="space-y-4 pt-2">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold text-slate-900">
                                Daftar Barang Dikirim
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
                                            value={item.qty}
                                            onChange={(e) =>
                                                handleItemChange(
                                                    idx,
                                                    'qty',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                        {form.errors[
                                            `items.${idx}.qty` as keyof typeof form.errors
                                        ] && (
                                            <p className="mt-1 text-xs text-red-600">
                                                {
                                                    form.errors[
                                                        `items.${idx}.qty` as keyof typeof form.errors
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
                        onCancel={() =>
                            router.get('/warehouse/stock-transfers')
                        }
                        submitLabel="Buat Transfer Stok"
                        processing={form.processing}
                    />
                </form>
            </div>
        </CompanyLayout>
    );
}
