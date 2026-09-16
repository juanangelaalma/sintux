import { calculateTax } from '@/lib/tax/tax-calculator';
import type { TaxDef } from '@/lib/tax/tax-calculator';

type LineInput = {
    qty: number;
    unitPrice: number;
    taxRate?: number;
    taxDef?: TaxDef | null;
};

export function getLineTotals(
    { qty, unitPrice, taxRate = 0, taxDef }: LineInput,
    isTaxInclusive: boolean,
) {
    const lineGross = qty * unitPrice;
    const def: TaxDef | null =
        taxDef ?? (taxRate > 0 ? { id: 0, rate: taxRate } : null);

    const { total: lineTax } = calculateTax(lineGross, def, isTaxInclusive);

    if (isTaxInclusive) {
        const lineSubtotal = lineGross - lineTax;

        return { lineGross, lineSubtotal, lineTax, lineTotal: lineGross };
    }

    const lineSubtotal = lineGross;

    return {
        lineGross,
        lineSubtotal,
        lineTax,
        lineTotal: lineSubtotal + lineTax,
    };
}
