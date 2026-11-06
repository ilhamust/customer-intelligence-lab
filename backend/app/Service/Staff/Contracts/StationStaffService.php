<?php

namespace App\Service\Staff\Contracts;

use App\Models\Staff\StationStaff;
use Illuminate\Database\Eloquent\Collection;

interface StationStaffService
{
    /**
     * Dapatkan daftar staf lapangan dengan filter, paginasi, dan ringkasan metrik KPI.
     */
    public function listStaff(array $filters = []): array;

    /**
     * Dapatkan detail staf berdasarkan ID.
     */
    public function getStaffById(string $id): StationStaff;

    /**
     * Buat data kontak staf baru dengan normalisasi nomor telepon ke format baku (628...).
     */
    public function createStaff(array $data): StationStaff;

    /**
     * Perbarui data kontak staf berdasarkan ID.
     */
    public function updateStaff(string $id, array $data): StationStaff;

    /**
     * Hapus data kontak staf berdasarkan ID.
     */
    public function deleteStaff(string $id): bool;

    /**
     * Toggle status bertugas (is_on_duty) staf.
     */
    public function toggleDutyStatus(string $id): StationStaff;

    /**
     * Dapatkan seluruh staf yang aktif (is_active = true) dan sedang bertugas (is_on_duty = true)
     * untuk pengiriman broadcast alert darurat 24/7.
     */
    public function getActiveOnDutyStaff(?string $stationName = null): Collection;

    /**
     * Normalisasi nomor telepon ke format baku internasional Indonesia (628...).
     */
    public function normalizePhone(string $phone): string;
}

