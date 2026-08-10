<?php

namespace Modules\Contact\Http\Controllers;

use App\Access\CompanyAccess;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Contact\Application\CreateContact;
use Modules\Contact\Application\DeleteContact;
use Modules\Contact\Application\GetContact;
use Modules\Contact\Application\GetContacts;
use Modules\Contact\Application\UpdateContact;
use Modules\Contact\Http\Requests\StoreContactRequest;
use Modules\Contact\Http\Requests\UpdateContactRequest;

class ContactController extends Controller
{
    private const TYPES = [
        'customers' => 'customer',
        'suppliers' => 'supplier',
        'employees' => 'employee',
    ];

    public function __construct(
        private readonly GetContacts $getContacts,
        private readonly GetContact $getContact,
        private readonly CreateContact $createContact,
        private readonly UpdateContact $updateContact,
        private readonly DeleteContact $deleteContact,
    ) {}

    /**
     * Resolve the branch ids for data querying based on the active session context scope.
     *
     * @return list<int>
     */
    private function branchIds(): array
    {
        $tenantId = (string) tenant('id');
        /** @var User $user */
        $user = auth()->user();

        return CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
    }

    /**
     * Display a listing of contacts by type.
     */
    public function index(string $type)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $dbType = self::TYPES[$type];
        $view = 'Contact/'.ucfirst($type).'/index';

        return Inertia::render($view, [
            'contacts' => $this->getContacts->execute($dbType, $this->branchIds()),
            'branches' => $this->accessibleBranches(),
            'type' => $type,
        ]);
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(string $type)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return Inertia::render('Contact/create', [
            'branches' => $this->accessibleBranches(),
            'type' => $type,
        ]);
    }

    /**
     * Show the form for editing the specified contact.
     */
    public function edit(string $type, int $id)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return Inertia::render('Contact/edit', [
            'branches' => $this->accessibleBranches(),
            'type' => $type,
            'contact' => $this->getContact->execute($id, $this->branchIds()),
        ]);
    }

    /**
     * Store a newly created contact.
     */
    public function store(StoreContactRequest $request, string $type)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $dbType = self::TYPES[$type];
        $this->createContact->execute($dbType, $request->validated());

        return redirect()
            ->route('company.contacts.index', $type)
            ->with('success', 'Contact created successfully.');
    }

    /**
     * Update the specified contact.
     */
    public function update(UpdateContactRequest $request, string $type, int $id)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $this->updateContact->execute($id, $request->validated(), $this->branchIds());

        return redirect()
            ->route('company.contacts.index', $type)
            ->with('success', 'Contact updated successfully.');
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(string $type, int $id)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $this->deleteContact->execute($id, $this->branchIds());

        return redirect()->back()->with('success', 'Contact removed successfully.');
    }

    /**
     * Active branches the authenticated member may access, for the UI.
     *
     * @return list<object>
     */
    private function accessibleBranches(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $branchIds = CompanyAccess::accessibleBranchIds($user, (string) tenant('id'));

        return DB::table('branches')
            ->whereIn('id', $branchIds)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_headquarters'])
            ->map(fn ($branch) => (object) [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_headquarters' => (bool) $branch->is_headquarters,
            ])
            ->all();
    }
}
