# Todo List: Hardening Modul Contact

> Berdasarkan QA: 2 Critical, 4 Important, + hardening lanjutan. Setiap task disertai test regresi. Branch kerja: `feature/contact-hardening` (dibuat dari `master`).

## Phase 0: Fondasi keamanan data & infrastruktur test
- [x] T1: Amankan migrasi `2026_08_07_000600` — hapus `contacts()->delete()`, `branch_id` nullable + backfill HQ/first-active (NOT NULL dijaga di aplikasi, bukan DB)
- [x] T2: Perbaiki kebocoran skema test — drop skema aktual `sch_*` di `tearDown`, `Tenant::query()->delete()`, hapus `SCHEMA_NAME` dead-code
- [x] T2b: **Bug produksi terungkap saat test** — `CreateCompanyUser::assignBranches` & `UpdateCompanyUser::assignBranches` query `DB::table('branches')` tanpa guard tenancy → membaca central (bukan schema tenant). Ditambah guard `tenancy()->initialize` konsisten dengan `CompanyAccess`. Test `test_switch_to_accessible_branch_succeeds` kini hijau.

### Checkpoint Phase 0
- [x] `php artisan test Modules/Contact` lulus (14/14)
- [x] Tidak ada skema `sch_*` tersisa di DB `testing`

### Catatan suite penuh (pre-existing di master, BUKAN regresi)
- `StockAdjustmentTest` × 3: `delete from "stock_movements"` di central → tabel tenant diakses dari central (pola test lama, sama dengan bug T2b)
- `TenantAuthTest`: `schema "company_test_auth" already exists` (skema tidak di-drop)
- `ProductHubStockTest`: `foreach() null` di `GetProducts.php:87`
- `ProductImageUploadTest`: file storage tidak ditemukan
- Rekomendasi: audit test Warehouse/Product dengan pola akses tabel tenant dari central (tugas fase lanjutan / backlog)

## Phase 1: Authorization granular + proteksi PII (Critical)
- [x] T3: Permission `contact.view/create/update/delete` di `RolePermissionSeeder` + mapping role
- [x] T4: Middleware `permission:contact.*` di routes Contact
- [x] T5: Pisahkan `ContactPresenter` → list projection & detail projection; PII tidak dikirim di list (`serializeForList` & `serializeForDetail`)
- [x] T6: Test authorization (403 tanpa permission; PII tidak ada di list props; 200 dengan permission) di `ContactAuthorizationTest.php`

### Checkpoint Phase 1
- [x] Test authorization & PII lulus (18/18 Contact tests pass)
- [x] `pint --test` clean

## Phase 2: Correctness — scope, validasi, atomicity (Important)
- [x] T7: Validasi `branch_id` pakai `contextBranchIds()` (scope aktif) + test
- [x] T8: Validasi format `npwp`/`identity_number`/telepon + `notes` max 5000 + test matrix
- [x] T9: `DB::transaction` + `lockForUpdate` di `UpdateContact` + `SyncContactAddresses` + test atomicity

### Checkpoint Phase 2
- [x] Test scope, validasi, atomicity lulus (21/21 Contact tests pass)
- [x] Alur CRUD contact end-to-end normal

## Phase 3: Hardening lanjutan (Suggestion)
- [x] T10: Anti-enumerasi — out-of-scope → 404 (bukan 403) + update test
- [x] T11: Validasi type URL vs type contact (`PUT /suppliers/{customer}` → 404) + test
- [x] T12: `authorize()` FormRequest cek membership (defense-in-depth)
- [x] T13: Hapus middleware `verified` (keputusan: tidak dipakai) + `SESSION_SECURE_COOKIE` production
- [x] Tambah `SensitiveInput` React UI (masking NIK/NPWP/Bank dengan toggle 👁️ Show/Hide)

### Checkpoint Phase 3
- [x] Test hardening lulus (23/23 Contact tests pass)
- [x] `pint --test` clean

## Phase 4: Catatan terbuka (opsional / backlog)
- [ ] T14: Audit logging CRUD contact
- [ ] T15: Pagination `GetContacts` (kontrak page-size + UI)
- [ ] T16: Bersihkan permission menggantung di seeder (`cashier` → `sales.order.*`)