import { Head } from '@inertiajs/react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import SettingsLayout from '@/layouts/settings/layout';

export default function Appearance() {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; label: string }[] = [
        { value: 'light', label: 'Light' },
        { value: 'dark', label: 'Dark' },
        { value: 'system', label: 'System' },
    ];

    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <div className="space-y-6">
                <header>
                    <h2 className="mb-0.5 text-base font-medium">Appearance settings</h2>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Update the appearance settings for your account</p>
                </header>
                <div className="inline-flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                    {tabs.map(({ value, label }) => (
                        <button
                            key={value}
                            onClick={() => updateAppearance(value)}
                            className={`flex items-center rounded-md px-3.5 py-1.5 text-sm transition-colors ${
                                appearance === value
                                    ? 'bg-white shadow-xs dark:bg-gray-700 dark:text-gray-100'
                                    : 'text-gray-500 hover:bg-gray-200/60 hover:text-black dark:text-gray-400 dark:hover:bg-gray-700/60'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>
        </>
    );
}

Appearance.layout = (page: any) => <SettingsLayout>{page}</SettingsLayout>;
