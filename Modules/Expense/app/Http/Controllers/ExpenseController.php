<?php

namespace Modules\Expense\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\TaxQuery;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Expense\Application\Expense\CreateExpense;
use Modules\Expense\Application\Expense\GetExpenseDetail;
use Modules\Expense\Application\Expense\GetExpenses;
use Modules\Expense\Application\Expense\GetExpenseSummary;
use Modules\Expense\Application\ExpenseTag\GetExpenseTags;
use Modules\Expense\Http\Requests\StoreExpenseRequest;
use Modules\Payment\Application\GetPaymentMethods;

/**
 * Controller tipis: hanya menerjemahkan HTTP ke use case. Tidak ada aturan
 * bisnis di sini (AGENTS.md §6).
 */
class ExpenseController extends Controller
{
    public function __construct(
        private readonly GetExpenses $getExpenses,
        private readonly GetExpenseSummary $getExpenseSummary,
        private readonly GetExpenseDetail $getExpenseDetail,
        private readonly CreateExpense $createExpense,
        private readonly GetExpenseTags $getExpenseTags,
        private readonly ChartOfAccountQuery $chartOfAccounts,
        private readonly TaxQuery $taxQuery,
        private readonly GetPaymentMethods $paymentMethods,
    ) {}

    public function index(): Response
    {
        $branchIds = $this->branchIds(request()->user());
        $filters = request()->only(['filter', 'search', 'status']);

        return Inertia::render('Expense/Expenses/index', [
            'expenses' => $this->getExpenses->execute($branchIds, $filters),
            'summary' => $this->getExpenseSummary->execute($branchIds),
            'statusCounts' => $this->getExpenseSummary->statusCounts($branchIds),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $branchIds = $this->branchIds($user);

        $activeBranch = collect(CompanyAccess::accessibleBranches($user, (string) session('active_tenant_id')))
            ->firstWhere('id', (int) session('active_branch_id'));

        return Inertia::render('Expense/Expenses/create', [
            'activeBranch' => $activeBranch,
            // PRD §7.2 kolom 1: daftar kas/bank. Kolom nomor opsi 10001
            // dipakai sebagai default sehingga form tidak kosong.
            'cashAccounts' => $this->chartOfAccounts->listCashAndBank(),
            // Kolom 11: dibatasi BR-02 lewat Accounting, bukan difilter di FE.
            'expenseAccounts' => $this->chartOfAccounts->listForExpense(),
            'taxes' => $this->taxQuery->listForPurchase(),
            'paymentMethods' => $this->paymentMethods->execute(),
            'contacts' => $this->contacts($branchIds),
            'tags' => $this->getExpenseTags->execute(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $user = $request->user();

        $activeBranch = collect(CompanyAccess::accessibleBranches($user, (string) session('active_tenant_id')))
            ->firstWhere('id', (int) session('active_branch_id'));

        abort_if(! $activeBranch, 422, 'Pilih cabang aktif terlebih dahulu.');

        $branchIds = $this->branchIds($user);
        $data = $request->validated();

        $expense = $this->createExpense->execute(
            [
                ...$data,
                'lines' => $data['lines'],
                'withholding' => $data['withholding'] ?? null,
                'tag_ids' => $data['tag_ids'] ?? [],
            ],
            (int) $activeBranch->id,
            $branchIds,
            $user?->id === null ? null : (int) $user->id,
            array_map(fn ($file): array => ['file' => $file], $request->file('attachments', [])),
        );

        return redirect()
            ->route('expenses.show', ['expense' => $expense->id])
            ->with('success', 'Biaya berhasil dibuat.');
    }

    public function show(int $expense): Response
    {
        return Inertia::render('Expense/Expenses/show', [
            'expense' => $this->getExpenseDetail->execute($expense, $this->branchIds(request()->user())),
        ]);
    }

    /**
     * Sales documents belong to the active branch: mengikuti cabang yang
     * sedang dipilih user, tidak pernah gabungan lintas cabang.
     *
     * @return list<int>
     */
    private function branchIds(?User $user): array
    {
        return CompanyAccess::contextBranchIds($user, (string) session('active_tenant_id'))
            ?? CompanyAccess::accessibleBranchIds($user, (string) session('active_tenant_id'));
    }

    /**
     * Q-14: tipe kontak supplier dan karyawan saja, digabung untuk form.
     *
     * @param  list<int>  $branchIds
     * @return list<array<string, mixed>>
     */
    private function contacts(array $branchIds): array
    {
        $contacts = app(GetContacts::class);

        return array_values(array_map(
            fn (array $contact): array => [
                'id' => $contact['id'],
                'name' => $contact['name'],
                'type' => $contact['type'],
                'email' => $contact['email'] ?? null,
            ],
            array_merge(
                $contacts->execute('supplier', $branchIds),
                $contacts->execute('employee', $branchIds),
            ),
        ));
    }
}
