<?php

namespace Modules\Contact\Http\Controllers;

use App\Http\Controllers\Controller;
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
     * Display a listing of contacts by type.
     */
    public function index(string $type)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $dbType = self::TYPES[$type];
        $view = 'Contact/' . ucfirst($type) . '/index';

        return Inertia::render($view, [
            'contacts' => $this->getContacts->execute($dbType),
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
            'type' => $type,
            'contact' => $this->getContact->execute($id),
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

        $this->updateContact->execute($id, $request->validated());

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

        $this->deleteContact->execute($id);

        return redirect()->back()->with('success', 'Contact removed successfully.');
    }
}
