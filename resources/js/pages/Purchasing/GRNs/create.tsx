import { Button, Input, Label } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';

type PurchaseOrderOption = {
    id: number;
    number: string;
};

type Props = {
    purchaseOrders: PurchaseOrderOption[];
};

export default function GrnsCreate({ purchaseOrders }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        do_no: '',
        purchase_order_id: '',
        customer: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/grns/fetch');
    };

    return (
        <CompanyLayout>
            <Head title="Fetch DO Supplier" />
            <div className="w-full max-w-xl space-y-6">
                <PageHeader
                    title="Fetch DO Supplier"
                    description="Masukkan nomor DO dari supplier untuk menarik daftar barang yang dialokasikan ke cabang ini."
                    actions={
                        <Link href="/purchasing/grns">
                            <Button type="button" variant="secondary">
                                Kembali
                            </Button>
                        </Link>
                    }
                />
                <form
                    onSubmit={handleSubmit}
                    className="space-y-4 rounded-xl border border-border bg-surface p-6"
                >
                    <div>
                        <Label className="block text-xs font-semibold text-foreground">
                            Nomor DO Supplier
                        </Label>
                        <Input
                            className="mt-1"
                            placeholder="cth. DO/2608/00230"
                            value={data.do_no}
                            onChange={(e) => setData('do_no', e.target.value)}
                            autoFocus
                        />
                        {errors.do_no && (
                            <p className="mt-1 text-xs text-danger">
                                {errors.do_no}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label className="block text-xs font-semibold text-foreground">
                            Customer di Sistem Supplier (opsional jika cabang
                            sudah punya mapping)
                        </Label>
                        <Input
                            className="mt-1"
                            placeholder="cth. AII JAKARTA"
                            value={data.customer}
                            onChange={(e) =>
                                setData('customer', e.target.value)
                            }
                        />
                        {errors.customer && (
                            <p className="mt-1 text-xs text-danger">
                                {errors.customer}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label className="block text-xs font-semibold text-foreground">
                            PO Terkait (wajib jika DO belum mencantumkan nomor
                            PO)
                        </Label>
                        <select
                            value={data.purchase_order_id}
                            onChange={(e) =>
                                setData('purchase_order_id', e.target.value)
                            }
                            className="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground"
                        >
                            <option value="">Otomatis dari DO…</option>
                            {purchaseOrders.map((po) => (
                                <option key={po.id} value={po.id}>
                                    {po.number}
                                </option>
                            ))}
                        </select>
                        {errors.purchase_order_id && (
                            <p className="mt-1 text-xs text-danger">
                                {errors.purchase_order_id}
                            </p>
                        )}
                    </div>
                    <Button
                        type="submit"
                        variant="primary"
                        isDisabled={processing || data.do_no.trim() === ''}
                    >
                        {processing ? 'Mengambil…' : 'Fetch Barang'}
                    </Button>
                </form>
            </div>
        </CompanyLayout>
    );
}
