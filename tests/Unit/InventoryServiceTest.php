<?php

namespace Tests\Unit;

use App\Application\Services\InventoryService;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_opening_balance()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $qty = 100;

        $item = $this->service->openingBalance($product, $branch, $qty);

        $this->assertInstanceOf(InventoryItem::class, $item);
        $this->assertEquals($qty, $item->on_hand);
        $this->assertEquals(0, $item->reserved);
        $this->assertEquals($product->id, $item->product_id);
        $this->assertEquals($branch->id, $item->branch_id);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'OPENING')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals($qty, $movement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_existing_opening_balance()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        // First opening
        $this->service->openingBalance($product, $branch, 50);
        // Second opening should add to it
        $item = $this->service->openingBalance($product, $branch, 30);

        $this->assertEquals(80, $item->on_hand);

        $movements = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'OPENING')
            ->get();
        $this->assertCount(2, $movements);
        $this->assertEquals(50, $movements[0]->qty);
        $this->assertEquals(30, $movements[1]->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_zero_qty_opening_balance()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $item = $this->service->openingBalance($product, $branch, 0);

        $this->assertEquals(0, $item->on_hand);
        $this->assertEquals(0, $item->reserved);

        // No movement created for zero qty
        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'OPENING')
            ->first();
        $this->assertNull($movement);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_negative_qty_opening_balance()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, -10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_receive_stock_transactionally()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $qty = 50;

        $item = $this->service->receiveStock($product, $branch, $qty);

        $this->assertInstanceOf(InventoryItem::class, $item);
        $this->assertEquals($qty, $item->on_hand);
        $this->assertEquals(0, $item->reserved);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'RECEIVE')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals($qty, $movement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_receive_stock_on_existing_item()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 20);
        $item = $this->service->receiveStock($product, $branch, 30);

        $this->assertEquals(50, $item->on_hand);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_zero_qty_receive()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $item = $this->service->receiveStock($product, $branch, 0);

        $this->assertEquals(0, $item->on_hand);

        // No movement created for zero qty
        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'RECEIVE')
            ->first();
        $this->assertNull($movement);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_negative_qty_receive()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->receiveStock($product, $branch, -5);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_reserve_stock()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $qty = 30;

        $this->service->openingBalance($product, $branch, 100);
        $item = $this->service->reserve($product, $branch, $qty);

        $this->assertInstanceOf(InventoryItem::class, $item);
        $this->assertEquals(100, $item->on_hand);
        $this->assertEquals($qty, $item->reserved);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'RESERVE')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals($qty, $movement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_zero_qty_reserve()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->reserve($product, $branch, 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_negative_qty_reserve()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->reserve($product, $branch, -10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_reserve_partial_stock()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 50);
        $item = $this->service->reserve($product, $branch, 20);

        $this->assertEquals(50, $item->on_hand);
        $this->assertEquals(20, $item->reserved);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_multiple_reserves()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->reserve($product, $branch, 20);
        $item = $this->service->reserve($product, $branch, 30);

        $this->assertEquals(50, $item->reserved);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_unreserve_stock()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $qty = 20;

        $this->service->openingBalance($product, $branch, 100);
        $this->service->reserve($product, $branch, $qty);
        $item = $this->service->unreserve($product, $branch, $qty);

        $this->assertInstanceOf(InventoryItem::class, $item);
        $this->assertEquals(100, $item->on_hand);
        $this->assertEquals(0, $item->reserved);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'UNRESERVE')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals($qty, $movement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_zero_qty_unreserve()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->reserve($product, $branch, 10);
        $this->service->unreserve($product, $branch, 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_negative_qty_unreserve()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->reserve($product, $branch, 10);
        $this->service->unreserve($product, $branch, -5);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_partial_unreserve()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->reserve($product, $branch, 50);
        $item = $this->service->unreserve($product, $branch, 20);

        $this->assertEquals(30, $item->reserved);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_transfer_stock_between_branches_transactionally()
    {
        $product = Product::factory()->create();
        $from = Branch::factory()->create();
        $to = Branch::factory()->create();
        $qty = 10;

        $this->service->openingBalance($product, $from, 50);

        $this->service->transfer($product, $from, $to, $qty);

        $fromItem = InventoryItem::where('product_id', $product->id)->where('branch_id', $from->id)->first();
        $toItem = InventoryItem::where('product_id', $product->id)->where('branch_id', $to->id)->first();

        $this->assertEquals(40, $fromItem->on_hand);
        $this->assertEquals(0, $fromItem->reserved);
        $this->assertEquals(10, $toItem->on_hand);
        $this->assertEquals(0, $toItem->reserved);

        $outMovement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $from->id)
            ->where('type', 'TRANSFER_OUT')
            ->first();
        $inMovement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $to->id)
            ->where('type', 'TRANSFER_IN')
            ->first();

        $this->assertNotNull($outMovement);
        $this->assertNotNull($inMovement);
        $this->assertEquals($qty, $outMovement->qty);
        $this->assertEquals($qty, $inMovement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_if_not_enough_stock_to_reserve()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 10);
        $this->service->reserve($product, $branch, 20);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_if_not_enough_reserved_to_unreserve()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 10);
        $this->service->reserve($product, $branch, 5);
        $this->service->unreserve($product, $branch, 10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_if_not_enough_stock_to_transfer()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $from = Branch::factory()->create();
        $to = Branch::factory()->create();

        $this->service->openingBalance($product, $from, 5);
        $this->service->transfer($product, $from, $to, 10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rolls_back_transaction_on_error()
    {
        $product = Product::factory()->create();
        $from = Branch::factory()->create();
        $to = Branch::factory()->create();

        $this->service->openingBalance($product, $from, 10);

        try {
            $this->service->transfer($product, $from, $to, 20); // Should throw
        } catch (HttpException $e) {
            // After exception, DB should be unchanged
            $fromItem = InventoryItem::where('product_id', $product->id)->where('branch_id', $from->id)->first();
            $toItem = InventoryItem::where('product_id', $product->id)->where('branch_id', $to->id)->first();

            $this->assertEquals(10, $fromItem->on_hand);
            $this->assertNull($toItem);

            $outMovement = StockMovement::where('product_id', $product->id)
                ->where('branch_id', $from->id)
                ->where('type', 'TRANSFER_OUT')
                ->first();
            $inMovement = StockMovement::where('product_id', $product->id)
                ->where('branch_id', $to->id)
                ->where('type', 'TRANSFER_IN')
                ->first();

            $this->assertNull($outMovement);
            $this->assertNull($inMovement);
            return;
        }
        $this->fail('Expected HttpException was not thrown');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_issue_stock()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $item = $this->service->issueStock($product, $branch, 30);

        $this->assertEquals(70, $item->on_hand);
        $this->assertEquals(0, $item->reserved);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'ISSUE')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-30, $movement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_if_not_enough_stock_to_issue()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 10);
        $this->service->reserve($product, $branch, 5);
        $this->service->issueStock($product, $branch, 10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_negative_qty_issue()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->issueStock($product, $branch, -10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_zero_qty_issue()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);
        $this->service->issueStock($product, $branch, 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_zero_qty_transfer()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $from = Branch::factory()->create();
        $to = Branch::factory()->create();

        $this->service->openingBalance($product, $from, 50);
        $this->service->transfer($product, $from, $to, 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_negative_qty_transfer()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $from = Branch::factory()->create();
        $to = Branch::factory()->create();

        $this->service->openingBalance($product, $from, 50);
        $this->service->transfer($product, $from, $to, -10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_same_branch_transfer()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 50);
        $this->service->transfer($product, $branch, $branch, 10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_adjust_stock_positive()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 50);
        $item = $this->service->adjust($product, $branch, 10);

        $this->assertEquals(60, $item->on_hand);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'ADJUST')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_adjust_stock_negative()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 50);
        $item = $this->service->adjust($product, $branch, -10);

        $this->assertEquals(40, $item->on_hand);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->where('type', 'ADJUST')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-10, $movement->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_adjust_to_negative_stock()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 10);
        $this->service->adjust($product, $branch, -20);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_on_zero_qty_adjust()
    {
        $this->expectException(HttpException::class);
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 50);
        $this->service->adjust($product, $branch, 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_idempotency_for_stock_movements_with_same_ref()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $ref = 'test-ref-123';

        $this->service->openingBalance($product, $branch, 100);

        // First issue with ref
        $item1 = $this->service->issueStock($product, $branch, 10, $ref);
        $this->assertEquals(90, $item1->on_hand);

        // Second issue with same ref - should be idempotent (no duplicate movement)
        $item2 = $this->service->issueStock($product, $branch, 5, $ref);
        $this->assertEquals(90, $item2->on_hand, 'Stock should remain unchanged due to idempotency');

        $movements = StockMovement::where('ref', $ref)->get();
        $this->assertCount(1, $movements, 'Should create only one movement due to idempotency');
        $this->assertEquals(-10, $movements[0]->qty);
        $this->assertEquals('ISSUE', $movements[0]->type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_multiple_stock_movements_with_same_ref_different_types()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $ref = 'test-ref-456';

        $this->service->openingBalance($product, $branch, 100);

        // Issue stock
        $this->service->issueStock($product, $branch, 10, $ref);
        // Receive stock with same ref
        $this->service->receiveStock($product, $branch, 5, $ref);

        $movements = StockMovement::where('ref', $ref)->get();
        $this->assertCount(2, $movements);
        $this->assertEquals(-10, $movements[0]->qty);
        $this->assertEquals(5, $movements[1]->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_concurrent_requests_with_same_ref_idempotently()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $ref = 'concurrent-ref-789';

        $this->service->openingBalance($product, $branch, 100);

        // Simulate concurrent requests by running in separate transactions
        $results = [];

        // Use database transactions to simulate concurrency
        DB::transaction(function () use ($product, $branch, $ref, &$results) {
            $result1 = $this->service->issueStock($product, $branch, 10, $ref);
            $results[] = $result1;
        });

        DB::transaction(function () use ($product, $branch, $ref, &$results) {
            $result2 = $this->service->issueStock($product, $branch, 5, $ref);
            $results[] = $result2;
        });

        $this->assertCount(2, $results);
        $movements = StockMovement::where('ref', $ref)->get();
        $this->assertCount(1, $movements, 'Should create only one movement due to idempotency');
        $this->assertEquals(-10, $movements[0]->qty);

        // Both results should reflect the same final state
        $this->assertEquals(90, $results[0]->on_hand);
        $this->assertEquals(90, $results[1]->on_hand);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_inventory_consistency_with_duplicate_refs()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $ref = 'consistency-ref-101';

        $this->service->openingBalance($product, $branch, 100);

        // Multiple operations with same ref - only first of each type should take effect
        $this->service->issueStock($product, $branch, 10, $ref);
        $this->service->issueStock($product, $branch, 5, $ref); // Should be ignored
        $this->service->receiveStock($product, $branch, 3, $ref); // Different type, should create new movement

        $item = InventoryItem::where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->first();

        // Expected: 100 - 10 + 3 = 93 (second issue ignored due to idempotency)
        $this->assertEquals(93, $item->on_hand);

        $movements = StockMovement::where('ref', $ref)->get();
        $this->assertCount(2, $movements); // ISSUE and RECEIVE, but only one ISSUE
        $this->assertEquals(-10, $movements[0]->qty);
        $this->assertEquals(3, $movements[1]->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_idempotency_for_reserve_operations()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $ref = 'reserve-ref-202';

        $this->service->openingBalance($product, $branch, 100);

        // First reserve
        $item1 = $this->service->reserve($product, $branch, 20, $ref);
        $this->assertEquals(20, $item1->reserved);

        // Second reserve with same ref - should be idempotent
        $item2 = $this->service->reserve($product, $branch, 15, $ref);
        $this->assertEquals(20, $item2->reserved, 'Reserved amount should remain unchanged');

        $movements = StockMovement::where('ref', $ref)->get();
        $this->assertCount(1, $movements);
        $this->assertEquals(20, $movements[0]->qty);
        $this->assertEquals('RESERVE', $movements[0]->type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_idempotency_for_adjust_operations()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();
        $ref = 'adjust-ref-303';

        $this->service->openingBalance($product, $branch, 50);

        // First adjust
        $item1 = $this->service->adjust($product, $branch, 10, $ref);
        $this->assertEquals(60, $item1->on_hand);

        // Second adjust with same ref - should be idempotent
        $item2 = $this->service->adjust($product, $branch, 5, $ref);
        $this->assertEquals(60, $item2->on_hand, 'Stock should remain unchanged');

        $movements = StockMovement::where('ref', $ref)->get();
        $this->assertCount(1, $movements);
        $this->assertEquals(10, $movements[0]->qty);
        $this->assertEquals('ADJUST', $movements[0]->type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_enforces_idempotency_for_transfer_operations()
    {
        $product = Product::factory()->create();
        $fromBranch = Branch::factory()->create();
        $toBranch = Branch::factory()->create();
        $ref = 'transfer-ref-404';

        $this->service->openingBalance($product, $fromBranch, 100);

        // First transfer
        $this->service->transfer($product, $fromBranch, $toBranch, 25, $ref);

        $fromItem = InventoryItem::where('product_id', $product->id)->where('branch_id', $fromBranch->id)->first();
        $toItem = InventoryItem::where('product_id', $product->id)->where('branch_id', $toBranch->id)->first();

        $this->assertEquals(75, $fromItem->on_hand);
        $this->assertEquals(25, $toItem->on_hand);

        // Second transfer with same ref - should be idempotent
        $this->service->transfer($product, $fromBranch, $toBranch, 10, $ref);

        // Stock should remain unchanged
        $fromItem2 = InventoryItem::where('product_id', $product->id)->where('branch_id', $fromBranch->id)->first();
        $toItem2 = InventoryItem::where('product_id', $product->id)->where('branch_id', $toBranch->id)->first();

        $this->assertEquals(75, $fromItem2->on_hand);
        $this->assertEquals(25, $toItem2->on_hand);

        $movements = StockMovement::where('ref', $ref)->get();
        $this->assertCount(2, $movements); // TRANSFER_OUT and TRANSFER_IN
        $this->assertEquals(25, $movements[0]->qty);
        $this->assertEquals(25, $movements[1]->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_different_refs_to_create_separate_movements()
    {
        $product = Product::factory()->create();
        $branch = Branch::factory()->create();

        $this->service->openingBalance($product, $branch, 100);

        // Different refs should create separate movements
        $this->service->issueStock($product, $branch, 10, 'ref-1');
        $this->service->issueStock($product, $branch, 15, 'ref-2');
        $this->service->issueStock($product, $branch, 20, 'ref-1'); // Same as first, should be ignored

        $item = InventoryItem::where('product_id', $product->id)->where('branch_id', $branch->id)->first();
        $this->assertEquals(75, $item->on_hand); // 100 - 10 - 15 = 75

        $movements = StockMovement::where('type', 'ISSUE')->get();
        $this->assertCount(2, $movements);
        $this->assertEquals(-10, $movements[0]->qty);
        $this->assertEquals(-15, $movements[1]->qty);
    }
}