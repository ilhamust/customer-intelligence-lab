<?php

namespace App\Http\Controllers\Bot;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use App\Models\Bot\Customer;
use App\Traits\ControllerHelpers;
use Exception;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ControllerHelpers;

    /**
     * List all customers with search, filtering, and pagination.
     */
    public function index(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $search = trim((string) $request->input('search', ''));
            $driverType = $request->input('driver_type', 'all');
            $status = $request->input('status', 'all');
            $perPage = (int) $request->input('per_page', 15);

            $query = Customer::query()->withCount('conversations');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('vehicle', 'like', "%{$search}%")
                        ->orWhere('residential_area', 'like', "%{$search}%")
                        ->orWhere('primary_route', 'like', "%{$search}%");
                });
            }

            if ($driverType !== 'all' && ! empty($driverType)) {
                $query->where('driver_type', $driverType);
            }

            if ($status !== 'all' && ! empty($status)) {
                $query->where('status', $status);
            }

            $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Summary metrics
            $summary = [
                'total_customers' => Customer::count(),
                'active_customers' => Customer::where('status', 'active')->count(),
                'total_points' => (int) Customer::sum('points_balance'),
                'ride_hailing_count' => Customer::where('driver_type', 'ride-hailing')->count(),
                'private_count' => Customer::where('driver_type', 'private')->count(),
            ];

            $response = [
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
                'summary' => $summary,
            ];

            return $this->respondWithData($response, 'Successfully retrieved customer list');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve customers.');
        }
    }

    /**
     * Get single customer 360° detail.
     */
    public function show(string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $customer = Customer::with(['conversations' => function ($q) {
                $q->orderByRaw('last_message_time DESC NULLS LAST')->limit(10);
            }])->find($id);

            if (! $customer) {
                throw new NotFoundException("Customer [{$id}] not found.");
            }

            return $this->respondWithData($customer, 'Successfully retrieved customer detail');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve customer detail.');
        }
    }

    /**
     * Get customer points balance and statistics.
     */
    public function points(string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $customer = Customer::find($id);

            if (! $customer) {
                throw new NotFoundException("Customer [{$id}] not found.");
            }

            $pointsData = [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'points_balance' => (int) $customer->points_balance,
                'tier' => $customer->points_balance >= 1000 ? 'Platinum' : ($customer->points_balance >= 500 ? 'Gold' : 'Silver'),
            ];

            return $this->respondWithData($pointsData, 'Successfully retrieved customer points');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve customer points.');
        }
    }

    /**
     * Get points ledger history for customer.
     */
    public function ledger(string $id, Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $customer = Customer::find($id);

            if (! $customer) {
                throw new NotFoundException("Customer [{$id}] not found.");
            }

            // Ledger records from meta or welcome bonus
            $history = $customer->meta['points_ledger'] ?? [
                [
                    'id' => 'led_init',
                    'description' => 'Welcome Registration Bonus',
                    'points' => $customer->points_balance,
                    'type' => 'credit',
                    'created_at' => $customer->created_at?->toIso8601String(),
                ],
            ];

            return $this->respondWithData([
                'customer_id' => $customer->id,
                'balance' => $customer->points_balance,
                'ledger' => $history,
            ], 'Successfully retrieved customer ledger');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve customer ledger.');
        }
    }
}
