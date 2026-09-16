export function formatCurrency(
    amount: number | string | null | undefined,
): string {
    const num = Number(amount || 0);

    return `Rp. ${num.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export function formatDate(dateString: string | null | undefined): string {
    if (!dateString) {
        return '-';
    }

    try {
        return new Date(dateString).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return String(dateString);
    }
}

/**
 * Format kuantitas: 50.0000 -> "50", 1.5000 -> "1,5".
 * Desimal berlebih dipangkas, ribuan memakai titik (id-ID).
 */
export function formatQty(value: number | string | null | undefined): string {
    const num = Number(value ?? 0);

    if (!Number.isFinite(num)) {
        return '0';
    }

    return num.toLocaleString('id-ID', { maximumFractionDigits: 4 });
}
