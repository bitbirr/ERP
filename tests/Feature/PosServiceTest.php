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
        $this->posService = new PosService($this->inventoryService);
    }

    /** @test */
    public function it_processes_a_receipt_correctly()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        // Create initial stock
        $this->inventoryService->openingBalance($product, $branch, 10);

        $receiptData = [
            'branch_id' => $branch->id,
            'created_by' => 1,
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
        $this->assertEquals(205, $receipt->grand_total);
        $this->assertCount(1, $receipt->lines);

        // Check stock decreased
        $item = InventoryItem::where('product_id', $product->id)->where('branch_id', $branch->id)->first();
        $this->assertEquals(8, $item->on_hand);

        // Check GL journal created
        $journal = GlJournal::where('description', 'Receipt #' . $receipt->id)->first();
        $this->assertNotNull($journal);
        $this->assertEquals(205, $journal->total_debit);

        // Check audit log
        $audit = AuditLog::where('action', 'create_receipt')->where('subject_id', $receipt->id)->first();
        $this->assertNotNull($audit);
    }

    /** @test */
    public function it_voids_a_receipt_correctly()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        // Create initial stock
        $this->inventoryService->openingBalance($product, $branch, 10);

        $receiptData = [
            'branch_id' => $branch->id,
            'created_by' => 1,
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
        $receipt->update(['status' => 'posted']); // Assume posted

        $voided = $this->posService->voidReceipt($receipt, ['voided_by' => 1]);

        $this->assertEquals('voided', $voided->status);
        $this->assertNotNull($voided->voided_at);

        // Check stock restored
        $item = InventoryItem::where('product_id', $product->id)->where('branch_id', $branch->id)->first();
        $this->assertEquals(10, $item->on_hand);

        // Check reversing GL journal
        $reversingJournal = GlJournal::where('description', 'Void Receipt #' . $receipt->id)->first();
        $this->assertNotNull($reversingJournal);

        // Check void audit log
        $audit = AuditLog::where('action', 'void_receipt')->where('subject_id', $receipt->id)->first();
        $this->assertNotNull($audit);
    }
}