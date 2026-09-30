import { calculateTax } from '@/lib/tax/tax-calculator';
import type {
    ExpenseLineRow,
    TaxDefinition,
    TaxOption,
    WithholdingRow,
} from './types';

export type ExpenseTotals = {
    subtotal: number;
    taxTotal: number;
    withholdingTotal: number;
    grandTotal: number;
};

const PRECISION = 4;

function round(value: number): number {
    const factor = 10 ** PRECISION;

    return (
        (Math.sign(value) *
            Math.round(Math.abs(value) * factor + Number.EPSILON)) /
        factor
    );
}

/**
 * Cermin dari CalculateExpenseTotals di backend, untuk pratinjau total live
 * di form. Backend tetap sumber kebenaran; angka di sini hanya tampilan
 * sebelum disimpan.
 */
export function calculateExpenseTotals(
    lines: ExpenseLineRow[],
    taxes: TaxOption[],
    withholding: WithholdingRow,
    isTaxInclusive: boolean,
): ExpenseTotals {
    const taxesById = new Map(taxes.map((tax) => [Number(tax.id), tax]));

    let subtotal = 0;
    let taxTotal = 0;

    for (const line of lines) {
        const amount = round(Number(line.amount) || 0);
        const taxId = line.tax_id === null ? null : Number(line.tax_id);
        const tax = taxId === null ? null : (taxesById.get(taxId) ?? null);
        const taxDef: TaxDefinition | null = tax
            ? {
                  id: Number(tax.id),
                  rate: Number(tax.rate),
                  type: tax.type,
                  dpp_multiplier: tax.dpp_multiplier,
                  members: tax.members?.map((member) => ({
                      id: Number(member.id),
                      signed_rate: Number(member.signed_rate),
                      is_compound: Boolean(member.is_compound),
                      dpp_multiplier: Boolean(member.dpp_multiplier),
                  })),
              }
            : null;

        const lineTax = round(
            calculateTax(amount, taxDef, isTaxInclusive).total,
        );
        const base = isTaxInclusive ? round(amount - lineTax) : amount;

        subtotal += base;
        taxTotal += lineTax;
    }

    subtotal = round(subtotal);
    taxTotal = round(taxTotal);

    const withholdingTotal = calculateWithholding(subtotal, withholding);

    return {
        subtotal,
        taxTotal,
        withholdingTotal,
        grandTotal: round(subtotal + taxTotal - withholdingTotal),
    };
}

/**
 * BR-05: persen dihitung dari nilai sebelum pajak (subtotal DPP).
 */
export function calculateWithholding(
    subtotal: number,
    withholding: WithholdingRow,
): number {
    if (!withholding.type) {
        return 0;
    }

    const value = Number(withholding.value) || 0;

    if (withholding.type === 'percent') {
        return round(subtotal * (value / 100));
    }

    return round(value);
}
