export function formatCurrency(
    amount: number | string | null | undefined,
): string {
    const num = Number(amount || 0);

    return `Rp. ${num.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/**
 * Format qty barang: potong nol berlebih (100.0000 → "100") tapi
 * pertahankan desimal yang bermakna (10,5). Maks 4 desimal sesuai
 * presisi kolom database.
 */
export function formatQty(value: number | string | null | undefined): string {
    const num = Number(value ?? 0);

    if (!Number.isFinite(num)) {
        return '-';
    }

    return num.toLocaleString('id-ID', {
        maximumFractionDigits: 4,
    });
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
