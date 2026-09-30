# OPEN QUESTIONS — Modul Biaya (Expense)

Sumber keputusan: `docs/expense/prd.md`. Berisi semua `[OPEN]` dari PRD yang belum
terjawab, ditambah pertanyaan baru dari Discovery.

Status `menunggu` = belum dijawab manusia. `dijawab` = sudah ada keputusan.
Baca ulang di awal tiap sesi kerja (PRD §13.2).

---

## Q-01 Harga inklusif + pengali 11/12

- Ditemukan di: `prd.md` BR-06 (Fase 1)
- Yang diketahui: contoh sumber Rp 100.000 dengan PPN 12% pengali 11/12 menghasilkan DPP 90.090,090090. Artikel yang sama menyatakan fitur ini belum mendukung pengali 11/12. `TaxCalculator.php:99-115` justru mendukungnya dan hasilnya cocok dengan contoh.
- Opsi A: tolak kombinasi di validasi. Opsi B: izinkan, andalkan `TaxCalculator`.
- Rekomendasi agent: **Opsi B.** Aritmetikanya cocok (`100000 / 1.11`). Teks sumbernya yang bertentangan, bukan kodenya.
- Status: dijawab (Expense Fase 1) — izinkan, tanpa validasi tambahan.

## Q-02 Pajak pemotongan di baris biaya

- Ditemukan di: `prd.md` §12 (Fase 1)
- Yang diketahui: `TaxCalculator` mendukung signed rate negatif, tapi `TaxQuery::listForPurchase()` menyembunyikan pajak `is_withholding` secara sengaja. PRD punya mekanisme pemotongan sendiri di kolom 15.
- Opsi A: pakai `listForPurchase()` apa adanya. Opsi B: tambah API Accounting khusus.
- Rekomendasi agent: **Opsi A.** Pemotongan sudah punya mekanisme sendiri; mengaktifkannya juga di baris membuka dua jalan untuk hal yang sama.
- Status: dijawab (Expense Fase 1) — Opsi A, tanpa perubahan Accounting.

## Q-03 "Bayar dari" saat "Bayar nanti" dicentang

- Ditemukan di: `prd.md` §12 (Fase 1, form)
- Yang diketahui: kolom 1 `pay_from_account_id` (kas/bank), kolom 2 `is_pay_later`. PRD tidak menyebut perlakuan kolom 1 saat kolom 2 aktif.
- Opsi A: sembunyikan kolom 1. Opsi B: nonaktifkan. Opsi C: biarkan, tidak dipakai kalau bayar nanti.
- Rekomendasi agent: **Opsi C.** Input user tidak hilang dan tidak ada logika UI tambahan.
- Status: dijawab (Expense Fase 1) — Opsi C. Konfirmasi ulang bila screenshot Jurnal menunjukkan lain.

## Q-04 Pemotongan terhadap nilai hutang

- Ditemukan di: `prd.md` §5 BR-07 dan §6 (Fase 1 jurnal, Fase 2 pelunasan)
- Yang diketahui: untuk jurnal PRD sudah memuat jawaban parsial (kredit Kas/Bank dikurangi pemotongan). Untuk pelunasan belum jelas. `grand_total` sudah dikurangi `withholding_total`, kredit Hutang Usaha juga `grand_total - withholding`, tapi kolom "Sisa tagihan" dihitung dari `grand_total - amount_paid`. Kalau `amount_paid` dicatat sebesar yang keluar dari kas, sisa tagihan tidak akan pernah nol.
- Opsi A: sisa tagihan diukur dari `grand_total - withholding_total`. Opsi B: pemotongan tidak mengurangi hutang, pelunasan kedua masuk akun penampung.
- Rekomendasi agent: **tidak ditebak.** Keduanya bisa dipertanggungjawabkan dan berbeda di Fase 2. Kasus tanpa pemotongan tidak terpengaruh.
- Status: **menunggu** — memblokir Fase 2, bukan Fase 1.

## Q-05 Jatuh tempo dan status Overdue

- Ditemukan di: `prd.md` §4.4 dan §12
- Yang diketahui: 17 kolom form PRD tidak punya kolom jatuh tempo, dan tabel `expenses` tidak punya `due_date`. Kolom `due_date` sudah ada di `purchase_payments`, `purchase_invoices`, `purchase_orders`, `sales_invoices`.
- Opsi A: tambah `due_date` opsional plus status `overdue`. Opsi B: tidak ada jatuh tempo, status hanya `open` dan `closed`.
- Rekomendasi agent: **Opsi B.** Menambah `due_date` berarti mengarang kolom form yang tidak ada di PRD maupun screenshot.
- Dampak: kolom `status` tetap `string(20)` supaya `draft` dan `overdue` bisa ditambahkan tanpa migrasi.
- Status: dijawab (Expense Fase 1) — Opsi B.

## Q-06 Pembulatan agar jurnal seimbang

- Ditemukan di: `prd.md` BR-01 (Fase 1)
- Yang diketahui: seluruh repo memakai `decimal(15,4)`, cast `'decimal:4'`, aritmetika `float`, dan `round($x, 2)`. Contoh sumber 6 desimal akan terpotong jadi 4 desimal. `RecordJournal.php:73` memvalidasi balance dengan `round($dr, 2) !== round($cr, 2)`, jadi nilai 4 desimal tidak akan lolos bila dijumlahkan apa adanya. `ext-bcmath` tidak ada di `composer.json` dan nol pemakaian `bcmath` di repo.
- Opsi A: 6 desimal khusus Expense, tapi `journal_lines` tetap 4 desimal sehingga ada kehilangan presisi. Opsi B: migrasi semua modul ke 6 desimal. Opsi C: 4 desimal internal, jurnal 2 desimal, sisi kredit diturunkan dari total debit.
- Rekomendasi agent: **Opsi C.** Kredit = `round(jumlah amount_before_tax, 2) + round(tax_total, 2) - round(withholding_total, 2)`, bukan `round(grand_total, 2)`. Balance di 2 desimal terjamin untuk semua kombinasi inklusif, majemuk, dan pemotongan.
- Dampak: BR-01 ditulis ulang jadi presisi 4 desimal internal, 2 desimal jurnal.
- Status: dijawab (Expense Fase 1) — Opsi C.

## Q-07 Pelunasan sebagian

- Ditemukan di: `prd.md` §5 BR-13 (Fase 2)
- Yang diketahui: `purchase_payment_allocations` sudah menyimpan `amount` per alokasi, jadi polanya mendukung pelunasan sebagian.
- Rekomendasi agent: izinkan. `amount_paid` hanya naik, tidak pernah turun.
- Status: **menunggu** — diproses di Fase 2.

## Q-08 Hapus massal bila sebagian item terblokir

- Ditemukan di: `prd.md` BR-19 (Fase 2)
- Yang diketahui: PRD sudah menetapkan perlakuannya, yaitu terapkan BR-17 per item, laporkan item gagal beserta alasannya, jangan batalkan seluruh batch. Yang belum diputuskan adalah bentuk respons UI.
- Rekomendasi agent: partial success plus daftar kegagalan. Bentuk visualnya detail Fase 2.
- Status: dijawab oleh PRD §5 BR-19. Detail UI menunggu Fase 2.

## Q-09 Paginasi, ukuran halaman, dan opsi urut

- Ditemukan di: `prd.md` §7.1 dan §12 (Fase 1)
- Yang diketahui: PRD mencatat paginasi tidak terlihat di screenshot. Semua modul memakai `paginate(15)`. `heroui-data-table.tsx` sorting-nya client-side dan membaca `(row as Record<string, unknown>)[colKey]`, jadi sorting hanya jalan bila nama properti row sama dengan key kolom. PRD §7.1 menyatakan header kolom bisa diurutkan dengan label `[INFERRED]`.
- Rekomendasi agent: `paginate(15)`, default `transaction_date desc, id desc`. Sorting level database, dan key kolom di `HerouiDataTable` disamakan dengan nama properti row supaya sorting client-side juga benar.
- Status: dijawab (Expense Fase 1) — `paginate(15)` plus sort default.

## Q-10 Pilihan stack teknologi

- Ditemukan di: `prd.md` §12 (Fase 0)
- Yang diketahui: stack terdeteksi, yaitu Laravel 13, Inertia 3, React 19, TypeScript, HeroUI v3, Tailwind v4, `nwidart/laravel-modules`, `stancl/tenancy`, PostgreSQL, PHPUnit 12 (bukan Pest), dan `dompdf`.
- Status: dijawab (2026-09-30) — stack terdeteksi, Q-10 tidak berlaku.

## Q-11 Periode terkunci / tutup buku tidak ada

- Ditemukan di: Discovery, untuk `prd.md` BR-14, BR-17, BR-26 (Fase 1 dan Fase 3)
- Yang diketahui: tidak ada tabel `fiscal_periods`, model `FiscalPeriod`, `PeriodLockService`, atau pengecekan tanggal terkunci di mana pun. Semua kemunculan "locked" di repo adalah `lockForUpdate()` atau validasi `CanBecomeChartOfAccountParent`. Ada permission `fiscal.report.view` di `RolePermissionSeeder.php` yang menunjuk ke modul tidak ada (tidak ada `Modules/Fiscal`). BR-14 dan BR-17 mewajibkan pemblokiran ubah/hapus bila tanggal di periode terkunci, dan validasi form PRD menuntut pesan "Tanggal ini berada di periode yang sudah dikunci. Pilih tanggal lain."
- Opsi A: bangun PeriodLock di Accounting sekarang. Opsi B: tunda dengan kode error `EXPENSE_LOCKED_PERIOD` dan satu method yang selalu null.
- Rekomendasi agent: **Opsi B.** PeriodLock adalah feature tersendiri dengan halaman UI, aturan reopen, dan entri backdated. Bukan prerequisite Expense.
- Dampak: BR-14 dan BR-17 belum sepenuhnya terpenuhi.
- Status: dijawab (2026-09-30, pemilik repo) — tunda.

## Q-12 Rekonsiliasi tidak ada

- Ditemukan di: Discovery, untuk `prd.md` BR-14 dan BR-17 (Fase 2)
- Yang diketahui: nol tabel, model, service, maupun test rekonsiliasi di seluruh repo. Nol kemunculan string `Reconcili`.
- Opsi A: bangun modul Rekonsiliasi lalu jadikan Expense pemblokirnya. Opsi B: tunda dengan kode error `EXPENSE_RECONCILED` dan method yang selalu false.
- Rekomendasi agent: **Opsi B.** Feature Rekonsiliasi berdiri sendiri dan tidak bisa dibangun sebagian.
- Dampak: BR-14 dan BR-17 bagian rekonsiliasi belum terpenuhi.
- Status: dijawab (2026-09-30, pemilik repo) — tunda.

## Q-13 Sumber default toggle "Harga termasuk pajak"

- Ditemukan di: `prd.md` BR-06 (Fase 1)
- Yang diketahui: repo tidak punya tabel company settings. Modul Company hanya punya `company_users`, `roles`, `permissions`, `role_permission`, `company_user_roles`, `company_user_branches`, `branches`. Semua modul memakai `->default(false)` untuk `is_tax_inclusive` tanpa mekanisme override.
- Opsi A: tambah tabel company settings baru. Opsi B: hardcode `false`.
- Rekomendasi agent: **Opsi B.** Tabel settings mengubah modul Company secara nyata dan butuh UI Pengaturan. Expense ikut pola existing agar tidak jadi satu-satunya fitur settings tanpa konsumen kedua.
- Dampak: toggle tidak bisa punya default berbeda per perusahaan.
- Status: dijawab (Expense Fase 1) — hardcode `false`.

## Q-14 Tipe kontak "lainnya" untuk Penerima

- Ditemukan di: `prd.md` §7.2 kolom 3 (Fase 1)
- Yang diketahui: kolom `type` di `contacts` adalah `string(20)` tanpa enum PHP dan tanpa `Rule::in`. Nilai yang dipakai: `customer`, `supplier`, `employee` (`create_contacts_table.php:16`). `GetContacts::execute(string $type, array $branchIds)` menerima satu tipe per panggilan, jadi dua tipe berarti dua panggilan lalu di-merge di lapisan Expense, tanpa perubahan modul Contact.
- Opsi A: supplier dan employee saja. Opsi B: tambah tipe `other` plus tab UI, route, dan permission.
- Rekomendasi agent: **Opsi A.** Opsi B menyentuh modul Contact untuk satu kolom form, dan tanpa halaman UI kontak `other` nilainya tidak akan pernah terisi.
- Dampak: penerima yang bukan supplier dan bukan karyawan tidak bisa dicatat lewat Expense.
- Status: dijawab (Expense Fase 1) — tipe `supplier` dan `employee`.

## Q-15 Cakupan kategori akun biaya

- Ditemukan di: `prd.md` §5 BR-02 (Fase 1)
- Yang diketahui: `AccountType` adalah model Eloquent, bukan enum. Tipe: `ASSET`, `LIABILITY`, `EQUITY`, `REVENUE`, `COST_OF_REVENUE` (prefix 5), `EXPENSE` (prefix 6). Kategori di bawah `EXPENSE`: `SELLING_EXPENSE` (600), `GENERAL_ADMIN_EXPENSE` (610), `PERSONNEL_EXPENSE` (620), `DEPRECIATION_EXPENSE` (630), `OTHER_EXPENSE` (690). Kategori `CASH_AND_BANK` ada di bawah `ASSET` (1101 Kas Kecil, 1102 Kas, 1103 Bank, 1104 Deposito).
- Opsi A: hanya tiga kategori literal. Opsi B: semua akun dengan account type `EXPENSE` atau `COST_OF_REVENUE`.
- Rekomendasi agent: **Opsi B.** Repo tidak punya kategori yang persis bernama "Beban" generik. Daftar putih tiga kategori bergantung pada nomor prefix, sementara aturan "harus akun beban" sudah dinyatakan tipe CoA. Akun baru otomatis masuk tanpa perubahan kode.
- Dampak: akun Beban Penyusutan (630) ikut bisa dipilih, padahal biasanya dibuat dari modul Aset Tetap.
- Status: dijawab (Expense Fase 1) — filter account type `EXPENSE` atau `COST_OF_REVENUE`, non-header, belum dihapus.

## Q-16 Mata uang dan multi-currency

- Ditemukan di: `prd.md` §7.2 kolom 9 dan §4.1 (Fase 1)
- Yang diketahui: tidak ada tabel `currencies`, model `Currency`, maupun tabel nilai tukar. `branches.currency_code` string(3) default `IDR` dijaga `BranchCurrencyTest.php`. Enam use case purchasing hardcode `IDR`. Tidak ada feature flag multi-currency, jadi tidak ada cara menentukan apakah kolom 9 harus tampil.
- Opsi A: kolom default `IDR`, kolom 9 disembunyikan. Opsi B: baca dari `currency_code` cabang aktif, butuh public API Company baru.
- Rekomendasi agent: **Opsi A.** Konsisten dengan enam modul yang ada dan tidak butuh API baru.
- Dampak: kolom 9 tidak pernah tampil, nilainya selalu `IDR`. Kolom tetap ada supaya penambahan multi-currency tidak butuh migrasi.
- Status: dijawab (Expense Fase 1) — hardcode `IDR`, UI menyembunyikan kolom.

## Q-17 Tag: master bersama atau milik Expense

- Ditemukan di: `prd.md` §4.3 dan §7.2 kolom 7 (Fase 1)
- Yang diketahui: satu-satunya tag adalah `Modules/Purchasing/Models/PurchaseTag` dengan pivot `purchase_order_purchase_tag`, `purchase_return_purchase_tag`, `purchase_payment_purchase_tag`. `PurchaseTag` tidak punya branch. Public API Purchasing: `GetPurchaseTags::execute()` dan `CreatePurchaseTag::execute(string $name)`. Prekonsumen lintas modul sudah ada di `CreatePurchasePayment.php`, yang memvalidasi tag lewat `GetPurchaseTags` lalu menulis pivot-nya sendiri.
- Opsi A: tabel `expense_tags` milik Expense. Opsi B: pakai `purchase_tags`. Opsi C: promosikan jadi modul bersama.
- Rekomendasi agent: **Opsi A.** Opsi B membuat setiap tag Expense berlabel "pembelian". Opsi C menyentuh Purchasing, Payment, Warehouse, dan frontend tanpa konsumen kedua yang nyata selain Expense.
- Dampak: satu tabel master tag duplikat. Expense dan Purchase tidak bisa menandai tag yang sama.
- Status: dijawab (Expense Fase 1) — tabel tag milik Expense.

## Q-18 Sisa tagihan dan status open sebelum pelunasan ada

- Ditemukan di: cross-check `prd.md` §4.4, §7.1, §3 (Fase 1)
- Yang diketahui: Fase 1 tidak punya pelunasan, tapi kolom "Sisa tagihan (dalam IDR)" dan kartu "Biaya belum dibayar" keduanya ada di daftar Fase 1. Keduanya hanya bermakna kalau ada `amount_paid` dan status `open`.
- Opsi A: simpan `amount_paid` dan status sejak Fase 1. Opsi B: tunda sampai Fase 2, kartu 3 selalu nol.
- Rekomendasi agent: **Opsi A.** `amount_paid` untuk bayar langsung diisi `grand_total` (BR-08), untuk bayar nanti `0` sampai Fase 2.
- Dampak: kartu 3 sudah benar angkanya di Fase 1, hanya belum bisa turun.
- Status: dijawab (Expense Fase 1) — `amount_paid` dan status sejak Fase 1.

## Q-19 Approval hanya untuk paket berbayar

- Ditemukan di: `prd.md` §5 BR-28 (Fase 3)
- Yang diketahui: tidak ada tabel `feature_flags`, manager flag, `subscriptions`, `plans`, `billing`, atau `Modules/Billing`. Modul Approval sendiri aktif untuk semua tenant tanpa gate.
- Opsi A: bangun mekanisme flag dan subscription dulu. Opsi B: approval aktif untuk semua tenant, gate per paket ditunda.
- Rekomendasi agent: **Opsi B.** Flag tanpa plan atau subscription yang nyata hanya jadi konfigurasi mati.
- Status: **menunggu** — memblokir Fase 3, tidak memblokir Fase 1 dan Fase 2.

## Q-20 Pengaturan penomoran transaksi

- Ditemukan di: `prd.md` §5 BR-04 (Fase 1)
- Yang diketahui: tidak ada `NumberSequence`, `DocumentNumber`, atau `number_sequences`. Tiap modul punya `nextNumber()` privat dengan format `PBL/{Ymd}/{%03d}`, `SI/{branchCode}/{Ymd}/{%03d}`, `RBL-{invoice}-{%02d}`. Pengaman yang dipakai: `pg_advisory_xact_lock(hashtext(...))` di Payment, `lockForUpdate()` di Sales dan Purchasing. `purchase_payments.number` unik per tenant, bukan per cabang.
- Opsi A: buat modul penomoran bersama. Opsi B: `nextNumber()` privat di `CreateExpense`, format default saja.
- Rekomendasi agent: **Opsi B.** Tidak ada konsumen kedua yang nyata dan AGENTS.md §7 melarang abstraksi tanpa alasan konkret. Karena kolom `number` adalah input teks bebas, `expenses` punya kolom `sequence` unsignedInteger unique terpisah: `sequence` selalu numerik sehingga aman diurutkan, sementara `number` bebas teks tanpa merusak penomoran berikutnya.
- Dampak: kolom 6 tidak punya ikon gear dan tidak ada format custom.
- Status: dijawab (Expense Fase 1) — format default mulai `10001`, kolom `sequence` terpisah.

## Q-21 Pembatasan hanya bisa diakses pembuatnya

- Ditemukan di: `prd.md` §5 BR-20 (Fase 1)
- Yang diketahui: repo tidak punya tabel settings, jadi tidak ada tempat untuk toggle ini. `CreatePurchaseInvoice` sudah menyimpan `created_by` plus snapshot `creator_name`, tapi kolom itu tidak pernah dipakai sebagai filter akses. Skoping yang tersedia sekarang adalah `branch_id` plus permission lewat `CompanyAccess::contextBranchIds()`.
- Opsi A: tambah tabel settings baru, sama seperti Q-13. Opsi B: tidak membatasi lebih dari `branch_id` plus permission.
- Rekomendasi agent: **Opsi B.** `branch_id` plus permission sudah mencegah user company atau cabang lain. Pembatasan per pembuat butuh mekanisme settings yang belum ada dan belum ada konsumen kedua.
- Dampak: user dengan permission `expense.view` melihat semua biaya di cabangnya, termasuk milik user lain.
- Status: dijawab (Expense Fase 1) — hanya `branch_id` plus permission.

## Q-22 Pembatasan akun kas dan akun biaya per peran

- Ditemukan di: `prd.md` §9 (Fase 3)
- Yang diketahui: `ChartOfAccount` tidak punya kolom allowance dan modularitas per peran tidak ada di mana pun di repo.
- Opsi A: tambah tabel allowance baru di Accounting. Opsi B: cukup permission `expense.create` untuk semua akun yang lolos BR-02.
- Rekomendasi agent: **Opsi B** untuk sekarang. Belum ada kebutuhan nyata yang terlihat, dan PRD sendiri menyuruh membaca artikel pembatasan CoA per peran lebih dulu.
- Status: **menunggu** — Fase 3, tidak memblokir Fase 1 dan Fase 2.

## Q-23 Format tanggal di tabel daftar

- Ditemukan di: `prd.md` §7.1 kolom Tanggal versus §7.2 kolom 4
- Yang diketahui: PRD §7.1 meminta `DD-MM-YYYY` di tabel daftar, §7.2 meminta `DD/MM/YYYY` di form. `formatDate()` di `resources/js/lib/format.ts` memakai locale id-ID sehingga menghasilkan `DD/MM/YYYY`, sama seperti seluruh tabel lain.
- Rekomendasi agent: tambah `formatDateDash()` di `resources/js/lib/format.ts` khusus untuk tabel daftar. Form tetap memakai `FormDatePicker` yang sudah `DD/MM/YYYY`.
- Status: dijawab (Expense Fase 1) — `formatDateDash` untuk daftar.

## Q-24 Nilai enum status

- Ditemukan di: `prd.md` §4.4
- Yang diketahui: hanya `Closed` yang terlihat di screenshot, label CONFIRMED. Nilai lain berlabel INFERRED.
- Opsi A: empat nilai (`draft`, `open`, `closed`, `overdue`) sejak awal. Opsi B: dua nilai (`open`, `closed`), kolom tetap `string(20)`.
- Rekomendasi agent: **Opsi B.** Menuliskan `draft` tanpa alur approval membuat enum berisi nilai yang tidak pernah terjadi, dan menambahkannya nanti cukup mengubah isi enum.
- Status: dijawab (Expense Fase 1) — `open` dan `closed`.

---

## Ringkasan

| ID | Topik | Status | Menghambat |
|---|---|---|---|
| Q-01 | Harga inklusif dan pengali 11/12 | dijawab | — |
| Q-02 | Pajak pemotongan di baris | dijawab | — |
| Q-03 | Bayar dari saat bayar nanti | dijawab | — |
| Q-04 | Pemotongan terhadap nilai hutang | **menunggu** | Fase 2 |
| Q-05 | Jatuh tempo dan Overdue | dijawab | — |
| Q-06 | Pembulatan agar jurnal balance | dijawab | — |
| Q-07 | Pelunasan sebagian | **menunggu** | Fase 2 |
| Q-08 | Hapus massal sebagian gagal | dijawab (PRD) | — |
| Q-09 | Paginasi dan urutan | dijawab | — |
| Q-10 | Stack teknologi | dijawab | — |
| Q-11 | Periode terkunci | dijawab (tunda) | BR-14 dan BR-17 sebagian |
| Q-12 | Rekonsiliasi | dijawab (tunda) | BR-14 dan BR-17 sebagian |
| Q-13 | Default pajak inklusif | dijawab | — |
| Q-14 | Tipe kontak lainnya | dijawab | — |
| Q-15 | Cakupan kategori akun | dijawab | — |
| Q-16 | Mata uang | dijawab | — |
| Q-17 | Master tag | dijawab | — |
| Q-18 | amount_paid sebelum pelunasan | dijawab | — |
| Q-19 | Approval per paket | **menunggu** | Fase 3 |
| Q-20 | Format penomoran | dijawab | — |
| Q-21 | Akses hanya pembuatnya | dijawab (tunda) | BR-20 |
| Q-22 | Pembatasan CoA per peran | **menunggu** | Fase 3 |
| Q-23 | Format tanggal daftar | dijawab | — |
| Q-24 | Nilai enum status | dijawab | — |

Yang masih menunggu: **Q-04, Q-07, Q-19, Q-22**. Tidak ada yang memblokir Fase 1.
Q-04 dan Q-07 harus terjawab sebelum Fase 2 dimulai. Q-19 dan Q-22 sebelum Fase 3 dimulai.
