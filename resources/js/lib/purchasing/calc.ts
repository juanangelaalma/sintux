type LineInput = {
    qty: number;
    unitPrice: number;
    taxRate: number;
};

export function getLineTotals({ qty, unitPrice, taxRate }: LineInput, isTaxInclusive: boolean) {
    const lineGross = qty * unitPrice;

    if (isTaxInclusive && taxRate > 0) {
        const lineSubtotal = lineGross / (1 + taxRate / 100);
        const lineTax = lineGross - lineSubtotal;

        return { lineGross, lineSubtotal, lineTax, lineTotal: lineGross };
    }

    const lineSubtotal = lineGross;
    const lineTax = lineSubtotal * (taxRate / 100);

    return { lineGross, lineSubtotal, lineTax, lineTotal: lineSubtotal + lineTax };
}
