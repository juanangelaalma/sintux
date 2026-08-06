# Implementation Plan: CRUD Branch (Company Module)

## Overview

Menambahkan pengelolaan branch (cabang) dalam konteks tenant. Table `branches` sudah ada (migrasi tenant); fitur ini menambah model Eloquent, controller + FormRequest, route, halaman Inertia satu-file (tabel + modal), dan menu sidebar permission-gated. Tidak ada hapus fisik — hanya toggle `is_active`. Flag `is_headquarters` read-only. Halaman & mutasi dibatasi permission `company.branch.manage`.

## Architecture Decisions

- **Model tenant**: `Modules/Company/app/Models/Branch.php` (default connection → tenant DB saat tenancy aktif). `is_headquarters` TIDAK masuk `$fillable` (read-only di level model).
- **Controller tipis**: `CompanyBranchController` hanya `index/store/update` (no `destroy`), mengikuti pola `CompanyUserController` (`abort_unless(hasPermissionTo('company.branch.manage'), 403)` di index + FormRequest `authorize()`).
- **Validasi**: FormRequest `Store/UpdateCompanyBranchRequest`. `code` unik per-tenant via `unique:branches,code` (berjalan di koneksi tenant — benar untuk data tenant). Update pakai `Rule::unique('branches','code')->ignore($branch->id)`.
- **Route**: `Route::resource('company/branches', ...)->only(['index','store','update'])->names('company.branches')` di dalam grup `EnsureCompanyMember`.
- **Tidak menambah abstraction** (no interface/repo/DTO): model + controller cukup untuk CRUD sederhana.

## Task List

### Phase 1: Backend (TDD)
- [ ] **Task 1: Tulis test dulu (red)** — `Modules/Company/tests/Feature/CompanyBranchCrudTest.php`
  - Acceptance: test menegakkan 5 skenario spec (admin manage, kode unik, owner manage, member 403, HQ read-only).
  - Verify: `php artisan test --filter=CompanyBranchCrudTest` → fail (class/route belum ada).
  - Files: `Modules/Company/tests/Feature/CompanyBranchCrudTest.php`
- [ ] **Task 2: Implementasi backend (green)** — Model `Branch`, `Store/UpdateCompanyBranchRequest`, `CompanyBranchController`, route.
  - Acceptance: test 5 skenario lulus; `pint` & `phpstan` clean pada file modul.
  - Verify: `php artisan test --filter=CompanyBranchCrudTest`; `composer lint:check`; `composer types:check`.
  - Files: `Modules/Company/app/Models/Branch.php`, `Modules/Company/app/Http/Requests/{Store,Update}CompanyBranchRequest.php`, `Modules/Company/app/Http/Controllers/CompanyBranchController.php`, `Modules/Company/routes/web.php`

### Checkpoint: Backend
- [ ] `php artisan test --filter=CompanyBranchCrudTest` → 5 tests pass
- [ ] `composer lint:check` & `composer types:check` clean

### Phase 2: Frontend
- [ ] **Task 3: Halaman Inertia + menu sidebar**
  - Acceptance: halaman `Company/Branches/index.tsx` (tabel: name/code/address/phone/status/HQ; aksi Edit + Deactivate/Activate; modal create/edit; HQ non-editable; tanpa tombol delete). Menu "Branches" di `company-sidebar.tsx` hanya muncul untuk pemegang `company.branch.manage`.
  - Verify: `npm run lint:check`, `npm run types:check`, `npm run build`.
  - Files: `resources/js/pages/Company/Branches/index.tsx`, `resources/js/layouts/company/company-sidebar.tsx`

### Checkpoint: Frontend
- [ ] `npm run build` sukses; lint & types clean

### Phase 3: Verifikasi menyeluruh
- [ ] **Task 4: Full verification**
  - Acceptance: seluruh suite (31+5 test) hijau; lint/type/build backend+frontend clean.
  - Verify: `php artisan test`; `composer lint:check`; `composer types:check`; `npm run lint:check`; `npm run types:check`; `npm run build`.

## Risks and Mitigations
| Risk | Impact | Mitigation |
|------|--------|------------|
| Validasi `unique:branches,code` jalan di koneksi salah | High | Branch adalah data tenant; rule unik memang harus di koneksi tenant (ter-initialize via ResolveTenant). Konfirmasi via test create+update. |
| Assertion tenant table setelah request gagal (tenancy sudah end) | High | Dalam test, re-initialize tenancy sebelum assert, lalu end. |
| Route resource `{branch}` model binding tak resolve di tenant DB | Med | Type-hint `Branch $branch` — binding berjalan saat tenancy aktif (di dalam request). |

## Open Questions
- (tidak ada)
