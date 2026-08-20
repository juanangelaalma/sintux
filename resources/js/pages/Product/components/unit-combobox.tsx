import React, { useState } from 'react';
import { SearchableSelect } from '@/components/ui/searchable-select';

type UomOption = {
    id: number;
    name: string;
    code: string;
};

type Props = {
    value: number;
    options: UomOption[];
    onChange: (uomId: number) => void;
    onOptionAdded?: (newOption: UomOption) => void;
};

export const UnitCombobox: React.FC<Props> = ({
    value,
    options,
    onChange,
    onOptionAdded,
}) => {
    const [createdOptions, setCreatedOptions] = useState<UomOption[]>([]);
    const [creating, setCreating] = useState(false);
    const uomOptions = [...options, ...createdOptions];

    const handleCreateNewUom = async (name: string) => {
        if (creating) {
            return;
        }

        setCreating(true);
        const code = name.toUpperCase().replace(/\s+/g, '_').substring(0, 10);

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content ?? '';

        try {
            const res = await fetch('/product/uoms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    name,
                    code,
                    is_active: true,
                }),
            });

            const newUom = await res.json();

            if (res.ok && newUom.id) {
                const created: UomOption = {
                    id: newUom.id,
                    name: newUom.name,
                    code: newUom.code,
                };
                setCreatedOptions((previousOptions) => [
                    ...previousOptions,
                    created,
                ]);
                onChange(created.id);

                if (onOptionAdded) {
                    onOptionAdded(created);
                }
            }
        } catch {
            // Handle error quietly
        } finally {
            setCreating(false);
        }
    };

    return (
        <SearchableSelect
            options={uomOptions.map((option) => ({
                id: option.id,
                label: `${option.name} (${option.code})`,
            }))}
            value={value}
            onChange={(selectedId) => {
                if (selectedId !== null) {
                    onChange(selectedId);
                }
            }}
            placeholder="Pilih atau masukkan unit"
            onCreateOption={handleCreateNewUom}
            canCreateOption={(query) =>
                !uomOptions.some(
                    (option) =>
                        option.name.toLowerCase() === query.toLowerCase() ||
                        option.code.toLowerCase() === query.toLowerCase(),
                )
            }
            isCreating={creating}
        />
    );
};
