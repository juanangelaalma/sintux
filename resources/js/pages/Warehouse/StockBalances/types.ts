export type StockBalance = {
    id: number;
    product_variant_id: number;
    warehouse_id: number;
    qty_on_hand: number;
    warehouse?: {
        id: number;
        name: string;
        code: string;
    };
    product_variant?: {
        id: number;
        sku: string;
        variant_name: string;
        product?: {
            id: number;
            code: string;
            name: string;
        };
    };
};

export type WarehouseOption = {
    id: number;
    name: string;
};

export const stockBalanceLabels = {
    plural: 'Saldo Stok',
    description: 'Lihat saldo stok per varian produk dan gudang.',
};
