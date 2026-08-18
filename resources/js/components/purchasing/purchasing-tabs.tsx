import { Link } from '@inertiajs/react';
import React from 'react';

type TabKey = 'invoices' | 'joins' | 'grns' | 'orders' | 'quotes' | 'requests';

type PurchasingTabsProps = {
    activeTab: TabKey;
};

export const PurchasingTabs: React.FC<PurchasingTabsProps> = ({ activeTab }) => {
    const tabs: { key: TabKey; label: string; href: string }[] = [
        { key: 'invoices', label: 'Invoice', href: '/purchasing/invoices' },
        { key: 'joins', label: 'Join invoice', href: '/purchasing/joins' },
        { key: 'grns', label: 'Delivery', href: '/purchasing/grns' },
        { key: 'orders', label: 'Order', href: '/purchasing/orders' },
        { key: 'quotes', label: 'Quotation', href: '/purchasing/quotes' },
        { key: 'requests', label: 'Request', href: '/purchasing/requests' },
    ];

    return (
        <div className="border-b border-slate-200">
            <nav className="-mb-px flex space-x-6 overflow-x-auto">
                {tabs.map((tab) => {
                    const isActive = activeTab === tab.key;

                    return (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            className={`whitespace-nowrap pb-3 text-sm font-medium transition-colors ${
                                isActive
                                    ? 'border-b-2 border-brand-600 font-semibold text-brand-600'
                                    : 'text-slate-500 hover:border-slate-300 hover:text-slate-700'
                            }`}
                        >
                            {tab.label}
                        </Link>
                    );
                })}
                <span className="cursor-not-allowed whitespace-nowrap pb-3 text-sm font-medium text-slate-400">
                    Require approval <span className="rounded-full bg-slate-200 px-1.5 py-0.5 text-xs text-slate-600">0</span>
                </span>
            </nav>
        </div>
    );
};