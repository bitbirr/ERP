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

        // Define opening balances per branch (physical and digital stock)
        $openingBalances = [
            'main' => [
                'SIM-PREP-4G' => 500,
                'VCH-100' => 1000,
                'EVD-TOPUP' => 0, // zero-stock item
                'AIR-ET' => 200,
                'TB-CASHIN' => 0, // zero-stock item
                'YIM-SVC' => 300,
            ],
            'hamada' => [
                'SIM-PREP-4G' => 300,
                'VCH-100' => 0, // zero-stock item
                'EVD-TOPUP' => 150,
                'AIR-ET' => 100,
                'TB-CASHIN' => 250,
                'YIM-SVC' => 0, // zero-stock item
            ],
            'chinaksen' => [
                'SIM-PREP-4G' => 0, // zero-stock item
                'VCH-100' => 200,
                'EVD-TOPUP' => 100,
                'AIR-ET' => 0, // zero-stock item
                'TB-CASHIN' => 150,
                'YIM-SVC' => 400,
            ],
        ];

        foreach ($products as $product) {
            foreach ($branches as $branch) {
                $branchCode = $branch->code;
                $productCode = $product->code;

                // Get opening balance for this product/branch, default to random if not specified
                $initialStock = $openingBalances[$branchCode][$productCode] ?? rand(0, 50);

                // Create inventory item with initial stock
                $inventoryItem = InventoryItem::firstOrCreate(
                    ['product_id' => $product->id, 'branch_id' => $branch->id],
                    [
                        'on_hand' => $initialStock,
                        'reserved' => 0,
                    ]
                );

                // Create stock movement for initial stock in (only if stock > 0)
                if ($initialStock > 0) {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'branch_id' => $branch->id,
                        'qty' => $initialStock,
                        'type' => 'OPENING',
                        'ref' => 'OPENING_BALANCE_' . $product->code . '_' . $branch->code,
                        'meta' => ['reason' => 'Opening balance seeding'],
                        'created_by' => $adminUser?->id,
                    ]);
                }
            }
        }

        $this->command->info('Inventory seeded with opening balances including zero-stock items.');
    }
}