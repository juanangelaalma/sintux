import {
    AlertDialog,
    Button,
    FieldError,
    Input,
    Label,
    Modal,
    TextField,
} from '@heroui/react';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { formatQty } from '@/lib/format';
import { clampQty, itemName, remainingQty, shippedQty } from './helpers';
import type { ReturnOption, StockTransfer } from './types';

export type TransferErrors = Record<string, unknown>;

const err = (errors: TransferErrors, key: string) =>
    errors[key] as string | undefined;

/* ---------- Konfirmasi generik (kirim / setujui / tolak) ---------- */

type ConfirmDialogProps = {
    triggerLabel: string;
    triggerVariant?: 'primary' | 'danger-soft';
    title: string;
    description: string;
    status?: 'accent' | 'danger';
    confirmLabel: string;
    confirmVariant?: 'primary' | 'danger';
    isPending: boolean;
    onConfirm: () => void;
};

export function ConfirmDialog({
    triggerLabel,
    triggerVariant = 'primary',
    title,
    description,
    status = 'accent',
    confirmLabel,
    confirmVariant = 'primary',
    isPending,
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <AlertDialog>
            <Button variant={triggerVariant}>{triggerLabel}</Button>
            <AlertDialog.Backdrop>
                <AlertDialog.Container>
                    <AlertDialog.Dialog className="sm:max-w-[420px]">
                        <AlertDialog.CloseTrigger />
                        <AlertDialog.Header>
                            <AlertDialog.Icon status={status} />
                            <AlertDialog.Heading>{title}</AlertDialog.Heading>
                        </AlertDialog.Header>
                        <AlertDialog.Body>
                            <p>{description}</p>
                        </AlertDialog.Body>
                        <AlertDialog.Footer>
                            <Button slot="close" variant="tertiary">
                                Batal
                            </Button>
                            <Button
                                isPending={isPending}
                                onPress={onConfirm}
                                variant={confirmVariant}
                            >
                                {isPending ? 'Memproses...' : confirmLabel}
                            </Button>
                        </AlertDialog.Footer>
                    </AlertDialog.Dialog>
                </AlertDialog.Container>
            </AlertDialog.Backdrop>
        </AlertDialog>
    );
}

/* ---------- Baris input qty yang dipakai bersama oleh terima & retur ---------- */

type QtyRowProps = {
    title: string;
    meta: string;
    label: string;
    value: number;
    max: number;
    error?: string;
    disabled?: boolean;
    hint?: ReactNode;
    onChange: (qty: number) => void;
};

function QtyRow({
    title,
    meta,
    label,
    value,
    max,
    error,
    disabled = false,
    hint,
    onChange,
}: QtyRowProps) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-border p-3">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-foreground">{title}</p>
                <p className="text-xs text-muted">{meta}</p>
                {hint}
            </div>
            <div className="flex items-end gap-2">
                <TextField
                    isDisabled={disabled}
                    isInvalid={Boolean(error)}
                    className="w-28"
                >
                    <Label className="text-xs font-semibold text-foreground">
                        {label}
                    </Label>
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={max}
                        step={1}
                        value={String(value)}
                        onFocus={(event) => event.target.select()}
                        onChange={(event) =>
                            onChange(
                                clampQty(parseInt(event.target.value, 10), max),
                            )
                        }
                    />
                    {error && <FieldError>{error}</FieldError>}
                </TextField>
                <Button
                    size="sm"
                    variant="tertiary"
                    isDisabled={disabled || max === 0}
                    onPress={() => onChange(max)}
                >
                    Maks
                </Button>
            </div>
        </div>
    );
}

/* ---------- Terima ---------- */

export function ReceiveDialog({
    transfer,
    errors,
}: {
    transfer: StockTransfer;
    errors: TransferErrors;
}) {
    const items = transfer.items ?? [];
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const [qtys, setQtys] = useState<Record<number, number>>({});

    // Selalu diisi ulang saat dibuka supaya tidak memakai data basi.
    const openDialog = () => {
        setQtys(
            Object.fromEntries(
                items.map((item) => [item.id, remainingQty(item)]),
            ),
        );
        setOpen(true);
    };

    const short = items.filter(
        (item) => (qtys[item.id] ?? 0) < remainingQty(item),
    );

    const submit = () => {
        setPending(true);
        router.post(
            `/warehouse/stock-transfers/${transfer.id}/receive`,
            {
                received_items: items.map((item) => ({
                    stock_transfer_item_id: item.id,
                    qty_received: qtys[item.id] ?? 0,
                })),
            },
            {
                onSuccess: () => setOpen(false),
                onFinish: () => setPending(false),
            },
        );
    };

    return (
        <Modal isOpen={open} onOpenChange={setOpen}>
            <Button onPress={openDialog} variant="primary">
                Terima Barang
            </Button>
            <Modal.Backdrop>
                <Modal.Container size="lg" scroll="inside">
                    <Modal.Dialog>
                        <Modal.CloseTrigger />
                        <Modal.Header>
                            <Modal.Heading>Terima Barang</Modal.Heading>
                        </Modal.Header>
                        <Modal.Body className="space-y-3">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-muted">
                                    Jumlah terisi sesuai sisa kiriman. Ubah
                                    hanya bila ada yang kurang.
                                </p>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onPress={openDialog}
                                >
                                    Terima semua
                                </Button>
                            </div>

                            {err(errors, 'received_items') && (
                                <p role="alert" className="text-sm text-danger">
                                    {err(errors, 'received_items')}
                                </p>
                            )}

                            {items.map((item, index) => {
                                const remaining = remainingQty(item);
                                const qty = qtys[item.id] ?? 0;
                                const gap = remaining - qty;

                                return (
                                    <QtyRow
                                        key={item.id}
                                        title={itemName(item)}
                                        meta={`Dikirim ${formatQty(shippedQty(item))} · Sisa ${formatQty(remaining)}`}
                                        label="Qty diterima"
                                        value={qty}
                                        max={remaining}
                                        disabled={remaining === 0}
                                        error={err(
                                            errors,
                                            `received_items.${index}.qty_received`,
                                        )}
                                        onChange={(value) =>
                                            setQtys((previous) => ({
                                                ...previous,
                                                [item.id]: value,
                                            }))
                                        }
                                        hint={
                                            gap > 0 && (
                                                <p className="mt-1 text-xs font-semibold text-warning">
                                                    Kurang {formatQty(gap)},
                                                    akan dicatat sebagai selisih
                                                </p>
                                            )
                                        }
                                    />
                                );
                            })}
                        </Modal.Body>
                        <Modal.Footer className="items-center">
                            <p
                                className={`mr-auto text-xs ${
                                    short.length
                                        ? 'font-semibold text-warning'
                                        : 'text-muted'
                                }`}
                            >
                                {short.length
                                    ? `${short.length} item punya selisih`
                                    : 'Semua item diterima lengkap'}
                            </p>
                            <Button slot="close" variant="secondary">
                                Batal
                            </Button>
                            <Button
                                isPending={pending}
                                isDisabled={items.length === 0}
                                onPress={submit}
                            >
                                {pending ? 'Memproses...' : 'Konfirmasi Terima'}
                            </Button>
                        </Modal.Footer>
                    </Modal.Dialog>
                </Modal.Container>
            </Modal.Backdrop>
        </Modal>
    );
}

/* ---------- Retur ke HO ---------- */

type ReturnLine = {
    stock_transfer_item_id: number;
    qty: number;
};

export function ReturnDialog({
    transfer,
    options,
    errors,
}: {
    transfer: StockTransfer;
    options: ReturnOption[];
    errors: TransferErrors;
}) {
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const [qtys, setQtys] = useState<Record<number, number>>({});
    const [submitted, setSubmitted] = useState<ReturnLine[]>([]);

    const openDialog = () => {
        setQtys({});
        setSubmitted([]);
        setOpen(true);
    };

    const lines: ReturnLine[] = options
        .map((option) => ({
            stock_transfer_item_id: option.stock_transfer_item_id,
            qty: qtys[option.stock_transfer_item_id] ?? 0,
        }))
        .filter((line) => line.qty > 0);
    const totalQty = lines.reduce((sum, line) => sum + line.qty, 0);

    /*
     * Error server dipetakan lewat index baris yang benar-benar dikirim,
     * bukan index baris di form, supaya pesan tetap menempel di baris yang
     * salah saat baris lain dilewati.
     */
    const qtyError = (itemId: number) => {
        const index = submitted.findIndex(
            (line) => line.stock_transfer_item_id === itemId,
        );

        return index === -1 ? undefined : err(errors, `items.${index}.qty`);
    };

    const submit = () => {
        if (lines.length === 0) {
            return;
        }

        setPending(true);
        setSubmitted(lines);
        router.post(
            `/warehouse/stock-transfers/${transfer.id}/return`,
            {
                origin_transfer_id: transfer.id,
                items: lines,
            },
            {
                onSuccess: () => setOpen(false),
                onFinish: () => setPending(false),
            },
        );
    };

    return (
        <Modal isOpen={open} onOpenChange={setOpen}>
            <Button onPress={openDialog} variant="secondary">
                Retur ke HO
            </Button>
            <Modal.Backdrop>
                <Modal.Container size="lg" scroll="inside">
                    <Modal.Dialog>
                        <Modal.CloseTrigger />
                        <Modal.Header>
                            <Modal.Heading>
                                Retur Stok ke Head Office
                            </Modal.Heading>
                        </Modal.Header>
                        <Modal.Body className="space-y-3">
                            <p className="text-sm text-muted">
                                Barang kembali ke{' '}
                                <span className="font-semibold text-foreground">
                                    {transfer.from_warehouse?.name ?? 'HQ'}
                                </span>{' '}
                                dengan asal pembelian yang tetap terlacak. Isi
                                qty hanya untuk item yang diretur.
                            </p>

                            {(['origin_transfer_id', 'items'] as const).map(
                                (key) =>
                                    err(errors, key) && (
                                        <p
                                            key={key}
                                            role="alert"
                                            className="text-sm text-danger"
                                        >
                                            {err(errors, key)}
                                        </p>
                                    ),
                            )}

                            {options.length === 0 ? (
                                <p
                                    role="status"
                                    className="rounded-lg border border-dashed border-border p-4 text-sm text-muted"
                                >
                                    Tidak ada barang yang bisa diretur. Semua
                                    stok item ini sudah habis di gudang tujuan.
                                </p>
                            ) : (
                                options.map((option) => (
                                    <QtyRow
                                        key={option.stock_transfer_item_id}
                                        title={
                                            option.product_name || option.sku
                                        }
                                        meta={`${option.sku} · masuk ${option.qty} · bisa diretur ${formatQty(option.returnable_qty)}`}
                                        label="Qty diretur"
                                        value={
                                            qtys[
                                                option.stock_transfer_item_id
                                            ] ?? 0
                                        }
                                        max={option.returnable_qty}
                                        disabled={option.returnable_qty === 0}
                                        error={qtyError(
                                            option.stock_transfer_item_id,
                                        )}
                                        onChange={(value) =>
                                            setQtys((previous) => ({
                                                ...previous,
                                                [option.stock_transfer_item_id]:
                                                    value,
                                            }))
                                        }
                                    />
                                ))
                            )}
                        </Modal.Body>
                        <Modal.Footer className="items-center">
                            <p className="mr-auto text-xs text-muted">
                                {lines.length > 0
                                    ? `${lines.length} item · total ${formatQty(totalQty)} diretur`
                                    : 'Belum ada item dipilih'}
                            </p>
                            <Button slot="close" variant="secondary">
                                Batal
                            </Button>
                            <Button
                                isPending={pending}
                                isDisabled={lines.length === 0}
                                onPress={submit}
                            >
                                {pending
                                    ? 'Memproses...'
                                    : 'Buat Retur Transfer'}
                            </Button>
                        </Modal.Footer>
                    </Modal.Dialog>
                </Modal.Container>
            </Modal.Backdrop>
        </Modal>
    );
}
