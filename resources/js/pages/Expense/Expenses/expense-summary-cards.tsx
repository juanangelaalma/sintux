import { useMemo } from 'react';
import { formatCurrency } from '@/lib/format';
import type { ExpenseSummary } from './types';

type SummaryCardsProps = {
    summary: ExpenseSummary;
    activeFilter: string;
    onFilterChange: (filter: string) => void;
};

type CardConfig = {
    filter: string;
    title: string;
    amountLabel: string;
};

const CARDS: CardConfig[] = [
    {
        filter: 'this_month',
        title: 'Total biaya bulan ini (dalam IDR)',
        amountLabel: 'Total',
    },
    {
        filter: 'last_30_days',
        title: 'Biaya 30 hari terakhir (dalam IDR)',
        amountLabel: 'Total',
    },
    {
        filter: 'unpaid',
        title: 'Biaya belum dibayar (dalam IDR)',
        amountLabel: 'Total',
    },
];

/**
 * Tiga kartu ringkasan (PRD §7.1). Kartu 1 dan 2 hanya menghitung biaya
 * lunas, kartu 3 menampilkan saldo belum lunas lintas semua periode.
 * Klik kartu memfilter daftar lewat query param yang sama dengan predikat
 * backend, jadi angka kartu dan isi tabel tidak pernah berbeda.
 */
export default function ExpenseSummaryCards({
    summary,
    activeFilter,
    onFilterChange,
}: SummaryCardsProps) {
    const values = useMemo(
        () => [summary.this_month, summary.last_30_days, summary.unpaid],
        [summary],
    );

    return (
        <div className="flex flex-col gap-3">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {CARDS.map((card, index) => {
                    const value = values[index];
                    const isActive = activeFilter === card.filter;

                    return (
                        <button
                            key={card.filter}
                            type="button"
                            aria-pressed={isActive}
                            onClick={() =>
                                onFilterChange(isActive ? '' : card.filter)
                            }
                            className={`flex flex-col overflow-hidden rounded-xl border text-start transition-colors ${
                                isActive
                                    ? 'border-accent bg-accent/5'
                                    : 'hover:border-border-strong border-border bg-surface'
                            }`}
                        >
                            <span className="flex items-center justify-between gap-2 bg-accent/10 px-3 py-2 text-xs font-medium text-accent">
                                <span>{card.title}</span>
                                <span className="text-on-accent inline-flex min-w-5 items-center justify-center rounded bg-accent px-1.5 py-0.5 text-[11px]">
                                    {value.count}
                                </span>
                            </span>
                            <span className="flex flex-col gap-0.5 px-3 py-2.5">
                                <span className="text-[11px] text-muted">
                                    {card.amountLabel}
                                </span>
                                <span className="text-base font-semibold text-foreground">
                                    {formatCurrency(value.total)}
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>
            <p className="text-end text-[11px] text-muted">
                Saldo adalah untuk semua jangka waktu, kecuali ada pernyataan
                lain
            </p>
        </div>
    );
}
