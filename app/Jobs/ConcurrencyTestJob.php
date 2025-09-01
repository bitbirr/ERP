<?php

namespace App\Jobs;

use App\Application\Services\InventoryService;
use App\Models\Product;
use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConcurrencyTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $productId;
    public $branchId;
    public $workerId;
    public $iteration;

    public function __construct($productId, $branchId, $workerId, $iteration)
    {
        $this->productId = $productId;
        $this->branchId = $branchId;
        $this->workerId = $workerId;
        $this->iteration = $iteration;
    }

    public function handle(InventoryService $inventoryService)
    {
        $product = Product::find($this->productId);
        $branch = Branch::find($this->branchId);

        if (!$product || !$branch) {
            $this->log("Worker {$this->workerId}, Iteration {$this->iteration}: Product or branch not found");
            return;
        }

        try {
            $operation = rand(0, 1) ? 'reserve' : 'issue';
            $quantity = rand(1, 5); // Random quantity 1-5

            $timestamp = now()->toISOString();
            $this->log("Worker {$this->workerId}, Iteration {$this->iteration}: Starting {$operation} of {$quantity} units at {$timestamp}");

            if ($operation === 'reserve') {
                $result = $inventoryService->reserve(
                    $product,
                    $branch,
                    $quantity,
                    "CONCURRENCY_TEST_W{$this->workerId}_I{$this->iteration}"
                );
                $this->log("Worker {$this->workerId}, Iteration {$this->iteration}: Reserve successful - On hand: {$result->on_hand}, Reserved: {$result->reserved}");
            } else {
                $result = $inventoryService->issueStock(
                    $product,
                    $branch,
                    $quantity,
                    "CONCURRENCY_TEST_W{$this->workerId}_I{$this->iteration}"
                );
                $this->log("Worker {$this->workerId}, Iteration {$this->iteration}: Issue successful - On hand: {$result->on_hand}, Reserved: {$result->reserved}");
            }

        } catch (\Exception $e) {
            $this->log("Worker {$this->workerId}, Iteration {$this->iteration}: {$operation} failed - {$e->getMessage()}");
        }

        // Small delay to increase chance of conflicts
        usleep(rand(10000, 50000)); // 10-50ms
    }

    private function log($message)
    {
        $logMessage = '[' . now()->toISOString() . '] ' . $message . PHP_EOL;
        file_put_contents(storage_path('logs/concurrency_test.log'), $logMessage, FILE_APPEND);
    }
}