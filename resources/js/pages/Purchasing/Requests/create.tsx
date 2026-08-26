import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import LineItemsEditor from '@/components/purchasing/line-items-editor';
import type {
    LineItemRow,
    ProductVariant,
} from '@/components/purchasing/line-items-editor';
import CompanyLayout from '@/layouts/company/company-layout';

type Branch = { id: number; name: string; code: string };

type Props = {
    branches: Branch[];
    productVariants: ProductVariant[];
};

export default function PurchaseRequestsCreate({
    branches,
    productVariants,
}: Props) {
    const { data, setData, post, processing } = useForm<{
        branch_id: number | string;
        request_date: string;
        required_date: string;
        note: string;
        items: (LineItemRow & { qty_requested?: number })[];
    }>({
        branch_id: branches[0]?.id ?? '',
        request_date: new Date().toISOString().split('T')[0],
        required_date: '',
        note: '',
        items: [
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                description: '',
                qty: 1,
                qty_requested: 1,
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
                qty_requested: 1,
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
        const updatedItem = { ...next[index], [field]: value };

        if (field === 'qty') {
            updatedItem.qty_requested = Number(value);
        }

        next[index] = updatedItem;
        setData('items', next);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const preparedItems = data.items.map((item) => ({
            ...item,
            qty_requested: Number(item.qty),
            unit_price: Number(item.unit_price || 0),
        }));

        setData('items', preparedItems);
        post('/purchasing/requests');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Permintaan Pembelian" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Pembelian
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Buat Permintaan Pembelian (Purchase Request)
                        </h1>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                >
                    <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Tanggal permintaan *
                            </label>
                            <input
                                type="date"
                                value={data.request_date}
                                onChange={(e) =>
                                    setData('request_date', e.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Tanggal dibutuhkan
                            </label>
                            <input
                                type="date"
                                value={data.required_date}
                                onChange={(e) =>
                                    setData('required_date', e.target.value)
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
                        <Link href="/purchasing/requests">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            isDisabled={processing}
                        >
                            Simpan Permintaan Pembelian
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
