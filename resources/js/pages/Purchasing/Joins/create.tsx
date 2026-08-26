import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import CompanyLayout from '@/layouts/company/company-layout';

type InvoiceOption = {
    id: number;
    number: string;
    total: number;
    supplier_name?: string;
};

type Props = {
    invoices: InvoiceOption[];
};

export default function JoinPurchaseInvoicesCreate({ invoices }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        join_date: string;
        invoice_ids: number[];
        note: string;
    }>({
        join_date: new Date().toISOString().split('T')[0],
        invoice_ids: [],
        note: '',
    });

    const toggleInvoice = (id: number) => {
        const next = data.invoice_ids.includes(id)
            ? data.invoice_ids.filter((i) => i !== id)
            : [...data.invoice_ids, id];
        setData('invoice_ids', next);
    };

    const selectedTotal = invoices
        .filter((i) => data.invoice_ids.includes(i.id))
        .reduce((sum, i) => sum + Number(i.total || 0), 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/joins');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Tukar Faktur" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Pembelian
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Buat Tukar Faktur (Join Invoice)
                        </h1>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Tanggal Tukar Faktur *
                            </label>
                            <input
                                type="date"
                                value={data.join_date}
                                onChange={(e) =>
                                    setData('join_date', e.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="mb-2 block text-xs font-semibold text-foreground">
                            Pilih Faktur Pembelian yang Akan Digabung *
                        </label>
                        {errors.invoice_ids && (
                            <p className="mb-2 text-xs text-danger">
                                {errors.invoice_ids}
                            </p>
                        )}
                        <div className="divide-y divide-border/60 overflow-hidden rounded-lg border border-border">
                            {invoices.length === 0 ? (
                                <div className="p-4 text-center text-sm text-muted">
                                    Tidak ada faktur pembelian bernilai
                                    outstanding untuk digabung.
                                </div>
                            ) : (
                                invoices.map((inv) => (
                                    <label
                                        key={inv.id}
                                        className="flex cursor-pointer items-center justify-between p-3.5 hover:bg-surface-secondary/60"
                                    >
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                checked={data.invoice_ids.includes(
                                                    inv.id,
                                                )}
                                                onChange={() =>
                                                    toggleInvoice(inv.id)
                                                }
                                                className="rounded border-border text-accent focus:ring-accent"
                                            />
                                            <div>
                                                <span className="font-semibold text-foreground">
                                                    Faktur #{inv.number}
                                                </span>
                                                <p className="text-xs text-muted">
                                                    {inv.supplier_name ||
                                                        'PT. Behaestex'}
                                                </p>
                                            </div>
                                        </div>
                                        <span className="font-bold text-foreground">
                                            Rp.{' '}
                                            {Number(
                                                inv.total || 0,
                                            ).toLocaleString('id-ID', {
                                                minimumFractionDigits: 2,
                                            })}
                                        </span>
                                    </label>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="flex items-center justify-between border-t border-border/60 pt-4">
                        <span className="text-sm font-semibold text-foreground">
                            Total Konsolidasi ({data.invoice_ids.length} faktur)
                        </span>
                        <span className="text-xl font-extrabold text-foreground">
                            Rp.{' '}
                            {selectedTotal.toLocaleString('id-ID', {
                                minimumFractionDigits: 2,
                            })}
                        </span>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-border/60 pt-4">
                        <Link href="/purchasing/joins">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            isDisabled={processing}
                        >
                            Simpan Tukar Faktur
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
