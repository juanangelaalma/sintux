<?php

namespace Modules\Approval\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Approval\Application\PerformApprovalAction;

class ApprovalActionController extends Controller
{
    public function __construct(
        private readonly PerformApprovalAction $performApprovalAction,
    ) {}

    public function approve(Request $request, int $mappingId): RedirectResponse
    {
        $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->performApprovalAction->execute(
            $mappingId,
            $request->user()->id,
            'approve',
            $request->input('comment'),
        );

        return back()->with('success', 'Persetujuan berhasil diberikan.');
    }

    public function reject(Request $request, int $mappingId): RedirectResponse
    {
        $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->performApprovalAction->execute(
            $mappingId,
            $request->user()->id,
            'reject',
            $request->input('comment'),
        );

        return back()->with('success', 'Transaksi berhasil ditolak.');
    }
}
