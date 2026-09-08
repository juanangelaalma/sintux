export const PurchaseRequestStatus = {
    Draft: 'draft',
    Pending: 'pending',
    Approved: 'approved',
    Cancelled: 'cancelled',
} as const;

export type PurchaseRequestStatusType =
    (typeof PurchaseRequestStatus)[keyof typeof PurchaseRequestStatus];

export const PURCHASE_REQUEST_STATUS_LABEL: Record<
    PurchaseRequestStatusType,
    string
> = {
    [PurchaseRequestStatus.Draft]: 'Draft',
    [PurchaseRequestStatus.Pending]: 'Menunggu',
    [PurchaseRequestStatus.Approved]: 'Disetujui',
    [PurchaseRequestStatus.Cancelled]: 'Dibatalkan',
};

export const PurchaseQuoteStatus = {
    Draft: 'draft',
    Sent: 'sent',
    Accepted: 'accepted',
    Cancelled: 'cancelled',
} as const;

export type PurchaseQuoteStatusType =
    (typeof PurchaseQuoteStatus)[keyof typeof PurchaseQuoteStatus];

export const PURCHASE_QUOTE_STATUS_LABEL: Record<
    PurchaseQuoteStatusType,
    string
> = {
    [PurchaseQuoteStatus.Draft]: 'Draft',
    [PurchaseQuoteStatus.Sent]: 'Terkirim',
    [PurchaseQuoteStatus.Accepted]: 'Disetujui',
    [PurchaseQuoteStatus.Cancelled]: 'Dibatalkan',
};

export const PurchaseOrderStatus = {
    Draft: 'draft',
    Pending: 'pending',
    Approved: 'approved',
    Sent: 'sent',
    PartiallyReceived: 'partially_received',
    Received: 'received',
    Cancelled: 'cancelled',
} as const;

export type PurchaseOrderStatusType =
    (typeof PurchaseOrderStatus)[keyof typeof PurchaseOrderStatus];

export const PURCHASE_ORDER_STATUS_LABEL: Record<
    PurchaseOrderStatusType,
    string
> = {
    [PurchaseOrderStatus.Draft]: 'Draft',
    [PurchaseOrderStatus.Pending]: 'Menunggu',
    [PurchaseOrderStatus.Approved]: 'Disetujui',
    [PurchaseOrderStatus.Sent]: 'Terkirim',
    [PurchaseOrderStatus.PartiallyReceived]: 'Sebagian Diterima',
    [PurchaseOrderStatus.Received]: 'Diterima',
    [PurchaseOrderStatus.Cancelled]: 'Dibatalkan',
};

export const GoodsReceiptStatus = {
    Draft: 'draft',
    Posted: 'posted',
} as const;

export type GoodsReceiptStatusType =
    (typeof GoodsReceiptStatus)[keyof typeof GoodsReceiptStatus];

export const GOODS_RECEIPT_STATUS_LABEL: Record<
    GoodsReceiptStatusType,
    string
> = {
    [GoodsReceiptStatus.Draft]: 'Draft',
    [GoodsReceiptStatus.Posted]: 'Diposting',
};

export const PurchaseInvoiceStatus = {
    Draft: 'draft',
    Pending: 'pending',
    Approved: 'approved',
    Paid: 'paid',
    Cancelled: 'cancelled',
} as const;

export type PurchaseInvoiceStatusType =
    (typeof PurchaseInvoiceStatus)[keyof typeof PurchaseInvoiceStatus];

export const PURCHASE_INVOICE_STATUS_LABEL: Record<
    PurchaseInvoiceStatusType,
    string
> = {
    [PurchaseInvoiceStatus.Draft]: 'Draft',
    [PurchaseInvoiceStatus.Pending]: 'Menunggu',
    [PurchaseInvoiceStatus.Approved]: 'Disetujui',
    [PurchaseInvoiceStatus.Paid]: 'Lunas',
    [PurchaseInvoiceStatus.Cancelled]: 'Dibatalkan',
};

export const JoinPurchaseInvoiceStatus = {
    Draft: 'draft',
    Ready: 'ready',
} as const;

export type JoinPurchaseInvoiceStatusType =
    (typeof JoinPurchaseInvoiceStatus)[keyof typeof JoinPurchaseInvoiceStatus];

export const JOIN_PURCHASE_INVOICE_STATUS_LABEL: Record<
    JoinPurchaseInvoiceStatusType,
    string
> = {
    [JoinPurchaseInvoiceStatus.Draft]: 'Draft',
    [JoinPurchaseInvoiceStatus.Ready]: 'Siap',
};

export const PURCHASING_STATUS_LABEL: Record<string, string> = {
    ...PURCHASE_REQUEST_STATUS_LABEL,
    ...PURCHASE_QUOTE_STATUS_LABEL,
    ...PURCHASE_ORDER_STATUS_LABEL,
    ...GOODS_RECEIPT_STATUS_LABEL,
    ...PURCHASE_INVOICE_STATUS_LABEL,
    ...JOIN_PURCHASE_INVOICE_STATUS_LABEL,
};
