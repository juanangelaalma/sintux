import React from 'react';

type SummaryProps = {
    unpaidTotal?: number;
    unpaidCount?: number;
    overdueTotal?: number;
    overdueCount?: number;
    paymentsSentTotal?: number;
    paymentsSentCount?: number;
};

export const PurchasingSummaryCards: React.FC<SummaryProps> = ({
    unpaidTotal = 0,
    unpaidCount = 0,
    overdueTotal = 0,
    overdueCount = 0,
    paymentsSentTotal = 0,
    paymentsSentCount = 0,
}) => {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {/* Card 1: Unpaid Invoices */}
            <div className="rounded-xl border border-amber-300 bg-amber-50/50 p-4 shadow-sm transition-all hover:shadow-md">
                <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-amber-900">Unpaid invoices</span>
                    <span className="inline-flex items-center rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white">
                        {unpaidCount}
                    </span>
                </div>
                <div className="mt-3">
                    <p className="text-[11px] font-medium uppercase text-slate-500">Total</p>
                    <p className="text-lg font-bold text-slate-900">
                        Rp {Number(unpaidTotal).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            </div>

            {/* Card 2: Overdue Invoices */}
            <div className="rounded-xl border border-rose-200 bg-rose-50/50 p-4 shadow-sm transition-all hover:shadow-md">
                <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-rose-900">Overdue invoices</span>
                    <span className="inline-flex items-center rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">
                        {overdueCount}
                    </span>
                </div>
                <div className="mt-3">
                    <p className="text-[11px] font-medium uppercase text-slate-500">Total</p>
                    <p className="text-lg font-bold text-slate-900">
                        Rp {Number(overdueTotal).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            </div>

            {/* Card 3: Payments Sent */}
            <div className="rounded-xl border border-emerald-300 bg-emerald-50/50 p-4 shadow-sm transition-all hover:shadow-md">
                <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-emerald-900">Payments sent last 30 days</span>
                    <span className="inline-flex items-center rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white">
                        {paymentsSentCount}
                    </span>
                </div>
                <div className="mt-3">
                    <p className="text-[11px] font-medium uppercase text-slate-500">Total</p>
                    <p className="text-lg font-bold text-slate-900">
                        Rp {Number(paymentsSentTotal).toLocaleString('id-ID', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            </div>
        </div>
    );
};