import { Head, usePage } from '@inertiajs/react';
import CompanyLayout from '@/layouts/company/company-layout';

type BranchSummary = {
    id: number;
    name: string;
    code: string;
    is_headquarters: boolean;
    contacts: {
        customers: number;
        suppliers: number;
        employees: number;
    };
};

type PageProps = {
    branchSummaries?: BranchSummary[];
};

export default function Dashboard() {
    const { props } = usePage<PageProps>();
    const branchSummaries = props.branchSummaries ?? [];
    const auth = props.auth as {
        branch_scope?: 'all' | 'branch' | null;
        branch?: { name: string } | null;
        branches?: { id: number; name: string }[];
    } | undefined;

    const scopeLabel =
        auth?.branch_scope === 'all'
            ? (auth.branches?.length ?? 0) > 1
                ? 'Semua cabang'
                : 'Semua cabang'
            : auth?.branch?.name ?? '';

    const totalContacts = branchSummaries.reduce(
        (sum, summary) =>
            sum +
            summary.contacts.customers +
            summary.contacts.suppliers +
            summary.contacts.employees,
        0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                        Dashboard
                    </h1>
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                        {scopeLabel ? `Cakupan: ${scopeLabel}` : 'Ringkasan cabang'}
                    </p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            Total kontak
                        </p>
                        <p className="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                            {totalContacts}
                        </p>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            Cabang diakses
                        </p>
                        <p className="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                            {branchSummaries.length}
                        </p>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                        <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                            Pelanggan
                        </p>
                        <p className="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                            {branchSummaries.reduce((sum, s) => sum + s.contacts.customers, 0)}
                        </p>
                    </div>
                </div>

                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-gray-200 md:min-h-min dark:border-gray-800">
                    <div className="overflow-x-auto p-5">
                        <table className="w-full text-left text-theme-sm">
                            <thead>
                                <tr className="border-b border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <th className="pb-3 pr-4 font-medium">Cabang</th>
                                    <th className="pb-3 pr-4 font-medium">Kode</th>
                                    <th className="pb-3 pr-4 font-medium">Customer</th>
                                    <th className="pb-3 pr-4 font-medium">Supplier</th>
                                    <th className="pb-3 pr-4 font-medium">Employee</th>
                                    <th className="pb-3 font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {branchSummaries.map((summary) => (
                                    <tr
                                        key={summary.id}
                                        className="border-b border-gray-100 last:border-0 dark:border-gray-800"
                                    >
                                        <td className="py-3 pr-4 font-medium text-gray-900 dark:text-white">
                                            {summary.name}
                                            {summary.is_headquarters && (
                                                <span className="ml-2 rounded bg-brand-50 px-1.5 py-0.5 text-theme-xs font-medium text-brand-700 dark:bg-white/5 dark:text-brand-300">
                                                    HQ
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3 pr-4 text-gray-500 dark:text-gray-400">
                                            {summary.code}
                                        </td>
                                        <td className="py-3 pr-4 text-gray-700 dark:text-gray-300">
                                            {summary.contacts.customers}
                                        </td>
                                        <td className="py-3 pr-4 text-gray-700 dark:text-gray-300">
                                            {summary.contacts.suppliers}
                                        </td>
                                        <td className="py-3 pr-4 text-gray-700 dark:text-gray-300">
                                            {summary.contacts.employees}
                                        </td>
                                        <td className="py-3 font-medium text-gray-900 dark:text-white">
                                            {summary.contacts.customers +
                                                summary.contacts.suppliers +
                                                summary.contacts.employees}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: any) => <CompanyLayout>{page}</CompanyLayout>;
