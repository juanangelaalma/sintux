# Implementation Plan: Company Management Module (Modular Monolith)

## Overview
Implementasi CRUD Company (Tenant) yang utuh menggunakan arsitektur Modular Monolith (`laravel-modules`). Modul ini akan dinamakan **Company** dan diletakkan di bawah direktori `Modules/Company`. Modul ini hanya bisa diakses oleh `superadmin` di panel admin (`/admin/companies`).

## Architecture Decisions
- **Modul Baru**: Buat modul `Company` menggunakan Artisan Command `module:make`.
- **Public API / Boundaries**: Modul Company mengekspos domain / application layer untuk pembuatan database schema secara dinamis (mengintegrasikan tenant creation + database credentials generation yang sebelumnya ada di seeder).
- **Controller & Request Validation**: Controller diletakkan di `Modules/Company/app/Http/Controllers/CompanyController.php`. Request validation diletakkan di `Modules/Company/app/Http/Requests`.
- **Presentation**: Render menggunakan Inertia.js React pages yang diletakkan di `resources/js/pages/admin/companies/` (mengikuti aturan letak Inertia pages yang terpusat).
- **Event-Driven Tenant Database Setup**: Saat tenant baru disimpan di database central (public), event `TenantCreated` dari `stancl/tenancy` akan otomatis men-trigger pembuatan schema PostgreSQL `company_{slug}` dan melakukan migrasi tenant secara otomatis.

---

## Task List

### Phase 1: Module Scaffolding
- **Task 1.1: Generate Company Module**
  - Buat modul `Company` menggunakan command laravel-modules.
  - Hapus file-file generator yang tidak terpakai (views, config bawaan) agar modul tetap bersih dan modular monolith minimalis.
- **Task 1.2: Register Autoloading**
  - Pastikan composer.json dan merge-plugin mendeteksi modul baru di `Modules/Company`.

### Checkpoint: Scaffolding
- [ ] Modul `Company` berhasil digenerate dan didaftarkan.
- [ ] Command `php artisan module:list` menampilkan modul `Company` dengan status `Enabled`.

### Phase 2: Backend (Domain & HTTP)
- **Task 2.1: Model & Migrations**
  - Karena data `tenants` (companies) sudah berada di central `public` schema (`tenants` table), kita akan memindahkan model `Tenant` ke modul Company jika perlu, atau meng-extend model Tenant bawaan dari modul Company.
- **Task 2.2: CompanyController & FormRequest**
  - Implementasikan index, create, store, edit, update, dan destroy endpoints.
  - Tambahkan FormRequest dengan validasi unik untuk `id` (tenant slug/subdomain) dan `schema_name`.
  - Keamanan: Gunakan middleware `EnsureSuperadmin` untuk memproteksi routes ini.
- **Task 2.3: Tenant Schema Creation Integration**
  - Ketika superadmin menyimpan Company baru, panggil `Tenant::create(...)` yang memicu auto-create schema PostgreSQL dan migrate (sudah terkonfigurasi di `TenancyServiceProvider` event listener).

### Checkpoint: Backend CRUD
- [ ] Backend routes `/admin/companies` terdaftar dan diproteksi middleware `EnsureSuperadmin`.
- [ ] Unit/Feature test untuk controller API berhasil disimulasikan.

### Phase 3: Frontend (Inertia React)
- **Task 3.1: Companies Index Page**
  - Buat `resources/js/pages/admin/companies/index.tsx` menampilkan list company yang ada (dengan kolom ID, Nama, Schema Name, Status Active, Created At).
- **Task 3.2: Company Form (Create & Edit)**
  - Buat form modal atau page untuk membuat dan meng-edit Company.
- **Task 3.3: Sidebar Navigation**
  - Hubungkan link "Tenants / Companies" di `admin-sidebar.tsx` ke route `admin.companies.index`.

### Checkpoint: Complete E2E
- [ ] Superadmin berhasil melakukan flow CRUD Company dari UI.
- [ ] Pembuatan company baru otomatis melahirkan schema PostgreSQL baru dengan tabel `branches`.
- [ ] Seluruh unit test & standard linting/types lulus (0 error).
