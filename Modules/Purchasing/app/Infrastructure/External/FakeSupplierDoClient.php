<?php

namespace Modules\Purchasing\Infrastructure\External;

use Illuminate\Validation\ValidationException;

class FakeSupplierDoClient implements SupplierDoClient
{
    /** @var array<string, array> */
    private array $responses = [];

    public ?string $lastCustomer = null;

    /**
     * @param  array  $rawData  Isi key "data" mentah seperti dari supplier.
     */
    public function addResponse(string $doNo, array $rawData): void
    {
        $this->responses[$doNo] = $rawData;
    }

    public function fetchByDoNo(string $doNo, ?string $customer = null): array
    {
        $this->lastCustomer = $customer;

        if (! array_key_exists($doNo, $this->responses)) {
            throw ValidationException::withMessages([
                'do_no' => "{$doNo} tidak ditemukan di sistem supplier.",
            ]);
        }

        return HttpSupplierDoClient::normalize($doNo, $this->responses[$doNo]);
    }
}
