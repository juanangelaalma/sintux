import React from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin/admin-layout';

export default function AdminDashboard() {
    return (
        <>
            <Head title="Admin Dashboard" />
            <div className="space-y-6">
                <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                    <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">Sintux Admin Panel</h1>
                    <p className="mt-2 text-gray-600 dark:text-gray-400">
                        Welcome to Sintux Platform Admin. Manage tenant companies, global users, and platform settings.
                    </p>
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = (page: any) => <AdminLayout>{page}</AdminLayout>;
