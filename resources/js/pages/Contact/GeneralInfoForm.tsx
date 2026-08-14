import AddressMapPicker from '@/components/address/address-map-picker';
import AddressSearch from '@/components/address/address-search';
import FormField from '@/components/ui/form-field';
import SelectInput from '@/components/ui/select-input';
import SensitiveInput from '@/components/ui/sensitive-input';
import TextInput from '@/components/ui/text-input';
import type { AddressValue } from '@/lib/nominatim';
import { identityTypeOptions } from './types';
import type { ContactForm } from './types';

type Props = {
    data: ContactForm;
    setData: <K extends keyof ContactForm>(key: K, value: ContactForm[K]) => void;
    errors: Record<string, string | undefined>;
    updateAddress: (
        key: 'billing_address' | 'shipping_address',
        patch: Partial<AddressValue>,
    ) => void;
    handleSameAsBillingChange: (checked: boolean) => void;
};

export default function GeneralInfoForm({
    data,
    setData,
    errors,
    updateAddress,
    handleSameAsBillingChange,
}: Props) {
    return (
        <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
                Informasi Umum
            </h3>

            <div className="space-y-4">
                <FormField label="Identitas" error={errors.identity_type}>
                    <SelectInput
                        value={data.identity_type}
                        onChange={(e) => setData('identity_type', e.target.value)}
                    >
                        <option value="">- Pilih Jenis Identitas -</option>
                        {identityTypeOptions.map((option) => (
                            <option key={option} value={option}>
                                {option}
                            </option>
                        ))}
                    </SelectInput>
                </FormField>

                <FormField label="Nomor Identitas" error={errors.identity_number}>
                    <SensitiveInput
                        value={data.identity_number}
                        onChange={(e) => setData('identity_number', e.target.value)}
                        placeholder="Contoh: 3171012345670001"
                    />
                </FormField>

                <FormField label="Email" error={errors.email}>
                    <TextInput
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField label="Nama Perusahaan" error={errors.company_name}>
                    <TextInput
                        type="text"
                        value={data.company_name}
                        onChange={(e) => setData('company_name', e.target.value)}
                    />
                </FormField>

                <FormField label="No Handphone" error={errors.mobile_phone}>
                    <TextInput
                        type="text"
                        value={data.mobile_phone}
                        onChange={(e) => setData('mobile_phone', e.target.value)}
                    />
                </FormField>

                <FormField label="No Telephone" error={errors.telephone}>
                    <TextInput
                        type="text"
                        value={data.telephone}
                        onChange={(e) => setData('telephone', e.target.value)}
                    />
                </FormField>

                <FormField label="No FAX" error={errors.fax}>
                    <TextInput
                        type="text"
                        value={data.fax}
                        onChange={(e) => setData('fax', e.target.value)}
                    />
                </FormField>

                <FormField label="No NPWP" error={errors.npwp}>
                    <SensitiveInput
                        value={data.npwp}
                        onChange={(e) => setData('npwp', e.target.value)}
                        placeholder="Contoh: 01.234.567.8-901.000"
                    />
                </FormField>
            </div>

            <div className="mt-6 border-t border-gray-200 pt-4 dark:border-gray-800">
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
        </section>
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

            <div className="mt-4">
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
