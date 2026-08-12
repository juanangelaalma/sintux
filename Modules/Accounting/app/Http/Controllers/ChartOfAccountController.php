<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Application\CreateChartOfAccount;
use Modules\Accounting\Application\GenerateChartOfAccountCode;
use Modules\Accounting\Application\GetChartOfAccounts;
use Modules\Accounting\Application\UpdateChartOfAccount;
use Modules\Accounting\Http\Requests\StoreChartOfAccountRequest;
use Modules\Accounting\Http\Requests\UpdateChartOfAccountRequest;
use Modules\Accounting\Models\AccountCategory;
use Modules\Accounting\Models\ChartOfAccount;

class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly GetChartOfAccounts $getChartOfAccounts,
        private readonly GenerateChartOfAccountCode $generateChartOfAccountCode,
        private readonly CreateChartOfAccount $createChartOfAccount,
        private readonly UpdateChartOfAccount $updateChartOfAccount,
    ) {}

    /**
     * Display the tenant's chart of accounts.
     */
    public function index(): Response
    {
        return Inertia::render('Accounting/ChartOfAccounts/index', [
            ...$this->getChartOfAccounts->execute(),
            'canManageAccounts' => Gate::allows('accounting.account.manage'),
        ]);
    }

    /**
     * Recommend the next code for the selected category.
     */
    public function suggestedCode(int $accountCategory): JsonResponse
    {
        $category = AccountCategory::query()->findOrFail($accountCategory);

        return response()->json([
            'code' => $this->generateChartOfAccountCode->execute($category),
        ]);
    }

    /**
     * Store a new chart of account.
     */
    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $this->createChartOfAccount->execute($request->validated());

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', 'Akun berhasil dibuat.');
    }

    /**
     * Update an existing chart of account.
     */
    public function update(UpdateChartOfAccountRequest $request, int $chartOfAccount): RedirectResponse
    {
        $account = ChartOfAccount::query()->findOrFail($chartOfAccount);
        $this->updateChartOfAccount->execute($account, $request->validated());

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }
}
