/**
 * Mirror TypeScript dari Modules\Accounting\Application\TaxCalculator.
 * Hanya untuk preview di FE — backend tetap sumber kebenaran angka.
 *
 * Aturan: pengali 11/12, majemuk (basis = net + pajak sebelumnya,
 * intermediate tanpa pembulatan), pembulatan 2 desimal half-up per anggota,
 * total = jumlah nilai bulat.
 */

export type TaxMemberDef = {
    id: number;
    signed_rate?: number | string;
    rate?: number | string;
    is_compound?: boolean;
    dpp_multiplier?: boolean;
};

export type TaxDef = {
    id: number;
    rate: number | string;
    type?: string;
    dpp_multiplier?: boolean;
    members?: TaxMemberDef[];
};

export type TaxBreakdownItem = {
    tax_id: number;
    rate: number;
    amount: number;
};

type NormalizedMember = {
    id: number;
    signed_rate: number;
    coefficient: number;
    is_compound: boolean;
};

const DPP_FACTOR = 11 / 12;

function roundHalfUp(value: number, decimals = 2): number {
    const factor = 10 ** decimals;

    return (
        (Math.sign(value) *
            Math.round(Math.abs(value) * factor + Number.EPSILON)) /
        factor
    );
}

function coefficient(signedRate: number, dppMultiplier: boolean): number {
    return (dppMultiplier ? DPP_FACTOR : 1) * (signedRate / 100);
}

function normalize(taxDef: TaxDef): NormalizedMember[] {
    if ((taxDef.type ?? 'single') === 'group') {
        return (taxDef.members ?? []).map((member) => {
            const signedRate = Number(member.signed_rate ?? member.rate ?? 0);
            const dppMultiplier = Boolean(member.dpp_multiplier);

            return {
                id: Number(member.id),
                signed_rate: signedRate,
                coefficient: coefficient(signedRate, dppMultiplier),
                is_compound: Boolean(member.is_compound),
            };
        });
    }

    const rate = Math.abs(Number(taxDef.rate ?? 0));
    const dppMultiplier = Boolean(taxDef.dpp_multiplier);

    return [
        {
            id: Number(taxDef.id),
            signed_rate: rate,
            coefficient: coefficient(rate, dppMultiplier),
            is_compound: false,
        },
    ];
}

function extractNet(gross: number, members: NormalizedMember[]): number {
    let accumulated = 0;

    for (const member of members) {
        accumulated += member.is_compound
            ? member.coefficient * (1 + accumulated)
            : member.coefficient;
    }

    const divisor = 1 + accumulated;

    return divisor > 0 ? gross / divisor : gross;
}

export function calculateTax(
    taxable: number,
    taxDef?: TaxDef | null,
    isTaxInclusive = false,
): { breakdown: TaxBreakdownItem[]; total: number } {
    if (!taxDef || !(taxable > 0)) {
        return { breakdown: [], total: 0 };
    }

    const members = normalize(taxDef);

    if (members.length === 0) {
        return { breakdown: [], total: 0 };
    }

    const net = isTaxInclusive ? extractNet(taxable, members) : taxable;

    const breakdown: TaxBreakdownItem[] = [];
    let rawAccumulated = 0;
    let total = 0;

    for (const member of members) {
        const basis = member.is_compound ? net + rawAccumulated : net;
        const raw = basis * member.coefficient;
        rawAccumulated += raw;

        const amount = roundHalfUp(raw, 2);
        total += amount;

        breakdown.push({ tax_id: member.id, rate: member.signed_rate, amount });
    }

    return { breakdown, total };
}
