<?php

namespace Modules\Approval\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Application\DeleteApprovalRule;
use Modules\Approval\Application\GetApprovalRule;
use Modules\Approval\Application\GetApprovalRuleLogs;
use Modules\Approval\Application\GetApprovalRules;
use Modules\Approval\Application\UpdateApprovalRule;
use Modules\Approval\Http\Requests\StoreApprovalRuleRequest;
use Modules\Approval\Http\Requests\UpdateApprovalRuleRequest;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Application\CompanyAccess;

class ApprovalRuleController extends Controller
{
    public function __construct(
        private readonly GetApprovalRules $getApprovalRules,
        private readonly GetApprovalRule $getApprovalRule,
        private readonly CreateApprovalRule $createApprovalRule,
        private readonly UpdateApprovalRule $updateApprovalRule,
        private readonly DeleteApprovalRule $deleteApprovalRule,
        private readonly GetApprovalRuleLogs $getApprovalRuleLogs,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'approval.rule.view')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat aturan approval.');
        }

        $typeKey = $request->query('transaction_type');
        $search = $request->query('search');

        $rules = $this->getApprovalRules->execute($typeKey, $search);
        $transactionTypes = ApprovalTransactionType::orderBy('id')->get();

        return Inertia::render('Approval/Rules/index', [
            'rules' => $rules,
            'transactionTypes' => $transactionTypes,
            'filters' => [
                'transaction_type' => $typeKey,
                'search' => $search,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'approval.rule.manage')) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola aturan approval.');
        }

        $transactionTypes = ApprovalTransactionType::orderBy('id')->get();
        $companyUsers = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Approval/Rules/create', [
            'transactionTypes' => $transactionTypes,
            'users' => $companyUsers,
        ]);
    }

    public function store(StoreApprovalRuleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'approval.rule.manage')) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola aturan approval.');
        }

        $this->createApprovalRule->execute($request->validated(), $user->id);

        return redirect()->route('approval.rules.index')
            ->with('success', 'Aturan approval berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'approval.rule.manage')) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola aturan approval.');
        }

        $rule = $this->getApprovalRule->execute($id);
        $transactionTypes = ApprovalTransactionType::orderBy('id')->get();
        $companyUsers = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Approval/Rules/edit', [
            'rule' => $rule,
            'transactionTypes' => $transactionTypes,
            'users' => $companyUsers,
        ]);
    }

    public function update(UpdateApprovalRuleRequest $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'approval.rule.manage')) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola aturan approval.');
        }

        $this->updateApprovalRule->execute($id, $request->validated(), $user->id);

        return redirect()->route('approval.rules.index')
            ->with('success', 'Aturan approval berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'approval.rule.manage')) {
            abort(403, 'Anda tidak memiliki akses untuk mengelola aturan approval.');
        }

        $this->deleteApprovalRule->execute($id, $user->id);

        return redirect()->route('approval.rules.index')
            ->with('success', 'Aturan approval berhasil dihapus.');
    }

    public function logs(Request $request, int $id): Response
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'approval.rule.view')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat log aturan approval.');
        }

        $rule = $this->getApprovalRule->execute($id);
        $logs = $this->getApprovalRuleLogs->execute($id);

        return Inertia::render('Approval/Rules/logs', [
            'rule' => $rule,
            'logs' => $logs,
        ]);
    }
}
