<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Models\CompanyUser;
use Modules\Product\Application\Variant\FindVariantBySkuAndColor;
use Modules\Purchasing\Application\GoodsReceipt\ApproveGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\ConfirmPhysicalQty;
use Modules\Purchasing\Application\GoodsReceipt\FetchGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\RejectGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\ReviseGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\SubmitGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\TransferApprovedGrn;
use Modules\Purchasing\Application\GoodsReceipt\UpdateReceivedQty;
use Modules\Purchasing\Application\GoodsReceipt\VerifyBundleBarcode;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Infrastructure\External\FakeSupplierDoClient;
use Modules\Purchasing\Infrastructure\External\HttpSupplierDoClient;
use Modules\Purchasing\Infrastructure\External\SupplierDoClient;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\GoodsReceiptItem;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;
use Tests\TestCase;

class GoodsReceiptWorkflowTest extends TestCase
{
    private ?string $activeSchemaName = null;

    private FakeSupplierDoClient $fakeDo;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();
        $this->cleanupCentralTables();

        $this->fakeDo = new FakeSupplierDoClient;
        $this->app->bind(SupplierDoClient::class, fn () => $this->fakeDo);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        parent::tearDown();
    }

    public function test_fetch_by_do_no_creates_grn_with_color_items(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-FETCH-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-FETCH-001', 'qty' => 20, 'price' => 50000],
        ]);

        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00230', $this->doPayload('DO/2608/00230', $poNumber, [
            $this->doProduct('LSBG20080', 'PAA-FETCH-001', 'Sarung A', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '10'],
            ]),
            $this->doProduct('LSBG20080B', 'PAA-FETCH-001-NEW', 'Sarung A Baru', [
                ['PUTIH', '10'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00230', $branchBId, 'BRB');

        $this->assertSame('DO/2608/00230', $grn->supplier_do_no);
        $this->assertSame('GRN-BRB-0001', $grn->number);
        $this->assertSame(GoodsReceiptStatus::Draft, $grn->status);
        $this->assertCount(3, $grn->items);
        // Harga supplier dalam sen: 5000000 → Rp50.000,00.
        $this->assertSame(50000.0, (float) $grn->items[0]->unit_price_supplier);
        $this->assertSame('DX G', $grn->items[0]->size);
        $this->assertNotEmpty($grn->items[0]->uom_name);
        $this->assertSame('LSBG20080', $grn->items[0]->supplier_barcode);
        $this->assertSame('HITAM MERAH PINK', $grn->items[0]->color_raw);
        $this->assertSame('not_verified', $grn->items[0]->verification_status);
        // HITAM MERAH PINK sudah ada master → dipakai; NAVY di-auto-mapping.
        $this->assertSame($variantRed, (int) $grn->items[0]->product_variant_id);
        $navyVariantId = (int) $grn->items[1]->product_variant_id;
        $this->assertGreaterThan(0, $navyVariantId);

        // prd_code yang sama sekali baru → produk dibuat tanpa kategori/satuan.
        $newVariantId = (int) $grn->items[2]->product_variant_id;
        $this->assertGreaterThan(0, $newVariantId);
        $newProductId = DB::table('product_variants')->where('id', $newVariantId)->value('product_id');
        $newProduct = DB::table('products')->where('id', $newProductId)->first();
        $this->assertNull($newProduct->category_id);
        $this->assertNull($newProduct->uom_id);

        tenancy()->end();
    }

    public function test_fetch_is_idempotent_and_rejects_other_branch(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $branchCId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-FETCH-002');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-FETCH-002', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00231', $this->doPayload('DO/2608/00231', $poNumber, [
            $this->doProduct('LSBG20081', 'PAA-FETCH-002', 'Sarung B', []),
        ]));

        $first = app(FetchGoodsReceipt::class)->execute('DO/2608/00231', $branchBId, 'BRB');
        $second = app(FetchGoodsReceipt::class)->execute('DO/2608/00231', $branchBId, 'BRB');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, (int) DB::table('goods_receipts')->where('supplier_do_no', 'DO/2608/00231')->count());

        $this->expectException(ValidationException::class);
        app(FetchGoodsReceipt::class)->execute('DO/2608/00231', $branchCId, 'BRC');

        tenancy()->end();
    }

    public function test_fetch_rejects_po_not_sent_and_missing_allocation(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $branchCId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-FETCH-003');
        $draftPoId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-FETCH-003', 'qty' => 20, 'price' => 50000],
        ], PurchaseOrderStatus::Draft);
        $draftNumber = DB::table('purchase_orders')->where('id', $draftPoId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00232', $this->doPayload('DO/2608/00232', $draftNumber, [
            $this->doProduct('LSBG20082', 'PAA-FETCH-003', 'Sarung C', []),
        ]));

        try {
            app(FetchGoodsReceipt::class)->execute('DO/2608/00232', $branchBId, 'BRB');
            $this->fail('Seharusnya ditolak karena PO belum terkirim.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('do_no', $e->errors());
        }

        // PO sent tapi untuk cabang lain.
        DB::table('purchase_orders')->where('id', $draftPoId)->update(['status' => PurchaseOrderStatus::Sent->value]);

        try {
            app(FetchGoodsReceipt::class)->execute('DO/2608/00232', $branchCId, 'BRC');
            $this->fail('Seharusnya ditolak karena tanpa alokasi cabang.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('do_no', $e->errors());
        }

        tenancy()->end();
    }

    public function test_verify_scan_and_qty_update(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-FETCH-004');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-FETCH-004', 'qty' => 30, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00233', $this->doPayload('DO/2608/00233', $poNumber, [
            $this->doProduct('LSBG20083', 'PAA-FETCH-004', 'Sarung D', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '20'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00233', $branchBId, 'BRB');

        try {
            app(VerifyBundleBarcode::class)->execute($grn->id, 'SALAH123', $branchBId);
            $this->fail('Barcode asing seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('barcode', $e->errors());
        }

        $items = app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20083', $branchBId);
        $this->assertCount(2, $items);
        $this->assertTrue(collect($items)->every(fn ($i) => $i->isVerified()));

        // Scan ganda idempoten.
        $again = app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20083', $branchBId);
        $this->assertTrue(collect($again)->every(fn ($i) => $i->isVerified()));

        // Qty melebihi DO ditolak; qty valid menjumlah ke bundle.
        try {
            app(UpdateReceivedQty::class)->updateItem((int) $grn->items[0]->id, 99, $branchBId);
            $this->fail('Qty over-DO seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qty_received', $e->errors());
        }

        app(UpdateReceivedQty::class)->updateItem((int) $grn->items[0]->id, 8, $branchBId);
        $bundleTotal = (float) GoodsReceiptItem::where('goods_receipt_id', $grn->id)
            ->where('supplier_barcode', 'LSBG20083')
            ->sum('qty_received');
        $this->assertSame(28.0, $bundleTotal);

        tenancy()->end();
    }

    public function test_submit_blocked_until_scan_and_confirm_without_touching_stock(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-FETCH-005');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-FETCH-005', 'qty' => 30, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00234', $this->doPayload('DO/2608/00234', $poNumber, [
            $this->doProduct('LSBG20084', 'PAA-FETCH-005', 'Sarung E', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '20'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00234', $branchBId, 'BRB');

        // Submit diblokir: belum scan (mapping sudah otomatis).
        try {
            app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);
            $this->fail('Submit seharusnya diblokir.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20084', $branchBId);

        // Submit diblokir: belum konfirmasi hitung fisik.
        try {
            app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);
            $this->fail('Submit seharusnya diblokir tanpa konfirmasi fisik.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        $this->confirmAllBundles($grn->id, $branchBId);

        $stockBefore = (float) (DB::table('stock_balances')->sum('qty_on_hand') ?? 0);

        $submitted = app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);

        $this->assertSame(GoodsReceiptStatus::Submitted, $submitted->status);
        $this->assertSame($stockBefore, (float) (DB::table('stock_balances')->sum('qty_on_hand') ?? 0));
        $this->assertSame(0.0, (float) DB::table('purchase_order_items')->where('purchase_order_id', $poId)->sum('qty_received'));

        tenancy()->end();
    }

    public function test_approve_posts_ho_stock_and_transfers_sync(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-APPR-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-APPR-001', 'qty' => 30, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00240', $this->doPayload('DO/2608/00240', $poNumber, [
            $this->doProduct('LSBG20090', 'PAA-APPR-001', 'Sarung F', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '20'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00240', $branchBId, 'BRB');
        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20090', $branchBId);
        $this->confirmAllBundles($grn->id, $branchBId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);

        $approved = app(ApproveGoodsReceipt::class)->execute($grn->id, 1);

        $this->assertSame(GoodsReceiptStatus::Approved, $approved->status);

        // Transfer synchronous: HO kosong, cabang penuh, tanpa antrean.
        $hqWarehouseId = DB::table('warehouses')
            ->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $this->assertSame(0.0, (float) DB::table('stock_balances')->where('warehouse_id', $hqWarehouseId)->sum('qty_on_hand'));

        $branchWarehouseId = DB::table('warehouses')
            ->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $this->assertSame(30.0, (float) DB::table('stock_balances')->where('warehouse_id', $branchWarehouseId)->sum('qty_on_hand'));

        $this->assertNotNull($approved->fresh()->transferred_at);

        $this->assertSame(
            PurchaseOrderStatus::Received->value,
            DB::table('purchase_orders')->where('id', $poId)->value('status')
        );

        tenancy()->end();
    }

    public function test_auto_transfer_records_number_and_source_grn(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        $grn = $this->driveGrnToSubmitted(
            $hqBranchId, $branchBId, 'PAA-SRC-001', 'DO/2608/00310', 'LSBG20210'
        );

        $approved = app(ApproveGoodsReceipt::class)->execute($grn->id, 1);

        $branchWarehouseId = DB::table('warehouses')
            ->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $transfer = DB::table('stock_transfers')
            ->where('to_warehouse_id', $branchWarehouseId)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($transfer);
        $this->assertSame('TRF/'.now()->format('Ymd').'/001', (string) $transfer->number);
        $this->assertSame(GoodsReceipt::class, $transfer->source_type);
        $this->assertSame($approved->id, (int) $transfer->source_id);

        tenancy()->end();
    }

    public function test_transfer_moves_stock_to_branch_and_is_idempotent(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-APPR-002');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-APPR-002', 'qty' => 30, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00241', $this->doPayload('DO/2608/00241', $poNumber, [
            $this->doProduct('LSBG20091', 'PAA-APPR-002', 'Sarung G', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '20'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00241', $branchBId, 'BRB');
        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20091', $branchBId);
        $this->confirmAllBundles($grn->id, $branchBId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);

        // Approve synchronous langsung menyelesaikan transfer.
        $approved = app(ApproveGoodsReceipt::class)->execute($grn->id, 1);
        $this->assertNotNull($approved->fresh()->transferred_at);

        // Stok HO habis terkirim, stok cabang (varian mirror) bertambah penuh.
        $hqWarehouseId = DB::table('warehouses')
            ->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $this->assertSame(0.0, (float) DB::table('stock_balances')->where('warehouse_id', $hqWarehouseId)->sum('qty_on_hand'));

        $blueMirrorId = app(FindVariantBySkuAndColor::class)
            ->execute('PAA-APPR-002', 'NAVY BIRU PRUSI', $branchBId)?->id;
        $this->assertNotNull($blueMirrorId);

        $branchWarehouseId = DB::table('warehouses')
            ->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $this->assertSame(30.0, (float) DB::table('stock_balances')->where('warehouse_id', $branchWarehouseId)->sum('qty_on_hand'));

        $this->assertSame(1, (int) DB::table('stock_transfers')
            ->where('from_warehouse_id', $hqWarehouseId)
            ->where('to_warehouse_id', $branchWarehouseId)
            ->count());
        $this->assertSame(
            'received',
            DB::table('stock_transfers')->where('from_warehouse_id', $hqWarehouseId)->value('status')
        );

        // Jalan kedua manual (use-case sync): tidak duplikat (idempoten).
        app(TransferApprovedGrn::class)->execute($approved->id, 1);
        $this->assertSame(1, (int) DB::table('stock_transfers')
            ->where('from_warehouse_id', $hqWarehouseId)
            ->where('to_warehouse_id', $branchWarehouseId)
            ->count());

        tenancy()->end();
    }

    public function test_approve_rejects_qty_exceeding_po_remaining_across_colors(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-OVER-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-OVER-001', 'qty' => 30, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        // DO 40 (20+20) melebihi sisa PO 30: warna kedua tidak boleh
        // "melihat" slot penuh yang sama dengan warna pertama.
        $this->fakeDo->addResponse('DO/2608/00290', $this->doPayload('DO/2608/00290', $poNumber, [
            $this->doProduct('LSBG20120', 'PAA-OVER-001', 'Sarung P', [
                ['HITAM MERAH PINK', '20'],
                ['NAVY BIRU PRUSI', '20'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00290', $branchBId, 'BRB');
        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20120', $branchBId);
        $this->confirmAllBundles($grn->id, $branchBId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);

        try {
            app(ApproveGoodsReceipt::class)->execute($grn->id, 1);
            $this->fail('Alokasi melebihi sisa PO seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        $this->assertSame(GoodsReceiptStatus::Submitted, $grn->fresh()->status);
        $this->assertSame(0.0, (float) (DB::table('stock_balances')->sum('qty_on_hand') ?? 0));

        tenancy()->end();
    }

    public function test_approve_rejects_sku_without_po_allocation(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-NOMATCH-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-NOMATCH-001', 'qty' => 30, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        // DO berisi prd_code yang tidak ada di PO sama sekali.
        $this->fakeDo->addResponse('DO/2608/00296', $this->doPayload('DO/2608/00296', $poNumber, [
            $this->doProduct('LSBG20140', 'PAA-OTHER-999', 'Sarung asing', [
                ['HITAM', '10'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00296', $branchBId, 'BRB');
        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20140', $branchBId);
        $this->confirmAllBundles($grn->id, $branchBId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);

        try {
            app(ApproveGoodsReceipt::class)->execute($grn->id, 1);
            $this->fail('SKU tanpa alokasi PO seharusnya ditolak dengan pesan khusus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
            $this->assertStringContainsString(
                'Tidak ada baris PO',
                (string) $e->errors()['grn'][0]
            );
        }

        tenancy()->end();
    }

    public function test_second_approve_is_rejected_and_posts_only_once(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-APPR-010');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-APPR-010', 'qty' => 10, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00291', $this->doPayload('DO/2608/00291', $poNumber, [
            $this->doProduct('LSBG20121', 'PAA-APPR-010', 'Sarung Q', [
                ['HITAM MERAH PINK', '10'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00291', $branchBId, 'BRB');
        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20121', $branchBId);
        $this->confirmAllBundles($grn->id, $branchBId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);

        app(ApproveGoodsReceipt::class)->execute($grn->id, 1);

        try {
            app(ApproveGoodsReceipt::class)->execute($grn->id, 1);
            $this->fail('Approve kedua seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        // Stok hanya diposting sekali (10 dikirim ke cabang, HO kosong).
        $branchWarehouseId = DB::table('warehouses')
            ->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $this->assertSame(10.0, (float) DB::table('stock_balances')->where('warehouse_id', $branchWarehouseId)->sum('qty_on_hand'));

        tenancy()->end();
    }

    public function test_reject_returns_to_branch_without_touching_stock(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        $grn = $this->driveGrnToSubmitted(
            $hqBranchId, $branchBId, 'PAA-REJ-001', 'DO/2608/00320', 'LSBG20220'
        );

        // Alasan wajib.
        try {
            app(RejectGoodsReceipt::class)->execute($grn->id, 1, '  ');
            $this->fail('Reject tanpa alasan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $rejected = app(RejectGoodsReceipt::class)->execute($grn->id, 1, 'Foto surat jalan buram, kirim ulang.');
        $this->assertSame(GoodsReceiptStatus::Rejected, $rejected->status);
        $this->assertSame('Foto surat jalan buram, kirim ulang.', $rejected->rejection_reason);

        // Tidak ada stok atau transfer yang terbentuk.
        $this->assertSame(0.0, (float) DB::table('stock_balances')->sum('qty_on_hand'));
        $this->assertSame(0, (int) DB::table('stock_transfers')->count());

        // Yang ditolak tidak bisa di-approve maupun di-reject ulang.
        try {
            app(ApproveGoodsReceipt::class)->execute($grn->id, 1);
            $this->fail('Approve atas yang ditolak seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        try {
            app(RejectGoodsReceipt::class)->execute($grn->id, 1, 'Lagi.');
            $this->fail('Reject ulang seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        tenancy()->end();
    }

    public function test_revise_resubmit_and_approve_after_reject(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $branchCId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        $grn = $this->driveGrnToSubmitted(
            $hqBranchId, $branchBId, 'PAA-REJ-002', 'DO/2608/00321', 'LSBG20221'
        );
        app(RejectGoodsReceipt::class)->execute($grn->id, 1, 'Qty fisik diragukan.');

        // Cabang lain tidak bisa revise.
        try {
            app(ReviseGoodsReceipt::class)->execute($grn->id, $branchCId);
            $this->fail('Revise cabang lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        // Revise → draft, alasan tetap tercatat.
        $revised = app(ReviseGoodsReceipt::class)->execute($grn->id, $branchBId);
        $this->assertSame(GoodsReceiptStatus::Draft, $revised->status);
        $this->assertSame('Qty fisik diragukan.', $revised->rejection_reason);

        // Tidak bisa revise dua kali.
        try {
            app(ReviseGoodsReceipt::class)->execute($grn->id, $branchBId);
            $this->fail('Revise atas draft seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        // Koreksi lalu kirim ulang → approve sukses seperti biasa.
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);
        $approved = app(ApproveGoodsReceipt::class)->execute($grn->id, 1);
        $this->assertSame(GoodsReceiptStatus::Approved, $approved->status);

        tenancy()->end();
    }

    public function test_fetch_rejects_do_for_different_mapped_customer(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-CONF-010');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-CONF-010', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        // Cabang B ter-mapping CUST B; DO ber-cust_name CUST X bukan untuknya.
        $this->fakeDo->addResponse('DO/2608/00292', $this->doPayload('DO/2608/00292', $poNumber, [
            $this->doProduct('LSBG20122', 'PAA-CONF-010', 'Sarung R', []),
        ], 'CUST X'));

        try {
            app(FetchGoodsReceipt::class)->execute('DO/2608/00292', $branchBId, 'BRB');
            $this->fail('DO customer lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('do_no', $e->errors());
        }

        $this->assertSame('CUST B', DB::table('branches')->where('id', $branchBId)->value('supplier_customer_name'));
        $this->assertSame(0, (int) DB::table('goods_receipts')->where('supplier_do_no', 'DO/2608/00292')->count());

        tenancy()->end();
    }

    public function test_fetch_rejects_customer_mapping_owned_by_other_branch(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $branchCId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-CONF-011');
        $poId = $this->createSentPo($hqBranchId, $branchCId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-CONF-011', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        // cust_name CUST B sudah dipakai cabang B; cabang C tidak boleh mengikatnya.
        $this->fakeDo->addResponse('DO/2608/00293', $this->doPayload('DO/2608/00293', $poNumber, [
            $this->doProduct('LSBG20123', 'PAA-CONF-011', 'Sarung S', []),
        ], 'CUST B'));

        try {
            app(FetchGoodsReceipt::class)->execute('DO/2608/00293', $branchCId, 'BRC');
            $this->fail('Mapping milik cabang lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('do_no', $e->errors());
        }

        $this->assertNull(DB::table('branches')->where('id', $branchCId)->value('supplier_customer_name'));
        $this->assertSame(0, (int) DB::table('goods_receipts')->where('supplier_do_no', 'DO/2608/00293')->count());

        tenancy()->end();
    }

    public function test_approve_completes_transfer_sync_without_error(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        $grn = $this->driveGrnToSubmitted(
            $hqBranchId, $branchBId, 'PAA-FAIL-001', 'DO/2608/00294', 'LSBG20130'
        );

        // Approve synchronous menyelesaikan semuanya tanpa antrean:
        // transfer terbentuk, tercatat received, tidak ada sisa tertahan.
        $approved = app(ApproveGoodsReceipt::class)->execute($grn->id, 1);
        $this->assertSame(GoodsReceiptStatus::Approved, $approved->status);
        $this->assertNotNull($approved->fresh()->transferred_at);
        $this->assertNull($approved->fresh()->transfer_error);
        $this->assertSame(1, (int) DB::table('stock_transfers')->count());
        $this->assertSame(
            'received',
            DB::table('stock_transfers')->orderByDesc('id')->value('status')
        );

        tenancy()->end();
    }

    public function test_hq_fetch_for_hq_allocation_skips_transfer(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        // PO dialokasikan ke HO sendiri: stok tetap di gudang HO.
        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-HQ-001');
        $poId = $this->createSentPo($hqBranchId, $hqBranchId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-HQ-001', 'qty' => 10, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00295', $this->doPayload('DO/2608/00295', $poNumber, [
            $this->doProduct('LSBG20131', 'PAA-HQ-001', 'Sarung HQ', [
                ['HITAM MERAH PINK', '10'],
            ]),
        ], 'CUST HQ'));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00295', $hqBranchId, 'HQ');
        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20131', $hqBranchId);
        $this->confirmAllBundles($grn->id, $hqBranchId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $hqBranchId, 1);

        $approved = app(ApproveGoodsReceipt::class)->execute($grn->id, 1);

        $this->assertSame(GoodsReceiptStatus::Approved, $approved->status);
        $this->assertNotNull($approved->fresh()->transferred_at);
        // Asal = tujuan: tidak ada stock transfer yang terbentuk.
        $this->assertSame(0, (int) DB::table('stock_transfers')->count());

        $hqWarehouseId = DB::table('warehouses')
            ->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $this->assertSame(10.0, (float) DB::table('stock_balances')->where('warehouse_id', $hqWarehouseId)->sum('qty_on_hand'));

        tenancy()->end();
    }

    public function test_branch_user_can_fetch_preview_and_store_grn_via_http(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-HTTP-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-HTTP-001', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00300', $this->doPayload('DO/2608/00300', $poNumber, [
            $this->doProduct('LSBG20200', 'PAA-HTTP-001', 'Sarung HTTP', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '10'],
            ]),
        ]));

        tenancy()->end();

        $branchUser = $this->createBranchUser('httpb', $tenantId, $branchBId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        // Header X-Inertia agar respons berupa JSON page (tanpa render
        // Blade/Vite). Version check Inertia hanya berlaku untuk GET.
        $preview = $this->actingAs($branchUser)->withHeaders(['X-Inertia' => 'true'])->post(
            route('purchasing.grns.fetch'), [
                'do_no' => 'DO/2608/00300',
                'purchase_order_id' => $poId,
                'customer' => 'CUST B',
            ]
        );
        $preview->assertOk();
        $preview->assertJsonPath('component', 'Purchasing/GRNs/preview');
        $preview->assertJsonPath('props.preview.do_no', 'DO/2608/00300');
        $preview->assertJsonPath('props.preview.customer', 'CUST B');
        $preview->assertJsonPath('props.preview.po_number', $poNumber);

        $store = $this->actingAs($branchUser)->post(route('purchasing.grns.store'), [
            'do_no' => 'DO/2608/00300',
            'purchase_order_id' => $poId,
            'customer' => 'CUST B',
        ]);
        $store->assertRedirect();

        tenancy()->initialize($tenantId);
        $grn = GoodsReceipt::where('supplier_do_no', 'DO/2608/00300')->first();
        $this->assertNotNull($grn);
        $this->assertSame($branchBId, (int) $grn->branch_id);
        $this->assertCount(2, $grn->items);
        tenancy()->end();
    }

    public function test_branch_user_cannot_view_other_branch_grn_via_http(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $branchCId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-HTTP-002');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-HTTP-002', 'qty' => 10, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00301', $this->doPayload('DO/2608/00301', $poNumber, [
            $this->doProduct('LSBG20201', 'PAA-HTTP-002', 'Sarung U', []),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00301', $branchBId, 'BRB');

        tenancy()->end();

        $userC = $this->createBranchUser('httpc', $tenantId, $branchCId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchCId]);

        $this->actingAs($userC)
            ->get(route('purchasing.grns.show', $grn->id))
            ->assertForbidden();
    }

    public function test_non_hq_user_cannot_approve_via_http(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        $grn = $this->driveGrnToSubmitted(
            $hqBranchId, $branchBId, 'PAA-HTTP-003', 'DO/2608/00302', 'LSBG20202'
        );

        tenancy()->end();

        $branchUser = $this->createBranchUser('httpb2', $tenantId, $branchBId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        $this->actingAs($branchUser)
            ->post(route('purchasing.grn-inbox.approve', $grn->id))
            ->assertForbidden();

        tenancy()->initialize($tenantId);
        $this->assertSame(GoodsReceiptStatus::Submitted, $grn->fresh()->status);
        tenancy()->end();
    }

    public function test_hq_user_can_approve_via_http(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        $grn = $this->driveGrnToSubmitted(
            $hqBranchId, $branchBId, 'PAA-HTTP-004', 'DO/2608/00303', 'LSBG20203'
        );

        tenancy()->end();

        $hqUser = $this->createBranchUser('httphq', $tenantId, $hqBranchId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($hqUser)
            ->post(route('purchasing.grn-inbox.approve', $grn->id))
            ->assertRedirect(route('purchasing.grn-inbox.show', $grn->id));

        tenancy()->initialize($tenantId);
        $this->assertSame(GoodsReceiptStatus::Approved, $grn->fresh()->status);
        tenancy()->end();
    }

    public function test_reject_revise_flow_via_http(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        $grn = $this->driveGrnToSubmitted(
            $hqBranchId, $branchBId, 'PAA-HTTP-005', 'DO/2608/00304', 'LSBG20204'
        );

        tenancy()->end();

        // Cabang tidak bisa akses inbox reject.
        $branchUser = $this->createBranchUser('httpb3', $tenantId, $branchBId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        $this->actingAs($branchUser)
            ->post(route('purchasing.grn-inbox.reject', $grn->id), ['reason' => 'X'])
            ->assertForbidden();

        // HO reject tanpa alasan ditolak validasi.
        $hqUser = $this->createBranchUser('httphq2', $tenantId, $hqBranchId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        $this->actingAs($hqUser)
            ->from(route('purchasing.grn-inbox.show', $grn->id))
            ->post(route('purchasing.grn-inbox.reject', $grn->id), [])
            ->assertSessionHasErrors(['reason']);

        // HO reject dengan alasan sukses.
        $this->actingAs($hqUser)
            ->post(route('purchasing.grn-inbox.reject', $grn->id), ['reason' => 'Qty kurang dari DO.'])
            ->assertRedirect(route('purchasing.grn-inbox.show', $grn->id));

        tenancy()->initialize($tenantId);
        $this->assertSame(GoodsReceiptStatus::Rejected, $grn->fresh()->status);
        $this->assertSame('Qty kurang dari DO.', (string) $grn->fresh()->rejection_reason);
        tenancy()->end();

        // Cabang revise kembali ke draft.
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        $this->actingAs($branchUser)
            ->post(route('purchasing.grns.revise', $grn->id))
            ->assertRedirect(route('purchasing.grns.show', $grn->id));

        tenancy()->initialize($tenantId);
        $this->assertSame(GoodsReceiptStatus::Draft, $grn->fresh()->status);
        tenancy()->end();
    }

    public function test_fetch_sends_branch_customer_mapping_to_supplier(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-CUST-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-CUST-001', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00250', $this->doPayload('DO/2608/00250', $poNumber, [
            $this->doProduct('LSBG20095', 'PAA-CUST-001', 'Sarung I', []),
        ]));

        app(FetchGoodsReceipt::class)->execute('DO/2608/00250', $branchBId, 'BRB');

        $this->assertSame('CUST B', $this->fakeDo->lastCustomer);

        tenancy()->end();
    }

    public function test_fetch_without_po_no_uses_manual_po_fallback(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-CUST-002');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-CUST-002', 'qty' => 20, 'price' => 50000],
        ]);
        $otherPoId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-CUST-002', 'qty' => 5, 'price' => 50000],
        ]);
        // Respons supplier saat ini: tanpa po_no.
        $this->fakeDo->addResponse('DO/2608/00251', $this->doPayload('DO/2608/00251', null, [
            $this->doProduct('LSBG20096', 'PAA-CUST-002', 'Sarung J', []),
        ]));

        try {
            app(FetchGoodsReceipt::class)->execute('DO/2608/00251', $branchBId, 'BRB');
            $this->fail('Tanpa po_no dan tanpa PO manual seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('purchase_order_id', $e->errors());
        }

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00251', $branchBId, 'BRB', $poId);
        $this->assertSame($poId, (int) $grn->purchase_order_id);

        // Payload ber-po_no yang beda dengan PO pilihan → ditolak.
        $poNumber = DB::table('purchase_orders')->where('id', $otherPoId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00252', $this->doPayload('DO/2608/00252', $poNumber, [
            $this->doProduct('LSBG20097', 'PAA-CUST-002', 'Sarung J', []),
        ]));

        try {
            app(FetchGoodsReceipt::class)->execute('DO/2608/00252', $branchBId, 'BRB', $poId);
            $this->fail('PO pilihan yang beda dengan po_no DO seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('purchase_order_id', $e->errors());
        }

        tenancy()->end();
    }

    public function test_normalize_rejects_empty_supplier_data(): void
    {
        try {
            HttpSupplierDoClient::normalize('DO-X', []);
            $this->fail('Data kosong seharusnya ditolak sebagai tidak ditemukan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('do_no', $e->errors());
        }
    }

    public function test_preview_does_not_persist_anything(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-PREV-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-PREV-001', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00260', $this->doPayload('DO/2608/00260', $poNumber, [
            $this->doProduct('LSBG20100', 'PAA-PREV-001', 'Sarung L', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '10'],
            ]),
        ]));

        $preview = app(FetchGoodsReceipt::class)->preview('DO/2608/00260', $branchBId, $poId);

        $this->assertNull($preview['existing_id']);
        $this->assertSame($poNumber, $preview['po']->number);
        $this->assertCount(2, $preview['items']);

        // Tidak ada yang tersimpan: GRN, items, maupun mapping cabang.
        DB::table('branches')->where('id', $branchBId)->update(['supplier_customer_name' => 'OTHER']);
        $preview = app(FetchGoodsReceipt::class)->preview('DO/2608/00260', $branchBId, $poId);

        $this->assertSame(0, (int) DB::table('goods_receipts')->count());
        $this->assertSame(0, (int) DB::table('goods_receipt_items')->count());
        $this->assertSame('OTHER', DB::table('branches')->where('id', $branchBId)->value('supplier_customer_name'));

        tenancy()->end();
    }

    public function test_preview_returns_existing_id_without_calling_supplier(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-PREV-002');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-PREV-002', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00261', $this->doPayload('DO/2608/00261', $poNumber, [
            $this->doProduct('LSBG20101', 'PAA-PREV-002', 'Sarung M', []),
        ]));

        $saved = app(FetchGoodsReceipt::class)->execute('DO/2608/00261', $branchBId, 'BRB');
        $preview = app(FetchGoodsReceipt::class)->preview('DO/2608/00261', $branchBId);

        $this->assertSame($saved->id, $preview['existing_id']);

        tenancy()->end();
    }

    public function test_physical_confirm_requires_scan_and_resets_on_qty_change(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-CONF-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-CONF-001', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00270', $this->doPayload('DO/2608/00270', $poNumber, [
            $this->doProduct('LSBG20110', 'PAA-CONF-001', 'Sarung N', [
                ['HITAM MERAH PINK', '10'],
                ['NAVY BIRU PRUSI', '10'],
            ]),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00270', $branchBId, 'BRB');
        $itemId = (int) $grn->items[0]->id;

        // Konfirmasi sebelum scan ditolak.
        try {
            app(ConfirmPhysicalQty::class)->execute($itemId, $branchBId, true);
            $this->fail('Konfirmasi sebelum scan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qty_confirmed', $e->errors());
        }

        app(VerifyBundleBarcode::class)->execute($grn->id, 'LSBG20110', $branchBId);

        $confirmed = app(ConfirmPhysicalQty::class)->execute($itemId, $branchBId, true);
        $this->assertTrue((bool) $confirmed->qty_confirmed);

        // Ubah qty → konfirmasi hangus.
        app(UpdateReceivedQty::class)->updateItem((int) $grn->items[0]->id, 9, $branchBId);
        $this->assertFalse((bool) GoodsReceiptItem::find($itemId)->qty_confirmed);

        try {
            app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);
            $this->fail('Submit seharusnya diblokir setelah qty diubah.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grn', $e->errors());
        }

        tenancy()->end();
    }

    public function test_product_without_color_breakdown_is_single_bundle_item(): void
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-NODET-001');
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-NODET-001', 'qty' => 100, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        // DO supplier tanpa detail_product (seperti BKB120447 asli).
        $this->fakeDo->addResponse('DO/2608/00280', $this->doPayload('DO/2608/00280', $poNumber, [
            $this->doProduct('BKB120447', 'PAA-NODET-001', 'Sarung O', []),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute('DO/2608/00280', $branchBId, 'BRB');

        $this->assertCount(1, $grn->items);
        $this->assertNull($grn->items[0]->color_raw);
        $this->assertSame(100.0, (float) $grn->items[0]->qty_do);
        $this->assertNotNull($grn->items[0]->product_variant_id);

        app(VerifyBundleBarcode::class)->execute($grn->id, 'BKB120447', $branchBId);
        $this->confirmAllBundles($grn->id, $branchBId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);
        app(ApproveGoodsReceipt::class)->execute($grn->id, 1);

        $branchWarehouseId = DB::table('warehouses')
            ->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $this->assertSame(100.0, (float) DB::table('stock_balances')->where('warehouse_id', $branchWarehouseId)->sum('qty_on_hand'));

        tenancy()->end();
    }

    /**
     * @param  list<array{0: string, 1: string}>  $colors
     */
    private function driveGrnToSubmitted(
        int $hqBranchId,
        int $branchBId,
        string $sku,
        string $doNo,
        string $barcode,
        array $colors = [['HITAM MERAH PINK', '10']]
    ): GoodsReceipt {
        [$variantRed] = $this->createColorVariants($hqBranchId, $sku);
        $total = array_sum(array_map(fn ($c) => (float) $c[1], $colors));
        $poId = $this->createSentPo($hqBranchId, $branchBId, [
            ['variant_id' => $variantRed, 'sku' => $sku, 'qty' => $total, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse($doNo, $this->doPayload($doNo, $poNumber, [
            $this->doProduct($barcode, $sku, 'Product '.$sku, $colors),
        ]));

        $grn = app(FetchGoodsReceipt::class)->execute($doNo, $branchBId, 'BRB');
        app(VerifyBundleBarcode::class)->execute($grn->id, $barcode, $branchBId);
        $this->confirmAllBundles($grn->id, $branchBId);
        app(SubmitGoodsReceipt::class)->execute($grn->id, $branchBId, 1);

        return $grn->fresh(['items']);
    }

    private function confirmAllBundles(int $grnId, int $branchId): void
    {
        $grn = GoodsReceipt::with(['items'])->findOrFail($grnId);
        $seen = [];

        foreach ($grn->items as $item) {
            $key = (string) $item->supplier_barcode;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            app(ConfirmPhysicalQty::class)->execute((int) $item->id, $branchId, true);
        }
    }

    private function createBranchUser(string $prefix, string $tenantId, int $branchId): User
    {
        $user = User::factory()->create([
            'email' => $prefix.'_'.uniqid().'@acme.test',
            'role' => 'user',
        ]);
        CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'role' => 'member',
            'branch_id' => $branchId,
            'is_default' => true,
        ]);

        return $user;
    }

    /**
     * @return array{0: string|int, 1: int, 2: int, 3?: int}
     */
    private function seedBasics(): array
    {
        $id = uniqid('grn_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Goods Receipt Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $hqBranchId = (int) DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchBId = (int) DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_'.$id,
            'supplier_customer_name' => 'CUST B',
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchCId = (int) DB::table('branches')->insertGetId([
            'name' => 'Branch C',
            'code' => 'BRC_'.$id,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CreateWarehousesForBranch::class)->ensureForAllBranches();

        tenancy()->end();

        $user = User::factory()->create(['email' => 'member_'.$id.'@acme.test', 'role' => 'user']);
        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'is_default' => true,
        ]);
        foreach ([$hqBranchId, $branchBId, $branchCId] as $bId) {
            DB::table('company_user_branches')->insert([
                'company_user_id' => $companyUser->id,
                'branch_id' => $bId,
            ]);
        }

        return [$tenant->id, $hqBranchId, $branchBId, $branchCId];
    }

    /**
     * Buat 1 varian warna HITAM MERAH PINK. Warna lain sengaja tidak dibuat
     * agar teruji sebagai detail unmapped (atau dipetakan via mapping).
     *
     * @return array{0: int} [redVariantId]
     */
    private function createColorVariants(int $branchId, string $sku): array
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.uniqid(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.uniqid(), 'code' => 'PCS'.uniqid(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'branch_id' => $branchId,
            'code' => $sku,
            'name' => 'Product '.$sku,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $red = (int) DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => $sku,
            'variant_name' => 'Variant HITAM MERAH PINK',
            'attributes' => json_encode(['color' => 'HITAM MERAH PINK']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$red];
    }

    /**
     * @param  list<array{variant_id: int, sku: string, qty: float, price: float}>  $items
     */
    private function createSentPo(
        int $hqBranchId,
        int $destBranchId,
        array $items,
        PurchaseOrderStatus $status = PurchaseOrderStatus::Sent
    ): int {
        $supplierId = (int) DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hqWarehouseId = DB::table('warehouses')
            ->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')
            ->where('branch_id', $destBranchId)->where('warehouse_type', 'regular')->value('id');

        $poId = (int) DB::table('purchase_orders')->insertGetId([
            'number' => 'PO/'.uniqid().'/001',
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'warehouse_id' => $hqWarehouseId,
            'branch_mode' => 'single',
            'status' => $status->value,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'is_tax_inclusive' => false,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($items as $item) {
            DB::table('purchase_order_items')->insert([
                'purchase_order_id' => $poId,
                'destination_branch_id' => $destBranchId,
                'destination_warehouse_id' => $destWarehouseId,
                'product_variant_id' => $item['variant_id'],
                'product_name' => 'Product '.$item['sku'],
                'sku' => $item['sku'],
                'qty_ordered' => $item['qty'],
                'qty_received' => 0,
                'unit_price' => $item['price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $poId;
    }

    public function test_fetch_uses_customer_input_and_binds_mapping(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $branchCId] = $this->seedBasics();
        tenancy()->initialize($tenantId);

        [$variantRed] = $this->createColorVariants($hqBranchId, 'PAA-CUST-003');
        $poId = $this->createSentPo($hqBranchId, $branchCId, [
            ['variant_id' => $variantRed, 'sku' => 'PAA-CUST-003', 'qty' => 20, 'price' => 50000],
        ]);
        $poNumber = DB::table('purchase_orders')->where('id', $poId)->value('number');
        $this->fakeDo->addResponse('DO/2608/00253', $this->doPayload('DO/2608/00253', $poNumber, [
            $this->doProduct('LSBG20098', 'PAA-CUST-003', 'Sarung K', []),
        ], 'AII PALEMBANG'));

        // Cabang C belum punya mapping: customer dari form dipakai untuk request,
        // lalu mapping diikat otomatis dari cust_name DO.
        $grn = app(FetchGoodsReceipt::class)->execute(
            'DO/2608/00253', $branchCId, 'BRC', null, 'AII PALEMBANG'
        );

        $this->assertSame('AII PALEMBANG', $this->fakeDo->lastCustomer);
        $this->assertSame(
            'AII PALEMBANG',
            DB::table('branches')->where('id', $branchCId)->value('supplier_customer_name')
        );
        $this->assertSame($branchCId, (int) $grn->branch_id);

        // Fetch dengan mapping tersimpan: input kosong pakai mapping.
        $this->fakeDo->addResponse('DO/2608/00254', $this->doPayload('DO/2608/00254', $poNumber, [
            $this->doProduct('LSBG20099', 'PAA-CUST-003', 'Sarung K', []),
        ], 'AII PALEMBANG'));
        app(FetchGoodsReceipt::class)->execute('DO/2608/00254', $branchCId, 'BRC');
        $this->assertSame('AII PALEMBANG', $this->fakeDo->lastCustomer);

        tenancy()->end();
    }

    private function doPayload(string $doNo, ?string $poNumber, array $products, string $custName = 'CUST B'): array
    {
        $payload = [
            'do_no' => $doNo,
            'do_date' => '2026-08-14',
            'cust_name' => $custName,
            'driver' => 'RIKI',
            'nopol' => '-',
            'inv_no' => 'SI/2608/00224',
            'transaction_type' => 'KONSINYASI',
            'product' => $products,
        ];

        // po_no null = mensimulasikan respons supplier saat ini (belum ada po_no).
        if ($poNumber !== null) {
            $payload['po_no'] = $poNumber;
        }

        return $payload;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $colors
     */
    private function doProduct(string $barcode, string $prdCode, string $name, array $colors): array
    {
        $details = [];
        foreach ($colors as [$color, $qty]) {
            $details[] = [
                'prd_code' => $prdCode,
                'prd_desc' => $name,
                'prd_type' => 'SARUNG',
                'color' => $color,
                'qty' => $qty,
            ];
        }

        $total = array_sum(array_map(fn ($c) => (float) $c[1], $colors));

        return [
            'barcode' => $barcode,
            'prd_code' => $prdCode,
            'prd_name' => $name,
            'size' => 'DX G',
            'price' => 5000000,
            'qty' => (string) ($total > 0 ? $total : 100),
            'detail_product' => $details,
        ];
    }

    private function cleanupCentralTables(): void
    {
        foreach (['company_user_branches', 'company_users', 'tenants', 'users'] as $table) {
            $exists = DB::selectOne(
                'SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?) AS exists',
                [$table]
            );
            if ($exists && (bool) $exists->exists) {
                DB::table($table)->delete();
            }
        }
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            $safe = str_replace('"', '""', $schemaName);
            DB::statement('DROP SCHEMA IF EXISTS "'.$safe.'" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public', 'information_schema') AND schema_name NOT LIKE 'pg_%'"
            );
            foreach ($schemas as $row) {
                if (str_starts_with($row->schema_name, 'sch_')) {
                    $this->dropSchema($row->schema_name);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
