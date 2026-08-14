import React, { useState, useRef, useEffect } from 'react';
import { Link } from '@inertiajs/react';

export const ProductActionsDropdown: React.FC = () => {
    const [open, setOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div className="relative inline-block text-left" ref={dropdownRef}>
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                className="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
            >
                <span>Tindakan</span>
                <svg
                    className={`size-4 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {open && (
                <div className="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                    <div className="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">Aksi Cepat</p>
                    </div>

                    <Link
                        href="/product/products/create"
                        className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg className="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Tambah Produk Baru</span>
                    </Link>

                    <Link
                        href="/warehouse/warehouses/create"
                        className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg className="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span>Tambah Gudang Baru</span>
                    </Link>

                    <Link
                        href="/warehouse/stock-requests/create"
                        className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg className="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>Buat Permintaan Stok</span>
                    </Link>

                    <Link
                        href="/warehouse/stock-transfers"
                        className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg className="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>Transfer Stok</span>
                    </Link>

                    <div className="my-1 border-t border-gray-100 dark:border-gray-700" />

                    <Link
                        href="/product?tab=master&sub=categories"
                        className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span>Atur Kategori Produk</span>
                    </Link>

                    <Link
                        href="/product?tab=master&sub=uoms"
                        className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span>Atur Satuan (UOM)</span>
                    </Link>

                    <Link
                        href="/product?tab=master&sub=brands"
                        className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span>Atur Brand</span>
                    </Link>
                </div>
            )}
        </div>
    );
};
