import { Button, Input, Label, ListBox, Select, Switch } from '@heroui/react';
import { Icon } from '@iconify/react';
import CurrencyInput from '@/components/ui/currency-input';
import type { AccountOption, ExpenseLineRow, TaxOption } from './types';

type ExpenseItemsEditorProps = {
    lines: ExpenseLineRow[];
    accounts: AccountOption[];
    taxes: TaxOption[];
    isTaxInclusive: boolean;
    onTaxInclusiveChange: (value: boolean) => void;
    onLineChange: (
        index: number,
        field: keyof ExpenseLineRow,
        value: number | string | null,
    ) => void;
    onAddLine: () => void;
    onRemoveLine: (index: number) => void;
};

/**
 * Tabel baris akun biaya (kolom 11-14). Default dua baris kosong sesuai
 * screenshot S5. Kolom Akun biaya sudah dibatasi Accounting lewat
 * listForExpense(), jadi komponen ini tidak perlu memfilter ulang.
 */
export default function ExpenseItemsEditor({
    lines,
    accounts,
    taxes,
    isTaxInclusive,
    onTaxInclusiveChange,
    onLineChange,
    onAddLine,
    onRemoveLine,
}: ExpenseItemsEditorProps) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex justify-end">
                <Switch
                    isSelected={isTaxInclusive}
                    onChange={onTaxInclusiveChange}
                    aria-label="Harga termasuk pajak"
                >
                    <Label className="text-xs font-medium text-foreground">
                        Harga termasuk pajak
                    </Label>
                </Switch>
            </div>

            <div className="overflow-hidden rounded-xl border border-border">
                <div className="grid grid-cols-[minmax(0,1.3fr)_minmax(0,2fr)_minmax(0,1.1fr)_minmax(0,1.2fr)_44px] items-center gap-2 bg-accent/10 px-3 py-2.5 text-xs font-medium text-accent">
                    <span>Akun biaya</span>
                    <span>Deskripsi</span>
                    <span>Pajak</span>
                    <span className="text-end">Jumlah</span>
                    <span />
                </div>

                {lines.map((line, index) => (
                    <div
                        key={index}
                        className="grid grid-cols-[minmax(0,1.3fr)_minmax(0,2fr)_minmax(0,1.1fr)_minmax(0,1.2fr)_44px] items-center gap-2 border-t border-border px-3 py-2"
                    >
                        <Select
                            aria-label={`Akun biaya baris ${index + 1}`}
                            placeholder="Pilih akun biaya"
                            selectedKey={
                                line.account_id === ''
                                    ? null
                                    : String(line.account_id)
                            }
                            onSelectionChange={(key) =>
                                onLineChange(
                                    index,
                                    'account_id',
                                    key === null ? '' : String(key),
                                )
                            }
                        >
                            <Label className="sr-only">Akun biaya</Label>
                            <Select.Trigger>
                                <Select.Value />
                                <Select.Indicator />
                            </Select.Trigger>
                            <Select.Popover>
                                <ListBox>
                                    {accounts.map((account) => (
                                        <ListBox.Item
                                            key={String(account.id)}
                                            id={String(account.id)}
                                            textValue={`${account.code} ${account.name}`}
                                        >
                                            {account.code} - {account.name}
                                            <ListBox.ItemIndicator />
                                        </ListBox.Item>
                                    ))}
                                </ListBox>
                            </Select.Popover>
                        </Select>

                        <Input
                            aria-label={`Deskripsi baris ${index + 1}`}
                            placeholder="Opsional"
                            value={line.description}
                            onChange={(e) =>
                                onLineChange(
                                    index,
                                    'description',
                                    e.target.value,
                                )
                            }
                        />

                        <Select
                            aria-label={`Pajak baris ${index + 1}`}
                            placeholder="Pilih pajak"
                            selectedKey={
                                line.tax_id === null || line.tax_id === ''
                                    ? null
                                    : String(line.tax_id)
                            }
                            onSelectionChange={(key) =>
                                onLineChange(
                                    index,
                                    'tax_id',
                                    key === null ? null : String(key),
                                )
                            }
                        >
                            <Label className="sr-only">Pajak</Label>
                            <Select.Trigger>
                                <Select.Value />
                                <Select.Indicator />
                            </Select.Trigger>
                            <Select.Popover>
                                <ListBox>
                                    {taxes.map((tax) => (
                                        <ListBox.Item
                                            key={String(tax.id)}
                                            id={String(tax.id)}
                                            textValue={tax.name}
                                        >
                                            {tax.name}
                                            <ListBox.ItemIndicator />
                                        </ListBox.Item>
                                    ))}
                                </ListBox>
                            </Select.Popover>
                        </Select>

                        <div className="rounded-lg border border-border bg-surface-secondary/40 px-2">
                            <CurrencyInput
                                ariaLabel={`Jumlah baris ${index + 1}`}
                                value={Number(line.amount) || 0}
                                onChange={(value) =>
                                    onLineChange(index, 'amount', value)
                                }
                            />
                        </div>

                        <Button
                            isIconOnly
                            variant="tertiary"
                            aria-label={`Hapus baris ${index + 1}`}
                            onPress={() => onRemoveLine(index)}
                            isDisabled={lines.length === 1}
                        >
                            <Icon
                                className="size-4 text-accent"
                                icon="gravity-ui:minus"
                            />
                        </Button>
                    </div>
                ))}
            </div>

            <div>
                <Button
                    variant="primary"
                    className="gap-1.5"
                    onPress={onAddLine}
                >
                    <Icon className="size-4" icon="gravity-ui:plus" />
                    Tambah data
                </Button>
            </div>
        </div>
    );
}
