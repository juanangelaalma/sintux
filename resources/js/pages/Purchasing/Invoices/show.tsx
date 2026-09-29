import { Button, Dropdown, Label } from '@heroui/react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import CompanyLayout from '@/layouts/company/company-layout';
import {
    InvoiceItemsPanel,
    InvoiceJournalModal,
    InvoiceMetaPanel,
    InvoiceSummaryPanel,
    InvoiceTabsPanel,
} from './invoice-detail-panels';
import type {
    InvoiceDocument,
    InvoiceJournal,
    InvoicePayment,
    InvoiceReturn,
    InvoiceSupplier,
} from './invoice-detail-panels';

type Props = {
    purchaseInvoice: InvoiceDocument;
    supplier: InvoiceSupplier;
    payments: InvoicePayment[];
    journal: InvoiceJournal | null;
    approval?: ApprovalStatusProps | null;
    returnContext?: {
        canReturn: boolean;
        returns: InvoiceReturn[];
    };
    paymentContext?: {
        canPay: boolean;
        outstanding: number;
    };
};

export default function PurchaseInvoicesShow({
    purchaseInvoice,
    supplier,
    payments,
    journal,
    approval,
    returnContext,
    paymentContext,
}: Props) {
    const [isJournalOpen, setIsJournalOpen] = useState(false);
    const canReturn = returnContext?.canReturn ?? false;
    const canPay = paymentContext?.canPay ?? false;
    const hasDocumentActions = canReturn || canPay;

    return (
        <CompanyLayout>
            <Head title={`Faktur Pembelian #${purchaseInvoice.number}`} />
            <div className="w-full">
                <div className="overflow-hidden border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <div className="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8 dark:border-gray-800">
                        <div className="flex min-w-0 items-center gap-3">
                            <div className="min-w-0">
                                <p className="text-xs font-semibold tracking-wide text-brand-600 uppercase dark:text-brand-400">
                                    Pembelian
                                </p>
                                <h1 className="mt-1 truncate text-xl font-bold text-gray-950 sm:text-2xl dark:text-gray-50">
                                    Faktur Pembelian #{purchaseInvoice.number}
                                </h1>
                            </div>
                            <PurchasingStatusBadge
                                status={purchaseInvoice.status}
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Link href="/purchasing/invoices">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            {hasDocumentActions ? (
                                <Dropdown>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        className="gap-2 font-semibold"
                                    >
                                        Tindakan
                                        <ChevronDown
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                    <Dropdown.Popover>
                                        <Dropdown.Menu
                                            onAction={(key) =>
                                                router.get(
                                                    String(key),
                                                    {},
                                                    { preserveState: true },
                                                )
                                            }
                                        >
                                            {canPay ? (
                                                <Dropdown.Item
                                                    key={`/purchase-payments/new?createdFrom=${purchaseInvoice.id}`}
                                                    id={`/purchase-payments/new?createdFrom=${purchaseInvoice.id}`}
                                                    textValue="Kirim pembayaran"
                                                >
                                                    <Label>
                                                        Kirim pembayaran
                                                    </Label>
                                                </Dropdown.Item>
                                            ) : null}
                                            {canReturn ? (
                                                <Dropdown.Item
                                                    key={`/purchasing/returns/new?createdFrom=${purchaseInvoice.id}`}
                                                    id={`/purchasing/returns/new?createdFrom=${purchaseInvoice.id}`}
                                                    textValue="Retur pembelian"
                                                >
                                                    <Label>
                                                        Retur pembelian
                                                    </Label>
                                                </Dropdown.Item>
                                            ) : null}
                                        </Dropdown.Menu>
                                    </Dropdown.Popover>
                                </Dropdown>
                            ) : null}
                            <ApprovalHeaderControls
                                approval={approval}
                                documentTitle={purchaseInvoice.number}
                            />
                        </div>
                    </div>

                    <InvoiceMetaPanel
                        invoice={purchaseInvoice}
                        supplier={supplier}
                        journal={journal}
                        onOpenJournal={() => setIsJournalOpen(true)}
                    />
                    <InvoiceItemsPanel items={purchaseInvoice.items} />
                    <InvoiceSummaryPanel invoice={purchaseInvoice} />
                    <InvoiceTabsPanel
                        invoiceId={purchaseInvoice.id}
                        canPay={canPay}
                        payments={payments}
                        returns={returnContext?.returns ?? []}
                    />
                </div>
            </div>

            {isJournalOpen && journal ? (
                <InvoiceJournalModal
                    invoiceNumber={purchaseInvoice.number}
                    journal={journal}
                    onClose={() => setIsJournalOpen(false)}
                />
            ) : null}
        </CompanyLayout>
    );
}
