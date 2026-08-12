import { Head, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useRef, useState } from 'react';
import Button from '@/components/ui/button';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import Modal from '@/components/ui/modal';
import MultiSelect from '@/components/ui/multi-select';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import { AngleRightIcon, ChevronDownIcon } from '@/icons';
import CompanyLayout from '@/layouts/company/company-layout';
import {
    store as storeChartOfAccount,
    suggestedCode,
    update as updateChartOfAccount,
} from '@/routes/accounting/chart-of-accounts';

type AccountNode = {
    id: number;
    parent_id: number | null;
    account_category_id: number;
    default_tax_id: number | null;
    code: string;
    name: string;
    description: string | null;
    is_header: boolean;
    is_archived: boolean;
    category_name: string;
    type_name: string;
    normal_balance: 'debit' | 'credit';
    children: AccountNode[];
};

type AccountRow = AccountNode & {
    depth: number;
};

type AccountCategory = {
    id: number;
    name: string;
    prefix: string | null;
    type_name: string;
    type_prefix: string | null;
};

type ParentAccount = {
    id: number;
    account_category_id: number;
    code: string;
    name: string;
};

type Tax = {
    id: number;
    name: string;
    code: string;
    rate: number;
    is_active: boolean;
};

type Props = {
    accounts: AccountNode[];
    categories: AccountCategory[];
    parentAccounts: ParentAccount[];
    taxes: Tax[];
    canManageAccounts: boolean;
};

type DetailType = 'none' | 'sub_account' | 'header';

type AccountForm = {
    account_category_id: string;
    detail_type: DetailType;
    parent_id: string;
    header_account_ids: number[];
    default_tax_id: string;
    code: string;
    name: string;
    description: string;
};

function collectParentIds(accounts: AccountNode[]): Set<number> {
    const ids = new Set<number>();

    const visit = (nodes: AccountNode[]) => {
        nodes.forEach((node) => {
            if (node.children.length > 0) {
                ids.add(node.id);
                visit(node.children);
            }
        });
    };

    visit(accounts);

    return ids;
}

function findAccount(
    accounts: AccountNode[],
    accountId: number,
): AccountNode | null {
    for (const account of accounts) {
        if (account.id === accountId) {
            return account;
        }

        const child = findAccount(account.children, accountId);

        if (child) {
            return child;
        }
    }

    return null;
}

function collectAccountIds(account: AccountNode): Set<number> {
    const ids = new Set<number>([account.id]);

    account.children.forEach((child) => {
        collectAccountIds(child).forEach((id) => ids.add(id));
    });

    return ids;
}

function flattenAccounts(accounts: AccountNode[]): AccountNode[] {
    return accounts.flatMap((account) => [
        account,
        ...flattenAccounts(account.children),
    ]);
}

function findAncestorIds(
    accounts: AccountNode[],
    accountId: number,
    ancestors: number[] = [],
): Set<number> {
    for (const account of accounts) {
        if (account.id === accountId) {
            return new Set(ancestors);
        }

        const result = findAncestorIds(account.children, accountId, [
            ...ancestors,
            account.id,
        ]);

        if (result.size > 0) {
            return result;
        }
    }

    return new Set();
}

function filterTree(
    accounts: AccountNode[],
    query: string,
    showArchived: boolean,
): AccountNode[] {
    return accounts.flatMap((account) => {
        const children = filterTree(account.children, query, showArchived);
        const matchesQuery = [
            account.code,
            account.name,
            account.category_name,
            account.type_name,
        ].some((value) => value.toLocaleLowerCase('id').includes(query));
        const isVisible = showArchived || !account.is_archived;

        if ((isVisible && matchesQuery) || children.length > 0) {
            return [{ ...account, children }];
        }

        return [];
    });
}

function flattenTree(
    accounts: AccountNode[],
    expandedIds: Set<number>,
    forceExpanded: boolean,
    depth = 0,
): AccountRow[] {
    return accounts.flatMap((account) => {
        const row = { ...account, depth };
        const showChildren = forceExpanded || expandedIds.has(account.id);

        return showChildren
            ? [
                  row,
                  ...flattenTree(
                      account.children,
                      expandedIds,
                      forceExpanded,
                      depth + 1,
                  ),
              ]
            : [row];
    });
}

export default function Index({
    accounts,
    categories,
    parentAccounts,
    taxes,
    canManageAccounts,
}: Props) {
    const [query, setQuery] = useState('');
    const [showArchived, setShowArchived] = useState(false);
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editAccount, setEditAccount] = useState<AccountNode | null>(null);
    const [isSuggestingCode, setIsSuggestingCode] = useState(false);
    const [suggestionError, setSuggestionError] = useState<string | null>(null);
    const suggestionRequest = useRef<AbortController | null>(null);
    const [expandedIds, setExpandedIds] = useState(() =>
        collectParentIds(accounts),
    );
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<AccountForm>({
            account_category_id: '',
            detail_type: 'none',
            parent_id: '',
            header_account_ids: [],
            default_tax_id: '',
            code: '',
            name: '',
            description: '',
        });
    const normalizedQuery = query.trim().toLocaleLowerCase('id');
    const allAccounts = useMemo(() => flattenAccounts(accounts), [accounts]);

    const rows = useMemo(() => {
        const filtered = filterTree(accounts, normalizedQuery, showArchived);

        return flattenTree(filtered, expandedIds, normalizedQuery.length > 0);
    }, [accounts, expandedIds, normalizedQuery, showArchived]);

    const toggleExpanded = (accountId: number) => {
        setExpandedIds((current) => {
            const next = new Set(current);

            if (next.has(accountId)) {
                next.delete(accountId);
            } else {
                next.add(accountId);
            }

            return next;
        });
    };

    const unavailableParentIds = useMemo(
        () =>
            editAccount ? collectAccountIds(editAccount) : new Set<number>(),
        [editAccount],
    );

    const availableParents = useMemo(
        () =>
            parentAccounts.filter(
                (account) =>
                    account.account_category_id ===
                        Number(data.account_category_id) &&
                    !unavailableParentIds.has(account.id),
            ),
        [data.account_category_id, parentAccounts, unavailableParentIds],
    );

    const unavailableHeaderAccountIds = useMemo(() => {
        if (!editAccount) {
            return new Set<number>();
        }

        return new Set([
            editAccount.id,
            ...findAncestorIds(accounts, editAccount.id),
        ]);
    }, [accounts, editAccount]);

    const availableHeaderAccounts = useMemo(
        () =>
            allAccounts.filter(
                (account) =>
                    !account.is_archived &&
                    account.account_category_id ===
                        Number(data.account_category_id) &&
                    !unavailableHeaderAccountIds.has(account.id),
            ),
        [allAccounts, data.account_category_id, unavailableHeaderAccountIds],
    );

    const openCreate = () => {
        clearErrors();
        reset();
        setEditAccount(null);
        setSuggestionError(null);
        setIsCreateOpen(true);
    };

    const openEdit = (accountId: number) => {
        const account = findAccount(accounts, accountId);

        if (!account) {
            return;
        }

        clearErrors();
        setSuggestionError(null);
        setIsCreateOpen(false);
        setEditAccount(account);
        setData({
            account_category_id: String(account.account_category_id),
            detail_type: account.is_header
                ? 'header'
                : account.parent_id
                  ? 'sub_account'
                  : 'none',
            parent_id: account.parent_id ? String(account.parent_id) : '',
            header_account_ids: account.children
                .filter((child) => !child.is_archived)
                .map((child) => child.id),
            default_tax_id: account.default_tax_id
                ? String(account.default_tax_id)
                : '',
            code: account.code,
            name: account.name,
            description: account.description ?? '',
        });
    };

    const closeAccountModal = () => {
        suggestionRequest.current?.abort();
        suggestionRequest.current = null;
        setIsSuggestingCode(false);
        setSuggestionError(null);
        setIsCreateOpen(false);
        setEditAccount(null);
        reset();
        clearErrors();
    };

    const selectCategory = async (categoryId: string) => {
        suggestionRequest.current?.abort();
        setData((current) => ({
            ...current,
            account_category_id: categoryId,
            detail_type: 'none',
            parent_id: '',
            header_account_ids: [],
            default_tax_id: '',
            code: '',
        }));
        setSuggestionError(null);

        if (!categoryId) {
            setIsSuggestingCode(false);

            return;
        }

        const controller = new AbortController();
        suggestionRequest.current = controller;
        setIsSuggestingCode(true);

        try {
            const response = await fetch(suggestedCode.url(categoryId), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error('Recommendation request failed.');
            }

            const result = (await response.json()) as { code: string };

            if (suggestionRequest.current === controller) {
                setData('code', result.code);
            }
        } catch (error) {
            if (!(
                error instanceof DOMException && error.name === 'AbortError'
            )) {
                setSuggestionError(
                    'Kode rekomendasi tidak dapat dibuat. Silakan coba lagi.',
                );
            }
        } finally {
            if (suggestionRequest.current === controller) {
                suggestionRequest.current = null;
                setIsSuggestingCode(false);
            }
        }
    };

    const selectDetailType = (detailType: DetailType) => {
        setData((current) => ({
            ...current,
            detail_type: detailType,
            parent_id: detailType === 'sub_account' ? current.parent_id : '',
            header_account_ids:
                detailType === 'header' ? current.header_account_ids : [],
            default_tax_id:
                detailType === 'header' ? '' : current.default_tax_id,
        }));
    };

    const submitAccount = (event: FormEvent) => {
        event.preventDefault();

        if (editAccount) {
            put(updateChartOfAccount.url(editAccount.id), {
                preserveScroll: true,
                onSuccess: closeAccountModal,
            });

            return;
        }

        post(storeChartOfAccount.url(), {
            preserveScroll: true,
            onSuccess: closeAccountModal,
        });
    };

    return (
        <>
            <Head title="Daftar Akun" />

            <div className="space-y-6">
                <PageHeader
                    title="Daftar Akun"
                    description="Lihat struktur Chart of Accounts perusahaan Anda."
                    actions={
                        canManageAccounts ? (
                            <Button onClick={openCreate}>Buat Akun Baru</Button>
                        ) : undefined
                    }
                />

                <section className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
                    <div className="border-b border-gray-100 px-5 py-4 dark:border-white/[0.05]">
                        <div className="rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-200">
                            Akun header digunakan sebagai pengelompokan.
                            Transaksi dicatat pada akun turunannya.
                        </div>

                        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={showArchived}
                                    onChange={(event) =>
                                        setShowArchived(event.target.checked)
                                    }
                                    className="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                                />
                                Tampilkan arsip akun
                            </label>

                            <div className="relative w-full sm:w-80">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Cari kode atau nama akun"
                                    className="h-10 w-full rounded-lg border border-gray-300 bg-transparent pr-3 pl-9 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white dark:placeholder:text-white/30"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="max-w-full overflow-x-auto">
                        <table className="w-full min-w-[1000px]">
                            <thead className="border-b border-brand-100">
                                <tr>
                                    <th className="w-40 px-5 py-3 text-left text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                        Kode Akun
                                    </th>
                                    <th className="px-5 py-3 text-left text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                        Nama Akun
                                    </th>
                                    <th className="w-56 px-5 py-3 text-left text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                        Kategori Akun
                                    </th>
                                    <th className="w-44 px-5 py-3 text-left text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                        Tipe Akun
                                    </th>
                                    <th className="w-36 px-5 py-3 text-left text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                        Saldo Normal
                                    </th>
                                    <th className="w-28 px-5 py-3 text-left text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                        Pajak
                                    </th>
                                    <th className="w-28 px-5 py-3 text-left text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                        Status
                                    </th>
                                    {canManageAccounts && (
                                        <th className="w-24 px-5 py-3 text-right text-xs font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={canManageAccounts ? 8 : 7}
                                            className="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            Tidak ada akun yang sesuai dengan
                                            pencarian.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((account) => {
                                        const hasChildren =
                                            account.children.length > 0;
                                        const isExpanded =
                                            normalizedQuery.length > 0 ||
                                            expandedIds.has(account.id);

                                        return (
                                            <tr
                                                key={account.id}
                                                className={
                                                    account.is_header
                                                        ? 'bg-gray-50/70 dark:bg-white/[0.02]'
                                                        : 'hover:bg-gray-50/60 dark:hover:bg-white/[0.02]'
                                                }
                                            >
                                                <td className="px-5 py-3 text-sm whitespace-nowrap text-gray-700 tabular-nums dark:text-gray-300">
                                                    {account.code}
                                                </td>
                                                <td className="px-5 py-3 text-sm text-gray-800 dark:text-gray-200">
                                                    <div
                                                        className="flex items-start gap-2"
                                                        style={{
                                                            paddingLeft: `${account.depth * 24}px`,
                                                        }}
                                                    >
                                                        {hasChildren ? (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    toggleExpanded(
                                                                        account.id,
                                                                    )
                                                                }
                                                                aria-label={`${isExpanded ? 'Tutup' : 'Buka'} ${account.name}`}
                                                                className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded text-gray-500 hover:bg-gray-200 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-white"
                                                            >
                                                                {isExpanded ? (
                                                                    <ChevronDownIcon className="size-3.5" />
                                                                ) : (
                                                                    <AngleRightIcon className="size-3.5" />
                                                                )}
                                                            </button>
                                                        ) : (
                                                            <span className="w-5 shrink-0 text-center text-gray-300">
                                                                —
                                                            </span>
                                                        )}

                                                        <div>
                                                            <span
                                                                className={
                                                                    account.is_header
                                                                        ? 'font-semibold text-gray-900 dark:text-white'
                                                                        : 'font-medium text-brand-600 dark:text-brand-400'
                                                                }
                                                            >
                                                                {account.name}
                                                            </span>
                                                            {account.description && (
                                                                <p className="mt-0.5 text-xs text-gray-500 italic dark:text-gray-400">
                                                                    {
                                                                        account.description
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-5 py-3 text-sm whitespace-nowrap text-brand-600 dark:text-brand-400">
                                                    {account.category_name}
                                                </td>
                                                <td className="px-5 py-3 text-sm whitespace-nowrap text-gray-600 dark:text-gray-300">
                                                    {account.type_name}
                                                </td>
                                                <td className="px-5 py-3 text-sm whitespace-nowrap text-gray-600 capitalize dark:text-gray-300">
                                                    {account.normal_balance}
                                                </td>
                                                <td className="px-5 py-3 text-sm whitespace-nowrap text-gray-600 dark:text-gray-300">
                                                    {taxes.find(
                                                        (tax) =>
                                                            tax.id ===
                                                            account.default_tax_id,
                                                    )?.code ?? '—'}
                                                </td>
                                                <td className="px-5 py-3 text-sm whitespace-nowrap">
                                                    <span
                                                        className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                                            account.is_archived
                                                                ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                                        }`}
                                                    >
                                                        {account.is_archived
                                                            ? 'Arsip'
                                                            : 'Aktif'}
                                                    </span>
                                                </td>
                                                {canManageAccounts && (
                                                    <td className="px-5 py-3 text-right text-sm whitespace-nowrap">
                                                        {!account.is_archived && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    openEdit(
                                                                        account.id,
                                                                    )
                                                                }
                                                                className="font-medium text-brand-500 hover:text-brand-600"
                                                            >
                                                                Edit
                                                            </button>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 dark:border-white/[0.05] dark:text-gray-400">
                        Menampilkan {rows.length} akun
                    </div>
                </section>
            </div>

            {(isCreateOpen || editAccount) && (
                <Modal
                    title={
                        editAccount ? 'Edit informasi akun' : 'Informasi akun'
                    }
                    maxWidth="2xl"
                    onClose={closeAccountModal}
                >
                    <form onSubmit={submitAccount} className="mt-5 space-y-4">
                        <FormField label="Nama" error={errors.name}>
                            <TextInput
                                required
                                maxLength={150}
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                placeholder="Contoh: Rekening Bank Utama"
                            />
                        </FormField>

                        <FormField
                            label="Nomor"
                            error={errors.code ?? suggestionError ?? undefined}
                        >
                            <TextInput
                                required
                                maxLength={50}
                                value={data.code}
                                onChange={(event) =>
                                    setData('code', event.target.value)
                                }
                                disabled={
                                    !data.account_category_id ||
                                    isSuggestingCode
                                }
                                placeholder={
                                    isSuggestingCode
                                        ? 'Membuat rekomendasi...'
                                        : 'Contoh: 1-10001'
                                }
                            />
                        </FormField>

                        <FormField
                            label="Kategori"
                            error={errors.account_category_id}
                        >
                            <SelectInput
                                required
                                value={data.account_category_id}
                                disabled={
                                    !!editAccount &&
                                    editAccount.children.length > 0
                                }
                                onChange={(event) =>
                                    void selectCategory(event.target.value)
                                }
                            >
                                <option value="">Pilih kategori</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                        disabled={
                                            !category.type_prefix ||
                                            !category.prefix
                                        }
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </SelectInput>
                        </FormField>

                        <FormField label="Detail" error={errors.detail_type}>
                            <SelectInput
                                required
                                value={data.detail_type}
                                disabled={!data.account_category_id}
                                onChange={(event) =>
                                    selectDetailType(
                                        event.target.value as DetailType,
                                    )
                                }
                            >
                                <option value="none">None</option>
                                <option value="sub_account">
                                    Sub-Akun Dari :
                                </option>
                                <option value="header">
                                    Akun Header dari:
                                </option>
                            </SelectInput>
                        </FormField>

                        {data.detail_type === 'sub_account' && (
                            <FormField
                                label="Pilih akun"
                                error={errors.parent_id}
                                labelClassName="sr-only"
                            >
                                <SelectInput
                                    required
                                    value={data.parent_id}
                                    onChange={(event) =>
                                        setData('parent_id', event.target.value)
                                    }
                                >
                                    <option value="">Pilih akun</option>
                                    {availableParents.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.code} — {account.name}
                                        </option>
                                    ))}
                                </SelectInput>
                            </FormField>
                        )}

                        {data.detail_type === 'header' && (
                            <FormField
                                label="Pilih akun yang menjadi turunan"
                                error={errors.header_account_ids}
                                labelClassName="sr-only"
                            >
                                <MultiSelect
                                    values={data.header_account_ids}
                                    options={availableHeaderAccounts.map(
                                        (account) => ({
                                            value: account.id,
                                            label: `${account.code} — ${account.name}`,
                                        }),
                                    )}
                                    onChange={(values) =>
                                        setData('header_account_ids', values)
                                    }
                                    placeholder="Pilih akun"
                                />
                            </FormField>
                        )}

                        {data.detail_type !== 'header' && (
                            <FormField
                                label="Pajak"
                                error={errors.default_tax_id}
                            >
                                <SelectInput
                                    value={data.default_tax_id}
                                    onChange={(event) =>
                                        setData(
                                            'default_tax_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih pajak</option>
                                    {taxes
                                        .filter(
                                            (tax) =>
                                                tax.is_active ||
                                                tax.id ===
                                                    editAccount?.default_tax_id,
                                        )
                                        .map((tax) => (
                                            <option key={tax.id} value={tax.id}>
                                                {tax.name} ({tax.code}) —{' '}
                                                {tax.rate}%
                                                {tax.is_active
                                                    ? ''
                                                    : ' (nonaktif)'}
                                            </option>
                                        ))}
                                </SelectInput>
                            </FormField>
                        )}

                        <FormField label="Deskripsi" error={errors.description}>
                            <textarea
                                rows={4}
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            />
                        </FormField>

                        {data.detail_type === 'header' && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Akun header digunakan untuk pengelompokan dan
                                tidak menerima transaksi langsung.
                            </p>
                        )}

                        <FormActions
                            onCancel={closeAccountModal}
                            submitLabel={
                                editAccount ? 'Simpan Perubahan' : 'Buat Akun'
                            }
                            cancelLabel="Batal"
                            processing={processing || isSuggestingCode}
                        />
                    </form>
                </Modal>
            )}
        </>
    );
}

Index.layout = (page: React.ReactNode) => <CompanyLayout>{page}</CompanyLayout>;
