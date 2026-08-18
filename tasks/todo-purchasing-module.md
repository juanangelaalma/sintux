# Todo List: Modul Purchasing (6 Dokumen Procurement)

> Branch: `feature/purchasing-module` (dibuat dari `master` lokal, tanpa push). Setiap fase diakhiri checkpoint test. TDD wajib per task. Detail lengkap di `plan-purchasing-module.md`.

## Fase 0: Baseline — Merge & Branch (local)
- [x] T0.1: Fast-forward update branch feature lokal ke master (contact-hardening, product-warehouse-module, contact, company-rbac-user-management)
- [x] T0.2: Fetch remote; cek master remote baru; merge ke master lokal bila ada
- [x] T0.3: Buat branch `feature/purchasing-module`
- [x] T0.4: Scaffold modul Purchasing (module.json requires Company/Contact/Product/Warehouse/Accounting + modules_statuses.json + folder)

### Checkpoint Fase 0
- [x] `git branch --no-merged master` kosong; branch = feature/purchasing-module
- [x] `php artisan module:list` menampilkan Purchasing
- [ ] `git status` clean (masih ada untracked `storage/tenantimg_6a83ccf8ba086/` — di-ignore, bukan di-commit)

## Fase 1: Fondasi — Migrasi + Model + Seeder
- [x] T1.1: Migrasi 13 tabel tenant (PR/items, Quote/items, PO/items, GRN/items, Invoice/items, Join/items)
- [x] T1.2: 13 Model Eloquent + relasi + casts
- [x] T1.3: Permission purchasing.* + role Purchasing + mapping RolePermissionSeeder
- [x] T1.4: `PurchaseFoundationTest`

### Checkpoint Fase 1
- [x] tenants:migrate & down sukses; test foundation lulus
- [x] `pint --test` + `phpstan` clean

## Fase 2: Public API lintas modul
- [x] T2.1: Accounting `GetPurchaseTaxes()` + test
- [x] T2.2: Warehouse `ReceivePurchaseStock()` + test

### Checkpoint Fase 2
- [x] Kedua API + test lulus; boundary terjaga (tanpa use model Warehouse/Accounting dari Purchasing)

## Fase 3: Purchase Request + Purchase Quote
- [x] T3.1: PR backend (Create/Get/GetList/Approve/Cancel + request + routes + controller)
- [x] T3.2: Quote backend (Create/Get/GetList/Send/Accept/Cancel + prefill PR)
- [x] T3.3: UI PR + Quote (index/create/show)
- [x] T3.4: Test PR & Quote (crud, workflow, 403/404)

### Checkpoint Fase 3
- [x] Test lulus; alur PR→Quote e2e; lint/types clean

## Fase 4: Purchase Order + GRN
- [x] T4.1: PO backend (Create/Get/GetList/Update-draft/Approve/Send/Cancel + prefill)
- [x] T4.2: GRN backend (Create/Get/GetList/Post → ReceivePurchaseStock + qty_received + status PO)
- [x] T4.3: UI PO + GRN (index/create/show + tombol approve/send/receive)
- [x] T4.4: Test PO & GRN (workflow, post→stok, over-receive, double-post)

### Checkpoint Fase 4
- [x] Test lulus; alur PO→approve→send→GRN→stok e2e; lint/types clean

## Fase 5: Purchase Invoice + Join Invoice
- [x] T5.1: Invoice backend (Create/Get/GetList/Approve + 3-way match)
- [x] T5.2: Join Invoice backend (Create/Get/GetList/Ready + konsolidasi)
- [x] T5.3: UI Invoice + Join (index/create/show)
- [x] T5.4: Test Invoice & Join (3-way match, konsolidasi, double-join)

### Checkpoint Fase 5
- [x] Test lulus; alur Invoice→approve→Join→ready e2e; lint/types clean

## Fase 6: Navigasi UI
- [x] T6.1: Sidebar 1 menu "Purchasing" (/purchasing, permission purchasing.po.view)
- [x] T6.2: `PurchasingActionsDropdown` (6 link dokumen)

### Checkpoint Fase 6
- [x] types:check / lint:check / build sukses; navigasi e2e

## Fase 7: Finalisasi + Review
- [x] T7.1: `composer ci:check` penuh (phpunit, pint, phpstan, types:check, eslint, build)
- [x] T7.2: Review lintas dimensi & boundary modul (AGENTS.md)
- [x] T7.3: Bersihkan dead code; verifikasi status lokal

### Checkpoint Fase 7
- [x] Semua quality gate lulus; tanpa pelanggaran boundary; status aman pada branch `feature/purchasing-module`
