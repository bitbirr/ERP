<?php

namespace App\Application\Services;

use App\Models\VoucherBatch;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VoucherService extends BaseService
{
    /**
     * Receive a voucher batch and create individual vouchers
     */
    public function receiveVoucherBatch(array $data): VoucherBatch
    {
        $this->validateBatchData($data);

        return $this->transaction(function () use ($data) {
            // Create the voucher batch
            $batch = VoucherBatch::create([
                'batch_number' => $data['batch_number'],
                'received_at' => now(),
                'total_vouchers' => $data['total_vouchers'],
                'serial_start' => $data['serial_start'],
                'serial_end' => $data['serial_end'],
                'status' => 'received',
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'serial_range' => [
                        'start' => $data['serial_start'],
                        'end' => $data['serial_end'],
                        'total' => $data['total_vouchers'],
                    ],
                    'received_at' => now()->toISOString(),
                ]),
            ]);

            // Generate individual vouchers
            $this->generateVouchersForBatch($batch);

            // Update batch status to processed
            $batch->update(['status' => 'processed']);

            return $batch->fresh();
        });
    }

    /**
     * Generate individual voucher records for a batch
     */
    private function generateVouchersForBatch(VoucherBatch $batch): void
    {
        $serialNumbers = $this->generateSerialNumbers(
            $batch->serial_start,
            $batch->serial_end
        );

        $vouchers = [];
        foreach ($serialNumbers as $serialNumber) {
            $vouchers[] = [
                'batch_id' => $batch->id,
                'serial_number' => $serialNumber,
                'status' => 'available',
                'metadata' => [
                    'batch_number' => $batch->batch_number,
                    'generated_at' => now()->toISOString(),
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert vouchers in chunks to handle large batches
        collect($vouchers)->chunk(1000)->each(function ($chunk) {
            Voucher::insert($chunk->toArray());
        });
    }

    /**
     * Generate serial numbers from start to end
     */
    private function generateSerialNumbers(string $start, string $end): array
    {
        $startNum = $this->serialToNumber($start);
        $endNum = $this->serialToNumber($end);

        if ($startNum > $endNum) {
            throw new InvalidArgumentException('Serial start cannot be greater than serial end');
        }

        $serials = [];
        for ($i = $startNum; $i <= $endNum; $i++) {
            $serials[] = $this->numberToSerial($i, strlen($start));
        }

        return $serials;
    }

    /**
     * Convert serial string to number for range calculation
     */
    private function serialToNumber(string $serial): int
    {
        // Handle both numeric and alphanumeric serials
        if (is_numeric($serial)) {
            return (int) $serial;
        }

        // For alphanumeric, convert to base 36
        return intval($serial, 36);
    }

    /**
     * Convert number back to serial string with proper padding
     */
    private function numberToSerial(int $number, int $length): string
    {
        $serial = (string) $number;

        // Pad with leading zeros to maintain original length
        return str_pad($serial, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Validate batch data
     */
    private function validateBatchData(array $data): void
    {
        if (empty($data['batch_number'])) {
            throw new InvalidArgumentException('Batch number is required');
        }

        if (empty($data['serial_start']) || empty($data['serial_end'])) {
            throw new InvalidArgumentException('Serial start and end are required');
        }

        if (!isset($data['total_vouchers']) || $data['total_vouchers'] <= 0) {
            throw new InvalidArgumentException('Total vouchers must be a positive number');
        }

        // Check if batch number already exists
        if (VoucherBatch::where('batch_number', $data['batch_number'])->exists()) {
            throw new InvalidArgumentException('Batch number already exists');
        }
    }

    /**
     * Get voucher batch by batch number
     */
    public function getVoucherBatch(string $batchNumber): ?VoucherBatch
    {
        return VoucherBatch::where('batch_number', $batchNumber)->first();
    }

    /**
     * Get available vouchers for a batch
     */
    public function getAvailableVouchers(string $batchNumber): Collection
    {
        $batch = $this->getVoucherBatch($batchNumber);

        if (!$batch) {
            return collect();
        }

        return $batch->vouchers()->where('status', 'available')->get();
    }

    /**
     * Reserve vouchers for an order
     */
    public function reserveVouchersForOrder(string $orderId, int $quantity, ?string $batchNumber = null): Collection
    {
        return $this->transaction(function () use ($orderId, $quantity, $batchNumber) {
            $query = Voucher::where('status', 'available');

            if ($batchNumber) {
                $batch = $this->getVoucherBatch($batchNumber);
                if (!$batch) {
                    throw new InvalidArgumentException('Voucher batch not found');
                }
                $query->where('batch_id', $batch->id);
            }

            $availableVouchers = $query->limit($quantity)->get();

            if ($availableVouchers->count() < $quantity) {
                throw new InvalidArgumentException('Insufficient available vouchers');
            }

            $reservations = collect();
            foreach ($availableVouchers as $voucher) {
                // Update voucher status
                $voucher->update([
                    'status' => 'reserved',
                    'reserved_for_order_id' => $orderId,
                ]);

                // Create reservation record
                $reservation = VoucherReservation::create([
                    'voucher_id' => $voucher->id,
                    'order_id' => $orderId,
                    'reserved_at' => now(),
                    'expires_at' => now()->addHours(24), // Default 24 hour expiry
                    'status' => 'active',
                ]);

                $reservations->push($reservation->load('voucher'));
            }

            return $reservations;
        });
    }

    /**
     * Cancel voucher reservation
     */
    public function cancelReservation(int $reservationId): bool
    {
        return $this->transaction(function () use ($reservationId) {
            $reservation = VoucherReservation::find($reservationId);

            if (!$reservation) {
                throw new InvalidArgumentException('Reservation not found');
            }

            if ($reservation->status !== 'active') {
                throw new InvalidArgumentException('Reservation is not active');
            }

            // Update reservation status
            $reservation->update(['status' => 'cancelled']);

            // Make voucher available again
            $reservation->voucher->update([
                'status' => 'available',
                'reserved_for_order_id' => null,
            ]);

            return true;
        });
    }

    /**
     * Get reservations for an order
     */
    public function getReservationsForOrder(string $orderId): Collection
    {
        return VoucherReservation::where('order_id', $orderId)
            ->with('voucher')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Extend reservation expiry
     */
    public function extendReservation(int $reservationId, int $hours = 24): bool
    {
        $reservation = VoucherReservation::find($reservationId);

        if (!$reservation) {
            throw new InvalidArgumentException('Reservation not found');
        }

        if ($reservation->status !== 'active') {
            throw new InvalidArgumentException('Reservation is not active');
        }

        $reservation->update([
            'expires_at' => now()->addHours($hours),
        ]);

        return true;
    }

    /**
     * Clean up expired reservations
     */
    public function cleanupExpiredReservations(): int
    {
        return $this->transaction(function () {
            $expiredReservations = VoucherReservation::where('status', 'active')
                ->where('expires_at', '<', now())
                ->get();

            $count = 0;
            foreach ($expiredReservations as $reservation) {
                $reservation->update(['status' => 'expired']);
                $reservation->voucher->update([
                    'status' => 'available',
                    'reserved_for_order_id' => null,
                ]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Issue vouchers for fulfillment
     */
    public function issueVouchersForFulfillment(string $orderId, string $fulfillmentId, array $voucherIds): Collection
    {
        return $this->transaction(function () use ($orderId, $fulfillmentId, $voucherIds) {
            $issuances = collect();

            foreach ($voucherIds as $voucherId) {
                $voucher = Voucher::find($voucherId);

                if (!$voucher) {
                    throw new InvalidArgumentException("Voucher {$voucherId} not found");
                }

                if ($voucher->status !== 'reserved') {
                    throw new InvalidArgumentException("Voucher {$voucher->serial_number} is not reserved");
                }

                if ($voucher->reserved_for_order_id !== $orderId) {
                    throw new InvalidArgumentException("Voucher {$voucher->serial_number} is not reserved for this order");
                }

                // Update voucher status
                $voucher->update([
                    'status' => 'issued',
                    'issued_at' => now(),
                ]);

                // Create issuance record
                $issuance = VoucherIssuance::create([
                    'voucher_id' => $voucher->id,
                    'order_id' => $orderId,
                    'fulfillment_id' => $fulfillmentId,
                    'issued_at' => now(),
                    'metadata' => [
                        'issued_via' => 'fulfillment',
                        'batch_number' => $voucher->batch->batch_number,
                    ],
                ]);

                // Update reservation status
                $reservation = $voucher->reservations()->where('status', 'active')->first();
                if ($reservation) {
                    $reservation->update(['status' => 'fulfilled']);
                }

                $issuances->push($issuance->load('voucher'));
            }

            return $issuances;
        });
    }

    /**
     * Issue vouchers by reservation IDs
     */
    public function issueVouchersByReservations(array $reservationIds, string $fulfillmentId): Collection
    {
        return $this->transaction(function () use ($reservationIds, $fulfillmentId) {
            $issuances = collect();

            foreach ($reservationIds as $reservationId) {
                $reservation = VoucherReservation::find($reservationId);

                if (!$reservation) {
                    throw new InvalidArgumentException("Reservation {$reservationId} not found");
                }

                if ($reservation->status !== 'active') {
                    throw new InvalidArgumentException("Reservation {$reservationId} is not active");
                }

                $voucher = $reservation->voucher;

                // Update voucher status
                $voucher->update([
                    'status' => 'issued',
                    'issued_at' => now(),
                ]);

                // Create issuance record
                $issuance = VoucherIssuance::create([
                    'voucher_id' => $voucher->id,
                    'order_id' => $reservation->order_id,
                    'fulfillment_id' => $fulfillmentId,
                    'issued_at' => now(),
                    'metadata' => [
                        'issued_via' => 'reservation',
                        'reservation_id' => $reservationId,
                        'batch_number' => $voucher->batch->batch_number,
                    ],
                ]);

                // Update reservation status
                $reservation->update(['status' => 'fulfilled']);

                $issuances->push($issuance->load('voucher'));
            }

            return $issuances;
        });
    }

    /**
     * Get issuances for an order
     */
    public function getIssuancesForOrder(string $orderId): Collection
    {
        return VoucherIssuance::where('order_id', $orderId)
            ->with('voucher')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get issuances for a fulfillment
     */
    public function getIssuancesForFulfillment(string $fulfillmentId): Collection
    {
        return VoucherIssuance::where('fulfillment_id', $fulfillmentId)
            ->with('voucher')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Void voucher issuance (for returns/cancellations)
     */
    public function voidIssuance(int $issuanceId, string $reason = null): bool
    {
        return $this->transaction(function () use ($issuanceId, $reason) {
            $issuance = VoucherIssuance::find($issuanceId);

            if (!$issuance) {
                throw new InvalidArgumentException('Issuance not found');
            }

            $voucher = $issuance->voucher;

            // Update voucher status back to available
            $voucher->update([
                'status' => 'available',
                'issued_at' => null,
                'reserved_for_order_id' => null,
            ]);

            // Update issuance metadata
            $metadata = $issuance->metadata ?? [];
            $metadata['voided_at'] = now()->toISOString();
            $metadata['void_reason'] = $reason;

            $issuance->update(['metadata' => $metadata]);

            return true;
        });
    }
}