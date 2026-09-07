export type ProductBundleItem = {
    id?: number;
    bundle_product_id?: number;
    item_product_id: number;
    quantity: number;
    item_product?: Product;
};

export type Product = {
    id: number;
    branch_id: number;
    code: string;
    name: string;
    barcode?: string | null;
    category_id: number;
    uom_id: number;
    description?: string | null;
    image_path?: string | null;
    product_type: 'single' | 'bundle';
    is_purchased: boolean;
    purchase_price: number;
    purchase_account_id?: number | null;
    purchase_tax_id?: number | null;
    is_sold: boolean;
    selling_price: number;
    sales_account_id?: number | null;
    sales_tax_id?: number | null;
    is_inventory_tracked: boolean;
    min_stock: number;
    inventory_account_id?: number | null;
    is_active: boolean;
    category?: {
        id: number;
        name: string;
    };
    uom?: {
        id: number;
        name: string;
        code: string;
    };
    bundle_items?: ProductBundleItem[];
    created_at?: string;
    updated_at?: string;
};

export type ProductForm = {
    branch_id: number;
    code: string;
    name: string;
    barcode: string;
    category_id: number;
    uom_id: number;
    description: string;
    image_path: string;
    product_type: 'single' | 'bundle';
    is_purchased: boolean;
    purchase_price: number;
    purchase_account_id: number | null;
    purchase_tax_id: number | null;
    is_sold: boolean;
    selling_price: number;
    sales_account_id: number | null;
    sales_tax_id: number | null;
    is_inventory_tracked: boolean;
    min_stock: number;
    inventory_account_id: number | null;
    is_active: boolean;
    bundle_items: Array<{
        item_product_id: number;
        quantity: number;
    }>;
};

export type ProductVariant = {
    id: number;
    branch_id: number;
    product_id: number;
    sku: string;
    variant_name: string;
    attributes?: Record<string, unknown> | null;
    is_active: boolean;
};

export const productLabels = {
    singular: 'Produk',
    plural: 'Produk',
    description: 'Kelola data produk, persediaan stok, HPP, dan paket bundle.',
};
