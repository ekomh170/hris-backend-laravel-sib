<?php

namespace App\Models;

use App\Enums\LeaveStatus;
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
     */
    public function scopeForManagerTeam($query, int $managerUserId)
    {
        return $query->whereHas('employee', function ($employeeQuery) use ($managerUserId) {
            $employeeQuery->where('manager_id', $managerUserId);
        });
    }

    /**
     * Scope untuk filter berdasarkan periode bulan (format: YYYY-MM)
     */
    public function scopeInPeriod($query, ?string $yearMonth)
    {
        if (!$yearMonth) {
            return $query;
        }

        return $query->whereRaw(
            "DATE_FORMAT(`start_date`, '%Y-%m') = ? OR DATE_FORMAT(`end_date`, '%Y-%m') = ?",
            [$yearMonth, $yearMonth]
        );
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
