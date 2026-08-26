<?php

namespace Modules\Approval\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Approval\Application\GetPendingApprovals;

class ApprovalInboxController extends Controller
{
    public function __construct(
        private readonly GetPendingApprovals $getPendingApprovals,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $pendingApprovals = $this->getPendingApprovals->execute($user->id);

        return Inertia::render('Approval/Inbox/index', [
            'pendingApprovals' => $pendingApprovals,
        ]);
    }
}
