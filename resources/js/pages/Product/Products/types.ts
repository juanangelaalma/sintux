export type Product = {
    id: number;
    code: string;
    name: string;
    category_id: number;
    brand_id: number | null;
    uom_id: number;
    description: string | null;
    is_active: boolean;
    category: {
        id: number;
        name: string;
    };
    brand: {
        id: number;
        name: string;
    } | null;
    uom: {
        id: number;
        name: string;
        code: string;
    };
    created_at: string;
    updated_at: string;
};

export type ProductForm = {
    code: string;
    name: string;
    category_id: number;
    brand_id: number | null;
    uom_id: number;
    description: string;
    is_active: boolean;
};

export type ProductVariant = {
    id: number;
    product_id: number;
    sku: string;
    variant_name: string;
    attributes: Record<string, unknown> | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type ProductVariantForm = {
    product_id: number;
    sku: string;
    variant_name: string;
    attributes: object | null;
    is_active: boolean;
};

export const productLabels = {
    singular: 'Produk',
    plural: 'Produk',
    description: 'Kelola produk dan varian.',
};