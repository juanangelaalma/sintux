<?php

namespace Modules\Expense\Application\Expense;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Expense\Models\Expense;
use Modules\Expense\Models\ExpenseTag;

/**
 * Daftar biaya dengan filter dan paginasi.
 *
 * Filter `filter` sengaja memakai predicate yang sama persis dengan
 * GetExpenseSummary, sehingga angka di kartu ringkasan dan hasil daftar
 * setelah kartu diklik selalu identik (PRD §11).
 */
class GetExpenses
{
    public const FILTER_THIS_MONTH = 'this_month';

    public const FILTER_LAST_30_DAYS = 'last_30_days';

    public const FILTER_UNPAID = 'unpaid';

    /**
     * @param  list<int>  $branchIds
     * @param  array{filter?: string|null, search?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function execute(array $branchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Expense::query()
            ->with(['lines', 'tags'])
            ->whereIn('branch_id', $branchIds);

        $this->applyFilter($query, $filters['filter'] ?? null);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $this->applySearch($query, (string) $filters['search']);
        }

        // Default terbaru di atas (PRD §7.1). `id` sebagai pemutus supaya
        // tanggal yang sama tetap punya urutan stabil antar halaman.
        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Expense $expense): array => [
                'id' => (int) $expense->id,
                'number' => (string) $expense->number,
                'transaction_date' => $expense->transaction_date->toDateString(),
                'contact_name' => $expense->contact_name,
                'status' => $expense->status->value,
                'grand_total' => round((float) $expense->grand_total, 2),
                'amount_paid' => round((float) $expense->amount_paid, 2),
                'outstanding' => round($expense->outstanding(), 2),
                'category_label' => $expense->categoryLabel(),
                'tags' => $expense->tags
                    ->map(fn (ExpenseTag $tag): array => [
                        'id' => (int) $tag->id,
                        'name' => (string) $tag->name,
                        'color' => $tag->color,
                    ])
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * Dipakai juga GetExpenseSummary supaya definisi "bulan ini", "30 hari",
     * dan "belum dibayar" hanya ada di satu tempat.
     *
     * @param  Builder<Expense>  $query
     */
    public function applyFilter($query, ?string $filter): void
    {
        if ($filter === self::FILTER_THIS_MONTH) {
            $query->whereBetween('transaction_date', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])->where('status', 'closed');

            return;
        }

        if ($filter === self::FILTER_LAST_30_DAYS) {
            $query
                ->where('transaction_date', '>=', Carbon::now()->subDays(30)->toDateString())
                ->where('status', 'closed');

            return;
        }

        if ($filter === self::FILTER_UNPAID) {
            // Semua periode: kartu 3 tidak dibatasi tanggal.
            $query->where('status', 'open');
        }
    }

    /**
     * Pencarian mencakup nomor biaya, kategori akun biaya, dan tag
     * (PRD §7.1, terkonfirmasi di S1).
     *
     * @param  Builder<Expense>  $query
     */
    private function applySearch($query, string $search): void
    {
        $needle = mb_strtolower(trim($search));

        $query->where(function ($builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(expenses.number) LIKE ?', ["%{$needle}%"])
                ->orWhereHas('lines', fn ($lines) => $lines->whereRaw('LOWER(expense_lines.account_name) LIKE ?', ["%{$needle}%"]))
                ->orWhereHas('tags', fn ($tags) => $tags->whereRaw('LOWER(expense_tags.name) LIKE ?', ["%{$needle}%"]));
        });
    }

    /**
     * Total dan jumlah transaksi untuk satu kartu ringkasan.
     *
     * @param  list<int>  $branchIds
     * @return array{total: float, count: int}
     */
    public function summaryFor(array $branchIds, ?string $filter): array
    {
        $query = Expense::query()->whereIn('branch_id', $branchIds);
        $this->applyFilter($query, $filter);

        $row = (clone $query)
            ->selectRaw('COALESCE(SUM(grand_total), 0) AS total, COUNT(*) AS aggregate_count')
            ->first();

        return [
            'total' => round((float) ($row->total ?? 0), 2),
            'count' => (int) ($row->aggregate_count ?? 0),
        ];
    }

    /**
     * Sisa tagihan belum dibayar: total outstanding, bukan total transaksi.
     *
     * @param  list<int>  $branchIds
     * @return array{total: float, count: int}
     */
    public function unpaidSummary(array $branchIds): array
    {
        $row = Expense::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'open')
            ->selectRaw('COALESCE(SUM(grand_total - amount_paid), 0) AS total, COUNT(*) AS aggregate_count')
            ->first();

        return [
            'total' => round((float) ($row->total ?? 0), 2),
            'count' => (int) ($row->aggregate_count ?? 0),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<string, int>
     */
    public function countsByStatus(array $branchIds): array
    {
        $rows = Expense::query()
            ->whereIn('branch_id', $branchIds)
            ->select('status', DB::raw('COUNT(*) AS aggregate_count'))
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        return $rows->map(fn ($count): int => (int) $count)->all();
    }
}
