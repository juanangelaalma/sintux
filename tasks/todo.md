# Todo List: CRUD Branch (Company Module)

## Phase 1: Backend (TDD)
- [ ] Task 1: Tulis `CompanyBranchCrudTest` (5 skenario) — expect red
- [ ] Task 2: Implementasi backend: `Branch` model, `Store/UpdateCompanyBranchRequest`, `CompanyBranchController`, route `company/branches` — expect green

### Checkpoint Backend
- [ ] `php artisan test --filter=CompanyBranchCrudTest` → pass (5)
- [ ] `composer lint:check` & `composer types:check` clean

## Phase 2: Frontend
- [ ] Task 3: Halaman `Company/Branches/index.tsx` + menu sidebar "Branches" (permission-gated)

### Checkpoint Frontend
- [ ] `npm run lint:check`, `npm run types:check`, `npm run build` clean

## Phase 3: Verifikasi
- [ ] Task 4: `php artisan test` full suite hijau + semua lint/type/build clean
