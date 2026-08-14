export type Brand = {
    id: number;
    name: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type BrandForm = {
    name: string;
    is_active: boolean;
};

export const brandLabels = {
    singular: 'Brand',
    plural: 'Brand',
    description: 'Kelola brand produk.',
};