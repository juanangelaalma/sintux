import { Chip, Tabs } from '@heroui/react';
import { Link, usePage } from '@inertiajs/react';
import { useFontsReady } from '@/hooks/use-fonts-ready';

export type TabKey =
    'invoices' | 'joins' | 'grns' | 'orders' | 'quotes' | 'requests';

type PurchasingTabsProps = {
    activeTab: TabKey;
    pendingGrnCount?: number;
};

type TabDef = {
    key: TabKey;
    label: string;
    href: string;
    hqOnly?: boolean;
};

const TABS: TabDef[] = [
    {
        key: 'invoices',
        label: 'Faktur',
        href: '/purchasing/invoices',
        hqOnly: true,
    },
    {
        key: 'joins',
        label: 'Tukar faktur',
        href: '/purchasing/joins',
        hqOnly: true,
    },
    {
        key: 'grns',
        label: 'GRN',
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
    {
        key: 'requests',
        label: 'Permintaan',
        href: '/purchasing/requests',
        hqOnly: true,
    },
];

export default function PurchasingTabs({
    activeTab,
    pendingGrnCount,
}: PurchasingTabsProps) {
    const { props } = usePage();
    const fontsReady = useFontsReady();
    const auth = (props.auth ?? {}) as { is_hq?: boolean };
    const shared = props.pendingGrnCount as number | undefined;
    const isHq = auth.is_hq ?? false;

    const tabs = TABS.filter((tab) => !tab.hqOnly || isHq);
    const pendingCount = Math.max(0, pendingGrnCount ?? shared ?? 0);

    if (tabs.length === 0) {
        return null;
    }

    const showIndicator = (tabKey: TabKey) =>
        fontsReady && tabKey === activeTab;

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
                            <span className="flex items-center gap-1.5">
                                {tab.label}
                                {tab.key === 'grns' && isHq && (
                                    <Chip
                                        size="sm"
                                        variant="soft"
                                        className="h-5 px-1.5 text-xs font-semibold"
                                    >
                                        {pendingCount}
                                    </Chip>
                                )}
                            </span>
                            {showIndicator(tab.key) && <Tabs.Indicator />}
                        </Tabs.Tab>
                    ))}
                </Tabs.List>
            </Tabs.ListContainer>
        </Tabs>
    );
}
