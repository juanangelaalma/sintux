import { Chip, Tabs } from '@heroui/react';
import { Link } from '@inertiajs/react';

export type TabKey =
    'invoices' | 'joins' | 'grns' | 'orders' | 'quotes' | 'requests';

type PurchasingTabsProps = {
    activeTab: TabKey;
};

const TABS: { key: TabKey; label: string; href: string }[] = [
    { key: 'invoices', label: 'Faktur', href: '/purchasing/invoices' },
    { key: 'joins', label: 'Tukar faktur', href: '/purchasing/joins' },
    { key: 'grns', label: 'Penerimaan', href: '/purchasing/grns' },
    { key: 'orders', label: 'Pesanan', href: '/purchasing/orders' },
    { key: 'quotes', label: 'Penawaran', href: '/purchasing/quotes' },
    { key: 'requests', label: 'Permintaan', href: '/purchasing/requests' },
];

export default function PurchasingTabs({ activeTab }: PurchasingTabsProps) {
    return (
        <Tabs className="w-full" selectedKey={activeTab}>
            <Tabs.ListContainer>
                <Tabs.List aria-label="Tab modul pembelian">
                    {TABS.map((tab) => (
                        <Tabs.Tab
                            key={tab.key}
                            id={tab.key}
                            href={tab.href}
                            render={(domProps: any) => <Link {...domProps} />}
                        >
                            {tab.label}
                            <Tabs.Indicator />
                        </Tabs.Tab>
                    ))}
                    <Tabs.Tab
                        id="approval"
                        isDisabled
                        className="flex items-center gap-1.5 opacity-60"
                    >
                        Membutuhkan persetujuan
                        <Chip
                            size="sm"
                            variant="soft"
                            className="h-5 px-1.5 text-xs font-semibold"
                        >
                            0
                        </Chip>
                    </Tabs.Tab>
                </Tabs.List>
            </Tabs.ListContainer>
        </Tabs>
    );
}
