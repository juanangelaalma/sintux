type BranchRef = { id: number; name: string; code: string };
type WarehouseRef = { id: number; code: string; name: string };

type ItemWithBranch = {
    id: number;
    destination_branch_id?: number;
    destination_expected_date?: string | null;
    destination_branch?: BranchRef | null;
    destination_warehouse?: WarehouseRef | null;
    line_total: number;
};

export type BranchGroupView<T extends ItemWithBranch> = {
    key: string;
    branchCode: string;
    branch?: BranchRef | null;
    warehouse?: WarehouseRef | null;
    date: string | null;
    items: T[];
    subtotal: number;
};

export function groupAndSortByBranch<T extends ItemWithBranch>(items: T[]): BranchGroupView<T>[] {
    const map = new Map<string, T[]>();

    items.forEach((it) => {
        const key = it.destination_branch?.code ?? `Cabang ${it.destination_branch_id ?? '-'}`;

        if (!map.has(key)) {
            map.set(key, []);
        }

        map.get(key)!.push(it);
    });

    const groups: BranchGroupView<T>[] = Array.from(map.entries()).map(([branchCode, groupItems]) => {
        const first = groupItems[0];
        const subtotal = groupItems.reduce((acc, it) => acc + Number(it.line_total || 0), 0);

        return {
            key: branchCode,
            branchCode,
            branch: first?.destination_branch ?? null,
            warehouse: first?.destination_warehouse ?? null,
            date: (first?.destination_expected_date as string | null) ?? null,
            items: groupItems,
            subtotal,
        };
    });

    groups.sort((a, b) => {
        const dateA = a.date ?? '9999-12-31';
        const dateB = b.date ?? '9999-12-31';

        return String(dateA).localeCompare(String(dateB));
    });

    return groups;
}
