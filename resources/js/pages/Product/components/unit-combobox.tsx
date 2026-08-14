import React, { useState, useRef, useEffect } from 'react';

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
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [uomOptions, setUomOptions] = useState<UomOption[]>(options);
    const [creating, setCreating] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setUomOptions(options);
    }, [options]);

    const selectedOption = uomOptions.find((o) => o.id === value);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const filtered = uomOptions.filter(
        (o) =>
            o.name.toLowerCase().includes(search.toLowerCase()) ||
            o.code.toLowerCase().includes(search.toLowerCase())
    );

    const hasExactMatch = uomOptions.some(
        (o) =>
            o.name.toLowerCase() === search.trim().toLowerCase() ||
            o.code.toLowerCase() === search.trim().toLowerCase()
    );

    const handleCreateNewUom = async () => {
        if (!search.trim() || creating) return;

        setCreating(true);
        const name = search.trim();
        const code = name.toUpperCase().replace(/\s+/g, '_').substring(0, 10);

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

        try {
            const res = await fetch('/product/uoms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
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
                setUomOptions((prev) => [...prev, created]);
                onChange(created.id);
                if (onOptionAdded) onOptionAdded(created);
                setSearch('');
                setOpen(false);
            }
        } catch (e) {
            // Handle error quietly
        } finally {
            setCreating(false);
        }
    };

    return (
        <div className="relative" ref={containerRef}>
            <div
                onClick={() => setOpen(true)}
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm flex items-center justify-between cursor-pointer focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"
            >
                <input
                    type="text"
                    value={open ? search : (selectedOption ? `${selectedOption.name} (${selectedOption.code})` : search)}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        if (!open) setOpen(true);
                    }}
                    onFocus={() => {
                        setOpen(true);
                        setSearch('');
                    }}
                    placeholder="Pilih atau masukkan unit"
                    className="w-full bg-transparent border-none p-0 text-sm focus:outline-none focus:ring-0 text-slate-900 placeholder:text-slate-400"
                />
                <svg
                    className={`size-4 text-slate-400 transition-transform duration-200 shrink-0 ${open ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            {/* Dropdown Menu (Image 1 style) */}
            {open && (
                <div className="absolute left-0 right-0 z-50 mt-1 max-h-56 overflow-y-auto rounded-lg bg-white border border-slate-200 shadow-lg text-sm">
                    {filtered.length > 0 && (
                        <div className="divide-y divide-slate-100">
                            {filtered.map((option) => (
                                <div
                                    key={option.id}
                                    onClick={() => {
                                        onChange(option.id);
                                        setSearch('');
                                        setOpen(false);
                                    }}
                                    className={`px-3 py-2 cursor-pointer hover:bg-indigo-50 hover:text-indigo-600 transition-colors ${
                                        option.id === value ? 'bg-indigo-50/60 font-bold text-indigo-600' : 'text-slate-700'
                                    }`}
                                >
                                    {option.name} ({option.code})
                                </div>
                            ))}
                        </div>
                    )}

                    {/* No result found & Add Recommendation */}
                    {search.trim().length > 0 && !hasExactMatch && (
                        <div className="p-3 text-center space-y-2">
                            {filtered.length === 0 && (
                                <div className="text-xs text-slate-400">
                                    No result found
                                </div>
                            )}

                            <button
                                type="button"
                                onClick={handleCreateNewUom}
                                disabled={creating}
                                className="text-xs font-semibold text-indigo-600 hover:underline block w-full text-center py-1 transition-colors disabled:opacity-50"
                            >
                                {creating ? 'Menambahkan...' : `Tambahkan "${search.trim()}"`}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
