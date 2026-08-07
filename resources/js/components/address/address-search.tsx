import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { mapNominatimAddress, searchPlaces } from '@/lib/nominatim';
import type { AddressValue, NominatimResult } from '@/lib/nominatim';

type AddressSearchProps = {
    onSelect: (address: AddressValue) => void;
    placeholder?: string;
};

export default function AddressSearch({
    onSelect,
    placeholder = 'Cari desa / kelurahan / kecamatan...',
}: AddressSearchProps) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<NominatimResult[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const timeoutRef = useRef<number | null>(null);

    useEffect(() => {
        return () => {
            if (timeoutRef.current !== null) {
                window.clearTimeout(timeoutRef.current);
            }

            abortRef.current?.abort();
        };
    }, []);

    const handleQueryChange = (value: string) => {
        setQuery(value);
        abortRef.current?.abort();

        if (timeoutRef.current !== null) {
            window.clearTimeout(timeoutRef.current);
            timeoutRef.current = null;
        }

        if (value.trim().length < 3) {
            setResults([]);
            setOpen(false);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        abortRef.current = controller;
        setLoading(true);

        timeoutRef.current = window.setTimeout(async () => {
            const data = await searchPlaces(value.trim(), controller.signal);

            if (controller.signal.aborted) {
                return;
            }

            setResults(data);
            setOpen(true);
            setLoading(false);
        }, 400);
    };

    const handleSelect = (result: NominatimResult) => {
        setQuery(result.display_name);
        setOpen(false);
        onSelect(mapNominatimAddress(result));
    };

    return (
        <div className="relative">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => handleQueryChange(e.target.value)}
                    onFocus={() => results.length > 0 && setOpen(true)}
                    placeholder={placeholder}
                    className="mt-1 block w-full rounded-lg border border-gray-300 py-2 pr-3 pl-9 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                />
                {loading && (
                    <div className="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500" />
                )}
            </div>

            {open && results.length > 0 && (
                <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    {results.map((result) => (
                        <li key={result.place_id}>
                            <button
                                type="button"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => handleSelect(result)}
                                className="block w-full px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-800"
                            >
                                {result.display_name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
