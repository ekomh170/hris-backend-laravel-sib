<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'period',
        'total_star',
        'review_description',
    ];

    /**
     * Mendapatkan atribut yang harus di-cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_star' => 'integer',
        ];
    }

    // ========== Relasi ==========

    /**
     * Relasi N:1 dengan Employee
     * Karyawan yang dinilai
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Relasi N:1 dengan User (sebagai reviewer)
     * User/Manager yang memberikan penilaian
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    // ========== Scopes ==========

    /**
     * Scope untuk filter review berdasarkan employee
     */
    public function scopeOfEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope untuk filter review berdasarkan periode
     */
    public function scopeInPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Scope untuk filter review yang dibuat oleh reviewer tertentu
     */
    public function scopeByReviewer($query, int $reviewerId)
    {
        return $query->where('reviewer_id', $reviewerId);
    }
}
