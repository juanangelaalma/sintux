import { Head, Link, useForm } from '@inertiajs/react';
import React, { useState, useMemo, useRef } from 'react';
import Button from '@/components/ui/button';
import { SearchableSelect } from '@/components/ui/searchable-select';
import CompanyLayout from '@/layouts/company/company-layout';
import { UnitCombobox } from '@/pages/Product/components/unit-combobox';
import type { ProductForm } from './types';

type CategoryOption = { id: number; name: string };
type UomOption = { id: number; name: string; code: string };
type AvailableProduct = {
    id: number;
    code: string;
    name: string;
    selling_price: number;
    purchase_price: number;
    uom?: { code: string };
};

type ChartOfAccountOption = {
    id: number;
    code: string;
    name: string;
};

type TaxOption = {
    id: number;
    code: string;
    name: string;
    rate: string;
};

type Props = {
    categories: CategoryOption[];
    uoms: UomOption[];
    availableProducts: AvailableProduct[];
    chartOfAccounts: ChartOfAccountOption[];
    purchaseTaxes: TaxOption[];
    salesTaxes: TaxOption[];
};

export default function Create({
    categories,
    uoms,
    availableProducts = [],
    chartOfAccounts = [],
    purchaseTaxes,
    salesTaxes,
}: Props) {
    const [activeFormTab, setActiveFormTab] = useState<'pricing' | 'bundle'>('pricing');
    const [uploadingImage, setUploadingImage] = useState(false);
    const [imageError, setImageError] = useState<string | null>(null);
    const [uomOptions, setUomOptions] = useState<UomOption[]>(uoms);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const chartOfAccountOptions = chartOfAccounts.map((account) => ({
        id: account.id,
        label: `${account.code} - ${account.name}`,
    }));
    const purchaseTaxOptions = purchaseTaxes.map((tax) => ({
        id: tax.id,
        label: `${tax.code} - ${tax.name} (${tax.rate}%)`,
    }));
    const salesTaxOptions = salesTaxes.map((tax) => ({
        id: tax.id,
        label: `${tax.code} - ${tax.name} (${tax.rate}%)`,
    }));

    const form = useForm<ProductForm>({
        code: '',
        name: '',
        barcode: '',
        category_id: categories[0]?.id ?? 0,
        uom_id: uoms[0]?.id ?? 0,
        description: '',
        image_path: '',
        product_type: 'single',
        is_purchased: true,
        purchase_price: 0,
        purchase_account_id: null,
        purchase_tax_id: null,
        is_sold: true,
        selling_price: 0,
        sales_account_id: null,
        sales_tax_id: null,
        is_inventory_tracked: true,
        min_stock: 0,
        inventory_account_id: null,
        is_active: true,
        bundle_items: [],
    });

    const handleImageSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
return;
}

        setImageError(null);

        // Security Validation 1: Extension check
        const filename = file.name.toLowerCase();
        const validExtensions = ['.jpg', '.jpeg', '.png'];
        const isValidExtension = validExtensions.some((ext) => filename.endsWith(ext));

        // Security Validation 2: Strict MIME type check
        const validMimeTypes = ['image/jpeg', 'image/png'];
        const isValidMime = validMimeTypes.includes(file.type);

        if (!isValidExtension || !isValidMime) {
            setImageError('Format file tidak diizinkan! Hanya diperbolehkan berkas gambar JPG (.jpg, .jpeg) dan PNG (.png).');

            if (fileInputRef.current) {
fileInputRef.current.value = '';
}

            return;
        }

        // Security Validation 3: File size check (Max 5MB)
        const maxSizeInBytes = 5 * 1024 * 1024;

        if (file.size > maxSizeInBytes) {
            setImageError('Ukuran berkas melebihi batas maksimal 5 MB.');

            if (fileInputRef.current) {
fileInputRef.current.value = '';
}

            return;
        }

        setUploadingImage(true);

        const formData = new FormData();
        formData.append('image', file);

        // Get CSRF Token
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

        try {
            const response = await fetch('/product/products/upload-image', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                const message = data.errors?.image?.[0] ?? data.message ?? 'Gagal mengunggah gambar.';
                setImageError(message);
            } else if (data.url) {
                form.setData('image_path', data.url);
            }
        } catch {
            setImageError('Terjadi kesalahan saat mengunggah berkas.');
        } finally {
            setUploadingImage(false);
        }
    };

    const handleAddBundleItem = (selectedProductId: number) => {
        if (!selectedProductId) {
return;
}

        const exists = form.data.bundle_items.some((i) => i.item_product_id === selectedProductId);

        if (exists) {
return;
}

        form.setData('bundle_items', [
            ...form.data.bundle_items,
            { item_product_id: selectedProductId, quantity: 1 },
        ]);
    };

    const handleUpdateBundleQty = (productId: number, qty: number) => {
        form.setData(
            'bundle_items',
            form.data.bundle_items.map((item) =>
                item.item_product_id === productId ? { ...item, quantity: Math.max(1, qty) } : item
            )
        );
    };

    const handleRemoveBundleItem = (productId: number) => {
        form.setData(
            'bundle_items',
            form.data.bundle_items.filter((item) => item.item_product_id !== productId)
        );
    };

    const bundleTotal = useMemo(() => {
        return form.data.bundle_items.reduce((sum, item) => {
            const prod = availableProducts.find((p) => p.id === item.item_product_id);
            const price = prod?.selling_price ?? prod?.purchase_price ?? 0;

            return sum + price * item.quantity;
        }, 0);
    }, [form.data.bundle_items, availableProducts]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/product/products');
    };

    return (
        <CompanyLayout>
            <Head title="Tambah produk baru" />

            <div className="space-y-6 pb-12">
                {/* Breadcrumb & Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <Link href="/product" className="text-xs font-medium text-indigo-600 hover:underline">
                            Daftar produk
                        </Link>
                        <h1 className="text-2xl font-bold text-slate-900">Tambah produk baru</h1>
                    </div>
                    <Button variant="secondary" className="text-xs">
                        Tampilkan petunjuk
                    </Button>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Hidden File Input */}
                    <input
                        type="file"
                        ref={fileInputRef}
                        onChange={handleImageSelect}
                        accept="image/jpeg,image/png"
                        className="hidden"
                    />

                    {/* Top Main Information Form & Image Upload */}
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div className="lg:col-span-2 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama produk <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Masukkan nama produk"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    required
                                />
                                {form.errors.name && (
                                    <span className="text-xs text-rose-500">{form.errors.name}</span>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Kode produk / SKU
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        placeholder="Contoh: SKU-1001"
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-700 mb-1">
                                        Barcode
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.barcode}
                                        onChange={(e) => form.setData('barcode', e.target.value)}
                                        placeholder="Masukkan barcode"
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Unit
                                </label>
                                <UnitCombobox
                                    value={form.data.uom_id}
                                    options={uomOptions}
                                    onChange={(id) => form.setData('uom_id', id)}
                                    onOptionAdded={(newUom) => setUomOptions((prev) => [...prev, newUom])}
                                />
                                {form.errors.uom_id && (
                                    <span className="text-xs text-rose-500">{form.errors.uom_id}</span>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-1">
                                    Kategori produk
                                </label>
                                <select
                                    value={form.data.category_id}
                                    onChange={(e) => form.setData('category_id', Number(e.target.value))}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                >
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <div className="flex justify-between items-center mb-1">
                                    <label className="block text-xs font-semibold text-slate-700">
                                        Deskripsi
                                    </label>
                                    <span className="text-[11px] text-slate-400">
                                        {form.data.description.length}/6000
                                    </span>
                                </div>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={3}
                                    maxLength={6000}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    placeholder="Masukkan deskripsi produk"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-700 mb-2">
                                    Tipe produk
                                </label>
                                <div className="flex items-center gap-6">
                                    <label className="flex items-center gap-2 cursor-pointer text-sm text-slate-700 font-medium">
                                        <input
                                            type="radio"
                                            name="product_type"
                                            value="single"
                                            checked={form.data.product_type === 'single'}
                                            onChange={() => {
                                                form.setData('product_type', 'single');
                                                setActiveFormTab('pricing');
                                            }}
                                            className="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span>Single</span>
                                    </label>
                                    <label className="flex items-center gap-2 cursor-pointer text-sm text-slate-700 font-medium">
                                        <input
                                            type="radio"
                                            name="product_type"
                                            value="bundle"
                                            checked={form.data.product_type === 'bundle'}
                                            onChange={() => {
                                                form.setData('product_type', 'bundle');
                                                setActiveFormTab('bundle');
                                            }}
                                            className="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span>Bundle</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {/* Image Upload Box Right */}
                        <div className="flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-xl p-6 bg-slate-50/50 text-center relative min-h-[220px]">
                            {form.data.image_path ? (
                                <div className="space-y-3 w-full flex flex-col items-center">
                                    <div className="w-32 h-32 rounded-lg overflow-hidden border border-slate-200 shadow-sm relative group bg-white">
                                        <img
                                            src={form.data.image_path}
                                            alt="Gambar Produk"
                                            className="w-full h-full object-cover"
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => fileInputRef.current?.click()}
                                            className="text-xs font-semibold text-indigo-600 hover:underline"
                                        >
                                            Ganti gambar
                                        </button>
                                        <span className="text-slate-300">•</span>
                                        <button
                                            type="button"
                                            onClick={() => form.setData('image_path', '')}
                                            className="text-xs font-semibold text-rose-600 hover:underline"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center">
                                    <div className="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-3">
                                        <svg className="size-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        disabled={uploadingImage}
                                        className="text-xs font-semibold text-indigo-600 hover:underline mb-1 disabled:opacity-50"
                                    >
                                        {uploadingImage ? 'Mengunggah gambar...' : 'Pilih gambar produk'}
                                    </button>
                                    <span className="text-[11px] text-slate-400">
                                        Format file JPG atau PNG (maks. 5MB)
                                    </span>
                                </div>
                            )}

                            {imageError && (
                                <span className="mt-2 text-xs text-rose-500 font-medium block">
                                    {imageError}
                                </span>
                            )}
                        </div>
                    </div>

                    {/* Bottom Tabbed Section */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="border-b border-slate-200 px-6 pt-4">
                            <nav className="flex space-x-6" aria-label="Form Tabs">
                                <button
                                    type="button"
                                    onClick={() => setActiveFormTab('pricing')}
                                    className={`pb-3 text-sm font-semibold transition-all border-b-2 ${
                                        activeFormTab === 'pricing'
                                            ? 'border-indigo-600 text-indigo-600'
                                            : 'border-transparent text-slate-500 hover:text-slate-700'
                                    }`}
                                >
                                    Harga & persediaan
                                </button>
                                {form.data.product_type === 'bundle' && (
                                    <button
                                        type="button"
                                        onClick={() => setActiveFormTab('bundle')}
                                        className={`pb-3 text-sm font-semibold transition-all border-b-2 ${
                                            activeFormTab === 'bundle'
                                                ? 'border-indigo-600 text-indigo-600'
                                                : 'border-transparent text-slate-500 hover:text-slate-700'
                                        }`}
                                    >
                                        Pengaturan bundle
                                    </button>
                                )}
                            </nav>
                        </div>

                        <div className="p-6 space-y-6">
                            {/* Tab 1: Harga & Persediaan */}
                            {activeFormTab === 'pricing' && (
                                <div className="space-y-6">
                                    {/* Saya beli produk ini */}
                                    <div className="space-y-3 pb-6 border-b border-slate-100">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={form.data.is_purchased}
                                                onChange={(e) => form.setData('is_purchased', e.target.checked)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="text-sm font-bold text-slate-900">
                                                Saya beli produk ini
                                            </span>
                                        </label>

                                        {form.data.is_purchased && (
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 pt-2">
                                                <div>
                                                    <label className="block text-xs font-medium text-slate-600 mb-1">
                                                        Harga beli satuan
                                                    </label>
                                                    <div className="relative">
                                                        <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-slate-500">
                                                            Rp
                                                        </span>
                                                        <input
                                                            type="number"
                                                            value={form.data.purchase_price}
                                                            onChange={(e) =>
                                                                form.setData('purchase_price', Number(e.target.value))
                                                            }
                                                            className="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                        />
                                                    </div>
                                                </div>

                                                <div>
                                                    <label className="block text-xs font-medium text-slate-600 mb-1">
                                                        Akun pembelian
                                                    </label>
                                                    <SearchableSelect
                                                        options={chartOfAccountOptions}
                                                        value={form.data.purchase_account_id}
                                                        onChange={(value) => form.setData('purchase_account_id', value)}
                                                        placeholder="Pilih akun pembelian"
                                                    />
                                                </div>

                                                <div>
                                                    <label className="block text-xs font-medium text-slate-600 mb-1">
                                                        Pajak beli
                                                    </label>
                                                    <SearchableSelect
                                                        options={purchaseTaxOptions}
                                                        value={form.data.purchase_tax_id}
                                                        onChange={(value) => form.setData('purchase_tax_id', value)}
                                                        placeholder="Pilih pajak beli"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Saya jual produk ini */}
                                    <div className="space-y-3 pb-6 border-b border-slate-100">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={form.data.is_sold}
                                                onChange={(e) => form.setData('is_sold', e.target.checked)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="text-sm font-bold text-slate-900">
                                                Saya jual produk ini
                                            </span>
                                        </label>

                                        {form.data.is_sold && (
                                            <div className="space-y-2">
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 pt-2">
                                                    <div>
                                                        <label className="block text-xs font-medium text-slate-600 mb-1">
                                                            Harga jual satuan
                                                        </label>
                                                        <div className="relative">
                                                            <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-slate-500">
                                                                Rp
                                                            </span>
                                                            <input
                                                                type="number"
                                                                value={form.data.selling_price}
                                                                onChange={(e) =>
                                                                    form.setData('selling_price', Number(e.target.value))
                                                                }
                                                                className="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                            />
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label className="block text-xs font-medium text-slate-600 mb-1">
                                                            Akun penjualan
                                                        </label>
                                                        <SearchableSelect
                                                            options={chartOfAccountOptions}
                                                            value={form.data.sales_account_id}
                                                            onChange={(value) => form.setData('sales_account_id', value)}
                                                            placeholder="Pilih akun penjualan"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label className="block text-xs font-medium text-slate-600 mb-1">
                                                            Pajak jual
                                                        </label>
                                                        <SearchableSelect
                                                            options={salesTaxOptions}
                                                            value={form.data.sales_tax_id}
                                                            onChange={(value) => form.setData('sales_tax_id', value)}
                                                            placeholder="Pilih pajak jual"
                                                        />
                                                    </div>
                                                </div>

                                                <div className="text-xs text-slate-500">
                                                    Butuh akun diskon?{' '}
                                                    <span className="text-indigo-600 cursor-pointer hover:underline">
                                                        Aktifkan sekarang
                                                    </span>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Monitor persediaan barang */}
                                    <div className="space-y-3">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={form.data.is_inventory_tracked}
                                                onChange={(e) =>
                                                    form.setData('is_inventory_tracked', e.target.checked)
                                                }
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <span className="text-sm font-bold text-slate-900">
                                                Monitor persediaan barang
                                            </span>
                                        </label>

                                        {form.data.is_inventory_tracked && (
                                            <div className="space-y-2">
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2">
                                                    <div>
                                                        <label className="block text-xs font-medium text-slate-600 mb-1">
                                                            Batas stok minimum
                                                        </label>
                                                        <input
                                                            type="number"
                                                            value={form.data.min_stock}
                                                            onChange={(e) =>
                                                                form.setData('min_stock', Number(e.target.value))
                                                            }
                                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label className="block text-xs font-medium text-slate-600 mb-1">
                                                            Akun persediaan barang default
                                                        </label>
                                                        <SearchableSelect
                                                            options={chartOfAccountOptions}
                                                            value={form.data.inventory_account_id}
                                                            onChange={(value) => form.setData('inventory_account_id', value)}
                                                            placeholder="Pilih akun persediaan"
                                                        />
                                                    </div>
                                                </div>

                                                <div className="text-[11px] text-slate-400">
                                                    Kuantitas awal dapat dicatat melalui penyesuaian stok
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Tab 2: Pengaturan Bundle (Sesuai Gambar 3) */}
                            {activeFormTab === 'bundle' && (
                                <div className="space-y-6">
                                    {/* Info Banner */}
                                    <div className="rounded-lg bg-indigo-50/80 p-3.5 border border-indigo-100 flex items-center gap-2.5 text-xs text-indigo-900">
                                        <svg className="size-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Komponen bundle harus terdiri dari produk yang dilacak berdasarkan qty.</span>
                                    </div>

                                    {/* Component Table */}
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-12 gap-4 pb-2 border-b border-slate-200 text-xs font-bold text-slate-700">
                                            <div className="col-span-6">Nama produk</div>
                                            <div className="col-span-2">Qty</div>
                                            <div className="col-span-3 text-right">Harga</div>
                                            <div className="col-span-1 text-center"></div>
                                        </div>

                                        {/* Added Component Rows */}
                                        {form.data.bundle_items.map((item) => {
                                            const prod = availableProducts.find((p) => p.id === item.item_product_id);
                                            const price = (prod?.selling_price ?? prod?.purchase_price ?? 0) * item.quantity;

                                            return (
                                                <div key={item.item_product_id} className="grid grid-cols-12 gap-4 items-center py-1">
                                                    <div className="col-span-6">
                                                        <select
                                                            value={item.item_product_id}
                                                            onChange={(e) => {
                                                                const newId = Number(e.target.value);
                                                                handleRemoveBundleItem(item.item_product_id);
                                                                handleAddBundleItem(newId);
                                                            }}
                                                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                                                        >
                                                            {availableProducts.map((p) => (
                                                                <option key={p.id} value={p.id}>
                                                                    {p.name} ({p.code})
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>

                                                    <div className="col-span-2 flex items-center gap-2">
                                                        <input
                                                            type="number"
                                                            value={item.quantity}
                                                            min={1}
                                                            onChange={(e) => handleUpdateBundleQty(item.item_product_id, Number(e.target.value))}
                                                            className="w-16 rounded-lg border border-slate-300 px-2 py-1.5 text-xs text-center focus:border-indigo-500 focus:outline-none"
                                                        />
                                                        <span className="text-xs text-slate-500 uppercase">{prod?.uom?.code ?? 'PCS'}</span>
                                                    </div>

                                                    <div className="col-span-3 text-right text-xs font-semibold text-slate-900">
                                                        Rp{price.toLocaleString('id-ID')},00
                                                    </div>

                                                    <div className="col-span-1 text-center">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveBundleItem(item.item_product_id)}
                                                            className="w-6 h-6 rounded-full border border-slate-300 text-slate-500 hover:text-rose-600 hover:border-rose-300 flex items-center justify-center text-sm"
                                                        >
                                                            &minus;
                                                        </button>
                                                    </div>
                                                </div>
                                            );
                                        })}

                                        {/* Select Product to Add Row */}
                                        <div className="grid grid-cols-12 gap-4 items-center pt-2">
                                            <div className="col-span-6">
                                                <select
                                                    value=""
                                                    onChange={(e) => handleAddBundleItem(Number(e.target.value))}
                                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-500 focus:border-indigo-500 focus:outline-none"
                                                >
                                                    <option value="">Pilih produk</option>
                                                    {availableProducts
                                                        .filter((p) => !form.data.bundle_items.some((b) => b.item_product_id === p.id))
                                                        .map((p) => (
                                                            <option key={p.id} value={p.id}>
                                                                {p.name} ({p.code})
                                                            </option>
                                                        ))}
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Bundle Account Section */}
                                    <div className="pt-4 border-t border-slate-100 space-y-2">
                                        <div className="flex justify-between items-center text-xs font-bold text-slate-700">
                                            <span>Akun (Anda bisa mengisi ini jika Monitor persediaan barang tercentang)</span>
                                            <span>Biaya</span>
                                        </div>
                                        <select className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-500 focus:border-indigo-500 focus:outline-none">
                                            <option value="">Pilih akun</option>
                                        </select>
                                    </div>

                                    {/* Total Footer */}
                                    <div className="pt-4 border-t border-slate-200 flex justify-end items-center gap-6 text-sm">
                                        <span className="font-bold text-slate-700">Total harga</span>
                                        <span className="font-bold text-slate-900">
                                            Rp{bundleTotal.toLocaleString('id-ID')},00
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Bottom Action Footer */}
                    <div className="flex justify-end items-center gap-3 pt-4 border-t border-slate-200">
                        <Link href="/product">
                            <Button variant="secondary" type="button">
                                Batalkan
                            </Button>
                        </Link>
                        <Button variant="primary" type="submit" disabled={form.processing || uploadingImage}>
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
