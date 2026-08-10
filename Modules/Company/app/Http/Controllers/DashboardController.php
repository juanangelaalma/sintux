<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Company\Access\CompanyAccess;
use Modules\Company\Application\GetDashboard;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboard $getDashboard,
    ) {}

    /**
     * Display the company dashboard scoped to accessible branches.
     */
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'branchSummaries' => $this->getDashboard->execute(
                CompanyAccess::accessibleBranchIds(auth()->user(), (string) tenant('id')),
            ),
        ]);
    }
}
