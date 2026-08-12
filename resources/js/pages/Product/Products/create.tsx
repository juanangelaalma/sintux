import { Head, useForm } from '@inertiajs/react';
import FormActions from '@/components/ui/form-actions';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import FormField from '@/components/ui/form-field';
import TextInput from '@/components/ui/text-input';
import SelectInput from '@/components/ui/select-input';
import { productLabels } from './types';
import type { ProductForm } from './types';

type CategoryOption = { id: number; name: string };
type BrandOption = { id: number; name: string };
type UomOption = { id: number; name: string; code: string };

type Props = {
    categories: CategoryOption[];
    brands: BrandOption[];
    uoms: UomOption[];
};

export default function Create({ categories, brands, uoms }: Props) {
    const form = useForm<ProductForm>({
        code: '',
        name: '',
        category_id: categories[0]?.id ?? 0,
        brand_id: null,
        uom_id: uoms[0]?.id ?? 0,
        description: '',
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/product/products', {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <CompanyLayout>
            <Head title={`Tambah ${productLabels.singular}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title={`Tambah ${productLabels.singular}`}
                    description={productLabels.description}
                />

                <form onSubmit={submit} className="space-y-6">
                    <FormField label="Kode Produk" required htmlFor="code">
                        <TextInput
                            id="code"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            placeholder="Contoh: LAPTOP-001"
                            autoFocus
                        />
                    </FormField>

                    <FormField label="Nama Produk" required htmlFor="name">
                        <TextInput
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Masukkan nama produk"
                        />
                    </FormField>

                    <FormField label="Kategori" required htmlFor="category_id">
                        <SelectInput
                            id="category_id"
                            value={form.data.category_id}
                            onChange={(e) => form.setData('category_id', Number(e.target.value))}
                        >
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField label="Brand" htmlFor="brand_id">
                        <SelectInput
                            id="brand_id"
                            value={form.data.brand_id ?? ''}
                            onChange={(e) =>
                                form.setData('brand_id', e.target.value ? Number(e.target.value) : null)
                            }
                        >
                            <option value="">Tidak ada brand</option>
                            {brands.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField label="Satuan (UOM)" required htmlFor="uom_id">
                        <SelectInput
                            id="uom_id"
                            value={form.data.uom_id}
                            onChange={(e) => form.setData('uom_id', Number(e.target.value))}
                        >
                            {uoms.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name} ({u.code})
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>

                    <FormField label="Deskripsi" htmlFor="description">
                        <textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            placeholder="Deskripsi produk (opsional)"
                        />
                    </FormField>

                    <FormField label="Status">
                        <div className="flex items-center gap-3">
                            <label className="flex cursor-pointer items-center gap-2">
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
                        submitLabel="Simpan"
                        processing={form.processing}
                    />
                </form>
            </div>
        </CompanyLayout>
    );
}
