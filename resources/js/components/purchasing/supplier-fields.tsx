import {
    FieldError,
    Input,
    Label,
    ListBox,
    Select,
    TextArea,
    TextField,
} from '@heroui/react';
import { useEffect } from 'react';

export type SupplierOption = {
    id: number;
    name: string;
    email?: string;
    billing_address?: string;
};

type SupplierFieldsProps = {
    supplierId: number | string;
    suppliers: SupplierOption[];
    onSupplierChange: (id: number) => void;
    email?: string;
    address?: string;
    onEmailChange?: (val: string) => void;
    onAddressChange?: (val: string) => void;
    error?: string;
};

export default function SupplierFields({
    supplierId,
    suppliers,
    onSupplierChange,
    email = '',
    address = '',
    onEmailChange,
    onAddressChange,
    error,
}: SupplierFieldsProps) {
    const handleSelectSupplier = (id: number) => {
        onSupplierChange(id);
        const selected = suppliers.find((s) => s.id === id);

        if (selected) {
            if (onEmailChange && selected.email) {
                onEmailChange(selected.email);
            }

            if (onAddressChange && selected.billing_address) {
                onAddressChange(selected.billing_address);
            }
        }
    };

    // Auto-fill initial supplier email and address if supplierId exists
    useEffect(() => {
        if (supplierId && suppliers.length > 0) {
            const selected = suppliers.find((s) => s.id === Number(supplierId));

            if (selected) {
                if (onEmailChange && !email && selected.email) {
                    onEmailChange(selected.email);
                }

                if (onAddressChange && !address && selected.billing_address) {
                    onAddressChange(selected.billing_address);
                }
            }
        }
    }, [supplierId, suppliers]);

    return (
        <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
            <div>
                <Label className="block text-xs font-semibold text-foreground">
                    Supplier *
                </Label>
                <Select
                    fullWidth
                    isRequired
                    isInvalid={Boolean(error)}
                    placeholder="Pilih supplier"
                    value={supplierId ? String(supplierId) : ''}
                    onChange={(val) => handleSelectSupplier(Number(val))}
                >
                    <Select.Trigger className="mt-1">
                        <Select.Value />
                        <Select.Indicator />
                    </Select.Trigger>
                    <Select.Popover>
                        <ListBox>
                            {suppliers.map((s) => (
                                <ListBox.Item
                                    key={s.id}
                                    id={String(s.id)}
                                    textValue={s.name}
                                >
                                    {s.name}
                                </ListBox.Item>
                            ))}
                        </ListBox>
                    </Select.Popover>
                </Select>
                {error && <FieldError>{error}</FieldError>}
            </div>

            <div>
                <TextField name="email">
                    <Label className="block text-xs font-semibold text-foreground">
                        Email Supplier
                    </Label>
                    <Input
                        type="email"
                        placeholder="contoh@supplier.com"
                        value={email}
                        onChange={(e) =>
                            onEmailChange && onEmailChange(e.target.value)
                        }
                        className="mt-1"
                    />
                </TextField>
            </div>

            <div className="md:col-span-2">
                <TextField name="address">
                    <Label className="block text-xs font-semibold text-foreground">
                        Alamat Penagihan
                    </Label>
                    <TextArea
                        rows={2}
                        placeholder="Terisi otomatis setelah supplier dipilih"
                        value={address}
                        onChange={(e) =>
                            onAddressChange && onAddressChange(e.target.value)
                        }
                        className="mt-1"
                    />
                </TextField>
            </div>
        </div>
    );
}
