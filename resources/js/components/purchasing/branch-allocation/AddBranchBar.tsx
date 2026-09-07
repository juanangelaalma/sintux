import { Button } from '@heroui/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import type { Branch } from './types';

type Props = {
    availableBranches: Branch[];
    initialDate: string;
    onAdd: (branchId: number | string, dateStr: string) => void;
    error?: string;
};

export function AddBranchBar({ availableBranches, initialDate, onAdd, error }: Props) {
    const [isPicking, setIsPicking] = useState(false);
    const [pick, setPick] = useState<string>('');
    const [date, setDate] = useState<string>(initialDate);

    const handleAdd = () => {
        const branchId = pick || String(availableBranches[0]?.id ?? '');

        if (!branchId) {
            return;
        }

        onAdd(branchId, date || initialDate);
        setPick('');
        setIsPicking(false);
    };

    if (availableBranches.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-border bg-surface-secondary/20 px-4 py-3 text-center text-xs text-muted">
                Semua cabang sudah dialokasi
            </div>
        );
    }

    if (!isPicking) {
        return (
            <div className="flex flex-wrap items-center gap-3">
                <Button type="button" variant="secondary" size="sm" className="gap-1.5 border border-dashed" onPress={() => setIsPicking(true)}>
                    <Plus className="size-3.5" aria-hidden />
                    Tambah cabang
                </Button>
                {error && <p className="text-xs text-danger">{error}</p>}
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-border bg-surface p-3 shadow-xs">
            <div className="flex flex-wrap items-end gap-3">
                <div className="min-w-[220px] flex-1">
                    <label htmlFor="add-branch-select" className="mb-1 block text-xs font-semibold text-foreground">
                        Cabang Tujuan
                    </label>
                    <select
                        id="add-branch-select"
                        aria-label="Pilih cabang tujuan baru"
                        value={pick || String(availableBranches[0]?.id ?? '')}
                        onChange={(e) => setPick(e.target.value)}
                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                    >
                        {availableBranches.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.code} — {b.name}
                                {b.is_headquarters ? ' (HQ)' : ''}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="add-branch-date" className="mb-1 block text-xs font-semibold text-foreground">
                        Tgl. Kirim
                    </label>
                    <input
                        id="add-branch-date"
                        aria-label="Tanggal kirim untuk cabang baru"
                        type="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                        className="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                    />
                </div>
                <Button type="button" variant="primary" size="sm" className="gap-1.5" onPress={handleAdd}>
                    <Plus className="size-3.5" aria-hidden />
                    Tambah
                </Button>
                <Button type="button" variant="secondary" size="sm" className="gap-1.5" onPress={() => setIsPicking(false)}>
                    <X className="size-3.5" aria-hidden />
                    Batal
                </Button>
            </div>
            {error && <p className="mt-2 text-xs text-danger">{error}</p>}
        </div>
    );
}
