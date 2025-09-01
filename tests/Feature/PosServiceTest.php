<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Receipt;
use App\Models\Product;
use App\Models\Branch;
use App\Models\GlJournal;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Application\Services\PosService;
use App\Application\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PosServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $posService;
    protected $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryService = new InventoryService();
        $this->posService = $this->createPosService($this->inventoryService, $this->getMockAuditLogger());
    }

    /** @test */
    public function it_processes_a_receipt_correctly()
    {
        // Create required GL accounts for POS posting
        \App\Models\GlAccount::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'code' => '1001',
            'name' => 'Cash Account',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'level' => 1,
            'is_postable' => true,
            'status' => 'ACTIVE',
        ]);

        \App\Models\GlAccount::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'code' => '4000',
            'name' => 'Sales Revenue',
            'type' => 'REVENUE',
            'normal_balance' => 'CREDIT',
            'level' => 1,
            'is_postable' => true,
            'status' => 'ACTIVE',
        ]);

        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        // Create initial stock
        $this->inventoryService->openingBalance($product, $branch, 10);

        $receiptData = [
            'branch_id' => $branch->id,
            'created_by' => $this->user->id,
        ];

        $lineItems = [
            [
                'product' => $product,
                'branch' => $branch,
                'qty' => 2,
                'price' => 100,
                'tax_amount' => 10,
                'discount' => 5,
                'account_id' => 1,
            ],
        ];

        $receipt = $this->posService->processReceipt($receiptData, $lineItems);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertNotNull($receipt->id);
        $this->assertEquals(205, $receipt->grand_total);

        // Refresh from database to ensure it's persisted
        $receipt->refresh();
        $this->assertCount(1, $receipt->lines);

        // Check stock decreased
        $item = InventoryItem::where('product_id', $product->id)->where('branch_id', $branch->id)->first();
        $this->assertEquals(8, $item->on_hand);

        // Check GL journal created
        $journal = GlJournal::where('memo', 'POS Receipt #' . $receipt->number)->first();
        $this->assertNotNull($journal);
        $this->assertEquals(205, $journal->getTotalDebit());

        // Check that receipt lines have stock movement references
        $receipt->refresh();
        foreach ($receipt->lines as $index => $line) {
            $this->assertNotNull($line->stock_movement_ref);
            $this->assertStringStartsWith($receipt->id . '-L', $line->stock_movement_ref);

            // Check that stock movement exists with the correct reference
            $stockMovement = \App\Models\StockMovement::where('ref', $line->stock_movement_ref)->first();
            $this->assertNotNull($stockMovement);
            $this->assertEquals('ISSUE', $stockMovement->type);
            $this->assertEquals($line->qty, abs($stockMovement->qty)); // ISSUE has negative qty
        }
    }

    /** @test */
    public function it_voids_a_receipt_correctly()
    {
        // Create required GL accounts for POS posting
        \App\Models\GlAccount::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'code' => '1001',
            'name' => 'Cash Account',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'level' => 1,
            'is_postable' => true,
            'status' => 'ACTIVE',
        ]);

        \App\Models\GlAccount::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'code' => '4000',
            'name' => 'Sales Revenue',
            'type' => 'REVENUE',
            'normal_balance' => 'CREDIT',
            'level' => 1,
            'is_postable' => true,
            'status' => 'ACTIVE',
        ]);

        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        // Create initial stock
        $this->inventoryService->openingBalance($product, $branch, 10);

        $receiptData = [
            'branch_id' => $branch->id,
            'created_by' => $this->user->id,
        ];

        $lineItems = [
            [
                'product' => $product,
                'branch' => $branch,
                'qty' => 2,
                'price' => 100,
                'tax_amount' => 10,
                'discount' => 5,
                'account_id' => 1,
            ],
        ];

        $receipt = $this->posService->processReceipt($receiptData, $lineItems);
        $receipt->update(['status' => 'POSTED']); // Assume posted

        $voided = $this->posService->voidReceipt($receipt, ['voided_by' => $this->user->id]);

        $this->assertEquals('VOIDED', $voided->status);
        $this->assertNotNull($voided->voided_at);

        // Check stock restored
        $item = InventoryItem::where('product_id', $product->id)->where('branch_id', $branch->id)->first();
        $this->assertEquals(10, $item->on_hand);

        // Check reversing GL journal
        $reversingJournal = GlJournal::where('memo', 'Void POS Receipt #' . $receipt->number)->first();
        $this->assertNotNull($reversingJournal);
    }
}