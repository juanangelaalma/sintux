export type Branch = {
    id: number;
    name: string;
    code: string;
    is_headquarters: boolean;
};

export type Warehouse = {
    id: number;
    name: string;
    code: string;
};

export type ProductVariant = {
    id: number;
    branch_id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
};

export type BranchItem = {
    uid: string;
    product_variant_id: number;
    description: string;
    qty: number;
    unit_price: number;
    tax_id: number | null;
};

export type BranchGroup = {
    uid: string;
    destination_branch_id: number;
    destination_warehouse_id: number | string;
    destination_expected_date: string;
    items: BranchItem[];
};

export type FlatAllocationRow = {
    destination_branch_id: number;
    destination_warehouse_id: number | string;
    destination_expected_date: string;
    product_variant_id: number;
    description: string;
    qty: number;
    qty_ordered: number;
    unit_price: number;
    tax_id: number | null;
};
