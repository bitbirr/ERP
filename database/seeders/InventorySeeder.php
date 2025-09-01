<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $branches = Branch::all();
        $adminUser = User::where('email', 'admin@example.com')->first();

        if ($products->isEmpty() || $branches->isEmpty()) {
            $this->command->warn('No products or branches found. Skipping inventory seeding.');
            return;
        }

        foreach ($products as $product) {
            foreach ($branches as $branch) {
                // Create inventory item with initial stock
                $initialStock = rand(10, 100);
                $inventoryItem = InventoryItem::firstOrCreate(
                    ['product_id' => $product->id, 'branch_id' => $branch->id],
                    [
                        'on_hand' => $initialStock,
                        'reserved' => 0,
                    ]
                );

                // Create stock movement for initial stock in
                StockMovement::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'qty' => $initialStock,
                    'type' => 'OPENING',
                    'ref' => 'INITIAL_STOCK_' . $product->code . '_' . $branch->code,
                    'meta' => ['reason' => 'Initial stock seeding'],
                    'created_by' => $adminUser?->id,
                ]);
            }
        }

        $this->command->info('Inventory seeded with initial stock.');
    }
}