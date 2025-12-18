<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends BaseModel
{
    use HasFactory;

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'date',
        'check_in_time',
        'check_out_time',
        'work_hour',
    ];

    /**
     * Mendapatkan atribut yang harus di-cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            // 'work_hour' => 'decimal:2',
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

    // ========== Scopes ==========

    /**
     * Scope untuk filter absensi berdasarkan employee
     */
    public function scopeOfEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope untuk filter absensi dalam bulan tertentu (format: YYYY-MM)
     */
    public function scopeInMonth($query, string $yearMonth)
    {
        return $query->whereRaw("DATE_FORMAT(`date`, '%Y-%m') = ?", [$yearMonth]);
    }

    /**
     * Scope untuk pencarian global absensi
     * Mencari berdasarkan:
     * - Nama karyawan
     * - Email karyawan
     * - Kode karyawan (employee_code)
     * - Departemen karyawan
     * - Posisi karyawan
     */
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($subQuery) use ($term) {
            $subQuery->whereHas('employee', function ($employeeQuery) use ($term) {
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
     * Scope untuk filter berdasarkan work hour range
     */
    public function scopeByWorkHour($query, ?float $minHours, ?float $maxHours)
    {
        if ($minHours !== null) {
            $query->where('work_hour', '>=', $minHours);
        }

        if ($maxHours !== null) {
            $query->where('work_hour', '<=', $maxHours);
        }

        return $query;
    }

    // ========== Metode Helper ==========

    /**
     * Hitung jam kerja berdasarkan check-in dan check-out
     * Formula: (check_out_time - check_in_time) dalam jam
     * Menghitung durasi kerja aktual tanpa pengurangan break otomatis
     */
    public function computeWorkHour(): void
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            $this->attributes['work_hour'] = 0;
            return;
        }

        $checkIn = \Carbon\Carbon::parse($this->check_in_time);
        $checkOut = \Carbon\Carbon::parse($this->check_out_time);

        // Hitung selisih menit dari check-in ke check-out
        $totalMinutes = $checkIn->diffInMinutes($checkOut);

        // Langsung konversi ke jam tanpa pengurangan break otomatis
        $this->attributes['work_hour'] = round($totalMinutes / 60, 2);
    }

    /**
     * Accessor: Konversi work_hour (decimal) → "HH:MM"
     * Otomatis dipakai saat toArray() / toJson()
     */
    public function getWorkHourAttribute($value): ?string
    {
        if (is_null($value) || $value === 0) {
            return '00:00';
        }

        // Pastikan value adalah numeric
        $numericValue = is_numeric($value) ? floatval($value) : 0;

        if ($numericValue <= 0) {
            return '00:00';
        }

        $hours = floor($numericValue);
        $minutes = round(($numericValue - $hours) * 60);

        // Handle jika minutes >= 60 akibat pembulatan
        if ($minutes >= 60) {
            $hours += 1;
            $minutes -= 60;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
