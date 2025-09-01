<?php

namespace App\Application\Services;

use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Domain\Audit\AuditLogger;

class InventoryService
{
    protected $auditLogger;

    public function __construct(?AuditLogger $auditLogger = null)
    {
        $this->auditLogger = $auditLogger ?? new AuditLogger();
    }
    /**
     * Opening balance: create or update inventory item, increment on_hand, log movement.
     */
    public function openingBalance(Product $product, Branch $branch, float $qty, array $ctx = []): InventoryItem
    {
        if ($qty < 0) {
            throw new HttpException(422, 'Opening balance quantity cannot be negative');
        }

        return DB::transaction(function () use ($product, $branch, $qty, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                $item = InventoryItem::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            $item->on_hand += $qty;
            $item->save();

            if ($qty != 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'qty' => $qty,
                    'type' => 'OPENING',
                    'ref' => $ctx['ref'] ?? null,
                    'meta' => $ctx['meta'] ?? null,
                    'created_by' => $ctx['created_by'] ?? null,
                ]);
            }

            return $item->fresh();
        });
    }

    /**
     * Receive stock: ensure item, increment on_hand, log movement.
     */
    public function receiveStock(Product $product, Branch $branch, float $qty, ?string $ref = null, array $ctx = []): InventoryItem
    {
        if ($qty < 0) {
            throw new HttpException(422, 'Receive quantity cannot be negative');
        }

        return DB::transaction(function () use ($product, $branch, $qty, $ref, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                $item = InventoryItem::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            $item->on_hand += $qty;
            $item->save();

            if ($qty != 0) {
                // Check for idempotency if ref is provided
                if ($ref) {
                    $existingMovement = StockMovement::where('ref', $ref)
                        ->where('product_id', $product->id)
                        ->where('branch_id', $branch->id)
                        ->where('type', 'RECEIVE')
                        ->first();

                    if ($existingMovement) {
                        // Return existing item state without creating duplicate movement
                        return $item->fresh();
                    }
                }

                $movement = StockMovement::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'qty' => $qty,
                    'type' => 'RECEIVE',
                    'ref' => $ref,
                    'meta' => $ctx['meta'] ?? null,
                    'created_by' => $ctx['created_by'] ?? null,
                ]);

                // Audit the stock receive
                $this->auditLogger->log(
                    'inventory.stock.received',
                    $movement,
                    null,
                    $movement->toArray(),
                    array_merge($ctx, [
                        'product_name' => $product->name,
                        'branch_name' => $branch->name,
                        'previous_on_hand' => $item->on_hand - $qty,
                        'new_on_hand' => $item->on_hand,
                        'received_quantity' => $qty,
                    ])
                );
            }

            return $item->fresh();
        });
    }

    /**
     * Reserve stock: validate available, increment reserved, log movement.
     */
    public function reserve(Product $product, Branch $branch, float $qty, ?string $ref = null, array $ctx = []): InventoryItem
    {
        if ($qty <= 0) {
            throw new HttpException(422, 'Reserve quantity must be positive');
        }

        return DB::transaction(function () use ($product, $branch, $qty, $ref, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item || $item->on_hand - $item->reserved < $qty) {
                throw new HttpException(422, 'Not enough available stock to reserve');
            }

            // Check for idempotency if ref is provided
            if ($ref) {
                $existingMovement = StockMovement::where('ref', $ref)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branch->id)
                    ->where('type', 'RESERVE')
                    ->first();

                if ($existingMovement) {
                    // Return existing item state without creating duplicate movement
                    return $item->fresh();
                }
            }

            $item->reserved += $qty;
            $item->save();

            StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'qty' => $qty,
                'type' => 'RESERVE',
                'ref' => $ref,
                'meta' => $ctx['meta'] ?? null,
                'created_by' => $ctx['created_by'] ?? null,
            ]);

            return $item->fresh();
        });
    }

    /**
     * Unreserve stock: validate reserved, decrement reserved, log movement.
     */
    public function unreserve(Product $product, Branch $branch, float $qty, ?string $ref = null, array $ctx = []): InventoryItem
    {
        if ($qty <= 0) {
            throw new HttpException(422, 'Unreserve quantity must be positive');
        }

        return DB::transaction(function () use ($product, $branch, $qty, $ref, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item || $item->reserved < $qty) {
                throw new HttpException(422, 'Not enough reserved stock to unreserve');
            }

            // Check for idempotency if ref is provided
            if ($ref) {
                $existingMovement = StockMovement::where('ref', $ref)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branch->id)
                    ->where('type', 'UNRESERVE')
                    ->first();

                if ($existingMovement) {
                    // Return existing item state without creating duplicate movement
                    return $item->fresh();
                }
            }

            $item->reserved -= $qty;
            $item->save();

            StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'qty' => $qty,
                'type' => 'UNRESERVE',
                'ref' => $ref,
                'meta' => $ctx['meta'] ?? null,
                'created_by' => $ctx['created_by'] ?? null,
            ]);

            return $item->fresh();
        });
    }

    /**
     * Issue stock: validate available, decrement on_hand, log movement.
     */
    public function issueStock(Product $product, Branch $branch, float $qty, ?string $ref = null, array $ctx = []): InventoryItem
    {
        if ($qty <= 0) {
            throw new HttpException(422, 'Issue quantity must be positive');
        }

        return DB::transaction(function () use ($product, $branch, $qty, $ref, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item || $item->on_hand - $item->reserved < $qty) {
                throw new HttpException(422, 'Not enough available stock to issue');
            }

            // Check for idempotency if ref is provided
            if ($ref) {
                $existingMovement = StockMovement::where('ref', $ref)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branch->id)
                    ->where('type', 'ISSUE')
                    ->first();

                if ($existingMovement) {
                    // Return existing item state without creating duplicate movement
                    return $item->fresh();
                }
            }

            $item->on_hand -= $qty;
            $item->save();

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'qty' => -$qty, // Negative for issue
                'type' => 'ISSUE',
                'ref' => $ref,
                'meta' => $ctx['meta'] ?? null,
                'created_by' => $ctx['created_by'] ?? null,
            ]);

            // Audit the stock issue
            $this->auditLogger->log(
                'inventory.stock.issued',
                $movement,
                null,
                $movement->toArray(),
                array_merge($ctx, [
                    'product_name' => $product->name,
                    'branch_name' => $branch->name,
                    'previous_on_hand' => $item->on_hand + $qty,
                    'new_on_hand' => $item->on_hand,
                    'issued_quantity' => $qty,
                ])
            );

            return $item->fresh();
        });
    }

    /**
     * Transfer stock: lock both source and destination, validate, move stock, log movements.
     */
    public function transfer(Product $product, Branch $from, Branch $to, float $qty, ?string $ref = null, array $ctx = []): void
    {
        if ($qty <= 0) {
            throw new HttpException(422, 'Transfer quantity must be positive');
        }

        if ($from->id === $to->id) {
            throw new HttpException(422, 'Cannot transfer to the same branch');
        }

        DB::transaction(function () use ($product, $from, $to, $qty, $ref, $ctx) {
            // Check for idempotency if ref is provided
            if ($ref) {
                $existingOutMovement = StockMovement::where('ref', $ref)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $from->id)
                    ->where('type', 'TRANSFER_OUT')
                    ->first();

                $existingInMovement = StockMovement::where('ref', $ref)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $to->id)
                    ->where('type', 'TRANSFER_IN')
                    ->first();

                if ($existingOutMovement && $existingInMovement) {
                    // Transfer already completed, return without creating duplicates
                    return;
                }
            }

            // Lock order by (product_id, branch_id) to avoid deadlocks
            $ids = [
                ['product_id' => $product->id, 'branch_id' => $from->id],
                ['product_id' => $product->id, 'branch_id' => $to->id],
            ];
            usort($ids, function ($a, $b) {
                return strcmp($a['product_id'] . $a['branch_id'], $b['product_id'] . $b['branch_id']);
            });

            $items = [];
            foreach ($ids as $id) {
                $item = InventoryItem::where('product_id', $id['product_id'])
                    ->where('branch_id', $id['branch_id'])
                    ->lockForUpdate()
                    ->first();
                $items[] = $item;
            }

            // Map locked items to source/dest
            $source = $from->id === $ids[0]['branch_id'] ? $items[0] : $items[1];
            $dest = $to->id === $ids[0]['branch_id'] ? $items[0] : $items[1];

            // Source must exist and have enough available
            if (!$source || $source->on_hand - $source->reserved < $qty) {
                throw new HttpException(422, 'Not enough available stock to transfer');
            }

            // Decrement source
            $source->on_hand -= $qty;
            $source->save();

            StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $from->id,
                'qty' => $qty,
                'type' => 'TRANSFER_OUT',
                'ref' => $ref,
                'meta' => $ctx['meta'] ?? null,
                'created_by' => $ctx['created_by'] ?? null,
            ]);

            // Ensure dest exists
            if (!$dest) {
                $dest = InventoryItem::create([
                    'product_id' => $product->id,
                    'branch_id' => $to->id,
                    'on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            // Increment dest
            $dest->on_hand += $qty;
            $dest->save();

            StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $to->id,
                'qty' => $qty,
                'type' => 'TRANSFER_IN',
                'ref' => $ref,
                'meta' => $ctx['meta'] ?? null,
                'created_by' => $ctx['created_by'] ?? null,
            ]);
        });
    }

    /**
     * Adjust stock: manual correction, increment/decrement on_hand, log movement.
     * Constraint: cannot make on_hand negative.
     * Requires 'inventory.adjust' capability.
     */
    public function adjust(Product $product, Branch $branch, float $qty, ?string $reason = null, ?string $ref = null, array $ctx = []): InventoryItem
    {
        if ($qty == 0) {
            throw new HttpException(422, 'Adjust quantity cannot be zero');
        }

        // Check if user has inventory.adjust capability
        $user = auth()->user();
        if (!$user || !$user->hasCapability('inventory.adjust', $branch)) {
            throw new HttpException(403, 'Insufficient permissions to adjust inventory');
        }

        return DB::transaction(function () use ($product, $branch, $qty, $reason, $ref, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                $item = InventoryItem::create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            // Constraint: cannot make on_hand negative
            if ($item->on_hand + $qty < 0) {
                throw new HttpException(422, 'Adjustment would result in negative stock');
            }

            // Check for idempotency if ref is provided
            if ($ref) {
                $existingMovement = StockMovement::where('ref', $ref)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branch->id)
                    ->where('type', 'ADJUST')
                    ->first();

                if ($existingMovement) {
                    // Return existing item state without creating duplicate movement
                    return $item->fresh();
                }
            }

            $previousOnHand = $item->on_hand;
            $item->on_hand += $qty;
            $item->save();

            // Prepare meta data with reason
            $meta = $ctx['meta'] ?? [];
            if ($reason) {
                $meta['reason'] = $reason;
            }

            $movement = StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'qty' => $qty,
                'type' => 'ADJUST',
                'ref' => $ref,
                'meta' => $meta,
                'created_by' => $ctx['created_by'] ?? null,
            ]);

            // Audit the stock adjustment
            $this->auditLogger->log(
                'inventory.stock.adjusted',
                $movement,
                null,
                $movement->toArray(),
                array_merge($ctx, [
                    'product_name' => $product->name,
                    'branch_name' => $branch->name,
                    'reason' => $reason,
                    'previous_on_hand' => $previousOnHand,
                    'new_on_hand' => $item->on_hand,
                    'adjusted_quantity' => $qty,
                ])
            );

            return $item->fresh();
        });
    }
}