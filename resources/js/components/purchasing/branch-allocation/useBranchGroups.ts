import { useCallback, useMemo, useState } from 'react';
import type { Branch, BranchGroup, BranchItem, FlatAllocationRow, ProductVariant, Warehouse } from './types';

function genId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return (crypto as unknown as { randomUUID: () => string }).randomUUID().slice(0, 9);
    }

    return Math.random().toString(36).slice(2, 9);
}

type UseBranchGroupsOptions = {
    branches: Branch[];
    warehousesByBranch: Record<string, Warehouse[]>;
    productVariants: ProductVariant[];
    initialDate: string;
    initialBranchId?: number | string;
};

export function useBranchGroups({
    branches,
    warehousesByBranch,
    productVariants,
    initialDate,
    initialBranchId,
}: UseBranchGroupsOptions) {
    const getRegularWarehouseId = useCallback(
        (branchId: number | string): number | string => {
            const list = warehousesByBranch[String(branchId)] ?? [];

            return list[0]?.id ?? '';
        },
        [warehousesByBranch],
    );

    const createEmptyItem = useCallback(
        (branchId: number | string): BranchItem => {
            const firstForBranch = productVariants.find((v) => v.branch_id === Number(branchId));

            return {
                uid: genId(),
                product_variant_id: firstForNewBranch(firstForBranch, productVariants),
                description: '',
                qty: 1,
                unit_price: 0,
                tax_id: null,
            };
        },
        [productVariants],
    );

    const createEmptyGroup = useCallback(
        (branchId: number | string, dateStr: string): BranchGroup => ({
            uid: genId(),
            destination_branch_id: Number(branchId),
            destination_warehouse_id: getRegularWarehouseId(branchId),
            destination_expected_date: dateStr,
            items: [createEmptyItem(branchId)],
        }),
        [createEmptyItem, getRegularWarehouseId],
    );

    const [groups, setGroups] = useState<BranchGroup[]>(() => []);

    const [expanded, setExpanded] = useState<Set<string>>(() => new Set());

    const sortedGroups = useMemo(() => [...groups].sort((a, b) => a.destination_expected_date.localeCompare(b.destination_expected_date)), [groups]);

    const availableBranches = useMemo(
        () => branches.filter((b) => !groups.some((g) => String(g.destination_branch_id) === String((b as unknown as { id: number }).id))),
        [branches, groups],
    );

    const toggleGroup = useCallback((uid: string) => {
        setExpanded((prev) => {
            const next = new Set(prev);

            if (next.has(uid)) {
                next.delete(uid);
            } else {
                next.add(uid);
            }

            return next;
        });
    }, []);

    const addBranchGroup = useCallback(
        (branchId: number | string, dateStr: string) => {
            if (groups.some((g) => String(g.destination_branch_id) === String(branchId))) {
                return false;
            }

            const newGroup = createEmptyGroup(branchId, dateStr);
            setGroups((prev) => [...prev, newGroup].sort((a, b) => a.destination_expected_date.localeCompare(b.destination_expected_date)));
            setExpanded((prev) => new Set([...prev, newGroup.uid]));

            return true;
        },
        [groups, createEmptyGroup],
    );

    const removeBranchGroup = useCallback(
        (uid: string) => {
            setGroups((prev) => prev.filter((g) => g.uid !== uid));
            setExpanded((prev) => {
                const n = new Set(prev);
                n.delete(uid);

                return n;
            });

            return true;
        },
        [groups.length],
    );

    const changeBranchForGroup = useCallback(
        (uid: string, newBranchId: number | string) => {
            if (groups.some((g) => g.uid !== uid && String(g.destination_branch_id) === String(newBranchId))) {
                return false;
            }

            setGroups((prev) =>
                prev.map((g) => {
                    if (g.uid !== uid) {
                        return g;
                    }

                    const newWarehouseId = getRegularWarehouseId(newBranchId);
                    const updatedItems = g.items.map((it) => {
                        const ok = productVariants.some((v) => v.id === it.product_variant_id && v.branch_id === Number(newBranchId));

                        if (ok) {
                            return it;
                        }

                        const firstForNew = productVariants.find((v) => v.branch_id === Number(newBranchId));

                        return { ...it, product_variant_id: firstForNew?.id ?? productVariants[0]?.id ?? it.product_variant_id };
                    });

                    return {
                        ...g,
                        destination_branch_id: Number(newBranchId),
                        destination_warehouse_id: newWarehouseId,
                        items: updatedItems,
                    };
                }),
            );

            return true;
        },
        [groups, getRegularWarehouseId, productVariants],
    );

    const changeGroupDate = useCallback((uid: string, newDate: string) => {
        setGroups((prev) => {
            const updated = prev.map((g) => (g.uid === uid ? { ...g, destination_expected_date: newDate } : g));

            return [...updated].sort((a, b) => a.destination_expected_date.localeCompare(b.destination_expected_date));
        });
    }, []);

    const addItemToGroup = useCallback(
        (uid: string) => {
            setGroups((prev) =>
                prev.map((g) => {
                    if (g.uid !== uid) {
                        return g;
                    }

                    return { ...g, items: [...g.items, createEmptyItem(g.destination_branch_id)] };
                }),
            );
        },
        [createEmptyItem],
    );

    const removeItemFromGroup = useCallback((uid: string, itemUid: string) => {
        setGroups((prev) =>
            prev.map((g) => {
                if (g.uid !== uid) {
                    return g;
                }

                if (g.items.length === 1) {
                    return g;
                }

                return { ...g, items: g.items.filter((it) => it.uid !== itemUid) };
            }),
        );
    }, []);

    const updateItemInGroup = useCallback((uid: string, itemUid: string, field: keyof BranchItem, value: string | number | null) => {
        setGroups((prev) =>
            prev.map((g) => {
                if (g.uid !== uid) {
                    return g;
                }

                const nextItems = g.items.map((it) => (it.uid === itemUid ? ({ ...it, [field]: value } as BranchItem) : it));

                return { ...g, items: nextItems };
            }),
        );
    }, []);

    const flatItems: FlatAllocationRow[] = useMemo(
        () =>
            sortedGroups.flatMap((g) =>
                g.items.map((it) => ({
                    destination_branch_id: Number(g.destination_branch_id),
                    destination_warehouse_id: g.destination_warehouse_id,
                    destination_expected_date: g.destination_expected_date,
                    product_variant_id: Number(it.product_variant_id),
                    description: it.description,
                    qty: Number(it.qty),
                    qty_ordered: Number(it.qty),
                    unit_price: Number(it.unit_price || 0),
                    tax_id: it.tax_id,
                })),
            ),
        [sortedGroups],
    );

    const totalAllocations = useMemo(() => groups.reduce((acc, g) => acc + g.items.length, 0), [groups]);

    return {
        groups,
        sortedGroups,
        expanded,
        availableBranches,
        flatItems,
        totalAllocations,
        toggleGroup,
        addBranchGroup,
        removeBranchGroup,
        changeBranchForGroup,
        changeGroupDate,
        addItemToGroup,
        removeItemFromGroup,
        updateItemInGroup,
        setGroups,
        setExpanded,
    };
}

function firstForNewBranch(firstForBranch: ProductVariant | undefined, all: ProductVariant[]): number {
    return firstForBranch?.id ?? all[0]?.id ?? 0;
}
