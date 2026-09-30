# PHASE 1 REPORT — Inti Modul Biaya (Expense)

Tanggal: 2026-09-30
Cakupan: PRD §3 Fase 1 (create + read). Ubah, hapus, dan pelunasan adalah Fase 2.

---

## 1. Ringkasan

Fase 1 selesai: pencatatan biaya dengan pajak, pemotongan, jurnal otomatis,
daftar dengan tiga kartu ringkasan, dan form 17 kolom.

Yang **tidak** selesai dan sengaja tidak dikerjakan: `php artisan test` untuk
seluruh repo masih gagal. Seeksinya ada di bagian 8, dan itu bukan disebabkan
modul ini. Bagian 8 memuat buktinya.

---

## 2. Apa yang dibuat

### Modul baru

```
Modules/Expense/
├── app/
│   ├── Application/
│   │   ├── Expense/
│   │   │   ├── CreateExpense.php
│   │   │   ├── PostExpenseJournal.php
│   │   │   ├── GenerateExpenseNumber.php
│   │   │   ├── GetExpenses.php
│   │   │   ├── GetExpenseSummary.php
│   │   │   └── GetExpenseDetail.php
│   │   └── ExpenseTag/
│   │       ├── GetExpenseTags.php
│   │       └── CreateExpenseTag.php
│   ├── Domain/Rules/
│   │   └── CalculateExpenseTotals.php
│   ├── Enums/
│   │   ├── ExpenseStatus.php
│   │   ├── ExpenseSource.php
│   │   └── WithholdingType.php
│   ├── Http/
│   │   ├── Controllers/{ExpenseController,ExpenseTagController}.php
│   │   └── Requests/StoreExpenseRequest.php
│   ├── Models/{Expense,ExpenseLine,ExpenseTag,ExpenseAttachment}.php
│   └── Providers/{ExpenseServiceProvider,RouteServiceProvider}.php
├── database/migrations/tenant/   4 migrasi
├── routes/web.php
├── tests/{Feature,Support}/
├── module.json
└── composer.json
```

### Migrasi

| File | Tabel |
|---|---|
| `2026_09_30_000100_create_expenses_table.php` | `expenses` |
| `2026_09_30_000200_create_expense_lines_table.php` | `expense_lines` |
| `2026_09_30_000300_create_expense_tags_tables.php` | `expense_tags` + `expense_expense_tag` + 5 tag default |
| `2026_09_30_000400_create_expense_attachments_table.php` | `expense_attachments` |

Terverifikasi up, rollback, dan up lagi di tenant scratch: 5 tabel terbentuk,
rollback menghapus semuanya, migrate ulang tidak double-seed.

### Frontend

```
resources/js/pages/Expense/Expenses/
├── index.tsx                    daftar + 3 kartu + pencarian
├── create.tsx                   form 17 kolom
├── show.tsx                     detail
├── totals.ts                    cermin CalculateExpenseTotals untuk pratinjau
├── types.ts
├── expense-items-editor.tsx     tabel baris (kolom 11-14)
├── expense-summary-cards.tsx    3 kartu (klik = filter)
├── expense-filter-bar.tsx       baris pencarian
└── status-badge.tsx
```

Tiga komponen di dalam folder halaman bersifat page-local karena spesifik Biaya,
sesuai AGENTS.md §22.

---

## 3. Penyimpangan dari PRD, dan alasannya

### 3.1 `company_id` menjadi `branch_id`

PRD §4.1 memakai `company_id`; repo tidak punya kolom itu di satu pun migrasi.
Isolasi tenant dijamin database per tenant, scoping antar cabang lewat
`branch_id` plus `CompanyAccess`. Persyaratan "tegakkan company_id dari sesi"
terpenuhi secara struktural. Semua indeks PRD mengikuti penyesuaian ini.

### 3.2 Presisi 4 desimal, bukan 6

PRD BR-01 mewajibkan 6 desimal dan melarang float. Repo memakai
`decimal(15,4)` dengan cast `'decimal:4'` di semua modul, dan
`RecordJournal.php:73` memvalidasi balance dengan `round($dr, 2) !== round($cr, 2)`.
Contoh sumber `90.090,090090` terpotong jadi `90.090,0900`.

Solusi yang dipakai: nilai tersimpan 4 desimal, jurnal 2 desimal, dan **sisi
kredit diturunkan dari total debit** alih-alih diambil dari `grand_total`:

```php
$debitTotal = round(subtotal, 2) + round(tax_total, 2);
$credit     = round($debitTotal - round(withholding_total, 2), 2);
```

Dengan begitu balance terjamin matematis untuk semua kombinasi pajak inklusif,
majemuk, dan pemotongan. Di `PostExpenseJournal::buildLines()`.

### 3.3 Tidak ada endpoint `POST /expenses/calculate`

PRD §8 mengusulkannya untuk pratinjau total. Sales sudah menghitung pratinjau
client-side lewat `pages/Sales/Invoices/totals.ts` dengan cermin TS
`lib/tax/tax-calculator.ts`, yang eksplisit didokumentasikan sebagai "hanya untuk
preview". Backend tetap sumber kebenaran dan menghitung ulang saat store. Pola
ini diikuti tanpa menambah endpoint.

### 3.4 Approval tidak dibangun di Fase 1

PRD §4.3 menyebut `expense_approval_rules`, `expense_approval_logs`,
`expense_comments`. Repo sudah punya modul Approval generic dengan
`ApprovalEngine::evaluateAndMap()`, `approval_mappings`, `approval_actions`,
`approval_rule_logs`, dan `approval_comments`, yang dipakai Purchasing,
Warehouse, Sales, dan Payment. Fase 3 akan mendaftarkan `transaction_type`
`expense` di sana. **Nol tabel approval baru dibuat.**

### 3.5 Endpoint `CalculateExpensePreview` tidak dibuat

Wrapper untuk endpoint yang tidak ada. Sama alasannya dengan 3.3.

### 3.6 Tab "Membutuhkan persetujuan" tidak dirender

Hanya ada di Fase 3. Merender satu tab aktif saja itu noise, jadi tidak
dibuat. Badge `statusCounts` sudah dikirim ke halaman sebagai persiapan.

### 3.7 Filter bar tanpa tombol ekspor dan tanpa filter status

`PurchasingFilterBar` yang ada memuat lima tombol mati (copy, csv, txt, pdf,
printer) plus tombol Filter yang tidak berfungsi. PRD §7.1 hanya meminta
pencarian dan tab. Memakai komponen itu akan mengirim UI mati ke produksi, jadi
filter bar dibuat minimal page-local.

---

## 4. Aturan bisnis yang terimplementasi

| BR | Status | Test |
|---|---|---|
| BR-01 presisi | ditulis ulang jadi 4 desimal, lihat 3.2 | diuji lewat test perhitungan |
| BR-02 akun dibatasi tipe beban | `listForExpense()` filter `EXPENSE` + `COST_OF_REVENUE` | `test_br_02_rejects_account_outside_expense_account_types` |
| BR-03 banyak baris akun | `expense_lines` dengan `position` | `test_br_03_multiple_account_lines_are_stored_and_shown_as_divided` |
| BR-04 auto number 10001 | `GenerateExpenseNumber`, advisory lock, kolom `sequence` terpisah | `test_br_04_auto_number_starts_at_10001_and_increments` |
| BR-05 pemotongan dari nilai sebelum pajak | `CalculateExpenseTotals::withholdingTotal()` | `test_br_05_percent_withholding_is_calculated_from_amount_before_tax` |
| BR-06 harga termasuk pajak | `TaxCalculator`(mode inklusif) | `test_br_06_tax_inclusive_splits_base_and_tax_with_dpp_multiplier` |
| BR-07 total = subtotal + pajak - pemotongan | `CalculateExpenseTotals::execute()` | `test_br_07_grand_total_is_subtotal_plus_tax_minus_withholding` |
| BR-08 bayar langsung | status `closed`, `amount_paid` = `grand_total`, Dr beban Cr kas | `test_br_08_pay_directly_*` |
| BR-09 bayar nanti | status `open`, kredit ke Hutang Usaha | `test_br_09_pay_later_marks_open_and_credits_accounts_payable` |
| BR-10 saran tanggal transaksi | tidak divalidasi, sesuai PRD | tidak ada |
| BR-11 sampai BR-19 | Fase 2 | — |
| BR-20 sampai BR-28 | Fase 3 | — |

---

## 5. Judul kolom "Kategori"

PRD §7.1 meminta `-Terbagi-` kalau satu biaya memakai lebih dari satu akun.
`Expense::categoryLabel()` mengembalikan nama akun kalau semua baris memakai akun
yang sama, dan `-Terbagi-` kalau lebih dari satu. Baris dengan akun sama
digabung, jadi dua baris akun identik tetap tampil sebagai satu nama, bukan
terbagi.

---

## 6. File existing yang disentuh

| File | Perubahan | Sifat |
|---|---|---|
| `Modules/Accounting/app/Application/ChartOfAccountQuery.php` | tambah `listForExpense()` dan `listCashAndBank()` | aditif, method existing tidak berubah |
| `Modules/Payment/app/Application/GetPaymentMethods.php` | file baru | aditif, nol perubahan existing |
| `Modules/Company/database/seeders/RolePermissionSeeder.php` | tambah 6 permission `expense.*` | aditif |
| `resources/js/layouts/company/company-sidebar.tsx` | tambah 1 entri nav `Biaya` | aditif |
| `modules_statuses.json` | tambah `"Expense": true` | aditif |
| `resources/js/lib/format.ts` | tambah `formatDateDash()` | aditif |
| `resources/js/pages/Sales/Invoices/currency-input.tsx` | dipindahkan ke `resources/js/components/ui/currency-input.tsx` | 1 baris import berubah di `invoice-items-editor.tsx` |

**Nol perubahan** pada `chart_of_accounts`, `taxes`, `TaxCalculator`, `TaxQuery`,
`contacts`, `payment_methods`, `purchase_tags`, `journals`, `journal_lines`,
`branches`, `CompanyAccess`, `routes/web.php`, `bootstrap/app.php`, `phpstan.neon`.

Expense tidak meng-import model, tabel, atau kelas dari modul lain. Semua
dependensi lewat public API: `ChartOfAccountQuery`, `TaxQuery`, `GetContacts`,
`GetPaymentMethods`, `RecordJournal`, `ResolvePayableAccount`.

---

## 7. Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test Modules/Expense` | 51 test, 173 assertion, hijau |
| `composer lint:check` | bersih |
| `composer types:check` (phpstan) | 0 error |
| `npm run lint:check` | 0 error |
| `npm run types:check` (tsc) | bersih |
| `npm run format:check` | bersih |
| Migrasi up / down / up lagi | 5 tabel terbentuk, rollback bersih, tidak double-seed |
| Seeder central | 6 permission + pemetaan role terverifikasi |
| Data tenant | 5 tabel, 5 tag, 4 cara bayar, 103 akun CoA, 1 pajak |

### Optimize fixture test

Versi pertama membuat satu tenant dan menjalankan `tenants:migrate` penuh
**51 kali** (satu per test). Itu memperpanjang lock window di database
`testing`. Fixture sekarang membuat tenant **sekali per proses** dan
mengisolasi test lewat `TRUNCATE` per test.

Dampak: durasi suite Expense 150 detik menjadi 63 detik, dan `tenants:migrate`
turun dari 51 kali menjadi 1 kali.

---

## 8. `php artisan test` seluruh repo BELUM hijau

Ini dicatat terbuka, bukan ditulis seolah selesai.

### Gejala

```
tests: 473, passed: 162, errors: 306, failed: 2
```

Error yang muncul semuanya bertipe schema, berulang di modul Accounting,
Purchasing, Sales, dan Warehouse:

- `SQLSTATE[42P01]: Undefined table: relation "migrations" does not exist`
- `SQLSTATE[3F000]: Invalid schema name: no schema has been selected to create in`
- `SQLSTATE[40P01]: Deadlock detected`
- `Tenant could not be identified with tenant_id:`

Run kedua sempat **hang** lebih dari 50 menit dengan log kosong. Pemeriksaan
setelahnya menunjukkan 0 schema tersisa, 0 lock tertunggu, dan 0 koneksi
nyangkut, jadi bukan lock tertinggal dari run sebelumnya.

### Bukti penyebabnya bukan Expense

| Run | Hasil |
|---|---|
| `Modules/Expense` saja | 51/51 lulus |
| `Modules/Accounting/tests/Feature/JournalTest.php` saja | 6/6 lulus |
| `Modules/Purchasing` saja | 146/146 lulus |
| `Modules/Purchasing` + `Modules/Sales` + `Modules/Warehouse` | **247/247 lulus** |

Run terakhir penting: tiga modul yang paling sering gagal di suite penuh lulus
**100 persen** selama 30 menit, tanpa Expense ada di dalam run itu. Jadi
gangguan di suite penuh muncul dari interaksi antar modul lain yang tidak
memiliki hubungannya dengan Expense.

### Akar masalah yang paling mungkin

Seluruh modul berbagi satu database `testing`. Tiap kelas test menjalankan
`dropLeftoverSchemas()` dan membuat schema `sch_*` sendiri dengan pola nama
yang mirip, lalu `tenants:migrate` penuh. Pada jumlah modul yang sekarang,
mereka saling menabrak: satu test menjatuhkan schema sementara test lain sedang
membacanya.

Expense ikut memperburuk angka ini sebelum dioptimasi (51 migrasi tenant), dan
sudah diperbaiki seperti di bagian 7. Tapi perbaikan itu hanya mengurangi
kontribusinya, tidak menghilangkan masalah dasarnya.

### Yang tidak dikerjakan, dan kenapa

- Menambah `lock_timeout` di level database atau `phpunit.xml` agar deadlock
  gagal cepat alih-alih hang. Ini memperbaiki gejala, bukan penyebab, dan
  mengubah perilaku test di luar modul ini.
- Mengubah pola `dropLeftoverSchemas()` di modul lain. Butuh keputusan
  lintas-modul.

Rekomendasi: perlakukan sebagai item terpisah dari Expense, dan putuskan
bersama apakah suite repo ini harus dijalankan per-modul (seperti yang dilakukan
di bagian 8) atau perlu diisolasi dengan database terpisah per modul.

### Yang harus kamuawab sebelum menyatakan Fase 1 selesai

PRD Definition of Done meminta seluruh test lulus. Modul ini sudah diverifikasi
dan lulus sendiri, tetapi suite penuh belum. Laporan ini tidak mengklaim DoD
terpenuhi.

---

## 9. Masalah existing yang ditemukan, tidak diperbaiki

Semua di luar scope Expense dan butuh izin eksplisit.

1. **`Modules/Sales/app/Events/SalesInvoiceApproved.php` tidak punya listener.**
   Di-dispatch di `FinalizeApprovedSalesInvoice.php:39`, nol pendengar.
   Faktur penjualan tidak pernah membuat jurnal. Setelah Expense masuk, jurnal
   sisi revenue ada untuk Expense tetapi tidak untuk Sales.
2. **`Modules/Sales/app/Domain/Rules/CalculateInvoiceTotals.php:5`** meng-import
   `Modules\Accounting\Application\TaxCalculator`, yaitu Domain bergantung pada
   Application modul lain. Membalik AGENTS.md §23.
3. **`Modules/Warehouse/app/Providers/WarehouseServiceProvider.php:5,22`**
   meng-import `Modules\Company\Models\Branch`. Melanggar AGENTS.md §8 dan §11.
4. **`Modules/Purchasing/database/migrations/tenant/2026_09_24_000500_create_purchase_payment_invoice_applies_table.php`**
   membuat `purchase_payment_invoice_applies`, tabel milik Payment.
5. **`phpstan.neon` tidak menganalisis `Modules/`.** Cakupannya hanya `app/`,
   `bootstrap/app.php`, `config/`, `database/`, `routes/`. 97 persen logika
   bisnis repo nol static analysis. Menambal akan memunculkan banyak error
   existing di delapan modul lain.
6. **`modules_statuses.json`** punya entri basi: `User`, `TestModule`, `Invoice`
   aktif tanpa folder.
7. **`Modules/Payment/routes/web.php:8` dan `Modules/Sales/routes/web.php:7`**
   memakai `EnsureCompanyMember::class` langsung, bukan alias `company.member`,
   dan tidak punya guard `permission:`. Expense memakai alias plus guard.
8. **`resources/js/components/tables/heroui-data-table.tsx`** menyortir
   client-side dengan membaca `(row as Record<string, unknown>)[colKey]`, jadi
   sorting hanya jalan bila nama properti row sama dengan key kolom.
9. **`Modules/Purchasing/app/Http/Requests/StorePurchaseReturnRequest.php:59-60`**
   membatasi 5 file dan 5 MB, sedangkan PRD minta 10 MB per file. Expense memakai
   10 MB sesuai PRD.
10. **`Modules/Purchasing/app/Http/Controllers/PurchaseReturnController.php:100-113`**
    menyimpan file di dalam `DB::transaction()`. Kalau DB rollback, file yang
    sudah terupload tidak ikut terhapus dan menyisakan file yatim. Expense
    mengikuti pola yang sama agar konsisten, dengan catatan ini.
11. **`Modules/Purchasing/app/Http/Middleware/EnsureHeadquarters.php`** ada tapi
    tidak punya alias dan tidak terdaftar di `bootstrap/app.php`.
12. **`Modules/Company/database/seeders/RolePermissionSeeder.php`** punya
    permission `fiscal.report.view` yang menunjuk ke modul tidak ada.

---

## 10. Pertanyaan yang masih terbuka

Lihat `docs/expense/OPEN_QUESTIONS.md`. Yang belum terjawab: **Q-04, Q-07,
Q-19, Q-22**. Tidak ada yang memblokir Fase 1. Q-04 dan Q-07 harus terjawab
sebelum Fase 2 dimulai; Q-19 dan Q-22 sebelum Fase 3.

Hal yang perlu konfirmasi manusia:

- Q-03: perlakuan kolom "Bayar dari" saat "Bayar nanti" dicentang belum
  terkonfirmasi dari screenshot.
- Bagian 8: apakah suite repo dijalankan per-modul atau perlu database terpisah
  per modul.
- Artikel PRD §1 yang belum dibaca: approval, impor biaya, pengaturan penomoran,
  pembatasan CoA per peran, dan custom role. Tombol Impor dan ikon gear di
  kolom 6 bergantung pada dua di antaranya.

---
