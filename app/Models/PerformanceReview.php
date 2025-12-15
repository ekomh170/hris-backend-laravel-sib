<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends BaseModel
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

    // ========== Enhanced Search Scopes ==========

    /**
     * Scope untuk pencarian global di berbagai field
     * Mencari di: nama karyawan, email, kode karyawan, departemen, posisi, deskripsi review, periode, nama reviewer
     */
    public function scopeSearch($query, ?string $searchTerm)
    {
        if (empty($searchTerm)) {
            return $query;
        }

        return $query->where(function ($subQuery) use ($searchTerm) {
            $subQuery->where('review_description', 'like', "%{$searchTerm}%")
                ->orWhere('period', 'like', "%{$searchTerm}%")
                ->orWhereHas('employee', function ($employeeQuery) use ($searchTerm) {
                    $employeeQuery->where('employee_code', 'like', "%{$searchTerm}%")
                        ->orWhere('position', 'like', "%{$searchTerm}%")
                        ->orWhere('contact', 'like', "%{$searchTerm}%")
                        ->orWhereHas('department', function ($deptQuery) use ($searchTerm) {
                            $deptQuery->where('name', 'like', "%{$searchTerm}%")
                                ->orWhere('description', 'like', "%{$searchTerm}%");
                        })
                        ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                            $userQuery->where('name', 'like', "%{$searchTerm}%")
                                ->orWhere('email', 'like', "%{$searchTerm}%");
                        });
                })
                ->orWhereHas('reviewer', function ($reviewerQuery) use ($searchTerm) {
                    $reviewerQuery->where('name', 'like', "%{$searchTerm}%");
                });
        });
    }

    /**
     * Scope untuk filter berdasarkan range rating/bintang
     */
    public function scopeByRatingRange($query, ?int $minRating = null, ?int $maxRating = null)
    {
        if ($minRating !== null) {
            $query->where('total_star', '>=', $minRating);
        }

        if ($maxRating !== null) {
            $query->where('total_star', '<=', $maxRating);
        }

        return $query;
    }

    /**
     * Scope untuk filter berdasarkan departemen karyawan yang direview
     */
    public function scopeByDepartment($query, ?string $department)
    {
        if (empty($department)) {
            return $query;
        }

        return $query->whereHas('employee.department', function ($deptQuery) use ($department) {
            $deptQuery->where('name', 'like', "%{$department}%")
                ->orWhere('description', 'like', "%{$department}%");
        });
    }

    /**
     * Scope untuk filter berdasarkan range tanggal pembuatan review
     */
    public function scopeByDateRange($query, ?string $dateFrom = null, ?string $dateTo = null)
    {
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        return $query;
    }

    /**
     * Scope untuk filter berdasarkan tahun tertentu
     */
    public function scopeByYear($query, ?string $year = null)
    {
        if (empty($year)) {
            return $query;
        }

        return $query->where(function ($subQuery) use ($year) {
            // Filter untuk format bulanan: "2025-10", "2025-11"
            $subQuery->where('period', 'like', "{$year}-%")
                // Filter untuk format kuartalan: "Q1-2025", "Q4-2025"
                ->orWhere('period', 'like', "%-{$year}")
                // Filter berdasarkan created_at juga
                ->orWhereYear('created_at', $year);
        });
    }

    /**
     * Scope untuk filter berdasarkan tipe periode (bulanan/kuartalan)
     */
    public function scopeByPeriodType($query, ?string $periodType = null)
    {
        if (empty($periodType)) {
            return $query;
        }

        if ($periodType === 'monthly') {
            // Format: "2025-10", "2025-11"
            return $query->whereRaw('period REGEXP ?', ['^[0-9]{4}-[0-9]{2}$']);
        } elseif ($periodType === 'quarterly') {
            // Format: "Q1-2025", "Q4-2025"
            return $query->whereRaw('period REGEXP ?', ['^Q[1-4]-[0-9]{4}$']);
        }

        return $query;
    }

    // ========== Statistics & Analytics Scopes ==========

    /**
     * Scope untuk mendapatkan statistik review employee
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $employeeId
     * @return array
     */
    public static function getEmployeeStatistics(int $employeeId): array
    {
        $reviews = static::where('employee_id', $employeeId)->get();

        if ($reviews->isEmpty()) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'overall_rating' => 'No Reviews Yet',
                'last_review_date' => null,
                'last_review_period' => null,
                'highest_rating' => 0,
                'lowest_rating' => 0,
            ];
        }

        $lastReview = $reviews->sortByDesc('created_at')->first();
        $averageRating = round($reviews->avg('total_star'), 1);

        // Konversi average rating ke kategori
        $overallRating = match(true) {
            $averageRating >= 9 => 'Outstanding',
            $averageRating >= 8 => 'Excellent',
            $averageRating >= 7 => 'Very Good',
            $averageRating >= 6 => 'Good',
            $averageRating >= 5 => 'Satisfactory',
            default => 'Needs Improvement',
        };

        return [
            'total_reviews' => $reviews->count(),
            'average_rating' => $averageRating,
            'overall_rating' => $overallRating,
            'last_review_date' => $lastReview->created_at?->format('Y-m-d'),
            'last_review_period' => $lastReview->period,
            'highest_rating' => $reviews->max('total_star'),
            'lowest_rating' => $reviews->min('total_star'),
        ];
    }

    /**
     * Scope untuk mendapatkan chart data monthly performance
     *
     * @param int $employeeId
     * @param string|null $year - Tahun yang ingin ditampilkan (default: tahun ini)
     * @return array
     */
    public static function getMonthlyChartData(int $employeeId, ?string $year = null): array
    {
        $year = $year ?? now()->format('Y');

        // Ambil semua review untuk tahun tersebut
        $reviews = static::where('employee_id', $employeeId)
            ->where('period', 'like', "{$year}-%")
            ->orderBy('period', 'asc')
            ->get();

        // Inisialisasi data untuk 12 bulan
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $chartData = [];

        foreach ($months as $index => $month) {
            $monthNumber = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            $period = "{$year}-{$monthNumber}";

            // Cari review untuk bulan ini
            $monthReviews = $reviews->filter(function($review) use ($period) {
                return $review->period === $period;
            });

            // Hitung rata-rata jika ada multiple review di bulan yang sama
            $averageRating = $monthReviews->isEmpty()
                ? null
                : round($monthReviews->avg('total_star'), 1);

            $chartData[] = [
                'month' => $month,
                'period' => $period,
                'rating' => $averageRating,
                'reviews_count' => $monthReviews->count(),
            ];
        }

        return [
            'year' => $year,
            'data' => $chartData,
        ];
    }

    /**
     * Scope untuk mendapatkan performance trend
     *
     * @param int $employeeId
     * @param int $limit - Jumlah periode terakhir yang ingin dihitung
     * @return array
     */
    public static function getPerformanceTrend(int $employeeId, int $limit = 3): array
    {
        $reviews = static::where('employee_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($reviews->count() < 2) {
            return [
                'trend' => 'stable',
                'message' => 'Not enough data to determine trend',
            ];
        }

        $latest = $reviews->first()->total_star;
        $previous = $reviews->slice(1)->avg('total_star');

        $difference = $latest - $previous;

        if ($difference > 1) {
            return [
                'trend' => 'improving',
                'message' => 'Performance is improving',
                'difference' => round($difference, 1),
            ];
        } elseif ($difference < -1) {
            return [
                'trend' => 'declining',
                'message' => 'Performance needs attention',
                'difference' => round($difference, 1),
            ];
        } else {
            return [
                'trend' => 'stable',
                'message' => 'Performance is stable',
                'difference' => round($difference, 1),
            ];
        }
    }
}
