<?php

namespace App\Http\Controllers\Alerts;

use App\Events\Alerts\UrgentAlertTriggered;
use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use App\Models\Alerts\UrgentAlert;
use App\Traits\ControllerHelpers;
use Exception;
use Illuminate\Http\Request;

class UrgentAlertController extends Controller
{
    use ControllerHelpers;

    /**
     * GET /api/v1/alerts
     * List urgent alerts with pagination and filters.
     */
    public function index(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $status = $request->input('status', 'all');
            $category = $request->input('category', 'all');
            $search = trim((string) $request->input('search', ''));
            $perPage = (int) $request->input('per_page', 15);

            $query = UrgentAlert::with(['customer', 'conversation', 'ticket']);

            if (! empty($status) && $status !== 'all') {
                $query->where('status', $status);
            }

            if (! empty($category) && $category !== 'all') {
                $query->where('incident_category', $category);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('alert_code', 'like', "%{$search}%")
                        ->orWhere('raw_message', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            }

            $paginated = (clone $query)->orderByDesc('created_at')->paginate($perPage);

            $summary = [
                'total_alerts' => UrgentAlert::count(),
                'active_count' => UrgentAlert::whereIn('status', ['triggered', 'dispatched'])->count(),
                'acknowledged_count' => UrgentAlert::where('status', 'acknowledged')->count(),
                'resolved_count' => UrgentAlert::where('status', 'resolved')->count(),
            ];

            return $this->respondWithData([
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
            ], 'Berhasil memuat daftar alert darurat.');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal memuat daftar alert darurat.');
        }
    }

    /**
     * GET /api/v1/alerts/active
     * Get active (unacknowledged/unresolved) urgent alerts for header badge & real-time monitoring.
     */
    public function active(): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $activeAlerts = UrgentAlert::with(['customer', 'conversation', 'ticket'])
                ->whereIn('status', ['triggered', 'dispatched'])
                ->orderByDesc('created_at')
                ->get();

            return $this->respondWithData([
                'active_count' => $activeAlerts->count(),
                'has_active' => $activeAlerts->isNotEmpty(),
                'alerts' => $activeAlerts,
            ], 'Status alert aktif berhasil dimuat.');
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal memuat status alert aktif.');
        }
    }

    /**
     * POST /api/v1/alerts/{id}/acknowledge
     * Tandai bahwa staf/admin telah mengetahui dan merespons alert.
     */
    public function acknowledge(Request $request, string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $alert = UrgentAlert::with(['customer', 'conversation', 'ticket'])->find($id);
            if (! $alert) {
                throw new NotFoundException("Alert dengan ID [{$id}] tidak ditemukan.");
            }

            $adminName = trim((string) $request->input('acknowledged_by', 'Admin'));

            $alert->update([
                'status' => 'acknowledged',
                'acknowledged_by' => $adminName ?: 'Admin',
                'acknowledged_at' => now(),
            ]);

            try {
                broadcast(new UrgentAlertTriggered($alert->fresh(['customer', 'conversation', 'ticket']), 'acknowledged'));
            } catch (\Throwable) {}

            return $this->respondWithData($alert->fresh(), "Alert [{$alert->alert_code}] berhasil di-acknowledge.");
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal meng-acknowledge alert.');
        }
    }

    /**
     * POST /api/v1/alerts/{id}/resolve
     * Selesaikan alert darurat.
     */
    public function resolve(string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $alert = UrgentAlert::with(['customer', 'conversation', 'ticket'])->find($id);
            if (! $alert) {
                throw new NotFoundException("Alert dengan ID [{$id}] tidak ditemukan.");
            }

            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            try {
                broadcast(new UrgentAlertTriggered($alert->fresh(['customer', 'conversation', 'ticket']), 'resolved'));
            } catch (\Throwable) {}

            return $this->respondWithData($alert->fresh(), "Alert [{$alert->alert_code}] berhasil ditandai selesai.");
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal menyelesaikan alert.');
        }
    }
}

