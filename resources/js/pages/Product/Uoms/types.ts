export type Uom = {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
};

export type UomForm = {
    name: string;
    code: string;
    is_active: boolean;
};

export const uomLabels = {
    singular: 'Satuan (UOM)',
    plural: 'Satuan (UOM)',
    description: 'Kelola satuan produk.',
};