export type ProductCategory = {
    id: number;
    name: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type ProductCategoryForm = {
    name: string;
    is_active: boolean;
};

export const categoryLabels = {
    singular: 'Kategori Produk',
    plural: 'Kategori Produk',
    description: 'Kelola kategori produk.',
};