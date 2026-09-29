<?php

namespace Modules\Purchasing\Infrastructure\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class HttpSupplierDoClient implements SupplierDoClient
{
    public function fetchByDoNo(string $doNo, ?string $customer = null): array
    {
        $config = config('purchasing.supplier_do', []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            throw ValidationException::withMessages([
                'do_no' => 'Integrasi DO supplier belum dikonfigurasi.',
            ]);
        }

        // Kredensial (api-key + digest auth) ikut terkirim: wajib HTTPS
        // agar tidak bocor lewat jaringan/proxy dalam bentuk plaintext.
        if (strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME)) !== 'https') {
            throw ValidationException::withMessages([
                'do_no' => 'Integrasi DO supplier belum dikonfigurasi dengan benar.',
            ]);
        }

        $request = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout((int) ($config['timeout'] ?? 15));

        if (! ($config['verify_ssl'] ?? true)) {
            $request->withoutVerifying();
        }

        if (($config['username'] ?? '') !== '') {
            $request->withDigestAuth((string) $config['username'], (string) ($config['password'] ?? ''));
        }

        // Catatan: api-key wajib di query string mengikuti spesifikasi
        // API supplier (tidak bisa dipindah ke header). Risikonya (URL
        // tercatat di access log) diterima karena koneksi selalu HTTPS.
        $query = [
            'api-key' => (string) ($config['api_key'] ?? ''),
            'no_do' => $doNo,
        ];

        if ($customer !== null && trim($customer) !== '') {
            $query['customer'] = $customer;
        }

        $response = $request->get($baseUrl.'/api/transaksi/get_delivery_order', $query);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'do_no' => 'Gagal mengambil DO dari supplier, coba lagi nanti.',
            ]);
        }

        return self::normalize($doNo, $response->json('data'));
    }

    /**
     * @param  mixed  $data  Isi key "data" dari respons supplier (untrusted).
     */
    public static function normalize(string $doNo, mixed $data): array
    {
        // Supplier mengembalikan HTTP 200 + data kosong saat DO tidak ada.
        if ($data === [] || $data === null) {
            throw ValidationException::withMessages([
                'do_no' => "{$doNo} tidak ditemukan di sistem supplier.",
            ]);
        }

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'do_no' => "Format {$doNo} dari supplier tidak dikenali.",
            ]);
        }

        $products = [];
        foreach ((array) ($data['product'] ?? []) as $product) {
            if (! is_array($product) || ($product['barcode'] ?? '') === '' || ($product['prd_code'] ?? '') === '') {
                throw ValidationException::withMessages([
                    'do_no' => "Format {$doNo} dari supplier tidak dikenali.",
                ]);
            }

            $details = [];
            foreach ((array) ($product['detail_product'] ?? []) as $detail) {
                if (! is_array($detail)) {
                    continue;
                }
                $details[] = [
                    'color' => (string) ($detail['color'] ?? ''),
                    'qty' => (float) ($detail['qty'] ?? 0),
                ];
            }

            $products[] = [
                'barcode' => (string) $product['barcode'],
                'prd_code' => (string) $product['prd_code'],
                'prd_name' => (string) ($product['prd_name'] ?? ''),
                'size' => isset($product['size']) && $product['size'] !== '' ? (string) $product['size'] : null,
                // Harga supplier dalam sen (×100): 4750000 = Rp47.500,00.
                'price' => (float) ($product['price'] ?? 0) / 100,
                'qty' => (float) ($product['qty'] ?? 0),
                'details' => $details,
            ];
        }

        return [
            'do_no' => (string) ($data['do_no'] ?? $doNo),
            'po_no' => isset($data['po_no']) && $data['po_no'] !== '' ? (string) $data['po_no'] : null,
            'do_date' => isset($data['do_date']) ? (string) $data['do_date'] : null,
            'cust_name' => isset($data['cust_name']) ? (string) $data['cust_name'] : null,
            'driver' => isset($data['driver']) ? (string) $data['driver'] : null,
            'nopol' => isset($data['nopol']) ? (string) $data['nopol'] : null,
            'inv_no' => isset($data['inv_no']) ? (string) $data['inv_no'] : null,
            'transaction_type' => isset($data['transaction_type']) ? (string) $data['transaction_type'] : null,
            'products' => $products,
        ];
    }
}
