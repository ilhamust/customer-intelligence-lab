<?php

namespace App\Service\Staff\Implements;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Models\Staff\StationStaff;
use App\Service\Staff\Contracts\StationStaffService;
use App\Traits\ServiceHelper;
use Illuminate\Database\Eloquent\Collection;

class StationStaffServiceImpl implements StationStaffService
{
    use ServiceHelper;

    /**
     * Dapatkan daftar staf lapangan dengan filter, paginasi, dan ringkasan metrik KPI.
     */
    public function listStaff(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = $filters['role'] ?? null;
        $stationName = $filters['station_name'] ?? null;
        $isOnDuty = $filters['is_on_duty'] ?? null;
        $isActive = $filters['is_active'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = StationStaff::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('station_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (! empty($role) && $role !== 'all') {
            $query->where('role', $role);
        }

        if (! empty($stationName) && $stationName !== 'all') {
            $query->where('station_name', $stationName);
        }

        if ($isOnDuty !== null && $isOnDuty !== '' && $isOnDuty !== 'all') {
            $boolDuty = filter_var($isOnDuty, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolDuty !== null) {
                $query->where('is_on_duty', $boolDuty);
            }
        }

        if ($isActive !== null && $isActive !== '' && $isActive !== 'all') {
            $boolActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolActive !== null) {
                $query->where('is_active', $boolActive);
            }
        }

        $paginated = (clone $query)
            ->orderByDesc('is_active')
            ->orderByDesc('is_on_duty')
            ->orderBy('name', 'asc')
            ->paginate($perPage);

        $summary = [
            'total_staff' => StationStaff::count(),
            'active_count' => StationStaff::where('is_active', true)->count(),
            'on_duty_count' => StationStaff::where('is_active', true)->where('is_on_duty', true)->count(),
            'off_duty_count' => StationStaff::where('is_active', true)->where('is_on_duty', false)->count(),
        ];

        return [
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
    }

    /**
     * Dapatkan detail staf berdasarkan ID.
     */
    public function getStaffById(string $id): StationStaff
    {
        $staff = StationStaff::find($id);
        if (! $staff) {
            throw new NotFoundException("Data staf dengan ID [{$id}] tidak ditemukan.");
        }

        return $staff;
    }

    /**
     * Buat data kontak staf baru dengan normalisasi nomor telepon ke format baku (628...).
     */
    public function createStaff(array $data): StationStaff
    {
        $m = __METHOD__;

        $normalizedPhone = $this->normalizePhone((string) ($data['phone'] ?? ''));

        $existing = StationStaff::where('phone', $normalizedPhone)->first();
        if ($existing) {
            throw new BadRequestException("Nomor WhatsApp [{$normalizedPhone}] sudah terdaftar atas nama {$existing->name}.");
        }

        $staff = StationStaff::create([
            'name' => trim((string) $data['name']),
            'phone' => $normalizedPhone,
            'role' => $data['role'] ?? 'field_technician',
            'station_name' => ! empty($data['station_name']) ? trim((string) $data['station_name']) : 'all',
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'is_on_duty' => isset($data['is_on_duty']) ? (bool) $data['is_on_duty'] : true,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
        ]);

        $this->logInfo($m, 'Station staff created successfully', [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'role' => $staff->role,
        ]);

        return $staff;
    }

    /**
     * Perbarui data kontak staf berdasarkan ID.
     */
    public function updateStaff(string $id, array $data): StationStaff
    {
        $m = __METHOD__;
        $staff = $this->getStaffById($id);

        if (isset($data['phone'])) {
            $normalizedPhone = $this->normalizePhone((string) $data['phone']);
            $duplicate = StationStaff::where('phone', $normalizedPhone)
                ->where('id', '!=', $staff->id)
                ->first();

            if ($duplicate) {
                throw new BadRequestException("Nomor WhatsApp [{$normalizedPhone}] sudah digunakan oleh staf lain ({$duplicate->name}).");
            }

            $data['phone'] = $normalizedPhone;
        }

        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        if (array_key_exists('station_name', $data)) {
            $data['station_name'] = ! empty($data['station_name']) ? trim((string) $data['station_name']) : 'all';
        }

        $staff->update($data);

        $this->logInfo($m, 'Station staff updated successfully', [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'phone' => $staff->phone,
        ]);

        return $staff->fresh();
    }

    /**
     * Hapus data kontak staf berdasarkan ID.
     */
    public function deleteStaff(string $id): bool
    {
        $m = __METHOD__;
        $staff = $this->getStaffById($id);

        $deleted = (bool) $staff->delete();

        $this->logInfo($m, 'Station staff deleted', [
            'staff_id' => $id,
            'name' => $staff->name,
            'phone' => $staff->phone,
        ]);

        return $deleted;
    }

    /**
     * Toggle status bertugas (is_on_duty) staf.
     */
    public function toggleDutyStatus(string $id): StationStaff
    {
        $m = __METHOD__;
        $staff = $this->getStaffById($id);

        $staff->update([
            'is_on_duty' => ! $staff->is_on_duty,
        ]);

        $this->logInfo($m, 'Station staff duty status toggled', [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'is_on_duty' => $staff->is_on_duty,
        ]);

        return $staff->fresh();
    }

    /**
     * Dapatkan seluruh staf yang aktif (is_active = true) dan sedang bertugas (is_on_duty = true)
     * untuk pengiriman broadcast alert darurat 24/7.
     */
    public function getActiveOnDutyStaff(?string $stationName = null): Collection
    {
        $query = StationStaff::query()
            ->where('is_active', true)
            ->where('is_on_duty', true);

        if (! empty($stationName) && $stationName !== 'all') {
            $query->where(function ($q) use ($stationName) {
                $q->where('station_name', 'all')
                    ->orWhere('station_name', $stationName);
            });
        }

        return $query->orderBy('name', 'asc')->get();
    }

    /**
     * Normalisasi nomor telepon ke format baku internasional Indonesia (628...).
     */
    public function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^\d]/', '', $phone);

        if (empty($clean)) {
            throw new BadRequestException('Format nomor telepon tidak valid.');
        }

        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        if (strlen($clean) < 10 || strlen($clean) > 16) {
            throw new BadRequestException('Panjang nomor telepon harus antara 10 hingga 16 digit.');
        }

        return $clean;
    }
}

