export type AccountOption = {
    id: number;
    code: string;
    name: string;
};

export type TaxOption = {
    id: number;
    code: string;
    name: string;
    rate: number;
    type?: string;
    dpp_multiplier?: boolean;
    members?: {
        id: number;
        name: string;
        signed_rate: number;
        is_compound: boolean;
        position: number;
        dpp_multiplier: boolean;
    }[];
};

export type PaymentMethodOption = {
    id: number;
    name: string;
    code: string;
};

export type ContactOption = {
    id: number;
    name: string;
    type: string;
    email: string | null;
};

export type TagOption = {
    id: number;
    name: string;
    color: string | null;
};

export type ExpenseStatusValue = 'open' | 'closed';

export type ExpenseLineRow = {
    account_id: number | string;
    description: string;
    tax_id: number | string | null;
    amount: number;
};

export type WithholdingRow = {
    type: '' | 'percent' | 'nominal';
    value: number;
    account_id: number | string | null;
};

export type ExpenseSummaryCard = {
    total: number;
    count: number;
};

export type ExpenseSummary = {
    this_month: ExpenseSummaryCard;
    last_30_days: ExpenseSummaryCard;
    unpaid: ExpenseSummaryCard;
};

export type ExpenseRow = {
    id: number;
    number: string;
    transaction_date: string;
    contact_name: string | null;
    status: ExpenseStatusValue;
    grand_total: number;
    amount_paid: number;
    outstanding: number;
    category_label: string;
    tags: TagOption[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type ExpenseFilters = {
    filter?: string;
    search?: string;
    status?: string;
};

/**
 * Bentuk `taxDef` yang diharapkan calculateTax() di lib/tax/tax-calculator.ts.
 * Cermin dari proyeksi TaxQuery::listForPurchase() di backend.
 */
export type TaxDefinition = {
    id: number;
    rate: number;
    type?: string;
    dpp_multiplier?: boolean;
    members?: {
        id: number;
        signed_rate?: number;
        rate?: number;
        is_compound?: boolean;
        dpp_multiplier?: boolean;
    }[];
};
