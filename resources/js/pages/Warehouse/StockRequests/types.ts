export interface Branch {
    id: number;
    name: string;
    code: string;
    is_headquarters: boolean;
}

export interface Warehouse {
    id: number;
    branch_id: number;
    code: string;
    name: string;
    warehouse_type: string;
    is_active: boolean;
    branch?: Branch;
}

export interface Product {
    id: number;
    code: string;
    name: string;
}

export interface ProductVariant {
    id: number;
    product_id: number;
    sku: string;
    variant_name: string;
    is_active: boolean;
    product?: Product;
}

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface StockRequestItem {
    id: number;
    stock_request_id: number;
    product_variant_id: number;
    qty_requested: number;
    qty_approved: number | null;
    available_qty?: number;
    product_variant?: ProductVariant;
}

export interface StockTransferItem {
    id: number;
    stock_transfer_id: number;
    product_variant_id: number;
    qty: number;
    product_variant?: ProductVariant;
}

export interface StockTransfer {
    id: number;
    stock_request_id: number;
    from_warehouse_id: number;
    to_warehouse_id: number;
    status: string;
    shipped_by: number | null;
    shipped_at: string | null;
    received_by: number | null;
    received_at: string | null;
    items?: StockTransferItem[];
}

export interface StockRequest {
    id: number;
    requesting_warehouse_id: number;
    destination_warehouse_id: number;
    requested_by: number;
    status:
        | 'pending'
        | 'approved'
        | 'partially_approved'
        | 'rejected'
        | 'completed';
    note: string | null;
    requested_at: string;
    created_at: string;
    requesting_warehouse?: Warehouse;
    destination_warehouse?: Warehouse;
    requested_by_user?: User;
    items?: StockRequestItem[];
    transfer?: StockTransfer;
}
