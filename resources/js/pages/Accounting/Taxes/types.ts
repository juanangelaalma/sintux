export type TaxAccountRef = {
    id: number;
    name: string;
};

export type TaxMemberRow = {
    id: number | null;
    name: string | null;
    rate: number;
    signed_rate: number;
    is_compound: boolean;
    position: number;
};

export type TaxRow = {
    id: number;
    name: string;
    code: string;
    type: 'single' | 'group';
    rate: number;
    is_withholding: boolean;
    dpp_multiplier: boolean;
    sales_account: TaxAccountRef | null;
    purchase_account: TaxAccountRef | null;
    is_active: boolean;
    members: TaxMemberRow[];
    in_use: boolean;
};

export type AccountOption = {
    id: number;
    code: string;
    name: string;
};

export type SingleTaxOption = {
    id: number;
    name: string;
    code: string;
    rate: number;
    signed_rate: number;
    dpp_multiplier: boolean;
};

export function formatRate(rate: number): string {
    const value = Number(rate);

    return `${value.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`;
}
