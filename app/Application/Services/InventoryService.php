<?php

namespace App\Application\Services;

use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InventoryService
{
    /**
     * Opening balance: create or update inventory item, increment on_hand, log movement.
     */
    public function openingBalance(Product $product, Branch $branch, float $qty, array $ctx = []): InventoryItem
    {
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

            StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'qty' => $qty,
                'type' => 'OPENING',
                'ref' => $ctx['ref'] ?? null,
                'meta' => $ctx['meta'] ?? null,
                'created_by' => $ctx['created_by'] ?? null,
            ]);

            return $item->fresh();
        });
    }

    /**
     * Receive stock: ensure item, increment on_hand, log movement.
     */
    public function receiveStock(Product $product, Branch $branch, float $qty, ?string $ref = null, array $ctx = []): InventoryItem
    {
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

            StockMovement::create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'qty' => $qty,
                'type' => 'RECEIVE',
                'ref' => $ref,
                'meta' => $ctx['meta'] ?? null,
                'created_by' => $ctx['created_by'] ?? null,
            ]);

            return $item->fresh();
        });
    }

    /**
     * Reserve stock: validate available, increment reserved, log movement.
     */
    public function reserve(Product $product, Branch $branch, float $qty, ?string $ref = null, array $ctx = []): InventoryItem
    {
        return DB::transaction(function () use ($product, $branch, $qty, $ref, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item || $item->on_hand - $item->reserved < $qty) {
                throw new HttpException(422, 'Not enough available stock to reserve');
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
        return DB::transaction(function () use ($product, $branch, $qty, $ref, $ctx) {
            $item = InventoryItem::where('product_id', $product->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (!$item || $item->reserved < $qty) {
                throw new HttpException(422, 'Not enough reserved stock to unreserve');
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
     * Transfer stock: lock both source and destination, validate, move stock, log movements.
     */
    public function transfer(Product $product, Branch $from, Branch $to, float $qty, ?string $ref = null, array $ctx = []): void
    {
        DB::transaction(function () use ($product, $from, $to, $qty, $ref, $ctx) {
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
}