<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends BaseModel
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employee_code',
        'position',
        'department_id',
        'join_date',
        'employment_status',
        'contact',
    ];

    /**
     * Atribut yang harus di-cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'join_date' => 'date',
        'employment_status' => EmploymentStatus::class,
    ];

    // ========== Relasi ==========

    /**
     * Relasi N:1 dengan User
     * Setiap karyawan terhubung ke satu akun login
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi N:1 dengan Department
     * Setiap karyawan terhubung ke satu departemen
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relasi 1:N dengan Attendance
     * Satu karyawan punya banyak catatan absensi
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id');
    }

    /**
     * Relasi 1:N dengan LeaveRequest
     * Satu karyawan bisa ajukan banyak cuti
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    /**
     * Relasi 1:N dengan PerformanceReview
     * Satu karyawan bisa menerima banyak penilaian kinerja
     */
    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'employee_id');
    }

    /**
     * Relasi 1:N dengan SalarySlip
     * Satu karyawan bisa menerima banyak slip gaji
     */
    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class, 'employee_id');
    }

    // ========== Scopes ==========

    /**
     * Scope untuk pencarian employee berdasarkan multiple fields
     */
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;
        return $query->where(function ($subQuery) use ($term) {
            $subQuery->where('employee_code', 'like', "%{$term}%")
                     ->orWhere('position', 'like', "%{$term}%")
                     ->orWhere('contact', 'like', "%{$term}%")
                     ->orWhereHas('department', function ($deptQuery) use ($term) {
                         $deptQuery->where('name', 'like', "%{$term}%")
                                   ->orWhere('description', 'like', "%{$term}%");
                     })
                     ->orWhereHas('user', function ($userQuery) use ($term) {
                         $userQuery->where('name', 'like', "%{$term}%")
                                   ->orWhere('email', 'like', "%{$term}%");
                     });
        });
    }
}
