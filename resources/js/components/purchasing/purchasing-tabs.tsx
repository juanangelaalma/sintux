import { Chip, Tabs } from '@heroui/react';
import { Link, usePage } from '@inertiajs/react';

export type TabKey =
    'invoices' | 'joins' | 'grns' | 'orders' | 'quotes' | 'requests';

type PurchasingTabsProps = {
    activeTab: TabKey;
};

const ALL_TABS: {
    key: TabKey;
    label: string;
    href: string;
    hqOnly?: boolean;
}[] = [
    { key: 'invoices', label: 'Faktur', href: '/purchasing/invoices' },
    { key: 'joins', label: 'Tukar faktur', href: '/purchasing/joins' },
    {
        key: 'grns',
        label: 'Penerimaan',
        href: '/purchasing/grns',
        hqOnly: true,
    },
    {
        key: 'orders',
        label: 'Pesanan',
        href: '/purchasing/orders',
        hqOnly: true,
    },
    {
        key: 'quotes',
        label: 'Penawaran',
        href: '/purchasing/quotes',
        hqOnly: true,
    },
    { key: 'requests', label: 'Permintaan', href: '/purchasing/requests' },
];

export default function PurchasingTabs({ activeTab }: PurchasingTabsProps) {
    const { props } = usePage();
    const auth = (props.auth ?? {}) as { is_hq?: boolean };
    const isHq = auth.is_hq ?? false;

    const tabs = ALL_TABS.filter((tab) => !tab.hqOnly || isHq);

    return (
        <Tabs className="w-full" selectedKey={activeTab}>
            <Tabs.ListContainer>
                <Tabs.List aria-label="Tab modul pembelian">
                    {tabs.map((tab) => (
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
                    {isHq && (
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
                    )}
                </Tabs.List>
            </Tabs.ListContainer>
        </Tabs>
    );
}
