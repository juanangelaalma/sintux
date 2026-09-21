<?php

namespace Modules\Purchasing\Infrastructure\External;

use Illuminate\Validation\ValidationException;

interface SupplierDoClient
{
    /**
     * Ambil DO supplier by nomor DO.
     *
     * @param  string|null  $customer  Nama customer supplier (mapping cabang).
     * @return array{
     *     do_no: string,
     *     po_no: string|null,
     *     do_date: string|null,
     *     cust_name: string|null,
     *     driver: string|null,
     *     nopol: string|null,
     *     inv_no: string|null,
     *     transaction_type: string|null,
     *     products: list<array{
     *         barcode: string,
     *         prd_code: string,
     *         prd_name: string,
     *         size: string|null,
     *         price: float,
     *         qty: float,
     *         details: list<array{color: string, qty: float}>
     *     }>
     * }
     *
     * @throws ValidationException Jika DO tidak ditemukan / respons tidak valid.
     */
    public function fetchByDoNo(string $doNo, ?string $customer = null): array;
}
