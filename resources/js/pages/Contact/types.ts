import { emptyAddress } from '@/lib/nominatim';
import type { AddressValue } from '@/lib/nominatim';

export type ContactType = 'customers' | 'suppliers' | 'employees';

export type Contact = {
    id: number;
    type: string;
    name: string;
    registered_at: string | null;
    tier_relation: string | null;
    identity_type: string | null;
    identity_number: string | null;
    company_name: string | null;
    email: string | null;
    mobile_phone: string | null;
    telephone: string | null;
    fax: string | null;
    npwp: string | null;
    notes: string | null;
    bank_name: string | null;
    bank_branch: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    is_active: boolean;
    shipping_same_as_billing: boolean;
    billing_address: AddressValue | null;
    shipping_address: AddressValue | null;
};

export type ContactForm = {
    name: string;
    registered_at: string;
    tier_relation: string;
    identity_type: string;
    identity_number: string;
    company_name: string;
    email: string;
    mobile_phone: string;
    telephone: string;
    fax: string;
    npwp: string;
    bank_name: string;
    bank_branch: string;
    bank_account_name: string;
    bank_account_number: string;
    is_active: boolean;
    shipping_same_as_billing: boolean;
    billing_address: AddressValue;
    shipping_address: AddressValue;
};

export const relationTypeOptions = [
    { value: 'A', label: 'A - Top' },
    { value: 'B', label: 'B - Middle Up' },
    { value: 'C', label: 'C - Middle' },
    { value: 'D', label: 'D - Middle Low' },
    { value: 'E', label: 'E - Low' },
    { value: 'R', label: 'R - Reseller' },
] as const;

export const identityTypeOptions = ['KTP', 'SIM', 'Pasport'] as const;

export const contactLabels = {
    customers: {
        singular: 'Customer',
        plural: 'Customers',
        description: 'Manage customer contact information.',
    },
    suppliers: {
        singular: 'Supplier',
        plural: 'Suppliers',
        description: 'Manage supplier contact details.',
    },
    employees: {
        singular: 'Employee',
        plural: 'Employees',
        description: 'Manage internal employee contacts.',
    },
} as const;

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export function toContactForm(contact: Contact | null): ContactForm {
    return {
        name: contact?.name ?? '',
        registered_at: contact?.registered_at ?? today(),
        tier_relation: contact?.tier_relation ?? '',
        identity_type: contact?.identity_type ?? '',
        identity_number: contact?.identity_number ?? '',
        company_name: contact?.company_name ?? '',
        email: contact?.email ?? '',
        mobile_phone: contact?.mobile_phone ?? '',
        telephone: contact?.telephone ?? '',
        fax: contact?.fax ?? '',
        npwp: contact?.npwp ?? '',
        bank_name: contact?.bank_name ?? '',
        bank_branch: contact?.bank_branch ?? '',
        bank_account_name: contact?.bank_account_name ?? '',
        bank_account_number: contact?.bank_account_number ?? '',
        is_active: contact?.is_active ?? true,
        shipping_same_as_billing: contact?.shipping_same_as_billing ?? true,
        billing_address: contact?.billing_address ?? emptyAddress(),
        shipping_address: contact?.shipping_address ?? emptyAddress(),
    };
}
