import FormField from '@/components/ui/form-field';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import { relationTypeOptions } from './types';
import type { ContactForm, ContactType } from './types';

type Props = {
    type: ContactType;
    data: ContactForm;
    setData: <K extends keyof ContactForm>(key: K, value: ContactForm[K]) => void;
    errors: Record<string, string | undefined>;
};

export default function ContactInfoForm({ type, data, setData, errors }: Props) {
    return (
        <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
                Informasi Kontak
            </h3>

            <div className="space-y-4">
                <FormField label="Name" required error={errors.name}>
                    <TextInput
                        type="text"
                        required
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>

                <FormField label="Tanggal Register" required error={errors.registered_at}>
                    <TextInput
                        type="date"
                        required
                        value={data.registered_at}
                        onChange={(e) => setData('registered_at', e.target.value)}
                    />
                </FormField>

                {type === 'customers' && (
                    <FormField label="Relation Type" error={errors.tier_relation}>
                        <SelectInput
                            value={data.tier_relation}
                            onChange={(e) => setData('tier_relation', e.target.value)}
                        >
                            <option value="">- Pilih Relation Type -</option>
                            {relationTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </SelectInput>
                    </FormField>
                )}

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
            </div>
        </section>
    );
}
