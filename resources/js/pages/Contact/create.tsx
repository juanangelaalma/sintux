import { Head } from '@inertiajs/react';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import ContactForm from './ContactForm';
import { contactLabels } from './types';
import type { ContactType } from './types';

type Props = {
    type: ContactType;
};

export default function Create({ type }: Props) {
    const labels = contactLabels[type];

    return (
        <CompanyLayout>
            <Head title={`Add ${labels.singular}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title={`Add ${labels.singular}`}
                    description={labels.description}
                />

                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <ContactForm type={type} mode="create" />
                </div>
            </div>
        </CompanyLayout>
    );
}
