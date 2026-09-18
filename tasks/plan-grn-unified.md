# Implementation Plan: GRN Tunggal (RCV dilebur ke GRN) + Detail Warna

## Overview
Hapus dokumen `BranchReception` (RCV). Satu-satunya dokumen penerimaan adalah GRN
(`goods_receipts` + `goods_receipt_items`): fetch DO → draft → submitted →
approved/rejected → transfer HO→cabang → FBL. GRN menampilkan rincian per warna
yang bisa direkonsiliasi terhadap FBL. Keputusan user: fresh (tanpa backfill),
form GRN manual HO dihapus, status `posted` dilebur ke `approved`.

## Architecture Decisions
- Tabel yang hidup: `goods_receipts` + `goods_receipt_items`. `branch_receptions*` di-drop.
- Items 1 tabel per (bundle, warna): kolom level-bundle didenormalisasi per baris
  warna; FK `purchase_invoice_items.goods_receipt_item_id` tetap valid.
- Status GRN: `draft → submitted → approved → rejected`; `approved` = stok terposting.
- Penomoran `GRN-` saja. Semua GRN lahir dari fetch DO cabang.
- Snapshot warna: `color` + `color_raw` di GRN items, dicopy ke invoice items.

## Task List

### Phase 1: Foundation (schema, enum, model)
- [ ] Task 1: migrasi `goods_receipts` (header DO + workflow, warehouse/receipt_date nullable)
- [ ] Task 2: migrasi `goods_receipt_items` + `purchase_invoice_items` (bundle + warna)
- [ ] Task 3: migrasi drop `branch_receptions*`
- [ ] Task 4: enum `GoodsReceiptStatus` baru, hapus `BranchReceptionStatus`
- [ ] Task 5: model `GoodsReceipt`/`GoodsReceiptItem`, hapus 3 model Reception

### Checkpoint 1
- [ ] `php artisan migrate:fresh --env=testing` setara lolos; lint PHP lolos

### Phase 2: Use-case GRN
- [ ] Task 6: `FetchGoodsReceipt` (fetch DO → GRN draft, auto-mapping warna)
- [ ] Task 7: `VerifyBundleBarcode`, `UpdateReceivedQty`, `ConfirmPhysicalQty` (grup barcode)
- [ ] Task 8: `Submit/Reject/ReviseGoodsReceipt`
- [ ] Task 9: `ApproveGoodsReceipt` (alokasi PO + posting stok + status)
- [ ] Task 10: `TransferApprovedReception` retarget GRN (`source_type` baru)
- [ ] Task 11: query `GetGoodsReceipts` (+pending count, search DO) + detail + receivable POs

### Checkpoint 2
- [ ] Alur cabang→HO end-to-end via test: fetch → scan → confirm → submit → approve → stok cabang

### Phase 3: FBL linkage
- [ ] Task 12: prefill + `resolveSupplierInvoiceNo` dari GRN langsung; warna ikut ke invoice items

### Phase 4: HTTP + routes
- [ ] Task 13: satu `GoodsReceiptController`, routes `grns/*`; hapus controller/routes RCV + manual; `HandleInertiaRequests` badge

### Phase 5: Frontend
- [ ] Task 14: halaman `GRNs/*` tunggal (list/detail/inbox/fetch), kolom warna, sidebar, tabs, `status.ts`

### Phase 6: Tests + cleanup
- [ ] Task 15: `BranchReceptionTest` → `GoodsReceiptWorkflowTest`; sesuaikan `GoodsReceiptTest`, `PurchaseInvoiceTest`
- [ ] Task 16: hapus file mati; grep `RCV|BranchReception` nol; `composer ci:check` hijau

## Risks and Mitigations
| Risk | Impact | Mitigation |
|---|---|---|
| `warehouse_id` nullable mengubah asumsi query | Med | Diisi saat approve; audit query join warehouse |
| Test lama besar (~1300 baris) | Med | Rewrite per skenario |
| Konflik nomor GRN vs histori RCV | Low | Sequence per branch, fresh |

## Open Questions (diputuskan)
- Fresh tanpa backfill: YA. Form manual HO: HAPUS. `posted`→`approved`: YA.
