# Todo List: CRUD Company (Modular Monolith)

## Phase 1: Module Scaffolding
- [ ] Task 1.1: Generate Company Module (`php artisan module:make Company --web`)
- [ ] Task 1.2: Bersihkan folder tidak terpakai di `Modules/Company/` (views, assets, dll)
- [ ] Task 1.3: Jalankan `composer dump-autoload` untuk mereset class map

## Phase 2: Backend
- [ ] Task 2.1: Buat `CompanyController` di `Modules/Company/app/Http/Controllers`
- [ ] Task 2.2: Buat Form Requests `StoreCompanyRequest` dan `UpdateCompanyRequest`
- [ ] Task 2.3: Daftarkan routes admin company di `Modules/Company/routes/web.php`
- [ ] Task 2.4: Sambungkan Event-Listener `stancl/tenancy` dengan controller flow

## Phase 3: Frontend & UI
- [ ] Task 3.1: Buat page list company `resources/js/pages/admin/companies/index.tsx`
- [ ] Task 3.2: Buat form modal/page `resources/js/pages/admin/companies/form.tsx`
- [ ] Task 3.3: Hubungkan sidebar admin ke `/admin/companies`
- [ ] Task 3.4: Buat integration test `Modules/Company/tests/Feature/CompanyCrudTest.php`

## Verification
- [ ] `composer lint` -> Passed
- [ ] `composer types:check` -> Passed
- [ ] `php artisan test` -> Passed
