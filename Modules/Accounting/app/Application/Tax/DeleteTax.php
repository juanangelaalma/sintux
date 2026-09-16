<?php

namespace Modules\Accounting\Application\Tax;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Tax;

class DeleteTax
{
    public function __construct(
        private readonly TaxReferences $references,
    ) {}

    public function execute(int $id): void
    {
        $tax = Tax::query()->findOrFail($id);

        $usage = $this->references->usage($tax->id);

        if ($usage !== []) {
            throw ValidationException::withMessages([
                'tax' => "Pajak tidak dapat dihapus karena digunakan pada {$this->references->describe($usage)}. Nonaktifkan pajak sebagai gantinya.",
            ]);
        }

        $tax->delete();
    }
}
