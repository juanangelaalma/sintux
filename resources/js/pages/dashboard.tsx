import { Head } from '@inertiajs/react';
import CompanyLayout from '@/layouts/company/company-layout';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="aspect-video overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900" />
                    <div className="aspect-video overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900" />
                    <div className="aspect-video overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900" />
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-gray-200 md:min-h-min dark:border-gray-800" />
            </div>
        </>
    );
}

Dashboard.layout = (page: any) => <CompanyLayout>{page}</CompanyLayout>;
