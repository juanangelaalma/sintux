export type Warehouse = {
    id: number;
    code: string;
    name: string;
    warehouse_type: string;
    is_active: boolean;
    branch?: {
        id: number;
        name: string;
    };
};

export type ProductVariant = {
    id: number;
    sku: string;
    variant_name: string;
    product?: {
        id: number;
        code: string;
        name: string;
    };
};

export type StockTransferItemLayer = {
    id: number;
    stock_transfer_item_id: number;
    stock_layer_id: number;
    qty_taken: number;
    unit_cost: number;
    stock_layer?: {
        id: number;
        received_at: string;
        source_type: string;
        source_id: number;
    };
};

export type StockTransferItem = {
    id: number;
    stock_transfer_id: number;
    product_variant_id: number;
    qty: number;
    qty_shipped?: number | null;
    qty_received?: number | null;
    product_variant?: ProductVariant;
    layers?: StockTransferItemLayer[];
    discrepancies?: StockTransferDiscrepancy[];
};

export type StockTransferDiscrepancy = {
    id: number;
    stock_transfer_id: number;
    stock_transfer_item_id: number;
    product_variant_id: number;
    shipped_qty: number;
    received_qty: number;
    difference_qty: number;
    reason?: string | null;
    status: 'pending' | 'investigating' | 'resolved';
    resolution_note?: string | null;
    resolved_at?: string | null;
    created_at: string;
    updated_at: string;
};

export type StockTransfer = {
    id: number;
    stock_request_id?: number | null;
    from_warehouse_id: number;
    to_warehouse_id: number;
    status: 'draft' | 'shipped' | 'received' | 'cancelled';
    shipped_by?: number | null;
    shipped_at?: string | null;
    received_by?: number | null;
    received_at?: string | null;
    created_at: string;
    updated_at: string;
    from_warehouse?: Warehouse;
    to_warehouse?: Warehouse;
    shipped_by_user?: { id: number; name: string };
    received_by_user?: { id: number; name: string };
    items?: StockTransferItem[];
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
