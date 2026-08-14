import FormField from '@/components/ui/form-field';
import SensitiveInput from '@/components/ui/sensitive-input';
import TextInput from '@/components/ui/text-input';
import type { ContactForm } from './types';

type Props = {
    data: ContactForm;
    setData: <K extends keyof ContactForm>(key: K, value: ContactForm[K]) => void;
    errors: Record<string, string | undefined>;
};

export default function BankInfoForm({ data, setData, errors }: Props) {
    return (
        <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 className="mb-4 text-sm font-semibold text-gray-900 dark:text-white">
                Informasi Bank
            </h3>

            <div className="space-y-4">
                <FormField label="Nama Bank" error={errors.bank_name}>
                    <TextInput
                        type="text"
                        value={data.bank_name}
                        onChange={(e) => setData('bank_name', e.target.value)}
                    />
                </FormField>

                <FormField label="Cabang Bank" error={errors.bank_branch}>
                    <TextInput
                        type="text"
                        value={data.bank_branch}
                        onChange={(e) => setData('bank_branch', e.target.value)}
                    />
                </FormField>

                <FormField label="Pemegang Akun Bank" error={errors.bank_account_name}>
                    <TextInput
                        type="text"
                        value={data.bank_account_name}
                        onChange={(e) => setData('bank_account_name', e.target.value)}
                    />
                </FormField>

                <FormField label="Nomor Rekening" error={errors.bank_account_number}>
                    <SensitiveInput
                        value={data.bank_account_number}
                        onChange={(e) => setData('bank_account_number', e.target.value)}
                    />
                </FormField>
            </div>
        </section>
    );
}
