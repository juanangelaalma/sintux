import { calculateTax } from '@/lib/tax/tax-calculator';
import type { DiscountType, SalesLineItemRow, SaleTaxOption } from './types';

export type ComputedLine = {
    line_gross: number;
    discount_amount: number;
    line_net: number;
    tax_amount: number;
    line_total: number;
};

export type InvoiceTotals = {
    lines: ComputedLine[];
    subtotal: number;
    line_discount_total: number;
    net_after_line_discount: number;
    invoice_discount_amount: number;
    tax_amount: number;
    total: number;
};

function discountAmount(
    basis: number,
    type?: DiscountType | '' | null,
    value?: number | null,
): number {
    if (!type || basis <= 0) {
        return 0;
    }

    if (type === 'percent') {
        return (basis * (Number(value) || 0)) / 100;
    }

    return Math.min(Number(value) || 0, basis);
}

/**
 * Preview perhitungan faktur di FE — mirror
 * Modules\Sales\Domain\Rules\CalculateInvoiceTotals. Backend tetap
 * sumber kebenaran; fungsi ini hanya untuk tampilan. Bagian pajak
 * didelegasikan ke helper tunggal lib/tax/tax-calculator.
 */
export function calculateInvoiceTotals(
    items: SalesLineItemRow[],
    taxes: SaleTaxOption[],
    invoiceDiscountType?: DiscountType | '' | null,
    invoiceDiscountValue?: number | null,
    isTaxInclusive = false,
): InvoiceTotals {
    const taxDefById = new Map(taxes.map((t) => [Number(t.id), t]));

    const nets = items.map((item) => {
        const gross = Number(item.qty || 0) * Number(item.unit_price || 0);
        const discount = discountAmount(
            gross,
            item.discount_type,
            item.discount_value,
        );

        return { gross, discount, net: gross - discount };
    });

    const subtotal = nets.reduce((sum, n) => sum + n.gross, 0);
    const lineDiscountTotal = nets.reduce((sum, n) => sum + n.discount, 0);
    const netAfterLineDiscount = subtotal - lineDiscountTotal;
    const invoiceDiscountAmount = discountAmount(
        netAfterLineDiscount,
        invoiceDiscountType,
        invoiceDiscountValue,
    );

    let taxAmount = 0;
    let total = 0;

    const lines: ComputedLine[] = items.map((item, i) => {
        const net = nets[i]?.net ?? 0;
        const allocated =
            netAfterLineDiscount > 0
                ? (invoiceDiscountAmount * net) / netAfterLineDiscount
                : 0;
        const taxable = net - allocated;
        const taxDef =
            item.tax_id != null
                ? taxDefById.get(Number(item.tax_id))
                : undefined;

        const { total: tax } = calculateTax(taxable, taxDef, isTaxInclusive);
        const lineTotal = isTaxInclusive ? taxable : taxable + tax;

        taxAmount += tax;
        total += lineTotal;

        return {
            line_gross: nets[i]?.gross ?? 0,
            discount_amount: nets[i]?.discount ?? 0,
            line_net: net,
            tax_amount: tax,
            line_total: lineTotal,
        };
    });

    return {
        lines,
        subtotal,
        line_discount_total: lineDiscountTotal,
        net_after_line_discount: netAfterLineDiscount,
        invoice_discount_amount: invoiceDiscountAmount,
        tax_amount: taxAmount,
        total,
    };
}
