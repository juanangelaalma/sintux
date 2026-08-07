import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { isEmptyAddress } from '@/lib/nominatim';
import type { AddressValue } from '@/lib/nominatim';
import { toContactForm } from './types';
import type { Contact, ContactForm, ContactType } from './types';

type Options = {
    type: ContactType;
    mode: 'create' | 'edit';
    contact?: Contact | null;
};

export function useContactForm({ type, mode, contact = null }: Options) {
    const { data, setData, post, put, processing, errors } = useForm<ContactForm>(
        toContactForm(contact),
    );

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (mode === 'create') {
            post(`/company/contacts/${type}`);
        } else if (contact) {
            put(`/company/contacts/${type}/${contact.id}`);
        }
    };

    const cancel = () => {
        router.visit(`/company/contacts/${type}`);
    };

    const updateAddress = (
        key: 'billing_address' | 'shipping_address',
        patch: Partial<AddressValue>,
    ) => {
        setData(key, { ...data[key], ...patch });
    };

    const handleSameAsBillingChange = (checked: boolean) => {
        if (!checked && isEmptyAddress(data.shipping_address)) {
            setData('shipping_address', { ...data.billing_address });
        }

        setData('shipping_same_as_billing', checked);
    };

    return {
        data,
        setData,
        errors,
        processing,
        submit,
        cancel,
        updateAddress,
        handleSameAsBillingChange,
    };
}
