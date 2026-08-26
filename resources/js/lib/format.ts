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
