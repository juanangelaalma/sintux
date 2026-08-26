import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import LineItemsEditor from '@/components/purchasing/line-items-editor';
import type {
    LineItemRow,
    ProductVariant,
} from '@/components/purchasing/line-items-editor';
import SupplierFields from '@/components/purchasing/supplier-fields';
import type { SupplierOption } from '@/components/purchasing/supplier-fields';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';

type Branch = { id: number; name: string; code: string };

type Props = {
    branches: Branch[];
    suppliers: SupplierOption[];
    productVariants: ProductVariant[];
};

export default function PurchaseQuotesCreate({
    branches,
    suppliers,
    productVariants,
}: Props) {
    const [supplierEmail, setSupplierEmail] = useState('');
    const [supplierAddress, setSupplierAddress] = useState('');

    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        quote_date: string;
        valid_until: string;
        note: string;
        items: LineItemRow[];
    }>({
        branch_id: branches[0]?.id ?? '',
        supplier_id: suppliers[0]?.id ?? '',
        quote_date: new Date().toISOString().split('T')[0],
        valid_until: '',
        note: '',
        items: [
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                description: '',
                qty: 1,
                unit_price: 0,
            },
        ],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                description: '',
                qty: 1,
                unit_price: 0,
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

    const updateItem = (
        index: number,
        field: keyof LineItemRow,
        value: string | number | null,
    ) => {
        const next = [...data.items];
        next[index] = { ...next[index], [field]: value };
        setData('items', next);
    };

    const subtotal = data.items.reduce(
        (sum, item) =>
            sum + Number(item.qty || 0) * Number(item.unit_price || 0),
        0,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/quotes');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Penawaran Harga" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Pembelian
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Buat Penawaran Harga (Purchase Quote)
                        </h1>
                    </div>
                    <div className="text-right">
                        <p className="text-xs font-medium text-muted">
                            Total Penawaran
                        </p>
                        <p className="mt-0.5 text-2xl font-black text-foreground">
                            {formatCurrency(subtotal)}
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                >
                    <SupplierFields
                        supplierId={data.supplier_id}
                        suppliers={suppliers}
                        onSupplierChange={(id) => setData('supplier_id', id)}
                        email={supplierEmail}
                        address={supplierAddress}
                        onEmailChange={setSupplierEmail}
                        onAddressChange={setSupplierAddress}
                        error={errors.supplier_id}
                    />

                    <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Tanggal penawaran *
                            </label>
                            <input
                                type="date"
                                value={data.quote_date}
                                onChange={(e) =>
                                    setData('quote_date', e.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Berlaku hingga
                            </label>
                            <input
                                type="date"
                                value={data.valid_until}
                                onChange={(e) =>
                                    setData('valid_until', e.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            />
                        </div>
                    </div>

                    <LineItemsEditor
                        items={data.items}
                        productVariants={productVariants}
                        onAddItem={addItem}
                        onRemoveItem={removeItem}
                        onUpdateItem={updateItem}
                    />

                    <div className="flex justify-end gap-3 border-t border-border/60 pt-4">
                        <Link href="/purchasing/quotes">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            isDisabled={processing}
                        >
                            Simpan Penawaran Harga
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
