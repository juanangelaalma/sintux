<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\Tax\CreateTax;
use Modules\Accounting\Application\Tax\DeleteTax;
use Modules\Accounting\Application\Tax\GetTaxes;
use Modules\Accounting\Application\Tax\UpdateTax;
use Modules\Accounting\Http\Requests\StoreTaxRequest;
use Modules\Accounting\Http\Requests\UpdateTaxRequest;
use Modules\Accounting\Models\Tax;

class TaxController extends Controller
{
    public function __construct(
        private readonly GetTaxes $getTaxes,
        private readonly CreateTax $createTax,
        private readonly UpdateTax $updateTax,
        private readonly DeleteTax $deleteTax,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Accounting/Taxes/index', [
            'taxes' => $this->getTaxes->execute(),
        ]);
    }

    public function create(ChartOfAccountQuery $accounts): Response
    {
        return Inertia::render('Accounting/Taxes/create', [
            'accounts' => $accounts->listChartOfAccounts(),
            'singleTaxes' => $this->groupableSingles(),
        ]);
    }

    public function store(StoreTaxRequest $request): RedirectResponse
    {
        $tax = $this->createTax->execute($request->validated());

        return redirect()->route('accounting.taxes.show', $tax->id)
            ->with('success', 'Pajak berhasil dibuat.');
    }

    public function show(int $tax): Response
    {
        return Inertia::render('Accounting/Taxes/show', [
            'tax' => $this->getTaxes->find($tax),
        ]);
    }

    public function edit(int $tax, ChartOfAccountQuery $accounts): Response
    {
        return Inertia::render('Accounting/Taxes/edit', [
            'tax' => $this->getTaxes->find($tax),
            'accounts' => $accounts->listChartOfAccounts(),
            'singleTaxes' => $this->groupableSingles(),
        ]);
    }

    public function update(UpdateTaxRequest $request, int $tax): RedirectResponse
    {
        $updated = $this->updateTax->execute($tax, $request->validated());

        return redirect()->route('accounting.taxes.show', $updated->id)
            ->with('success', 'Pajak berhasil diperbarui.');
    }

    public function destroy(int $tax): RedirectResponse
    {
        $this->deleteTax->execute($tax);

        return redirect()->route('accounting.taxes.index')
            ->with('success', 'Pajak berhasil dihapus.');
    }

    /**
     * Pajak satuan aktif sebagai kandidat anggota grup.
     *
     * @return list<array<string, mixed>>
     */
    private function groupableSingles(): array
    {
        return Tax::query()
            ->where('type', Tax::TYPE_SINGLE)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'rate', 'is_withholding', 'dpp_multiplier'])
            ->map(fn (Tax $tax): array => [
                'id' => $tax->id,
                'name' => $tax->name,
                'code' => $tax->code,
                'rate' => (float) $tax->rate,
                'signed_rate' => $tax->is_withholding ? -abs((float) $tax->rate) : (float) $tax->rate,
                'dpp_multiplier' => $tax->dpp_multiplier,
            ])
            ->values()
            ->all();
    }
}
