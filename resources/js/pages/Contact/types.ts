import { emptyAddress } from '@/lib/nominatim';
import type { AddressValue } from '@/lib/nominatim';

export type ContactType = 'customers' | 'suppliers' | 'employees';

export type Contact = {
    id: number;
    type: string;
    name: string;
    email: string | null;
    phone: string | null;
    notes: string | null;
    is_active: boolean;
    shipping_same_as_billing: boolean;
    billing_address: AddressValue | null;
    shipping_address: AddressValue | null;
};

export type ContactForm = {
    name: string;
    email: string;
    phone: string;
    notes: string;
    is_active: boolean;
    shipping_same_as_billing: boolean;
    billing_address: AddressValue;
    shipping_address: AddressValue;
};

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

export function toContactForm(contact: Contact | null): ContactForm {
    return {
        name: contact?.name ?? '',
        email: contact?.email ?? '',
        phone: contact?.phone ?? '',
        notes: contact?.notes ?? '',
        is_active: contact?.is_active ?? true,
        shipping_same_as_billing: contact?.shipping_same_as_billing ?? true,
        billing_address: contact?.billing_address ?? emptyAddress(),
        shipping_address: contact?.shipping_address ?? emptyAddress(),
    };
}
