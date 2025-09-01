<?php

namespace App\Http\Controllers;

use App\Application\Services\VoucherService;
use App\Models\VoucherBatch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    private VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * Receive a voucher batch
     */
    public function receiveBatch(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'batch_number' => 'required|string|max:255',
                'serial_start' => 'required|string|max:255',
                'serial_end' => 'required|string|max:255',
                'total_vouchers' => 'required|integer|min:1',
                'metadata' => 'nullable|array',
            ]);

            $batch = $this->voucherService->receiveVoucherBatch($validated);

            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $batch,
                    'message' => 'Voucher batch received successfully',
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive voucher batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get voucher batch details
     */
    public function getBatch(string $batchNumber): JsonResponse
    {
        try {
            $batch = $this->voucherService->getVoucherBatch($batchNumber);

            if (!$batch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher batch not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $batch->load('vouchers'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve voucher batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available vouchers for a batch
     */
    public function getAvailableVouchers(string $batchNumber): JsonResponse
    {
        try {
            $vouchers = $this->voucherService->getAvailableVouchers($batchNumber);

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_number' => $batchNumber,
                    'available_vouchers' => $vouchers,
                    'count' => $vouchers->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available vouchers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all voucher batches
     */
    public function listBatches(Request $request): JsonResponse
    {
        try {
            $query = VoucherBatch::query();

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $batches = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $batches,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve voucher batches',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reserve vouchers for an order
     */
    public function reserveVouchers(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string|max:255',
                'quantity' => 'required|integer|min:1',
                'batch_number' => 'nullable|string|max:255',
            ]);

            $reservations = $this->voucherService->reserveVouchersForOrder(
                $validated['order_id'],
                $validated['quantity'],
                $validated['batch_number'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'reservations' => $reservations,
                    'count' => $reservations->count(),
                    'message' => 'Vouchers reserved successfully',
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reserve vouchers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel voucher reservation
     */
    public function cancelReservation(Request $request, int $reservationId): JsonResponse
    {
        try {
            $this->voucherService->cancelReservation($reservationId);

            return response()->json([
                'success' => true,
                'message' => 'Reservation cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel reservation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reservations for an order
     */
    public function getOrderReservations(string $orderId): JsonResponse
    {
        try {
            $reservations = $this->voucherService->getReservationsForOrder($orderId);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'reservations' => $reservations,
                    'count' => $reservations->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order reservations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extend reservation expiry
     */
    public function extendReservation(Request $request, int $reservationId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'hours' => 'nullable|integer|min:1|max:168', // Max 1 week
            ]);

            $hours = $validated['hours'] ?? 24;

            $this->voucherService->extendReservation($reservationId, $hours);

            return response()->json([
                'success' => true,
                'message' => "Reservation extended by {$hours} hours",
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend reservation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean up expired reservations
     */
    public function cleanupExpiredReservations(): JsonResponse
    {
        try {
            $count = $this->voucherService->cleanupExpiredReservations();

            return response()->json([
                'success' => true,
                'data' => [
                    'expired_reservations_cleaned' => $count,
                    'message' => "{$count} expired reservations cleaned up",
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup expired reservations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Issue vouchers for fulfillment
     */
    public function issueVouchers(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|string|max:255',
                'fulfillment_id' => 'required|string|max:255',
                'voucher_ids' => 'required|array|min:1',
                'voucher_ids.*' => 'integer|exists:vouchers,id',
            ]);

            $issuances = $this->voucherService->issueVouchersForFulfillment(
                $validated['order_id'],
                $validated['fulfillment_id'],
                $validated['voucher_ids']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'issuances' => $issuances,
                    'count' => $issuances->count(),
                    'message' => 'Vouchers issued successfully',
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue vouchers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Issue vouchers by reservation IDs
     */
    public function issueVouchersByReservations(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reservation_ids' => 'required|array|min:1',
                'reservation_ids.*' => 'integer|exists:voucher_reservations,id',
                'fulfillment_id' => 'required|string|max:255',
            ]);

            $issuances = $this->voucherService->issueVouchersByReservations(
                $validated['reservation_ids'],
                $validated['fulfillment_id']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'issuances' => $issuances,
                    'count' => $issuances->count(),
                    'message' => 'Vouchers issued successfully',
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue vouchers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get issuances for an order
     */
    public function getOrderIssuances(string $orderId): JsonResponse
    {
        try {
            $issuances = $this->voucherService->getIssuancesForOrder($orderId);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'issuances' => $issuances,
                    'count' => $issuances->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order issuances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get issuances for a fulfillment
     */
    public function getFulfillmentIssuances(string $fulfillmentId): JsonResponse
    {
        try {
            $issuances = $this->voucherService->getIssuancesForFulfillment($fulfillmentId);

            return response()->json([
                'success' => true,
                'data' => [
                    'fulfillment_id' => $fulfillmentId,
                    'issuances' => $issuances,
                    'count' => $issuances->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve fulfillment issuances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void voucher issuance
     */
    public function voidIssuance(Request $request, int $issuanceId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $this->voucherService->voidIssuance($issuanceId, $validated['reason'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Issuance voided successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to void issuance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
