import { router, useForm } from '@inertiajs/react';
import React from 'react';
import AddressMapPicker from '@/components/address/address-map-picker';
import AddressSearch from '@/components/address/address-search';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import TextInput from '@/components/ui/text-input';
import { isEmptyAddress } from '@/lib/nominatim';
import type { AddressValue } from '@/lib/nominatim';
import { toContactForm } from './types';
import type { Contact, ContactForm as ContactFormData, ContactType } from './types';

type ContactFormProps = {
    type: ContactType;
    mode: 'create' | 'edit';
    contact?: Contact | null;
};

export default function ContactForm({ type, mode, contact = null }: ContactFormProps) {
    const { data, setData, post, put, processing, errors } = useForm<ContactFormData>(
        toContactForm(contact),
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'create') {
            post(`/company/contacts/${type}`);
        } else if (contact) {
            put(`/company/contacts/${type}/${contact.id}`);
        }
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

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <FormField label="Name" error={errors.name}>
                <TextInput
                    type="text"
                    required
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
            </FormField>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Email" error={errors.email}>
                    <TextInput
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField label="Phone" error={errors.phone}>
                    <TextInput
                        type="text"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                    />
                </FormField>
            </div>

            <FormField label="Notes" error={errors.notes}>
                <textarea
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    rows={2}
                />
            </FormField>

            <FormField label="Status">
                <label className="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="rounded border-gray-300"
                    />
                    Active
                </label>
            </FormField>

            <div className="border-t border-gray-200 pt-6 dark:border-gray-800">
                <FormField label="Alamat">
                    <label className="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            checked={data.shipping_same_as_billing}
                            onChange={(e) => handleSameAsBillingChange(e.target.checked)}
                            className="rounded border-gray-300"
                        />
                        Alamat pengiriman sama dengan alamat penagihan
                    </label>
                </FormField>

                <div className="mt-4 space-y-6">
                    <AddressSection
                        title="Alamat Penagihan"
                        prefix="billing_address"
                        value={data.billing_address}
                        errors={errors}
                        onChange={(patch) => updateAddress('billing_address', patch)}
                    />

                    {!data.shipping_same_as_billing && (
                        <AddressSection
                            title="Alamat Pengiriman"
                            prefix="shipping_address"
                            value={data.shipping_address}
                            errors={errors}
                            onChange={(patch) => updateAddress('shipping_address', patch)}
                        />
                    )}
                </div>
            </div>

            <div className="border-t border-gray-200 pt-4 dark:border-gray-800">
                <FormActions
                    onCancel={() => router.visit(`/company/contacts/${type}`)}
                    submitLabel={mode === 'create' ? 'Create' : 'Update'}
                    processing={processing}
                />
            </div>
        </form>
    );
}

type AddressSectionProps = {
    title: string;
    prefix: 'billing_address' | 'shipping_address';
    value: AddressValue;
    errors: Record<string, string | undefined>;
    onChange: (patch: Partial<AddressValue>) => void;
};

function AddressSection({ title, prefix, value, errors, onChange }: AddressSectionProps) {
    return (
        <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
            <h4 className="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{title}</h4>

            <FormField label="Cari lokasi" error={errors[`${prefix}.kelurahan`]}>
                <AddressSearch
                    onSelect={(address) => onChange({ ...address })}
                    placeholder="Ketik nama desa / kelurahan / kecamatan..."
                />
            </FormField>

            <div className="mt-3">
                <AddressMapPicker value={value} onChange={onChange} />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4">
                <FormField label="Detail / Jalan" error={errors[`${prefix}.detail`]}>
                    <TextInput
                        type="text"
                        placeholder="Jl. Melati No. 5, RT/RW opsional di kolom bawah"
                        value={value.detail}
                        onChange={(e) => onChange({ detail: e.target.value })}
                    />
                </FormField>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <FormField label="RT" error={errors[`${prefix}.rt`]}>
                    <TextInput type="text" value={value.rt} onChange={(e) => onChange({ rt: e.target.value })} />
                </FormField>

                <FormField label="RW" error={errors[`${prefix}.rw`]}>
                    <TextInput type="text" value={value.rw} onChange={(e) => onChange({ rw: e.target.value })} />
                </FormField>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <FormField label="Desa / Kelurahan" error={errors[`${prefix}.kelurahan`]}>
                    <TextInput
                        type="text"
                        value={value.kelurahan}
                        onChange={(e) => onChange({ kelurahan: e.target.value })}
                    />
                </FormField>

                <FormField label="Kecamatan" error={errors[`${prefix}.kecamatan`]}>
                    <TextInput
                        type="text"
                        value={value.kecamatan}
                        onChange={(e) => onChange({ kecamatan: e.target.value })}
                    />
                </FormField>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <FormField label="Kabupaten / Kota" error={errors[`${prefix}.kabupaten`]}>
                    <TextInput
                        type="text"
                        value={value.kabupaten}
                        onChange={(e) => onChange({ kabupaten: e.target.value })}
                    />
                </FormField>

                <FormField label="Provinsi" error={errors[`${prefix}.provinsi`]}>
                    <TextInput
                        type="text"
                        value={value.provinsi}
                        onChange={(e) => onChange({ provinsi: e.target.value })}
                    />
                </FormField>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <FormField label="Latitude" error={errors[`${prefix}.latitude`]}>
                    <TextInput type="text" readOnly value={value.latitude} />
                </FormField>

                <FormField label="Longitude" error={errors[`${prefix}.longitude`]}>
                    <TextInput type="text" readOnly value={value.longitude} />
                </FormField>
            </div>
        </div>
    );
}
