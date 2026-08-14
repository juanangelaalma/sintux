export type StockAdjustmentItem = {
    id: number;
    stock_adjustment_id: number;
    product_variant_id: number;
    qty: number;
    unit_cost: number | null;
    note: string | null;
    product_variant?: {
        id: number;
        sku: string;
        variant_name: string;
        product?: {
            id: number;
            code: string;
            name: string;
            uom?: {
                id: number;
                name: string;
                code: string;
            };
        };
    };
};

export type StockAdjustment = {
    id: number;
    warehouse_id: number;
    adjustment_number: string;
    type: 'in' | 'out';
    status: 'draft' | 'posted' | 'cancelled';
    note: string | null;
    adjusted_by: number;
    adjusted_at: string | null;
    created_at: string;
    updated_at: string;
    warehouse?: {
        id: number;
        name: string;
        code: string;
        branch?: {
            id: number;
            name: string;
        };
    };
    adjusted_by_user?: {
        id: number;
        name: string;
        email: string;
    };
    items?: StockAdjustmentItem[];
};
