<?php

namespace App\Console\Commands;

use App\Application\Services\InventoryService;
use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Jobs\ConcurrencyTestJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class ConcurrencyTest extends Command
{
    protected $signature = 'test:concurrency {--workers=10} {--iterations=5}';
    protected $description = 'Test concurrency for inventory reserve and issue operations using queue jobs';

    private $inventoryService;
    private $product;
    private $branch;
    private $logFile = 'concurrency_test.log';

    public function __construct(InventoryService $inventoryService)
    {
        parent::__construct();
        $this->inventoryService = $inventoryService;
    }

    public function handle()
    {
        $workers = (int) $this->option('workers');
        $iterations = (int) $this->option('iterations');

        $this->info("Starting concurrency test with {$workers} workers, {$iterations} iterations each");

        // Clear log file
        file_put_contents(storage_path('logs/' . $this->logFile), '');

        // Setup test data
        $this->setupTestData();

        // Get initial stock
        $initialStock = $this->getCurrentStock();
        $this->info("Initial stock: {$initialStock['on_hand']} on hand, {$initialStock['reserved']} reserved");

        // Dispatch concurrent jobs
        $this->dispatchConcurrentJobs($workers, $iterations);

        // Wait for jobs to complete (simulate waiting)
        $this->info('Waiting for jobs to complete...');
        sleep(5); // Give time for jobs to process

        // Verify results
        $this->verifyResults($initialStock);

        $this->info('Concurrency test completed');
    }

    private function setupTestData()
    {
        $this->product = Product::first() ?? Product::factory()->create(['name' => 'Test Product']);
        $this->branch = Branch::first() ?? Branch::factory()->create(['name' => 'Test Branch']);

        // Set initial stock to 50
        $this->inventoryService->openingBalance($this->product, $this->branch, 50);
        $this->log("Setup: Created product {$this->product->id} and branch {$this->branch->id} with 50 units");
    }

    private function dispatchConcurrentJobs($workers, $iterations)
    {
        for ($workerId = 0; $workerId < $workers; $workerId++) {
            for ($iteration = 0; $iteration < $iterations; $iteration++) {
                ConcurrencyTestJob::dispatch(
                    $this->product->id,
                    $this->branch->id,
                    $workerId,
                    $iteration
                );
            }
        }
        $this->info("Dispatched " . ($workers * $iterations) . " jobs to queue");
    }

    private function getCurrentStock()
    {
        $item = InventoryItem::where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->first();

        return [
            'on_hand' => $item ? $item->on_hand : 0,
            'reserved' => $item ? $item->reserved : 0,
        ];
    }

    private function verifyResults($initialStock)
    {
        $finalStock = $this->getCurrentStock();
        $this->info("Final stock: {$finalStock['on_hand']} on hand, {$finalStock['reserved']} reserved");

        // Calculate expected totals
        $totalMovements = DB::table('stock_movements')
            ->where('product_id', $this->product->id)
            ->where('branch_id', $this->branch->id)
            ->where('ref', 'like', 'CONCURRENCY_TEST_%')
            ->get();

        $expectedOnHand = $initialStock['on_hand'];
        $expectedReserved = $initialStock['reserved'];

        foreach ($totalMovements as $movement) {
            if ($movement->type === 'RESERVE') {
                $expectedReserved += $movement->qty;
            } elseif ($movement->type === 'ISSUE') {
                $expectedOnHand -= $movement->qty;
            }
        }

        $this->info("Expected: {$expectedOnHand} on hand, {$expectedReserved} reserved");

        // Check for consistency
        if ($finalStock['on_hand'] === $expectedOnHand && $finalStock['reserved'] === $expectedReserved) {
            $this->info('✓ Stock levels are consistent');
        } else {
            $this->error('✗ Stock levels are inconsistent!');
        }

        // Check for negative values
        if ($finalStock['on_hand'] >= 0 && $finalStock['reserved'] >= 0) {
            $this->info('✓ No negative stock values');
        } else {
            $this->error('✗ Negative stock values detected!');
        }

        // Check available stock
        $available = $finalStock['on_hand'] - $finalStock['reserved'];
        if ($available >= 0) {
            $this->info('✓ Available stock is non-negative');
        } else {
            $this->error('✗ Available stock is negative!');
        }

        $this->info("Total movements processed: " . $totalMovements->count());
    }

    private function log($message)
    {
        $logMessage = '[' . now()->toISOString() . '] ' . $message . PHP_EOL;
        file_put_contents(storage_path('logs/' . $this->logFile), $logMessage, FILE_APPEND);
        $this->line($message);
    }
}