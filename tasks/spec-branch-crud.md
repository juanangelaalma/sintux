# Spec: CRUD Branch (Company Module)

## Objective

Tambah kemampuan mengelola cabang (branch) dalam konteks sebuah company/tenant. Pengguna yang ditargetkan adalah Owner dan Admin dari tenant (pemegang permission `company.branch.manage`). Tabel `branches` sudah ada di migrasi tenant; feature ini menambahkan lapisan UI + HTTP di atasnya.

**Keputusan yang sudah disepakati:**
- UI: satu halaman index + modal inline untuk create/edit (konsisten dengan Company Users).
- **Tidak ada hapus fisik** — branch dinonaktifkan lewat toggle `is_active`.
- Flag `is_headquarters` **read-only** (dibuat otomatis saat company dibuat, tidak bisa diubah lewat CRUD).
- Halaman hanya bisa diakses pemegang permission `company.branch.manage` (index & mutasi sama-sama 403 tanpa permission).

## Tech Stack

- Laravel 13 / PHP 8.3, `stancl/tenancy` (schema per tenant), Inertia + React + TypeScript (Vite).
- Modul: `Modules/Company` (Modular Monolith).

## Commands

```sh
Build:       npm run build
Dev:         npm run dev
Lint:        npm run lint:check
Type check:  npm run types:check
PHP lint:    composer lint:check
PHP types:   composer types:check
Test:        php artisan test --filter=CompanyBranchCrudTest
```

## Project Structure

```
Modules/Company/
├── app/
│   ├── Http/
│   │   ├── Controllers/CompanyBranchController.php   → index, store, update (no destroy)
│   │   └── Requests/StoreCompanyBranchRequest.php, UpdateCompanyBranchRequest.php
│   ├── Models/Branch.php                              → Eloquent tenant model
│   └── Application/                                  → (tidak perlu untuk CRUD sederhana)
├── routes/web.php                                    → tambah route resource (only index/store/update)
└── tests/Feature/CompanyBranchCrudTest.php           → feature test

resources/js/
├── pages/Company/Branches/index.tsx                  → halaman Inertia
└── layouts/company/company-sidebar.tsx               → menu "Branches" (permission-gated)
```

Prinsip: gunakan pola yang sudah ada (thin controller + FormRequest + halaman satu file). Tidak membuat interface/repository/DTO — Eloquent model + controller sudah cukup untuk CRUD tenant-scoped sederhana.

## Code Style

Mengikuti pola `CompanyUserController` yang sudah ada: constructor-injected services (jika ada), `abort_unless(hasPermissionTo(...), 403)`, FormRequest dengan `authorize()` permission + `rules()` validasi, redirect `back()->with('success', ...)`. Frontend satu file per halaman, modal inline, type alias lokal, `useForm` dari Inertia.

## Testing Strategy

`Modules/Company/tests/Feature/CompanyBranchCrudTest.php` mengikuti pola `CompanyUserManagementTest` (buat tenant + schema + provision user). Kasus:

1. Admin (pemegang `company.branch.manage`) bisa `index` 200, `store` branch baru, `update` (termasuk nonaktifkan).
2. Kode unik ditolak (validasi `unique:branches,code`).
3. Owner bisa manage.
4. Member tanpa permission dapat 403 di `index` dan `store`.
5. HQ read-only: mengirim `is_headquarters` tidak mengubah branch (tidak memindahkan HQ).

## Boundaries

- **Always:** jalankan test sebelum commit; ikuti pola modul & permission yang ada; validasi input di FormRequest; ikuti gaya kode (pint/prettier/eslint).
- **Ask first:** perubahan schema/migrasi; menambah dependency; mengubah seed permission.
- **Never:** hapus HQ logic bawaan; mengubah `is_headquarters` lewat CRUD; hapus fisik branch; akses tabel tenant dari luar konteks tenancy.

## Success Criteria

- `php artisan test --filter=CompanyBranchCrudTest` pass (5 kasus di atas).
- `npm run lint:check`, `npm run types:check`, `npm run build` clean.
- `composer lint:check` & `composer types:check` clean.
- Route `company.branches.index/store/update` terdaftar; tidak ada `destroy`.
- Halaman `/company/branches` hanya bisa diakses pemegang permission; menu sidebar muncul hanya untuk pemegang permission.
- HQ tidak bisa diubah lewat UI/API.

## Open Questions

- (tidak ada — keputusan UI, delete, HQ, dan akses sudah dikonfirmasi)
