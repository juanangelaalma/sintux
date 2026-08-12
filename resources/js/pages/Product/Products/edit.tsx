import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import Modal from '@/components/ui/modal';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import { productLabels } from './types';
import type { Product, ProductForm, ProductVariant, ProductVariantForm } from './types';

type CategoryOption = { id: number; name: string };
type BrandOption = { id: number; name: string };
type UomOption = { id: number; name: string; code: string };

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    product: Product;
    variants: Paginated<ProductVariant>;
    categories: CategoryOption[];
    brands: BrandOption[];
    uoms: UomOption[];
};

export default function Edit({ product, variants, categories, brands, uoms }: Props) {
    const form = useForm<ProductForm>({
        code: product.code,
        name: product.name,
        category_id: product.category_id,
        brand_id: product.brand_id,
        uom_id: product.uom_id,
        description: product.description ?? '',
        is_active: product.is_active,
    });

    const [variantModal, setVariantModal] = useState<{
        open: boolean;
        editing?: ProductVariant;
    }>({ open: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/product/products/${product.id}`);
    };

    const handleDeleteVariant = (variant: ProductVariant) => {
        if (confirm(`Remove variant ${variant.variant_name}?`)) {
            router.delete(`/product/products/${product.id}/variants/${variant.id}`);
        }
    };

    return (
        <CompanyLayout>
            <Head title={`Edit ${productLabels.singular}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={`Edit ${productLabels.singular}`}
                    description={productLabels.description}
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <FormField label="Kode Produk" required htmlFor="code">
                            <TextInput
                                id="code"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value)}
                                placeholder="Contoh: LAPTOP-001"
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

                        <FormField label="Status">
                            <div className="mt-2 flex items-center gap-3">
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
                    </div>

                    <FormField label="Deskripsi" htmlFor="description" className="lg:col-span-2">
                        <textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            rows={3}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            placeholder="Deskripsi produk (opsional)"
                        />
                    </FormField>

                    <FormActions
                        onCancel={() => window.history.back()}
                        submitLabel="Perbarui"
                        processing={form.processing}
                    />
                </form>

                <section className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Varian</h2>
                        <Button onClick={() => setVariantModal({ open: true })}>Tambah Varian</Button>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
                        <table className="min-w-full">
                            <thead className="border-b border-gray-100 dark:border-white/[0.05]">
                                <tr>
                                    <th className="px-5 py-3 text-theme-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">SKU</th>
                                    <th className="px-5 py-3 text-theme-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Nama Varian</th>
                                    <th className="px-5 py-3 text-theme-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th>
                                    <th className="px-5 py-3 text-theme-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                                {variants.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                            Belum ada varian untuk produk ini.
                                        </td>
                                    </tr>
                                ) : (
                                    variants.data.map((variant) => (
                                        <tr key={variant.id}>
                                            <td className="px-4 py-3 text-theme-sm font-medium text-gray-900 dark:text-white">{variant.sku}</td>
                                            <td className="px-4 py-3 text-theme-sm text-gray-500 dark:text-gray-400">{variant.variant_name}</td>
                                            <td className="px-4 py-3 text-theme-sm">
                                                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                                                    variant.is_active
                                                        ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                                                        : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                                                }`}>
                                                    {variant.is_active ? 'Aktif' : 'Tidak Aktif'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-theme-sm text-right">
                                                <div className="space-x-3 font-medium">
                                                    <button
                                                        onClick={() => setVariantModal({ open: true, editing: variant })}
                                                        className="text-brand-500 hover:text-brand-600"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteVariant(variant)}
                                                        className="text-red-500 hover:text-red-600"
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <VariantModal
                    productId={product.id}
                    modal={variantModal}
                    onClose={() => setVariantModal({ open: false })}
                />
            </div>
        </CompanyLayout>
    );
}

function VariantModal({
    productId,
    modal,
    onClose,
}: {
    productId: number;
    modal: { open: boolean; editing?: ProductVariant };
    onClose: () => void;
}) {
    const variantForm = useForm<ProductVariantForm>({
        product_id: productId,
        sku: modal.editing?.sku ?? '',
        variant_name: modal.editing?.variant_name ?? '',
        attributes: modal.editing?.attributes ?? {},
        is_active: modal.editing?.is_active ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (modal.editing) {
            variantForm.put(`/product/products/${productId}/variants/${modal.editing.id}`, {
                onSuccess: onClose,
            });
        } else {
            variantForm.post(`/product/products/${productId}/variants`, {
                onSuccess: onClose,
            });
        }
    };

    if (!modal.open) {
        return null;
    }

    return (
        <Modal title={modal.editing ? 'Edit Varian' : 'Tambah Varian'} onClose={onClose}>
            <form onSubmit={submit} className="mt-4 space-y-4">
                <FormField label="SKU" required htmlFor="sku">
                    <TextInput
                        id="sku"
                        value={variantForm.data.sku}
                        onChange={(e) => variantForm.setData('sku', e.target.value)}
                        placeholder="Contoh: LAPTOP-001-BLK-16"
                    />
                </FormField>

                <FormField label="Nama Varian" required htmlFor="variant_name">
                    <TextInput
                        id="variant_name"
                        value={variantForm.data.variant_name}
                        onChange={(e) => variantForm.setData('variant_name', e.target.value)}
                        placeholder="Contoh: Black / 16GB"
                    />
                </FormField>

                <FormField label="Status">
                    <div className="flex items-center gap-3">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={variantForm.data.is_active}
                                onChange={(e) => variantForm.setData('is_active', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                            />
                            <span className="text-sm text-gray-700 dark:text-gray-300">Aktif</span>
                        </label>
                    </div>
                </FormField>

                <FormActions
                    onCancel={onClose}
                    submitLabel={modal.editing ? 'Perbarui' : 'Simpan'}
                    processing={variantForm.processing}
                />
            </form>
        </Modal>
    );
}
