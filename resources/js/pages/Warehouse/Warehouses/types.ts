export type BranchOption = {
    id: number;
    name: string;
    code: string;
    is_headquarters: boolean;
};

export type Warehouse = {
    id: number;
    branch_id: number;
    code: string;
    name: string;
    warehouse_type: 'consignment' | 'regular' | 'general';
    address: string | null;
    is_active: boolean;
    branch?: BranchOption;
};

export type WarehouseForm = {
    branch_id: number;
    code: string;
    name: string;
    warehouse_type: 'consignment' | 'regular' | 'general';
    address: string;
    is_active: boolean;
};

export const warehouseTypeLabels = {
    consignment: 'Konsinyasi',
    regular: 'Reguler',
    general: 'Umum',
} as const;

export const warehouseLabels = {
    singular: 'Gudang',
    plural: 'Gudang',
    description: 'Kelola gudang per branch.',
};
