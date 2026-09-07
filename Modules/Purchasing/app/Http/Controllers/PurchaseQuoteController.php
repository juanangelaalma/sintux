<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseSummary;
use Modules\Purchasing\Application\PurchaseQuote\AcceptPurchaseQuote;
use Modules\Purchasing\Application\PurchaseQuote\CancelPurchaseQuote;
use Modules\Purchasing\Application\PurchaseQuote\CreatePurchaseQuote;
use Modules\Purchasing\Application\PurchaseQuote\GetPurchaseQuoteDetail;
use Modules\Purchasing\Application\PurchaseQuote\GetPurchaseQuotes;
use Modules\Purchasing\Application\PurchaseQuote\SendPurchaseQuote;
use Modules\Purchasing\Http\Requests\StorePurchaseQuoteRequest;

class PurchaseQuoteController extends Controller
{
    public function __construct(
        private readonly GetPurchaseQuotes $getPurchaseQuotes,
        private readonly GetPurchaseQuoteDetail $getPurchaseQuoteDetail,
        private readonly CreatePurchaseQuote $createPurchaseQuote,
        private readonly SendPurchaseQuote $sendPurchaseQuote,
        private readonly AcceptPurchaseQuote $acceptPurchaseQuote,
        private readonly CancelPurchaseQuote $cancelPurchaseQuote,
        private readonly GetPurchaseSummary $getPurchaseSummary,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $purchaseQuotes = $this->getPurchaseQuotes->execute($accessibleBranchIds, $filters);
        $summary = $this->getPurchaseSummary->execute($accessibleBranchIds);

        return Inertia::render('Purchasing/Quotes/index', [
            'purchaseQuotes' => $purchaseQuotes,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        return Inertia::render('Purchasing/Quotes/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'productVariants' => app(GetPurchaseVariants::class)->execute($accessibleBranchIds),
        ]);
    }

    public function store(StorePurchaseQuoteRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createPurchaseQuote->execute($validated, (string) $branchCode, $accessibleBranchIds);

        return redirect()->route('purchasing.quotes.index')
            ->with('success', 'Penawaran harga berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $purchaseQuote = $this->getPurchaseQuoteDetail->execute($id);

        return Inertia::render('Purchasing/Quotes/show', [
            'purchaseQuote' => $purchaseQuote,
        ]);
    }

    public function send(int $id): RedirectResponse
    {
        $this->sendPurchaseQuote->execute($id);

        return redirect()->route('purchasing.quotes.show', $id)
            ->with('success', 'Penawaran harga dikirim.');
    }

    public function accept(int $id): RedirectResponse
    {
        $this->acceptPurchaseQuote->execute($id);

        return redirect()->route('purchasing.quotes.show', $id)
            ->with('success', 'Penawaran harga diterima.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->cancelPurchaseQuote->execute($id);

        return redirect()->route('purchasing.quotes.show', $id)
            ->with('success', 'Penawaran harga dibatalkan.');
    }

    /**
     * @return list<int>
     */
    private function resolveBranchIds(User $user, string $tenantId): array
    {
        return CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
    }
}
