import { Button, Label, SearchField } from '@heroui/react';
import { X } from 'lucide-react';

type ExpenseFilterBarProps = {
    value: string;
    onChange: (search: string) => void;
};

/**
 * Baris pencarian di panel "Daftar biaya". Hanya ada pencarian: PRD §7.1
 * tidak meminta filter status, filter tag terpisah, maupun ekspor (Impor
 * menyusul di Fase 2). Pencarian mencakup nomor biaya, kategori akun biaya,
 * dan tag karena backend tangani ketiganya dalam satu parameter.
 *
 * Fully controlled: state reside di halaman supaya tidak perlu menyinkronkan
 * dua arah antara nilai server dan input.
 */
export default function ExpenseFilterBar({
    value,
    onChange,
}: ExpenseFilterBarProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="w-full sm:w-72">
                <SearchField
                    fullWidth
                    value={value}
                    onChange={(next) => onChange(next)}
                    aria-label="Cari biaya"
                >
                    <Label className="sr-only">Cari biaya</Label>
                    <SearchField.Group>
                        <SearchField.SearchIcon />
                        <SearchField.Input placeholder="Nomor biaya, kategori akun, tag..." />
                        <SearchField.ClearButton />
                    </SearchField.Group>
                </SearchField>
            </div>

            {value !== '' && (
                <Button
                    variant="tertiary"
                    className="gap-1.5 text-muted"
                    onPress={() => onChange('')}
                >
                    <X className="size-4" />
                    Reset
                </Button>
            )}
        </div>
    );
}
