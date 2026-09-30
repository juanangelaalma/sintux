import { useState } from 'react';

type CurrencyInputProps = {
    ariaLabel: string;
    value: number;
    onChange: (value: number) => void;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
};

/**
 * Format tampilan angka: 138636 -> "138.636" (id-ID, live saat mengetik).
 * Maks 4 desimal, sesuai presisi kolom decimal(15,4) di database.
 */
export function formatRupiahInput(raw: string): string {
    if (!raw) {
        return '';
    }

    const cleaned = raw.replace(/[^0-9,]/g, '');
    const commaIndex = cleaned.indexOf(',');
    const intPart = commaIndex === -1 ? cleaned : cleaned.slice(0, commaIndex);
    const decPart =
        commaIndex === -1
            ? ''
            : cleaned
                  .slice(commaIndex + 1)
                  .replace(/,/g, '')
                  .slice(0, 4);

    const normalizedInt = intPart.replace(/^0+(?=\d)/, '');
    const grouped = normalizedInt.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return commaIndex === -1 ? grouped : `${grouped},${decPart}`;
}

export function parseRupiahInput(text: string): number {
    if (!text) {
        return 0;
    }

    const parsed = Number(text.replace(/\./g, '').replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
}

/**
 * Input nominal rupiah: menampilkan separator ribuan saat mengetik
 * ("138636" -> "138.636"), menyimpan angka mentah ke form.
 */
export default function CurrencyInput({
    ariaLabel,
    value,
    onChange,
    disabled = false,
    placeholder = '0',
    className = '',
}: CurrencyInputProps) {
    const numericValue = Number(value || 0);
    const [prevValue, setPrevValue] = useState(numericValue);
    const [text, setText] = useState(() =>
        numericValue ? formatRupiahInput(String(numericValue)) : '',
    );

    // Sinkronisasi perubahan dari luar (mis. ganti akun me-reset nominal).
    if (prevValue !== numericValue) {
        setPrevValue(numericValue);
        setText(numericValue ? formatRupiahInput(String(numericValue)) : '');
    }

    return (
        <input
            type="text"
            inputMode="decimal"
            aria-label={ariaLabel}
            disabled={disabled}
            placeholder={placeholder}
            className={`w-full min-w-0 border-none bg-transparent px-2 py-1.5 text-right text-xs text-foreground outline-none focus:ring-0 disabled:bg-surface-secondary/50 disabled:text-muted ${className}`}
            value={text}
            onChange={(e) => {
                const formatted = formatRupiahInput(e.target.value);
                setText(formatted);
                onChange(parseRupiahInput(formatted));
            }}
        />
    );
}
