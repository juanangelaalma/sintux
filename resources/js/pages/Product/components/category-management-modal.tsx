import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/components/ui/modal';
import Button from '@/components/ui/button';

type Category = {
    id: number;
    name: string;
    products_count?: number;
};

type Props = {
    show: boolean;
    onClose: () => void;
    categories: Category[];
};

export const CategoryManagementModal: React.FC<Props> = ({
    show,
    onClose,
    categories = [],
}) => {
    const [search, setSearch] = useState('');
    const [isCreating, setIsCreating] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingName, setEditingName] = useState('');

    if (!show) return null;

    const filteredCategories = categories.filter((c) =>
        c.name.toLowerCase().includes(search.toLowerCase())
    );

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newCategoryName.trim()) return;

        router.post(
            '/product/categories',
            { name: newCategoryName, is_active: true },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setNewCategoryName('');
                    setIsCreating(false);
                },
            }
        );
    };

    const handleUpdateSubmit = (id: number) => {
        if (!editingName.trim()) return;

        router.put(
            `/product/categories/${id}`,
            { name: editingName, is_active: true },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    setEditingName('');
                },
            }
        );
    };

    return (
        <Modal title="Atur kategori produk" onClose={onClose} maxWidth="md">
            <div className="space-y-4 pt-2">
                {/* Search & Add Category Bar */}
                <div className="flex items-center gap-3">
                    <div className="relative flex-1">
                        <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari kategori"
                            className="w-full rounded-lg border border-slate-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    {!isCreating && (
                        <button
                            type="button"
                            onClick={() => setIsCreating(true)}
                            className="rounded-lg border border-indigo-600 px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 transition-colors shrink-0"
                        >
                            Tambah kategori
                        </button>
                    )}
                </div>

                {/* Inline Add Category Form */}
                {isCreating && (
                    <form onSubmit={handleCreateSubmit} className="flex items-center gap-2 p-3 bg-indigo-50/60 rounded-lg border border-indigo-100">
                        <input
                            type="text"
                            value={newCategoryName}
                            onChange={(e) => setNewCategoryName(e.target.value)}
                            placeholder="Nama kategori baru"
                            className="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none"
                            autoFocus
                        />
                        <Button type="submit" variant="primary" className="px-3 py-1 text-xs">
                            Simpan
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            className="px-3 py-1 text-xs"
                            onClick={() => {
                                setIsCreating(false);
                                setNewCategoryName('');
                            }}
                        >
                            Batal
                        </Button>
                    </form>
                )}

                {/* Categories List Table (Image 2 style) */}
                <div className="max-h-72 overflow-y-auto border-t border-b border-slate-200 divide-y divide-slate-100">
                    <div className="flex items-center justify-between py-2 text-xs font-bold text-slate-700 uppercase tracking-wider sticky top-0 bg-white">
                        <span>Nama</span>
                        <span className="flex items-center gap-1">
                            Jumlah
                            <svg className="size-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                    </div>

                    {filteredCategories.length === 0 ? (
                        <div className="py-8 text-center text-xs text-slate-400">
                            Tidak ada kategori ditemukan.
                        </div>
                    ) : (
                        filteredCategories.map((c) => (
                            <div key={c.id} className="flex items-center justify-between py-2.5 group">
                                {editingId === c.id ? (
                                    <div className="flex items-center gap-2 flex-1 mr-4">
                                        <input
                                            type="text"
                                            value={editingName}
                                            onChange={(e) => setEditingName(e.target.value)}
                                            className="flex-1 rounded-lg border border-slate-300 px-2.5 py-1 text-xs focus:border-indigo-500 focus:outline-none"
                                            autoFocus
                                        />
                                        <button
                                            type="button"
                                            onClick={() => handleUpdateSubmit(c.id)}
                                            className="text-xs font-semibold text-indigo-600 hover:underline"
                                        >
                                            Simpan
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setEditingId(null)}
                                            className="text-xs text-slate-400 hover:underline"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium text-slate-800">
                                            {c.name}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setEditingId(c.id);
                                                setEditingName(c.name);
                                            }}
                                            className="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-100 transition-colors opacity-80 group-hover:opacity-100"
                                            title="Ubah nama"
                                        >
                                            <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                    </div>
                                )}

                                <span className="text-xs font-semibold text-slate-600">
                                    {c.products_count ?? 0}
                                </span>
                            </div>
                        ))
                    )}
                </div>

                {/* Footer Action */}
                <div className="flex justify-end pt-2">
                    <Button
                        type="button"
                        variant="primary"
                        className="bg-indigo-600 px-6 py-2 text-sm font-semibold"
                        onClick={onClose}
                    >
                        Selesai
                    </Button>
                </div>
            </div>
        </Modal>
    );
};
