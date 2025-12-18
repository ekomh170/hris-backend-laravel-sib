<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends BaseModel
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'reviewed_by',
        'reviewer_note',
        'foto_cuti'
    ];

    /**
     * Mendapatkan atribut yang harus di-cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => LeaveStatus::class,
        ];
    }

    // ========== Relasi ==========

    /**
     * Relasi N:1 dengan Employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relasi N:1 dengan User (reviewer)
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ========== Scopes ==========

    /**
     * Scope untuk filter status Pending
     */
    public function scopePending($query)
    {
        return $query->where('status', LeaveStatus::PENDING);
    }

    /**
     * Scope untuk filter status Approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', LeaveStatus::APPROVED);
    }

    /**
     * Scope untuk filter status Rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', LeaveStatus::REJECTED);
    }

    /**
     * Scope untuk filter leave request tim yang dikelola manager
     * Manager mengelola employees melalui department yang dia kelola
     */
    public function scopeForManagerTeam($query, int $managerUserId)
    {
        return $query->whereHas('employee.department', function ($deptQuery) use ($managerUserId) {
            $deptQuery->where('manager_id', $managerUserId);
        });
    }

    /**
     * Scope: Filter cuti yang BERIRISAN / TUMPANG TINDIH dengan bulan tertentu (YYYY-MM)
     * Contoh: cuti 28 Des 2025 – 5 Jan 2026 → akan muncul di period=2025-12 DAN 2026-01
     */
    public function scopeInPeriod($query, ?string $yearMonth)
    {
        if (!$yearMonth || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return $query;
        }

        $startOfMonth = $yearMonth . '-01';
        $endOfMonth   = Carbon::parse($startOfMonth)->endOfMonth()->format('Y-m-d');

        return $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
            $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
              ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
              ->orWhereRaw('? BETWEEN start_date AND end_date', [$startOfMonth])
              ->orWhereRaw('? BETWEEN start_date AND end_date', [$endOfMonth]);
        });
    }

    /**
     * Scope untuk pencarian global leave request
     * Mencari berdasarkan:
     * - Nama employee
     * - Email employee
     * - Kode employee (employee_code)
     * - Departemen employee
     * - Posisi employee
     * - Reason/alasan cuti
     */
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($subQuery) use ($term) {
            $subQuery->where('reason', 'like', "%{$term}%")
                ->orWhereHas('employee', function ($employeeQuery) use ($term) {
                    $employeeQuery->where('employee_code', 'like', "%{$term}%")
                        ->orWhere('position', 'like', "%{$term}%")
                        ->orWhere('department', 'like', "%{$term}%")
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        });
                });
        });
    }

    /**
     * Scope untuk filter berdasarkan status
     */
    public function scopeByStatus($query, ?string $status)
    {
        if (!$status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter berdasarkan department
     */
    public function scopeByDepartment($query, ?string $department)
    {
        if (!$department) {
            return $query;
        }

        return $query->whereHas('employee.department', function ($deptQuery) use ($department) {
            $deptQuery->where('name', 'like', "%{$department}%");
        });
    }

    /**
     * Scope untuk filter berdasarkan range tanggal
     */
    public function scopeByDateRange($query, ?string $startDate, ?string $endDate)
    {
        if ($startDate) {
            $query->where('start_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('end_date', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope untuk filter berdasarkan durasi cuti (dalam hari)
     */
    public function scopeByDuration($query, ?int $minDays, ?int $maxDays)
    {
        if ($minDays !== null) {
            $query->whereRaw('DATEDIFF(end_date, start_date) + 1 >= ?', [$minDays]);
        }

        if ($maxDays !== null) {
            $query->whereRaw('DATEDIFF(end_date, start_date) + 1 <= ?', [$maxDays]);
        }

        return $query;
    }

    // ========== Metode Helper ==========

    /**
     * Approve leave request
     */
    public function review(int $reviewerId, string $status, ?string $note = null): void
    {
        // Pastikan status valid
        $statusEnum = $status === 'Approved' ? LeaveStatus::APPROVED : LeaveStatus::REJECTED;

        $this->status = $statusEnum;
        $this->reviewed_by = $reviewerId;

        // Default note otomatis sesuai status
        $this->reviewer_note = $note ?? match ($statusEnum) {
            LeaveStatus::APPROVED => 'Approved, Selamat berlibur!',
            LeaveStatus::REJECTED => 'Rejected, Permintaan cuti ditolak.',
        };

        $this->save();
    }
}
