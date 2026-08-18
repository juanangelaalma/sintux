# Implementation Plan: Modul Purchasing (6 Dokumen Procurement)

## Overview

Membangun modul **Purchasing** (Modular Monolith, `Modules/Purchasing`) dengan 6 dokumen procurement yang independen namun saling menjadi sumber (opsional prefill): **Purchase Request**, **Purchase Quote**, **Purchase Order**, **Goods Receipt (GRN)**, **Purchase Invoice**, dan **Join Invoice (Tukar Faktur)**. GRN menjadi jalur **inbound stok formal** ke Warehouse (mengurangi ketergantungan pada Adjustment). Semua nilai historis (nama/SKU/UOM/harga/tax) di-freeze di item dokumen (AGENTS.md §13).

## Architecture Decisions

- **Branch**: `feature/purchasing-module` dibuat dari `master` lokal (sudah berisi contact-hardening, tidak di-push). Merge master dengan branch lain secara lokal (tanpa push).
- **Dokumen independen**: setiap dokumen bisa dibuat langsung; relasi sumber (Quote←PR, PO←Quote/PR) opsional untuk prefill item.
- **Alur status**:
  - PR: `draft → pending → approved → fulfilled` (+ `cancelled`) — dengan approval
  - Quote: `draft → sent → accepted` (+ `expired`/`cancelled`) — per supplier
  - PO: `draft → pending → approved → sent → received` (+ `cancelled`) — dengan approval
  - GRN: `draft → posted` — post memanggil Warehouse public API
  - Invoice: `draft → approved → paid` — 3-way match PO + GRN saat approve
  - Join Invoice: `draft → ready` — konsolidasi invoice `approved` untuk pembayaran massal (dipakai modul Payment nanti, belum catat bayar)
- **Boundary modul**: Purchasing **hanya** memakai public Application API modul lain. Tidak boleh `use Modules\Warehouse\Models\*`, `Modules\Accounting\Models\*`, dst. GRN memanggil Warehouse API di dalam transaksi yang sama (koneksi tenant sama, aman).
- **API publik baru**:
  - Accounting: `GetPurchaseTaxes()` — list tax aktif (id, name, code, rate)
  - Warehouse: `ReceivePurchaseStock()` — buat `StockLayer` (`source_type='purchase_order'`) + upsert `stock_balances` + `StockMovement 'purchase_in'` (pola `PostStockAdjustment::processAdjustmentIn`, di-copy jadi API publik)
- **Snapshot historis**: item dokumen menyimpan `product_name`, `sku`, `uom_name`, `unit_price`, `tax_rate` saat dokumen dibuat; posting tidak membaca Product.
- **Tanpa mata uang**: cakupan awal satu mata uang default (tanpa kolom currency). Konfirmasi saat Fase 1.
- **Permission**: `purchasing.request.*`, `purchasing.quote.*`, `purchasing.po.*`, `purchasing.grn.*`, `purchasing.invoice.*` + **role baru `Purchasing`** (level branch) di `RolePermissionSeeder`.
- **UI navigasi**: sidebar cukup **1 menu "Purchasing"** (langsung ke daftar PO). Navigasi ke 6 dokumen via dropdown **"Tindakan"** di header (replikasi pola `product-actions-dropdown.tsx`).
- **Prinsip AGENTS.md**: tidak menambah abstraction (interface/repo/DTO) tanpa alasan; controller tipis; validasi di FormRequest; transaksi di use case; TDD.

## Task List

### Fase 0: Baseline — Merge & Branch (local)
- [ ] T0.1: Fast-forward update branch feature lokal ke master (contact-hardening, product-warehouse-module, contact, company-rbac-user-management)
- [ ] T0.2: Fetch remote; cek `master` remote ada yang baru; bila ada, merge ke master lokal
- [ ] T0.3: Buat branch `feature/purchasing-module` dari `master`
- [ ] T0.4: Scaffold modul `Purchasing` (module.json requires Company/Contact/Product/Warehouse/Accounting, modules_statuses.json, folder app/Http/routes/database)

### Checkpoint Fase 0
- [ ] `git branch --no-merged master` kosong
- [ ] `git status` clean; branch = `feature/purchasing-module`
- [ ] `php artisan module:list` menampilkan Purchasing

### Fase 1: Fondasi — Migrasi + Model + Seeder
- [ ] T1.1: Migrasi 13 tabel tenant (purchase_requests/items, purchase_quotes/items, purchase_orders/items, goods_receipts/items, purchase_invoices/items, join_purchase_invoices/items)
- [ ] T1.2: 13 Model Eloquent + relasi + casts
- [ ] T1.3: Permission `purchasing.*` + role `Purchasing` + mapping di `RolePermissionSeeder`
- [ ] T1.4: Test foundation `PurchaseFoundationTest`

### Checkpoint Fase 1
- [ ] `tenants:migrate` sukses; down() berhasil
- [ ] Test foundation lulus; `pint --test` + `phpstan` clean

### Fase 2: Public API lintas modul
- [ ] T2.1: Accounting `GetPurchaseTaxes()` + test
- [ ] T2.2: Warehouse `ReceivePurchaseStock()` + test

### Checkpoint Fase 2
- [ ] Kedua API + test lulus
- [ ] Boundary terjaga (Purchasing belum menyentuh model Warehouse/Accounting)

### Fase 3: Purchase Request + Purchase Quote
- [ ] T3.1: PR backend (Create/Get/GetList/Approve/Cancel + FormRequest + routes + controller)
- [ ] T3.2: Quote backend (Create/Get/GetList/Send/Accept/Cancel + prefill dari PR + FormRequest + routes + controller)
- [ ] T3.3: UI PR + Quote (index/create/show + dropdown awal)
- [ ] T3.4: Test PR & Quote (crud, workflow approval, 403/404)

### Checkpoint Fase 3
- [ ] Test modul lulus; alur PR→Quote e2e; lint/types clean

### Fase 4: Purchase Order + GRN
- [ ] T4.1: PO backend (Create/Get/GetList/Update-draft/Approve/Send/Cancel + prefill dari Quote/PR)
- [ ] T4.2: GRN backend (Create/Get/GetList/Post → ReceivePurchaseStock + qty_received + status PO)
- [ ] T4.3: UI PO + GRN (index/create/show + tombol approve/send/receive)
- [ ] T4.4: Test PO & GRN (crud, workflow, post→stok, over-receive, double-post)

### Checkpoint Fase 4
- [ ] Test modul lulus; alur PO→approve→send→GRN→stok e2e; lint/types clean

### Fase 5: Purchase Invoice + Join Invoice
- [ ] T5.1: Invoice backend (Create/Get/GetList/Approve + 3-way match)
- [ ] T5.2: Join Invoice backend (Create/Get/GetList/Ready + konsolidasi)
- [ ] T5.3: UI Invoice + Join (index/create/show)
- [ ] T5.4: Test Invoice & Join (3-way match, join konsolidasi, double-join)

### Checkpoint Fase 5
- [ ] Test modul lulus; alur Invoice→approve→Join→ready e2e; lint/types clean

### Fase 6: Navigasi UI
- [ ] T6.1: Sidebar 1 menu "Purchasing" (`/purchasing`, permission `purchasing.po.view`)
- [ ] T6.2: Reusable `PurchasingActionsDropdown` (6 link dokumen) di header

### Checkpoint Fase 6
- [ ] `npm run types:check/lint:check/build` sukses
- [ ] Navigasi sidebar→PO→semua dokumen e2e

### Fase 7: Finalisasi + Review
- [ ] T7.1: `composer ci:check` penuh
- [ ] T7.2: Review lintas dimensi (skill code-review-and-quality)
- [ ] T7.3: Bersihkan dead code; commit per fase

### Checkpoint Fase 7
- [ ] Semua quality gate lulus; tidak ada pelanggaran boundary modul
- [ ] `git status` clean

## Risks and Mitigations
| Risk | Impact | Mitigation |
|------|--------|------------|
| Refactor `ReceivePurchaseStock` merusak adjustment existing | Med | Di-copy (bukan dipindah) dari pola `PostStockAdjustment`; test Warehouse existing dijalankan ulang |
| 3-way match (invoice > received) kompleks | Med | Rule di use case + test matrix di Fase 5 |
| 13 migrasi + seeder besar konflik tenant | Med | Migrasi sequential (0xxx); test foundation Fase 1 |
| Task UI L-size | Med | Pecah per halaman dokumen; UI per fase |

## Open Questions
- **Mata uang**: asumsi 1 mata uang default (tanpa kolom currency) pada cakupan awal — perlu konfirmasi.
- **Join Invoice & Payment**: Join hanya menghasilkan dokumen konsolidasi (status `ready`); pencatatan pembayaran ada di modul Payment (belum dibangun).
