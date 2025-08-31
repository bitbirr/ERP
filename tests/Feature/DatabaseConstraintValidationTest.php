<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\Receipt;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class DatabaseConstraintValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Branch $branch;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->product = Product::factory()->create();
    }

    /** @test */
    public function it_enforces_non_negative_on_hand_quantity()
    {
        $this->expectException(QueryException::class);

        // Try to insert negative on_hand
        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => -1.0,
            'reserved' => 0.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_enforces_non_negative_reserved_quantity()
    {
        $this->expectException(QueryException::class);

        // Try to insert negative reserved
        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 10.0,
            'reserved' => -1.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_enforces_reserved_less_than_or_equal_to_on_hand()
    {
        $this->expectException(QueryException::class);

        // Try to insert reserved > on_hand
        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 5.0,
            'reserved' => 10.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_allows_zero_values_for_on_hand_and_reserved()
    {
        // Zero values should be allowed
        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 0.0,
            'reserved' => 0.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 0.0,
            'reserved' => 0.0,
        ]);
    }

    /** @test */
    public function it_allows_boundary_condition_reserved_equals_on_hand()
    {
        // reserved = on_hand should be allowed
        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 10.0,
            'reserved' => 10.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 10.0,
            'reserved' => 10.0,
        ]);
    }

    /** @test */
    public function it_enforces_unique_product_codes()
    {
        $this->expectException(QueryException::class);

        // Create first product
        Product::factory()->create(['code' => 'TEST001']);

        // Try to create duplicate code
        Product::factory()->create(['code' => 'TEST001']);
    }

    /** @test */
    public function it_enforces_stock_movement_qty_not_zero()
    {
        $this->expectException(QueryException::class);

        // Try to insert qty = 0
        DB::table('stock_movements')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 0.0,
            'type' => 'RECEIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_allows_negative_qty_for_stock_movements()
    {
        // Negative qty should be allowed (for issues/adjustments)
        DB::table('stock_movements')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => -5.0,
            'type' => 'ISSUE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'qty' => -5.0,
            'type' => 'ISSUE',
        ]);
    }

    /** @test */
    public function it_enforces_valid_stock_movement_type_enum()
    {
        $this->expectException(QueryException::class);

        // Try to insert invalid type
        DB::table('stock_movements')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'qty' => 10.0,
            'type' => 'INVALID_TYPE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_allows_all_valid_stock_movement_types()
    {
        $validTypes = ['OPENING', 'RECEIVE', 'ISSUE', 'RESERVE', 'UNRESERVE', 'TRANSFER_OUT', 'TRANSFER_IN', 'ADJUST'];

        foreach ($validTypes as $type) {
            DB::table('stock_movements')->insert([
                'id' => $this->faker->uuid(),
                'product_id' => $this->product->id,
                'branch_id' => $this->branch->id,
                'qty' => 1.0,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertDatabaseHas('stock_movements', [
                'type' => $type,
            ]);
        }
    }

    /** @test */
    public function it_enforces_valid_receipt_status_enum()
    {
        $this->expectException(QueryException::class);

        // Try to insert invalid status
        DB::table('receipts')->insert([
            'id' => $this->faker->uuid(),
            'branch_id' => $this->branch->id,
            'number' => 'TEST001',
            'status' => 'INVALID_STATUS',
            'subtotal' => 100.00,
            'tax_total' => 0.00,
            'discount_total' => 0.00,
            'grand_total' => 100.00,
            'paid_total' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_allows_all_valid_receipt_statuses()
    {
        $validStatuses = ['DRAFT', 'POSTED', 'VOIDED', 'REFUNDED'];

        foreach ($validStatuses as $status) {
            DB::table('receipts')->insert([
                'id' => $this->faker->uuid(),
                'branch_id' => $this->branch->id,
                'number' => 'TEST' . $status,
                'status' => $status,
                'subtotal' => 100.00,
                'tax_total' => 0.00,
                'discount_total' => 0.00,
                'grand_total' => 100.00,
                'paid_total' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertDatabaseHas('receipts', [
                'status' => $status,
            ]);
        }
    }

    /** @test */
    public function it_enforces_valid_payment_method_enum()
    {
        $this->expectException(QueryException::class);

        // Try to insert invalid payment method
        DB::table('receipts')->insert([
            'id' => $this->faker->uuid(),
            'branch_id' => $this->branch->id,
            'number' => 'TEST002',
            'status' => 'DRAFT',
            'payment_method' => 'INVALID_METHOD',
            'subtotal' => 100.00,
            'tax_total' => 0.00,
            'discount_total' => 0.00,
            'grand_total' => 100.00,
            'paid_total' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function it_allows_all_valid_payment_methods()
    {
        $validMethods = ['CASH', 'CARD', 'MOBILE', 'TRANSFER', 'MIXED'];

        foreach ($validMethods as $method) {
            DB::table('receipts')->insert([
                'id' => $this->faker->uuid(),
                'branch_id' => $this->branch->id,
                'number' => 'TEST' . $method,
                'status' => 'DRAFT',
                'payment_method' => $method,
                'subtotal' => 100.00,
                'tax_total' => 0.00,
                'discount_total' => 0.00,
                'grand_total' => 100.00,
                'paid_total' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->assertDatabaseHas('receipts', [
                'payment_method' => $method,
            ]);
        }
    }

    /** @test */
    public function it_handles_maximum_decimal_values()
    {
        $maxValue = 999999999999.999; // Max for decimal(16,3)

        // Should allow maximum values
        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => $maxValue,
            'reserved' => $maxValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'on_hand' => $maxValue,
            'reserved' => $maxValue,
        ]);
    }

    /** @test */
    public function it_handles_minimum_decimal_values()
    {
        $minValue = 0.001; // Smallest positive value

        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => $minValue,
            'reserved' => $minValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'on_hand' => $minValue,
            'reserved' => $minValue,
        ]);
    }

    /** @test */
    public function it_enforces_unique_inventory_items_per_product_branch()
    {
        $this->expectException(QueryException::class);

        // Create first inventory item
        InventoryItem::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
        ]);

        // Try to create duplicate
        InventoryItem::factory()->create([
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /** @test */
    public function it_enforces_unique_receipt_numbers_per_branch()
    {
        $this->expectException(QueryException::class);

        // Create first receipt
        Receipt::factory()->create([
            'branch_id' => $this->branch->id,
            'number' => 'RCP001',
        ]);

        // Try to create duplicate number for same branch
        Receipt::factory()->create([
            'branch_id' => $this->branch->id,
            'number' => 'RCP001',
        ]);
    }

    /** @test */
    public function it_validates_product_type_against_predefined_values()
    {
        $validTypes = ['YIMULU', 'VOUCHER', 'EVD', 'SIM', 'TELEBIRR', 'E_AIRTIME'];

        // Test valid types
        foreach ($validTypes as $type) {
            $product = Product::factory()->create(['type' => $type]);
            $this->assertEquals($type, $product->type);
        }

        // Test invalid type (should still be allowed at DB level, but we can check application logic)
        $product = Product::factory()->create(['type' => 'INVALID_TYPE']);
        $this->assertDatabaseHas('products', ['type' => 'INVALID_TYPE']);
    }

    /** @test */
    public function it_validates_product_pricing_strategy_against_predefined_values()
    {
        $validStrategies = ['FIXED', 'DISCOUNT', 'EXACT', 'MARKUP'];

        // Test valid strategies
        foreach ($validStrategies as $strategy) {
            $product = Product::factory()->create(['pricing_strategy' => $strategy]);
            $this->assertEquals($strategy, $product->pricing_strategy);
        }

        // Test invalid strategy (should still be allowed at DB level)
        $product = Product::factory()->create(['pricing_strategy' => 'INVALID_STRATEGY']);
        $this->assertDatabaseHas('products', ['pricing_strategy' => 'INVALID_STRATEGY']);
    }

    /** @test */
    public function it_handles_fractional_quantities_with_high_precision()
    {
        $fractionalQty = 0.001; // High precision

        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => $fractionalQty,
            'reserved' => 0.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'on_hand' => $fractionalQty,
        ]);
    }

    /** @test */
    public function it_enforces_boundary_condition_reserved_slightly_greater_than_on_hand()
    {
        $this->expectException(QueryException::class);

        // reserved slightly greater than on_hand
        DB::table('inventory_items')->insert([
            'id' => $this->faker->uuid(),
            'product_id' => $this->product->id,
            'branch_id' => $this->branch->id,
            'on_hand' => 10.0,
            'reserved' => 10.001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}