export type CustomerOption = {
    id: number;
    name: string;
    email?: string | null;
};

export type EmployeeOption = {
    id: number;
    name: string;
};

export type SaleWarehouseOption = {
    id: number;
    code: string;
    name: string;
    warehouse_type: string;
};

export type SaleVariantOption = {
    id: number;
    product_id: number;
    branch_id: number;
    product_name: string;
    sku: string;
    uom_name?: string | null;
    selling_price: number;
};

export type SaleTaxOption = {
    id: number;
    code: string;
    name: string;
    rate: number | string;
};

export type PaymentTermOption = {
    id: string;
    name: string;
};

export type DiscountType = 'percent' | 'nominal';

export type SalesLineItemRow = {
    product_variant_id: number;
    qty: number;
    unit_price: number;
    discount_type?: DiscountType | '';
    discount_value?: number;
    tax_id?: number | null;
};
