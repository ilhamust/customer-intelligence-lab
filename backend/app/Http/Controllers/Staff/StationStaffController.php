<?php

namespace App\Http\Controllers\Staff;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStationStaffRequest;
use App\Http\Requests\Staff\UpdateStationStaffRequest;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use App\Service\Staff\Contracts\StationStaffService;
use App\Traits\ControllerHelpers;
use Exception;
use Illuminate\Http\Request;

class StationStaffController extends Controller
{
    use ControllerHelpers;

    public function __construct(
        protected StationStaffService $stationStaffService
    ) {}

    /**
     * GET /api/v1/staff
     * Dapatkan daftar seluruh staf lapangan beserta ringkasan status bertugas.
     */
    public function index(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $filters = $request->only([
                'search',
                'role',
                'station_name',
                'is_on_duty',
                'is_active',
                'per_page',
            ]);

            $result = $this->stationStaffService->listStaff($filters);

            return $this->respondWithData($result, 'Berhasil memuat daftar kontak staf lapangan.');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal memuat daftar kontak staf lapangan.');
        }
    }

    /**
     * POST /api/v1/staff
     * Tambahkan kontak staf lapangan baru.
     */
    public function store(StoreStationStaffRequest $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $staff = $this->stationStaffService->createStaff($request->validated());

            return $this->respondWithData($staff, 'Kontak staf lapangan berhasil ditambahkan.', 201);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal menambahkan kontak staf lapangan.');
        }
    }

    /**
     * GET /api/v1/staff/{id}
     * Dapatkan detail satu staf lapangan.
     */
    public function show(string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $staff = $this->stationStaffService->getStaffById($id);

            return $this->respondWithData($staff, 'Detail kontak staf berhasil ditemukan.');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal memuat detail kontak staf.');
        }
    }

    /**
     * PUT /api/v1/staff/{id}
     * Perbarui data kontak staf lapangan.
     */
    public function update(UpdateStationStaffRequest $request, string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $staff = $this->stationStaffService->updateStaff($id, $request->validated());

            return $this->respondWithData($staff, 'Data kontak staf berhasil diperbarui.');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal memperbarui data kontak staf.');
        }
    }

    /**
     * DELETE /api/v1/staff/{id}
     * Hapus data kontak staf lapangan.
     */
    public function destroy(string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $this->stationStaffService->deleteStaff($id);

            return $this->respondWithData(['deleted' => true, 'id' => $id], 'Kontak staf berhasil dihapus.');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal menghapus kontak staf.');
        }
    }

    /**
     * PATCH /api/v1/staff/{id}/toggle-duty
     * Toggle status bertugas (On-Duty / Off-Duty) staf lapangan.
     */
    public function toggleDuty(string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $staff = $this->stationStaffService->toggleDutyStatus($id);

            $statusText = $staff->is_on_duty ? 'On-Duty (Siaga)' : 'Off-Duty (Libur/Istirahat)';

            return $this->respondWithData($staff, "Status tugas {$staff->name} diubah menjadi {$statusText}.");
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Gagal mengubah status tugas staf.');
        }
    }
}

