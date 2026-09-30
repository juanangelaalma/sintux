# PHASE 0 REPORT — Discovery Modul Biaya (Expense)

Tanggal: 2026-09-30
Status: selesai, menunggu persetujuan manusia untuk mulai Fase 1.

Dokumen ini menjawab PRD §3 "Fase 0 — Discovery": deteksi stack, konvensi,
dependensi, dan usulan struktur direktori.

---

## 1. Stack terdeteksi

PRD §12 Q-10 menanyakan pilihan stack dengan asumsi repositori mungkin kosong.
Repositori tidak kosong. Stack yang dipakai modul baru:

| Lapisan | Teknologi | Bukti |
|---|---|---|
| Backend | Laravel 13, PHP 8.4 | `composer.json` |
| Modul | `nwidart/laravel-modules` ^13, folder `Modules/<Name>` di root | `config/modules.php`, `modules_statuses.json` |
| Multi-tenancy | `stancl/tenancy`, database per tenant | `config/tenancy.php` |
| ORM | Eloquent | `Modules/*/app/Models` |
| Database | PostgreSQL | migrasi memakai `jsonb`, `pg_advisory_xact_lock` |
| Test | PHPUnit 12, **bukan Pest** | `composer.json`, 63 test modul |
| Frontend | Inertia 3 + React 19 + TypeScript | `package.json` |
| UI kit | HeroUI v3 + Tailwind v4 | `package.json` |
| Ikon | `lucide-react` + `@iconify/react` | `package.json` |
| PDF | `dompdf/dompdf` ^3.1 | `composer.json`, `PurchaseOrderController` |

Catatan penting: **PRD §0.1 dan §0.2 mengasumsikan stack netral, jadi Q-10 tidak
berlaku.** Yang berlaku adalah aturan repo: ikuti konvensi yang sudah ada.

---

## 2. Konvensi yang harus diikuti

### 2.1 Lokasi modul

Modul ada di `D:\gabut\sintux\Modules\`, **bukan** `app/Modules`. `app/Modules`
tidak ada. Sembilan modul aktif: Accounting, Approval, Company, Contact, Payment,
Product, Purchasing, Sales, Warehouse.

### 2.2 Autoload

Setiap modul punya `composer.json` sendiri dengan PSR-4 `Modules\<Name>\ => app/`.
Root `composer.json` tidak punya entry `Modules\`, semuanya digabung oleh
`wikimedia/composer-merge-plugin` lewat `extra.merge-plugin.include: ["Modules/*/composer.json"]`.
Jadi modul baru cukup menambah `composer.json` sendiri lalu `composer dump-autoload`.

### 2.3 Struktur folder

Path yang benar-benar dipakai di repo (AGENTS.md §2 menyebut `Domain/` dan
`Infrastructure/`, tapi repo tidak konsisten):

```
Modules/<Name>/
├── app/
│   ├── Application/<SubContext>/   use case
│   ├── Domain/Rules/               hanya di Sales
│   ├── Enums/                      Purchasing, Warehouse, Sales, Approval
│   ├── Events/ + Listeners/        Sales, Purchasing, Warehouse, Payment, Approval
│   ├── Models/                     semua modul, flat, tanpa suffix Model
│   ├── Providers/                  ServiceProvider + RouteServiceProvider (+ EventServiceProvider)
│   └── Http/{Controllers,Requests,Middleware}/
├── database/
│   ├── migrations/tenant/          SEMUA modul kecuali Company
│   ├── factories/
│   └── seeders/
├── routes/{web.php,api.php}
├── resources/views/
├── tests/{Feature,Unit,Support}/
├── module.json
└── composer.json
```

Tiga koreksi terhadap AGENTS.md yang perlu dicatat:

1. **Model tidak pernah pakai suffix `Model`.** `Modules\Accounting\Models\Journal`,
   bukan `JournalModel`. Aturan §3/§4/§5 di AGENTS.md menyebut `Infrastructure/Models/`;
   tidak ada satu pun folder `Infrastructure/Models` di repo.
2. **8 dari 9 modul tidak punya `Domain/`.** Hanya `Sales` punya
   `Domain/Rules/CalculateInvoiceTotals.php`, dan itu bukan entity, melainkan
   service function stateless yang menerima dan mengembalikan array.
3. **`database/migrations/tenant/` adalah subfolder kustom**, bukan default
   nwidart. Dip terdaftar di `config/tenancy.php:186-190` untuk
   `php artisan tenants:migrate`. Modul baru cukup menaruh migrasi di sana.

### 2.4 Registrasi modul

Tiga file yang perlu disentuh saat menambah modul:

| File | Perubahan |
|---|---|
| `modules_statuses.json` | tambah `"<Name>": true` |
| `Modules/<Name>/composer.json` | file baru, PSR-4 |
| `Modules/<Name>/module.json` | file baru, mendaftarkan provider |

`bootstrap/app.php` **tidak** disentuh. Satu-satunya referensi modul di sana adalah
`CompanyServiceProvider::configureMiddleware()`, yang mendaftarkan alias
`company.member`, alias `permission`, dan middleware `ResolveTenant`.

### 2.5 Otorisasi

Tidak ada `spatie/laravel-permission`. Implementasi sendiri di modul Company:

- `Modules/Company/app/Http/Middleware/EnsurePermission.php` — alias `permission`.
- `Modules/Company/app/Providers/AccessServiceProvider.php` — `Gate::before()` tanpa
  `Gate::define()`. **Nama ability adalah slug permission itu sendiri.**
- Katalog permission: `Modules/Company/database/seeders/RolePermissionSeeder.php`.
  Seeder ini **central** (jalan sekali), jadi menambah permission tidak butuh migrasi tenant.
- Skoping cabang: `Modules/Company/app/Application/CompanyAccess.php` dengan
  `contextBranchIds()`, `accessibleBranchIds()`, `isActiveBranchHq()`.

Pola route yang dipakai Accounting (paling lengkap):

```php
Route::middleware(['auth', 'verified', 'company.member'])->group(function () {
    Route::get(...)->middleware('permission:accounting.account.view');
});
```

Sales dan Payment masih memakai `EnsureCompanyMember::class` langsung dan tidak
punya guard `permission:`. Expense memakai alias dan guard permission.

---

## 3. Status dependensi

PRD §3 Fase 0 poin 2 dan 3 meminta daftar 11 dependensi. Tiap baris: ada / tidak
ada / sebagian, plus path.

| # | Dependensi | Status | Path |
|---|---|---|---|
| 1 | Chart of Accounts | **sebagian** | `Modules/Accounting/app/Models/ChartOfAccount.php`, `AccountType.php`, `AccountCategory.php`; API `Modules/Accounting/app/Application/ChartOfAccountQuery.php` |
| 2 | Kontak | **ada, sebagian** | `Modules/Contact/app/Models/Contact.php`; API `Modules/Contact/app/Application/GetContacts.php` |
| 3 | Pajak | **ada** | `Modules/Accounting/app/Models/Tax.php`, `TaxGroupMember.php`; API `TaxQuery.php`, `TaxCalculator.php` |
| 4 | Tag | **sebagian** | Hanya `Modules/Purchasing/app/Models/PurchaseTag.php`. Tidak ada tag generik |
| 5 | Cara Pembayaran | **sebagian** | `Modules/Payment/app/Models/PaymentMethod.php`. Tidak ada public Application API |
| 6 | Mata uang | **tidak ada** | Tidak ada tabel `currencies` atau model. Hanya kolom `string(3)` default `IDR` |
| 7 | Penomoran transaksi | **tidak ada** | Tidak ada `NumberSequence`. Tiap modul punya `nextNumber()` privat |
| 8 | Jurnal umum (general ledger) | **ada** | `Modules/Accounting/app/Models/Journal.php`, `JournalLine.php`; API `Journal/RecordJournal.php`, `ReverseJournal.php`, `GetJournalByReference.php`, `ResolvePayableAccount.php` |
| 9 | Periode / tutup buku | **tidak ada** | Nol. Lihat Q-11 |
| 10 | Rekonsiliasi | **tidak ada** | Nol. Lihat Q-12 |
| 11 | Lampiran / file storage | **sebagian** | Tidak ada model bersama. Preseden: `Modules/Purchasing/app/Models/PurchaseReturnAttachment.php` |

### 3.1 Rincian tiap dependensi

**1. Chart of Accounts — sebagian.** `ChartOfAccount` punya `id`, `parent_id`,
`account_category_id`, `code`, `name`, `description`, `is_header`, `default_tax_id`,
`seed_key`, soft delete. `AccountType` dan `AccountCategory` adalah **model
Eloquent, bukan enum PHP** — kolom `code` berupa string di DB
(`create_coa_account_types_table.php`, `create_coa_account_categories_table.php`).

`ChartOfAccountQuery` offering tiga method: `listChartOfAccounts()` (semua non-header),
`isEligible(int $accountId)`, `findBySeedKey(string $seedKey)`. **Tidak ada filter
tipe atau kategori**, jadi BR-02 tidak bisa dipenuhi tanpa menambah method.

Tipe yang di-seed (`ChartOfAccountsSeeder.php`): `ASSET`, `LIABILITY`, `EQUITY`,
`REVENUE`, `COST_OF_REVENUE` (prefix 5), `EXPENSE` (prefix 6). Kategori di bawah
`EXPENSE`: `SELLING_EXPENSE` (600), `GENERAL_ADMIN_EXPENSE` (610),
`PERSONNEL_EXPENSE` (620), `DEPRECIATION_EXPENSE` (630), `OTHER_EXPENSE` (690).
`CASH_AND_BANK` di bawah `ASSET`: 1101, 1102, 1103, 1104.

Seed key penting: `accounting.coa.2101` (Utang Usaha, hutang default),
`accounting.coa.1404` (PPN Masukan), `accounting.coa.2201` (PPN Keluaran).

**2. Kontak — sebagian.** `GetContacts::execute(string $type, array $branchIds)`
menerima **satu** tipe per panggilan dan mensyaratkan `$branchIds`. PRD butuh dua
tipe sekaligus (supplier dan employee). Dua panggilan lalu merge di lapisan Expense
menyelesaikan ini tanpa perubahan modul Contact. `ContactPresenter::serializeForList()`
mengembalikan `id`, `branch_id`, `type`, `name`, `email`, `mobile_phone`,
`telephone`, `is_active`, `is_ho_only`.

**3. Pajak — ada, dan sudah persis yang dibutuhkan.** `TaxCalculator` adalah pure
function tanpa DB dan tanpa auth. Handle pengali 11/12, majemuk, dan inklusif.
Rounding tiap anggota `round($raw, 2, PHP_ROUND_HALF_UP)`, total adalah jumlah nilai
yang sudah dibulatkan. `TaxQuery::listForPurchase()` mengembalikan pajak dengan
`input_account_id` terisi, yaitu PPN Masukan — persis yang dibutuhkan baris biaya.
Satu-satunya keterbatasan: `TaxQuery` menyembunyikan pajak `is_withholding` secara
sengaja. Lihat Q-02.

Ada cermin TypeScript di `resources/js/lib/tax/tax-calculator.ts` yang
dokumentasikan eksplisit sebagai "hanya untuk preview".

**4. Tag — sebagian.** `purchase_tags` milik Purchasing, tanpa branch. Public API
`GetPurchaseTags::execute()` dan `CreatePurchaseTag::execute(string $name)`.
Preseden konsumsi lintas modul sudah ada di
`Modules/Payment/app/Application/PurchasePayment/CreatePurchasePayment.php`, yang
memvalidasi ID tag lewat `GetPurchaseTags` lalu menulis pivot-nya sendiri. Lihat Q-17.

**5. Cara Pembayaran — sebagian.** `payment_methods` sudah di-seed dengan tepat
empat default PRD: `cash` (Kas Tunai), `cheque` (Cek & Giro, default form S5),
`bank_transfer` (Transfer Bank), `credit_card` (Kartu Kredit). Tidak ada
`GetPaymentMethods`. `PaymentMethodController` membaca modelnya langsung, jadi
Expense tidak boleh melakukan hal sama. Butuh satu use case publik baru.

**6. Mata uang — tidak ada.** Tidak ada tabel, model, maupun nilai tukar.
`branches.currency_code` ada (default `IDR`, dijaga `BranchCurrencyTest.php`).
Enam use case purchasing hardcode `'IDR'`. Tidak ada feature flag yang bisa
menentukan apakah kolom 9 harus tampil. Lihat Q-16.

**7. Penomoran — tidak ada.** Tidak ada `NumberSequence`, `DocumentNumber`, atau
`number_sequences`. Tiap modul punya `nextNumber()` privat:
`PBL/{Ymd}/{%03d}` (payment), `SI/{branchCode}/{Ymd}/{%03d}` (sales),
`RBL-{invoice}-{%02d}` (return). Pengaman: `pg_advisory_xact_lock(hashtext(...))`
di Payment, `lockForUpdate()` di Sales dan Purchasing. `purchase_payments.number`
unik per tenant. Lihat Q-20.

**8. Jurnal — ada, API-nya kokoh.** `RecordJournal::execute(array $data)` menerima
`branch_id`, `journal_date`, `reference_type`, `reference_id`, `memo`, dan `lines`
(`account_id`, `debit`, `credit`, `memo`). Validasi: minimal 2 baris, akun harus
ada dan non-header, tidak ada nilai negatif, tiap baris tepat satu sisi
(debit XOR kredit), dan **balance dicek dengan `round($totalDebit, 2) !==
round($totalCredit, 2)`**. Status ditulis `posted` di dalam use case, jadi tidak
ada `PostJournal` terpisah.

`ResolvePayableAccount::execute(?int $supplierId)` mengembalikan akun hutang
default dari seed key `accounting.coa.2101`. Saat ini mengabaikan argumennya.
`ReverseJournal::execute(int $journalId, ?string $memo)` membalik baris dan
menywap debit dengan kredit. `GetJournalByReference::execute(string $type, int $id)`
untuk pembacaan jurnal dari halaman detail.

Pola pemakaian yang harus diikuti: consumer memanggil `RecordJournal` **di dalam
`DB::transaction()` miliknya sendiri**, bukan lewat event. Preseden:
`Modules/Payment/app/Application/Finalize/FinalizePurchasePayment.php:206`,
`Modules/Payment/app/Application/Deposit/CreateDepositPayment.php:21`,
`Modules/Purchasing/app/Application/PurchaseReturn/FinalizePurchaseReturn.php:29`.

**9. Periode terkunci — tidak ada.** Nol artefak. Ada permission
`fiscal.report.view` di seeder yang menunjuk ke modul tidak ada. Lihat Q-11.

**10. Rekonsiliasi — tidak ada.** Nol artefak, nol kemunculan string `Reconcili`.
Lihat Q-12.

**11. Lampiran — sebagian.** Preseden `PurchaseReturnAttachment`:
`id`, `purchase_return_id`, `path`, `original_name`, `mime`, `size`, timestamps,
cascade delete. Disimpan dengan `$file->store('purchase-returns/'.$id, 'local')`
**di dalam** `DB::transaction()` pembuatan dokumen
(`PurchaseReturnController.php:100-113`), limit 5 file dan 5 MB
(`StorePurchaseReturnRequest.php:59-60`), diunduh via
`Storage::disk('local')->download()` dengan `StreamedResponse`.
PRD minta 10 MB per file.

---

## 4. Kontrak PRD yang tidak cocok dengan repo

PRD ditulis dengan asumsi arsitektur berbeda. Enam koreksi wajib, sudah
disepakati pemilik repo.

### 4.1 Tidak ada kolom `company_id`

PRD §4.1 memakai `company_id` di setiap tabel dan §8 menyatakan "semua endpoint wajib
menegakkan `company_id` dari sesi". Repo ini **tidak punya satu pun kolom
`company_id`** di 115 migrasi modul. Isolasi tenant dijamin `stancl/tenancy`
(database per tenant), dan scoping antar cabang lewat `branch_id` +
`CompanyAccess::contextBranchIds()`.

Terjemahan: kolom `company_id` menjadi `branch_id`, dan seluruh indeks
`company_id` menjadi `branch_id`. Persyaratan "tegakkan company_id dari sesi"
terpenuhi secara struktural, tanpa kode tambahan.

### 4.2 Presisi uang 4 desimal, bukan 6

BR-01 dan §0.2 aturan 3 mewajibkan 6 desimal dan melarang float. Repo memakai
`decimal(15,4)`, cast `'decimal:4'`, dan aritmetika `float` di semua modul.
`ext-bcmath` tidak ada di `composer.json` dan nol pemakaian `bcmath` di repo.
Lihat Q-06 untuk aturan pembulatan yang dipilih.

### 4.3 Approval sudah generik

PRD §4.3 dan §5 BR-21 sampai BR-27 menyebut `expense_approval_rules`,
`expense_approval_logs`, dan `expense_comments`. Repo sudah punya modul Approval
generic dengan `approval_rules`, `approval_mappings`, `approval_actions`,
`approval_rule_logs`, `approval_comments`, plus `ApprovalEngine::evaluateAndMap()`
dan event `TransactionApprovalFinalized`. Dipakai oleh Purchasing, Warehouse,
Sales, dan Payment.

Expense memakai `ApprovalEngine` dengan `transaction_type` `expense`. Tabel
approval khusus PRD dihapus. Modul ini akan otomatis mendapat inbox, rule editor,
komentar, log, dan multi-stage approval yang sudah ada.

### 4.4 Status dan tanggal jatuh tempo

PRD §4.4 mengusulkan empat status termasuk `overdue`, tapi 17 kolom form PRD
tidak punya kolom jatuh tempo sehingga `overdue` tidak punya sumber tanggal.
Fase 1 memakai `open` dan `closed` saja; kolom tetap `string(20)`. Lihat Q-05 dan Q-24.

### 4.5 Withholding type

PRD §4.1 memakai enum `percent` dan `amount`. Preseden repo,
`Modules/Payment/app/Models/PurchasePaymentWithholding.php`, memakai `percent` dan
`nominal`. Expense memakai `percent` dan `nominal` supaya konsisten.

### 4.6 Kolom company_id vs branch_id pada jurnal

`RecordJournal` mewajibkan `branch_id`, dan `journals.branch_id` menuju
`branches`. Expense selalu mengisi `branch_id` dari cabang aktif sesi, persis
seperti `SalesInvoiceController::store()`.

---

## 5. Perbedaan antara PRD dan implementasi yang sudah ada di repo

Ditemukan saat discovery, **tidak diperbaiki** oleh modul ini karena di luar scope
dan akan meminta izin eksplisit. Semua dilaporkan di `PHASE_1_REPORT.md` §8.

1. **`Modules/Sales/app/Events/SalesInvoiceApproved.php` tidak punya listener.**
   Di-dispatch di `FinalizeApprovedSalesInvoice.php:39`, nol pendengar. Artinya
   faktur penjualan tidak pernah membuat jurnal sama sekali. Setelah Expense masuk,
   jurnal sisi revenue akan ada untuk Expense tetapi tidak untuk Sales.
2. **`Modules/Sales/app/Domain/Rules/CalculateInvoiceTotals.php:5`** meng-import
   `Modules\Accounting\Application\TaxCalculator`, yaitu Domain meng bergantung
   pada Application modul lain. Membalik AGENTS.md §23.
3. **`Modules/Warehouse/app/Providers/WarehouseServiceProvider.php:5,22`**
   meng-import `Modules\Company\Models\Branch` dan meng-observenya. Melanggar
   AGENTS.md §8 dan §11.
4. **`Modules/Purchasing/database/migrations/tenant/2026_09_24_000500_create_purchase_payment_invoice_applies_table.php`**
   membuat `purchase_payment_invoice_applies`, yang merupakan tabel milik Payment.
5. **`phpstan.neon` tidak menganalisis `Modules/`.** Cakupannya hanya `app/`,
   `bootstrap/app.php`, `config/`, `database/`, `routes/`. Jadi 97 persen logika
   bisnis repo nol static analysis. Menambal ini akan memunculkan banyak error
   existing di delapan modul lain.
6. **`modules_statuses.json` punya entri basi.** `User`, `TestModule`, dan
   `Invoice` ditandai aktif tapi foldernya tidak ada.
7. **`Modules/Payment/routes/web.php:8` dan `Modules/Sales/routes/web.php:7`**
   memakai `EnsureCompanyMember::class` langsung, bukan alias `company.member`,
   dan tidak punya guard `permission:`.
8. **`resources/js/components/tables/heroui-data-table.tsx`** menyortir client-side
   dengan membaca `(row as Record<string, unknown>)[colKey]`, jadi sorting hanya
   jalan bila nama properti row sama dengan key kolom. Relevan untuk Q-09.
9. **Limit upload `purchase-returns` berbeda** dari PRD: 5 file dan 5 MB
   (`StorePurchaseReturnRequest.php:59-60`), bukan 10 MB.
10. **`PurchaseReturnController.php:100-113` menyimpan file di dalam
    `DB::transaction()`.** Kalau DB rollback, file yang sudah terupload tidak ikut
    terhapus sehingga menyisakan file yatim.
11. **`Modules/Purchasing/app/Http/Middleware/EnsureHeadquarters.php`** ada tapi
    tidak punya alias dan tidak terdaftar di `bootstrap/app.php`.

---

## 6. Usulan struktur direktori

```
Modules/Expense/
├── app/
│   ├── Application/
│   │   ├── Expense/
│   │   │   ├── CreateExpense.php
│   │   │   ├── PostExpenseJournal.php
│   │   │   ├── GetExpenses.php
│   │   │   ├── GetExpenseSummary.php
│   │   │   ├── GetExpenseDetail.php
│   │   │   └── GenerateExpenseNumber.php
│   │   └── ExpenseTag/
│   │       ├── GetExpenseTags.php
│   │       └── CreateExpenseTag.php
│   ├── Domain/
│   │   └── Rules/
│   │       └── CalculateExpenseTotals.php
│   ├── Enums/
│   │   ├── ExpenseStatus.php
│   │   ├── ExpenseSource.php
│   │   └── WithholdingType.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ExpenseController.php
│   │   │   └── ExpenseTagController.php
│   │   └── Requests/
│   │       └── StoreExpenseRequest.php
│   ├── Models/
│   │   ├── Expense.php
│   │   ├── ExpenseLine.php
│   │   ├── ExpenseTag.php
│   │   └── ExpenseAttachment.php
│   └── Providers/
│       ├── ExpenseServiceProvider.php
│       └── RouteServiceProvider.php
├── database/
│   └── migrations/
│       └── tenant/
│           ├── 2026_09_30_000100_create_expenses_table.php
│           ├── 2026_09_30_000200_create_expense_lines_table.php
│           ├── 2026_09_30_000300_create_expense_tags_tables.php
│           └── 2026_09_30_000400_create_expense_attachments_table.php
├── routes/
│   └── web.php
├── tests/
│   ├── Feature/
│   │   ├── ExpenseCalculationTest.php
│   │   ├── ExpenseJournalTest.php
│   │   ├── ExpenseNumberingTest.php
│   │   ├── ExpenseCreateTest.php
│   │   ├── ExpenseListTest.php
│   │   └── ExpenseTenantIsolationTest.php
│   └── Support/
│       └── ExpenseFixture.php
├── module.json
└── composer.json
```

Penjelasan:

- **Tanpa `Infrastructure/`.** Tabel `{Model}` terjadi di `Accounting`, `Contact`,
  `Company`, dan `Product`. Modul yang punya `Infrastructure/` hanya Purchasing,
  dan isinya satu interface client eksternal.
- **Tanpa `Http/Middleware/`.** Expense memakai alias `company.member` dan alias
  `permission` milik Company, sesuai AGENTS.md §8 bagian middleware.
- **Tanpa `EventServiceProvider`.** `PostExpenseJournal` dipanggil langsung dari
  `CreateExpense` di dalam `DB::transaction()`, mengikuti pola
  `FinalizePurchasePayment` dan `FinalizePurchaseReturn`.
- **`Domain/Rules/CalculateExpenseTotals.php`** satu-satunya kelas Domain, meniru
  `Sales\Domain\Rules\CalculateInvoiceTotals`. Tugasnya tunggal: menghitung DPP,
  pajak, pemotongan, dan total. Tidak ada entity atau value object karena tidak ada
  invariants yang perlu dijaga di luar kalkulasi.
- **Tanpa `CalculateExpensePreview`.** PRD §8 mengusulkan `POST /expenses/calculate`
  untuk pratinjau total. Sales sudah menghitung pratinjau client-side lewat
  `pages/Sales/Invoices/totals.ts` dengan cermin TS `lib/tax/tax-calculator.ts`,
  sementara backend tetap menghitung ulang. Endpoint itu tidak dibuat.
- **Tanpa `Repository`, `DTO`, `Mapper`, atau `Factory`.** Semua modul di repo
  menulis langsung lewat Eloquent.

Frontend:

```
resources/js/pages/Expense/Expenses/
├── index.tsx
├── create.tsx
├── show.tsx
├── types.ts
├── expense-items-editor.tsx
├── expense-summary-cards.tsx
└── status-badge.tsx
```

Tiga komponen halaman (`expense-items-editor`, `expense-summary-cards`, `status-badge`)
page-local karena spesifik Biaya, sesuai AGENTS.md §22 yang melarang memindahkan
komponen bisnis ke `components/` hanya karena kelihatannya reusable.

---

## 7. File existing yang akan disentuh

Hanya enam, semuanya aditif.

| # | File | Perubahan | Alasan |
|---|---|---|---|
| 1 | `Modules/Accounting/app/Application/ChartOfAccountQuery.php` | tambah `listForExpense()` dan `listCashAndBank()` | BR-02 butuh filter tipe CoA; `pay_from_account_id` butuh daftar kas/bank. Method existing tidak berubah |
| 2 | `Modules/Payment/app/Application/GetPaymentMethods.php` | file baru | Kolom 5 form butuh daftar cara pembayaran aktif. `PaymentMethod` tidak boleh diimpor Expense |
| 3 | `Modules/Company/database/seeders/RolePermissionSeeder.php` | tambah 6 permission | `expense.view`, `create`, `update`, `delete`, `pay`, `approve`. Seeder central, jadi tanpa migrasi tenant |
| 4 | `resources/js/layouts/company/company-sidebar.tsx` | tambah 1 entri nav | Tanpa `hqOnly`, karena biaya bisa dicatat semua cabang seperti Penjualan |
| 5 | `modules_statuses.json` | tambah `"Expense": true` | Aktivasi modul |
| 6 | `resources/js/lib/format.ts` | tambah `formatDateDash()` | PRD §7.1 minta `DD-MM-YYYY` di tabel daftar, sementara `formatDate()` menghasilkan `DD/MM/YYYY` |

Plus satu pemindahan: `resources/js/pages/Sales/Invoices/currency-input.tsx`
dipromosikan ke `resources/js/components/ui/currency-input.tsx` dengan satu
perubahan import di `invoice-items-editor.tsx`. Dipakai dua halaman setelah
Expense masuk, memenuhi bar AGENTS.md §18.

**Tidak ada perubahan** pada `chart_of_accounts`, `taxes`, `TaxCalculator`,
`TaxQuery`, `contacts`, `payment_methods`, `purchase_tags`, `journals`,
`journal_lines`, `branches`, `CompanyAccess`, `routes/web.php`, `bootstrap/app.php`,
maupun `phpstan.neon`.

---

## 8. Verifikasi Discovery

- Tabel `expenses` akan memakai `company_id`? Tidak. `branch_id`, alasan di §4.1.
- Apakah ada dependensi Fase 1 yang hilang? Tiga, dan semuanya sudah diputuskan:
  Q-11 (periode terkunci) dan Q-12 (rekonsiliasi) ditunda, Q-13 (default pajak
  inklusif) memakai hardcode `false`.
- Apakah Q-04 memblokir Fase 1? Tidak. Q-04 hanya soal pengukuran sisa tagihan
  saat ada pemotongan, yang baru muncul di Fase 2.
- Apakah ada `[OPEN]` di PRD yang belum punya jawaban? Ya, Q-07, Q-19, dan Q-22
  masih `menunggu`. Semuanya Fase 2 atau Fase 3.

Fase 0 selesai. Menunggu persetujuan untuk mulai Fase 1.
