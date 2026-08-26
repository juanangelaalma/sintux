import { Button, Dropdown, Label } from '@heroui/react';
import { router } from '@inertiajs/react';
import { ChevronDown, Download, Plus } from 'lucide-react';

const CREATE_ITEMS: { label: string; href: string }[] = [
    { label: 'Faktur pembelian', href: '/purchasing/invoices/create' },
    { label: 'Tukar faktur', href: '/purchasing/joins/create' },
    { label: 'Pesanan pembelian', href: '/purchasing/orders/create' },
    { label: 'Penawaran harga', href: '/purchasing/quotes/create' },
    { label: 'Permintaan pembelian', href: '/purchasing/requests/create' },
];

export default function PurchasingHeaderDropdown() {
    return (
        <div className="flex items-center gap-2">
            <Button variant="secondary" className="gap-1.5 font-medium">
                <Download className="size-4 text-muted" />
                Impor
            </Button>

            <Dropdown>
                <Button variant="primary" className="gap-2 font-semibold">
                    <Plus className="size-4" />
                    Buat pembelian baru
                    <ChevronDown className="size-4" />
                </Button>
                <Dropdown.Popover>
                    <Dropdown.Menu
                        onAction={(key) =>
                            router.get(String(key), {}, { preserveState: true })
                        }
                    >
                        {CREATE_ITEMS.map((item) => (
                            <Dropdown.Item
                                key={item.href}
                                id={item.href}
                                textValue={item.label}
                            >
                                <Label>{item.label}</Label>
                            </Dropdown.Item>
                        ))}
                    </Dropdown.Menu>
                </Dropdown.Popover>
            </Dropdown>
        </div>
    );
}
