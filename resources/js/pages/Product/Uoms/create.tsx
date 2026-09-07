import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import PageHeader from '@/components/ui/page-header';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import { uomLabels } from './types';
import type { UomForm } from './types';

export default function Create() {
    const form = useForm<UomForm>({
        name: '',
        code: '',
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/product/uoms', {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <CompanyLayout>
            <Head title={`Tambah ${uomLabels.singular}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Tambah ${uomLabels.singular}`}
                    description={uomLabels.description}
                />

                <form onSubmit={submit} className="space-y-6">
                    <FormField label="Nama" required>
                        <TextInput
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Masukkan nama satuan"
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </FormField>

                    <FormField label="Kode" required>
                        <TextInput
                            id="code"
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                            placeholder="Masukkan kode satuan (contoh: PCS, KG, M)"
                        />
                        <InputError message={form.errors.code} />
                    </FormField>

                    <FormField label="Status">
                        <div className="flex items-center gap-3">
                            <label className="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_active',
                                            e.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                />
                                <span className="text-sm text-gray-700 dark:text-gray-300">
                                    Aktif
                                </span>
                            </label>
                        </div>
                    </FormField>

                    <FormActions
                        onCancel={() => window.history.back()}
                        submitLabel="Simpan"
                        processing={form.processing}
                    />
                </form>
            </div>
        </CompanyLayout>
    );
}
