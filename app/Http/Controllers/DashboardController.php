<?php

namespace App\Http\Controllers;

use App\Access\CompanyAccess;
use App\Application\GetDashboard;
use Inertia\Inertia;
use Inertia\Response;

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
