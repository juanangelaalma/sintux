import { Head, useForm } from '@inertiajs/react';
import FormActions from '@/components/ui/form-actions';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import FormField from '@/components/ui/form-field';
import TextInput from '@/components/ui/text-input';
import Button from '@/components/ui/button';
import InputError from '@/components/input-error';
import { uomLabels } from './types';
import type { Uom, UomForm } from './types';

type Props = {
    uom: Uom;
};

export default function Edit({ uom }: Props) {
    const form = useForm<UomForm>({
        name: uom.name,
        code: uom.code,
        is_active: uom.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/product/uoms/${uom.id}`);
    };

    return (
        <CompanyLayout>
            <Head title={`Edit ${uomLabels.singular}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Edit ${uomLabels.singular}`}
                    description={uomLabels.description}
                />

                <form onSubmit={submit} className="space-y-6">
                    <FormField label="Nama" required>
                        <TextInput
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Masukkan nama satuan"
                        />
                        <InputError message={form.errors.name} />
                    </FormField>

                    <FormField label="Kode" required>
                        <TextInput
                            id="code"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            placeholder="Masukkan kode satuan"
                        />
                        <InputError message={form.errors.code} />
                    </FormField>

                    <FormField label="Status">
                        <div className="flex items-center gap-3">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                />
                                <span className="text-sm text-gray-700 dark:text-gray-300">Aktif</span>
                            </label>
                        </div>
                    </FormField>

                    <FormActions
                        onCancel={() => window.history.back()}
                        submitLabel="Perbarui"
                        processing={form.processing}
                    />
                </form>
            </div>
        </CompanyLayout>
    );
}
