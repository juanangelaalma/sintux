import { Head, Link } from '@inertiajs/react';
import React from 'react';
import PageHeader from '@/components/ui/page-header';
import SettingsLayout from '@/layouts/settings/layout';

type Log = {
    id: number;
    changed_by?: number | null;
    change_type: 'created' | 'updated' | 'deleted';
    before_value?: any;
    after_value?: any;
    changed_at: string;
};

type Rule = {
    id: number;
    name: string;
};

type Props = {
    rule: Rule;
    logs: Log[];
};

export default function Logs({ rule, logs }: Props) {
    return (
        <SettingsLayout>
            <Head title={`Log Perubahan: ${rule.name}`} />

            <div className="max-w-4xl space-y-6">
                <PageHeader
                    title={`Log Perubahan: ${rule.name}`}
                    description="Riwayat audit trail pembuatan dan perubahan aturan approval ini."
                    actions={
                        <Link href="/approval/rules">
                            <span className="text-xs font-medium text-brand-600 hover:underline">
                                ← Kembali ke daftar aturan
                            </span>
                        </Link>
                    }
                />

                {logs.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900">
                        Belum ada riwayat perubahan untuk aturan ini.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {logs.map((log) => (
                            <div
                                key={log.id}
                                className="space-y-3 rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-semibold tracking-wider text-gray-900 uppercase dark:text-white">
                                            {log.change_type}
                                        </span>
                                        <span className="text-xs text-gray-500">
                                            oleh User #
                                            {log.changed_by ?? 'System'}
                                        </span>
                                    </div>
                                    <span className="text-xs text-gray-400">
                                        {new Date(
                                            log.changed_at,
                                        ).toLocaleString('id-ID')}
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 gap-4 font-mono text-xs md:grid-cols-2">
                                    {log.before_value && (
                                        <div className="rounded border border-rose-100 bg-rose-50/50 p-3 dark:border-rose-900/30 dark:bg-rose-950/20">
                                            <div className="mb-1 font-sans font-semibold text-rose-700 dark:text-rose-400">
                                                Sebelum:
                                            </div>
                                            <pre className="overflow-x-auto text-[11px]">
                                                {JSON.stringify(
                                                    log.before_value,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </div>
                                    )}

                                    {log.after_value && (
                                        <div className="rounded border border-emerald-100 bg-emerald-50/50 p-3 dark:border-emerald-900/30 dark:bg-emerald-950/20">
                                            <div className="mb-1 font-sans font-semibold text-emerald-700 dark:text-emerald-400">
                                                Sesudah:
                                            </div>
                                            <pre className="overflow-x-auto text-[11px]">
                                                {JSON.stringify(
                                                    log.after_value,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </SettingsLayout>
    );
}
