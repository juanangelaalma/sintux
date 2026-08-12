<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\AccountCategory;
use Modules\Accounting\Models\AccountType;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Tax;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Seed a default chart of accounts in the current tenant database.
     */
    public function run(): void
    {
        (new AccountType)->getConnection()->transaction(function (): void {
            $accountTypes = [
                ['code' => 'ASSET', 'prefix' => '1', 'name' => 'Aset', 'normal_balance' => 'debit', 'financial_statement' => 'balance_sheet', 'sort_order' => 10],
                ['code' => 'LIABILITY', 'prefix' => '2', 'name' => 'Liabilitas', 'normal_balance' => 'credit', 'financial_statement' => 'balance_sheet', 'sort_order' => 20],
                ['code' => 'EQUITY', 'prefix' => '3', 'name' => 'Ekuitas', 'normal_balance' => 'credit', 'financial_statement' => 'balance_sheet', 'sort_order' => 30],
                ['code' => 'REVENUE', 'prefix' => '4', 'name' => 'Pendapatan', 'normal_balance' => 'credit', 'financial_statement' => 'income_statement', 'sort_order' => 40],
                ['code' => 'COST_OF_REVENUE', 'prefix' => '5', 'name' => 'Beban Pokok Pendapatan', 'normal_balance' => 'debit', 'financial_statement' => 'income_statement', 'sort_order' => 50],
                ['code' => 'EXPENSE', 'prefix' => '6', 'name' => 'Beban', 'normal_balance' => 'debit', 'financial_statement' => 'income_statement', 'sort_order' => 60],
            ];

            foreach ($accountTypes as $accountType) {
                $prefix = $accountType['prefix'];
                unset($accountType['prefix']);

                $storedAccountType = AccountType::query()->updateOrCreate(
                    ['code' => $accountType['code']],
                    $accountType,
                );

                if (! $storedAccountType->prefix) {
                    $storedAccountType->update(['prefix' => $prefix]);
                }
            }

            $categories = [
                ['account_type' => 'ASSET', 'code' => 'CASH_AND_BANK', 'prefix' => '100', 'name' => 'Kas dan Bank', 'sort_order' => 110],
                ['account_type' => 'ASSET', 'code' => 'RECEIVABLE', 'prefix' => '101', 'name' => 'Piutang', 'sort_order' => 120],
                ['account_type' => 'ASSET', 'code' => 'INVENTORY', 'prefix' => '102', 'name' => 'Persediaan', 'sort_order' => 130],
                ['account_type' => 'ASSET', 'code' => 'OTHER_CURRENT_ASSET', 'prefix' => '103', 'name' => 'Aset Lancar Lainnya', 'sort_order' => 140],
                ['account_type' => 'ASSET', 'code' => 'FIXED_ASSET', 'prefix' => '150', 'name' => 'Aset Tetap', 'sort_order' => 150],
                ['account_type' => 'LIABILITY', 'code' => 'CURRENT_LIABILITY', 'prefix' => '200', 'name' => 'Liabilitas Jangka Pendek', 'sort_order' => 210],
                ['account_type' => 'LIABILITY', 'code' => 'TAX_LIABILITY', 'prefix' => '201', 'name' => 'Utang Pajak', 'sort_order' => 220],
                ['account_type' => 'LIABILITY', 'code' => 'LONG_TERM_LIABILITY', 'prefix' => '250', 'name' => 'Liabilitas Jangka Panjang', 'sort_order' => 250],
                ['account_type' => 'EQUITY', 'code' => 'EQUITY', 'prefix' => '300', 'name' => 'Ekuitas', 'sort_order' => 310],
                ['account_type' => 'REVENUE', 'code' => 'OPERATING_REVENUE', 'prefix' => '400', 'name' => 'Pendapatan Usaha', 'sort_order' => 410],
                ['account_type' => 'REVENUE', 'code' => 'OTHER_REVENUE', 'prefix' => '490', 'name' => 'Pendapatan Lainnya', 'sort_order' => 420],
                ['account_type' => 'COST_OF_REVENUE', 'code' => 'COST_OF_REVENUE', 'prefix' => '500', 'name' => 'Beban Pokok Pendapatan', 'sort_order' => 510],
                ['account_type' => 'EXPENSE', 'code' => 'SELLING_EXPENSE', 'prefix' => '600', 'name' => 'Beban Penjualan', 'sort_order' => 610],
                ['account_type' => 'EXPENSE', 'code' => 'GENERAL_ADMIN_EXPENSE', 'prefix' => '610', 'name' => 'Beban Umum dan Administrasi', 'sort_order' => 620],
                ['account_type' => 'EXPENSE', 'code' => 'PERSONNEL_EXPENSE', 'prefix' => '620', 'name' => 'Beban Karyawan', 'sort_order' => 630],
                ['account_type' => 'EXPENSE', 'code' => 'DEPRECIATION_EXPENSE', 'prefix' => '630', 'name' => 'Beban Penyusutan dan Amortisasi', 'sort_order' => 640],
                ['account_type' => 'EXPENSE', 'code' => 'OTHER_EXPENSE', 'prefix' => '690', 'name' => 'Beban Lainnya', 'sort_order' => 690],
            ];

            $typesByCode = AccountType::query()->get()->keyBy('code');

            foreach ($categories as $category) {
                $prefix = $category['prefix'];
                $storedCategory = AccountCategory::query()->updateOrCreate(
                    ['code' => $category['code']],
                    [
                        'account_type_id' => $typesByCode[$category['account_type']]->getKey(),
                        'name' => $category['name'],
                        'sort_order' => $category['sort_order'],
                    ],
                );

                if (! $storedCategory->prefix) {
                    $storedCategory->update(['prefix' => $prefix]);
                }
            }

            $categoriesByCode = AccountCategory::query()->with('accountType')->get()->keyBy('code');
            $accountGroups = $this->accountGroups();
            $inputAccount = null;
            $outputAccount = null;

            foreach ($accountGroups as $group) {
                $category = $categoriesByCode[$group['category']];
                $parentSeedKey = 'accounting.coa.'.$group['category'].'.header';
                $defaultParentCode = $this->defaultCode($category, 1);
                $parent = ChartOfAccount::withTrashed()
                    ->where('seed_key', $parentSeedKey)
                    ->first()
                    ?? ChartOfAccount::withTrashed()
                        ->whereIn('code', [$group['code'], $defaultParentCode])
                        ->first()
                    ?? ChartOfAccount::withTrashed()
                        ->where('account_category_id', $category->getKey())
                        ->whereNull('parent_id')
                        ->where('name', $group['name'])
                        ->first();

                if (! $parent) {
                    $parent = ChartOfAccount::query()->create([
                        'seed_key' => $parentSeedKey,
                        'code' => $defaultParentCode,
                        'parent_id' => null,
                        'account_category_id' => $category->getKey(),
                        'name' => $group['name'],
                        'description' => $group['description'],
                        'is_header' => true,
                    ]);
                } elseif (! $parent->seed_key) {
                    $parent->update(['seed_key' => $parentSeedKey]);
                }

                foreach ($group['children'] as $index => [$legacyCode, $name]) {
                    $seedKey = 'accounting.coa.'.$legacyCode;
                    $defaultCode = $this->defaultCode($category, $index + 2);
                    $account = ChartOfAccount::withTrashed()
                        ->where('seed_key', $seedKey)
                        ->first()
                        ?? ChartOfAccount::withTrashed()
                            ->whereIn('code', [$legacyCode, $defaultCode])
                            ->first()
                        ?? ChartOfAccount::withTrashed()
                            ->where('account_category_id', $category->getKey())
                            ->where('name', $name)
                            ->first();

                    if (! $account) {
                        $account = ChartOfAccount::query()->create([
                            'seed_key' => $seedKey,
                            'code' => $defaultCode,
                            'parent_id' => $parent->getKey(),
                            'account_category_id' => $category->getKey(),
                            'name' => $name,
                            'description' => null,
                            'is_header' => false,
                        ]);
                    } elseif (! $account->seed_key) {
                        $account->update(['seed_key' => $seedKey]);
                    }

                    if ($legacyCode === '1404') {
                        $inputAccount = $account;
                    }

                    if ($legacyCode === '2201') {
                        $outputAccount = $account;
                    }
                }
            }

            if (! $inputAccount || ! $outputAccount) {
                throw new \LogicException('Akun posting PPN default tidak ditemukan.');
            }

            $tax = Tax::query()->firstOrCreate(
                ['code' => 'PPN'],
                [
                    'name' => 'PPN',
                    'rate' => 11,
                    'input_account_id' => $inputAccount->id,
                    'output_account_id' => $outputAccount->id,
                    'is_active' => true,
                ],
            );

            $tax->fill(array_filter([
                'input_account_id' => $tax->input_account_id ? null : $inputAccount->id,
                'output_account_id' => $tax->output_account_id ? null : $outputAccount->id,
            ], fn (?int $accountId): bool => $accountId !== null))->save();
        });
    }

    /**
     * Build a default code without changing codes already stored in the database.
     */
    private function defaultCode(AccountCategory $category, int $sequence): string
    {
        return $category->accountType->prefix
            .'-'.$category->prefix
            .str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array{
     *     category: string,
     *     code: string,
     *     name: string,
     *     description: string,
     *     children: array<int, array{string, string}>
     * }>
     */
    private function accountGroups(): array
    {
        return [
            [
                'category' => 'CASH_AND_BANK',
                'code' => '1100',
                'name' => 'Kas dan Bank',
                'description' => 'Akun kas, rekening bank, dan setara kas.',
                'children' => [
                    ['1101', 'Kas Kecil'],
                    ['1102', 'Kas'],
                    ['1103', 'Bank'],
                    ['1104', 'Deposito Jangka Pendek'],
                ],
            ],
            [
                'category' => 'RECEIVABLE',
                'code' => '1200',
                'name' => 'Piutang',
                'description' => 'Tagihan kepada pelanggan dan pihak lainnya.',
                'children' => [
                    ['1201', 'Piutang Usaha'],
                    ['1202', 'Piutang Karyawan'],
                    ['1203', 'Piutang Lainnya'],
                    ['1209', 'Penyisihan Piutang Tak Tertagih'],
                ],
            ],
            [
                'category' => 'INVENTORY',
                'code' => '1300',
                'name' => 'Persediaan',
                'description' => 'Persediaan yang dimiliki untuk dijual atau digunakan dalam produksi.',
                'children' => [
                    ['1301', 'Persediaan Barang Dagang'],
                    ['1302', 'Persediaan Bahan Baku'],
                    ['1303', 'Persediaan Barang Dalam Proses'],
                    ['1304', 'Persediaan Barang Jadi'],
                ],
            ],
            [
                'category' => 'OTHER_CURRENT_ASSET',
                'code' => '1400',
                'name' => 'Aset Lancar Lainnya',
                'description' => 'Aset lancar selain kas, piutang, dan persediaan.',
                'children' => [
                    ['1401', 'Biaya Dibayar di Muka'],
                    ['1402', 'Uang Muka Pembelian'],
                    ['1403', 'Pajak Dibayar di Muka'],
                    ['1404', 'PPN Masukan'],
                ],
            ],
            [
                'category' => 'FIXED_ASSET',
                'code' => '1500',
                'name' => 'Aset Tetap',
                'description' => 'Aset berwujud berumur manfaat lebih dari satu periode.',
                'children' => [
                    ['1501', 'Tanah'],
                    ['1502', 'Bangunan'],
                    ['1503', 'Kendaraan'],
                    ['1504', 'Peralatan dan Mesin'],
                    ['1505', 'Inventaris Kantor'],
                    ['1592', 'Akumulasi Penyusutan Bangunan'],
                    ['1593', 'Akumulasi Penyusutan Kendaraan'],
                    ['1594', 'Akumulasi Penyusutan Peralatan dan Mesin'],
                    ['1595', 'Akumulasi Penyusutan Inventaris Kantor'],
                ],
            ],
            [
                'category' => 'CURRENT_LIABILITY',
                'code' => '2100',
                'name' => 'Liabilitas Jangka Pendek',
                'description' => 'Kewajiban yang jatuh tempo dalam satu tahun atau satu siklus operasi.',
                'children' => [
                    ['2101', 'Utang Usaha'],
                    ['2102', 'Utang Gaji'],
                    ['2103', 'Beban yang Masih Harus Dibayar'],
                    ['2104', 'Pendapatan Diterima di Muka'],
                    ['2105', 'Uang Muka Pelanggan'],
                    ['2106', 'Utang Lainnya'],
                ],
            ],
            [
                'category' => 'TAX_LIABILITY',
                'code' => '2200',
                'name' => 'Utang Pajak',
                'description' => 'Kewajiban perpajakan perusahaan.',
                'children' => [
                    ['2201', 'PPN Keluaran'],
                    ['2202', 'Utang PPh 21'],
                    ['2203', 'Utang PPh 23'],
                    ['2204', 'Utang PPh 25/29'],
                    ['2205', 'Utang Pajak Lainnya'],
                ],
            ],
            [
                'category' => 'LONG_TERM_LIABILITY',
                'code' => '2500',
                'name' => 'Liabilitas Jangka Panjang',
                'description' => 'Kewajiban dengan jatuh tempo lebih dari satu tahun.',
                'children' => [
                    ['2501', 'Utang Bank Jangka Panjang'],
                    ['2502', 'Utang Pembiayaan'],
                    ['2503', 'Liabilitas Sewa'],
                ],
            ],
            [
                'category' => 'EQUITY',
                'code' => '3100',
                'name' => 'Ekuitas',
                'description' => 'Hak residual pemilik atas aset setelah dikurangi liabilitas.',
                'children' => [
                    ['3101', 'Modal Disetor'],
                    ['3102', 'Tambahan Modal Disetor'],
                    ['3103', 'Prive atau Dividen'],
                    ['3201', 'Saldo Laba'],
                    ['3202', 'Laba Rugi Tahun Berjalan'],
                ],
            ],
            [
                'category' => 'OPERATING_REVENUE',
                'code' => '4100',
                'name' => 'Pendapatan Usaha',
                'description' => 'Pendapatan dari kegiatan utama perusahaan.',
                'children' => [
                    ['4101', 'Penjualan Barang'],
                    ['4102', 'Pendapatan Jasa'],
                    ['4103', 'Pendapatan Usaha Lainnya'],
                    ['4191', 'Retur dan Potongan Penjualan'],
                ],
            ],
            [
                'category' => 'OTHER_REVENUE',
                'code' => '4200',
                'name' => 'Pendapatan Lainnya',
                'description' => 'Pendapatan di luar kegiatan utama perusahaan.',
                'children' => [
                    ['4201', 'Pendapatan Bunga'],
                    ['4202', 'Keuntungan Selisih Kurs'],
                    ['4203', 'Keuntungan Penjualan Aset'],
                    ['4209', 'Pendapatan Lain-lain'],
                ],
            ],
            [
                'category' => 'COST_OF_REVENUE',
                'code' => '5100',
                'name' => 'Beban Pokok Pendapatan',
                'description' => 'Biaya langsung untuk menghasilkan barang atau jasa yang dijual.',
                'children' => [
                    ['5101', 'Harga Pokok Penjualan'],
                    ['5102', 'Beban Bahan Baku'],
                    ['5103', 'Beban Tenaga Kerja Langsung'],
                    ['5104', 'Beban Overhead Produksi'],
                    ['5105', 'Beban Pokok Jasa'],
                ],
            ],
            [
                'category' => 'SELLING_EXPENSE',
                'code' => '6100',
                'name' => 'Beban Penjualan',
                'description' => 'Beban untuk aktivitas pemasaran dan penjualan.',
                'children' => [
                    ['6101', 'Beban Iklan dan Promosi'],
                    ['6102', 'Beban Komisi Penjualan'],
                    ['6103', 'Beban Pengiriman'],
                    ['6104', 'Beban Perjalanan Dinas Penjualan'],
                ],
            ],
            [
                'category' => 'GENERAL_ADMIN_EXPENSE',
                'code' => '6200',
                'name' => 'Beban Umum dan Administrasi',
                'description' => 'Beban operasional umum dan administrasi perusahaan.',
                'children' => [
                    ['6201', 'Beban Sewa'],
                    ['6202', 'Beban Listrik, Air, dan Internet'],
                    ['6203', 'Beban Alat Tulis Kantor'],
                    ['6204', 'Beban Telepon dan Komunikasi'],
                    ['6205', 'Beban Perbaikan dan Pemeliharaan'],
                    ['6206', 'Beban Jasa Profesional'],
                    ['6207', 'Beban Perizinan dan Administrasi'],
                    ['6208', 'Beban Asuransi'],
                ],
            ],
            [
                'category' => 'PERSONNEL_EXPENSE',
                'code' => '6300',
                'name' => 'Beban Karyawan',
                'description' => 'Beban kompensasi dan kesejahteraan karyawan.',
                'children' => [
                    ['6301', 'Beban Gaji dan Upah'],
                    ['6302', 'Beban Tunjangan'],
                    ['6303', 'Beban BPJS'],
                    ['6304', 'Beban Pelatihan Karyawan'],
                ],
            ],
            [
                'category' => 'DEPRECIATION_EXPENSE',
                'code' => '6400',
                'name' => 'Beban Penyusutan dan Amortisasi',
                'description' => 'Alokasi biaya aset selama masa manfaatnya.',
                'children' => [
                    ['6401', 'Beban Penyusutan Bangunan'],
                    ['6402', 'Beban Penyusutan Kendaraan'],
                    ['6403', 'Beban Penyusutan Peralatan dan Mesin'],
                    ['6404', 'Beban Penyusutan Inventaris Kantor'],
                    ['6405', 'Beban Amortisasi'],
                ],
            ],
            [
                'category' => 'OTHER_EXPENSE',
                'code' => '6900',
                'name' => 'Beban Lainnya',
                'description' => 'Beban di luar kegiatan operasional utama perusahaan.',
                'children' => [
                    ['6901', 'Beban Administrasi Bank'],
                    ['6902', 'Beban Bunga'],
                    ['6903', 'Kerugian Selisih Kurs'],
                    ['6904', 'Kerugian Penjualan Aset'],
                    ['6905', 'Beban Pajak Penghasilan'],
                    ['6909', 'Beban Lain-lain'],
                ],
            ],
        ];
    }
}
