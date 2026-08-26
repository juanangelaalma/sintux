import { Head, usePage } from '@inertiajs/react';
import FormActions from '@/components/ui/form-actions';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import type { Auth, Branch } from '@/types';
import BankInfoForm from './BankInfoForm';
import ContactInfoForm from './ContactInfoForm';
import GeneralInfoForm from './GeneralInfoForm';
import { contactLabels } from './types';
import type { Contact, ContactType } from './types';
import { useContactForm } from './useContactForm';

type Props = {
    type: ContactType;
    contact: Contact;
    branches: Branch[];
};

export default function Edit({ type, contact, branches }: Props) {
    const labels = contactLabels[type];
    const { auth } = usePage<{ auth?: Auth }>().props;
    const form = useContactForm({ type, mode: 'edit', contact, branches });

    return (
        <CompanyLayout>
            <Head title={`Edit ${labels.singular}`} />

            <div className="mx-auto max-w-7xl space-y-6">
                <PageHeader
                    title={`Edit ${labels.singular}`}
                    description={`Update ${labels.singular.toLowerCase()} information.`}
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
                            handleSameAsBillingChange={
                                form.handleSameAsBillingChange
                            }
                        />
                        <BankInfoForm
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                        />
                    </div>

                    <FormActions
                        onCancel={form.cancel}
                        submitLabel="Update"
                        processing={form.processing}
                    />
                </form>
            </div>
        </CompanyLayout>
    );
}
