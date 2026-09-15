export const SalesInvoiceStatus = {
    Pending: 'pending',
    Approved: 'approved',
    Cancelled: 'cancelled',
} as const;

export type SalesInvoiceStatusType =
    (typeof SalesInvoiceStatus)[keyof typeof SalesInvoiceStatus];

export const SALES_INVOICE_STATUS_LABEL: Record<
    SalesInvoiceStatusType,
    string
> = {
    [SalesInvoiceStatus.Pending]: 'Menunggu',
    [SalesInvoiceStatus.Approved]: 'Disetujui',
    [SalesInvoiceStatus.Cancelled]: 'Dibatalkan',
};
