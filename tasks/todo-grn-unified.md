# Todo: GRN Tunggal — SELESAI

- [x] Phase 1: migrasi + enum + model GRN
- [x] Phase 2: use-case GRN (fetch/verify/submit/approve/transfer/query)
- [x] Phase 3: keterkaitan FBL (prefill + warna)
- [x] Phase 4: controller + routes + inertia badge
- [x] Phase 5: frontend GRN tunggal + kolom warna
- [x] Phase 6: rewrite test + bersih-bersih + CI hijau

Verifikasi akhir:
- `php artisan test` → 288 tests, 285 passed, 3 skipped, 0 failed
- `pint --test` → passed, `phpstan` → 0 errors
- `tsc --noEmit` → passed, `eslint` → 0 errors (2 warnings pre-existing)
- `prettier` → file yang disentuh sudah diformat
