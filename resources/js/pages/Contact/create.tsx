import { Head, usePage } from '@inertiajs/react';
import FormActions from '@/components/ui/form-actions';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import type { Auth, Branch } from '@/types';
import BankInfoForm from './BankInfoForm';
import ContactInfoForm from './ContactInfoForm';
import GeneralInfoForm from './GeneralInfoForm';
import { contactLabels } from './types';
import type { ContactType } from './types';
import { useContactForm } from './useContactForm';

type Props = {
    type: ContactType;
    branches: Branch[];
};

export default function Create({ type, branches }: Props) {
    const labels = contactLabels[type];
    const { auth } = usePage<{ auth?: Auth }>().props;
    const form = useContactForm({ type, mode: 'create', branches });

    return (
        <CompanyLayout>
            <Head title={`Add ${labels.singular}`} />

            <div className="mx-auto max-w-7xl space-y-6">
                <PageHeader
                    title={`Add ${labels.singular}`}
                    description={labels.description}
                />

                <form onSubmit={form.submit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <ContactInfoForm
                            type={type}
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            branches={branches}
                            branchScope={auth?.branch_scope}
                        />
                        <GeneralInfoForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            updateAddress={form.updateAddress}
                            handleSameAsBillingChange={form.handleSameAsBillingChange}
                        />
                        <BankInfoForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                        />
                    </div>

                    <FormActions
                        onCancel={form.cancel}
                        submitLabel="Create"
                        processing={form.processing}
                    />
                </form>
            </div>
        </CompanyLayout>
    );
}
