<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerformanceReviewResource;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PerformanceReviewController extends Controller
{
    /**
     * Get all performance reviews dengan fitur pencarian dan filter yang lengkap
     * Admin: semua review
     * Manager: review yang dia buat untuk timnya
     * Employee: review untuk dirinya sendiri
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $query = PerformanceReview::with(['employee.user', 'reviewer']);

        // Filter berdasarkan role (otorisasi yang sudah ada)
        if ($user->isEmployee()) {
            abort_if(!$user->employee, 422, 'Employee profile not available');
            $query->where('employee_id', $user->employee->id);
        } elseif ($user->isManager()) {
            $query->where('reviewer_id', $user->id);
        }

        // ========== Parameter Pencarian & Filter yang Ditingkatkan ==========

        // Pencarian global di berbagai field
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Filter berdasarkan karyawan tertentu (yang sudah ada)
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter berdasarkan reviewer tertentu (khusus Admin/Manager)
        if ($reviewerId = $request->query('reviewer_id')) {
            // Hanya boleh jika user bisa melihat review dari berbagai reviewer
            if ($user->isAdminHr()) {
                $query->where('reviewer_id', $reviewerId);
            }
        }

        // Filter berdasarkan periode (yang sudah ada, ditingkatkan)
        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }

        // Filter berdasarkan tahun
        if ($year = $request->query('year')) {
            $query->byYear($year);
        }

        // Filter berdasarkan tipe periode (bulanan/kuartalan)
        if ($periodType = $request->query('period_type')) {
            $query->byPeriodType($periodType);
        }

        // Filter berdasarkan departemen
        if ($department = $request->query('department')) {
            $query->byDepartment($department);
        }

        // Filter berdasarkan range rating
        $minRating = $request->query('min_rating');
        $maxRating = $request->query('max_rating');
        if ($minRating !== null || $maxRating !== null) {
            $query->byRatingRange(
                $minRating ? (int)$minRating : null,
                $maxRating ? (int)$maxRating : null
            );
        }

        // Filter berdasarkan range tanggal
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        if ($dateFrom || $dateTo) {
            $query->byDateRange($dateFrom, $dateTo);
        }

        // ========== Pengurutan ==========
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        // Validasi kolom pengurutan
        $allowedSortColumns = [
            'created_at', 'period', 'total_star', 'employee_name'
        ];

        if (in_array($sortBy, $allowedSortColumns)) {
            if ($sortBy === 'employee_name') {
                // Urutkan berdasarkan nama karyawan (relasi)
                $query->join('employees', 'performance_reviews.employee_id', '=', 'employees.id')
                      ->join('users', 'employees.user_id', '=', 'users.id')
                      ->orderBy('users.name', $sortOrder)
                      ->select('performance_reviews.*');
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ========== Paginasi ==========
        $perPage = min($request->query('per_page', 10), 100);
        $reviews = $query->paginate($perPage);

        // ========== Response yang Ditingkatkan dengan Metadata Filter ==========
        return response()->json([
            'success' => true,
            'message' => 'Performance reviews retrieved successfully',
            'data' => [
                // Info paginasi
                'current_page'    => $reviews->currentPage(),
                'per_page'        => $reviews->perPage(),
                'total'           => $reviews->total(),
                'last_page'       => $reviews->lastPage(),
                'from'            => $reviews->firstItem(),
                'to'              => $reviews->lastItem(),

                // Data Performance Review (sudah difilter oleh Resource)
                'data'            => PerformanceReviewResource::collection($reviews->items()),

                // URL navigasi
                'first_page_url'  => $reviews->url(1),
                'last_page_url'   => $reviews->url($reviews->lastPage()),
                'next_page_url'   => $reviews->nextPageUrl(),
                'prev_page_url'   => $reviews->previousPageUrl(),
                'path'            => $reviews->path(),

                // Link paginasi untuk UI
                'links'           => $reviews->linkCollection()->toArray(),

                // ========== BARU: Metadata Filter ==========
                'filters' => [
                    'search'        => $request->query('search'),
                    'employee_id'   => $request->query('employee_id'),
                    'reviewer_id'   => $request->query('reviewer_id'),
                    'period'        => $request->query('period'),
                    'year'          => $request->query('year'),
                    'period_type'   => $request->query('period_type'),
                    'department'    => $request->query('department'),
                    'min_rating'    => $request->query('min_rating'),
                    'max_rating'    => $request->query('max_rating'),
                    'date_from'     => $request->query('date_from'),
                    'date_to'       => $request->query('date_to'),
                    'sort_by'       => $sortBy,
                    'sort_order'    => $sortOrder,
                ],
            ],
        ]);
    }

    /**
     * Get my performance reviews (employee)
     *
     * @param Request $request
     * @return JsonResponse
     * * Query Parameters:
     * - period:
     *     • YYYY     → semua review di tahun itu
     *     • YYYY-MM  → semua review di tahun-bulan itu
     * - per_page: 5|10|20|50 (default: 10)
     * - page: integer
     * - Employee biasa → lihat review dirinya
     * - Admin HR → juga bisa lihat review dirinya sendiri
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_if(!$user->employee, 422, 'Employee profile not available');

        $query = PerformanceReview::where('employee_id', $user->employee->id)
            ->with(['employee.user', 'reviewer'])
            ->orderByDesc('created_at');

        $inputPeriod = $request->query('period');
        $appliedFilter = null;
        $message = 'You have no performance reviews yet.';

        if ($inputPeriod) {
            // 1. Hanya tahun: YYYY
            if (preg_match('/^\d{4}$/', $inputPeriod)) {
                $query->where('period', 'LIKE', $inputPeriod . '%');
                $appliedFilter = $inputPeriod;
                $message = "No performance reviews found in {$inputPeriod}.";
            }
            // 2. Tahun + periode/bulan: YYYY-Q1, YYYY-Q2, YYYY-06, dll
            elseif (preg_match('/^\d{4}[-\/](Q[1-4]|[0-1]?\d)$/', $inputPeriod)) {
                $query->where('period', $inputPeriod);
                $appliedFilter = $inputPeriod;
                $message = "No performance reviews found for period {$inputPeriod}.";
            }
            // Format lain → diabaikan (bisa ditambah validasi kalau mau)
        }

        // Cek total data setelah filter
        $totalFiltered = $query->count();

        if ($totalFiltered === 0) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'current_page'   => 1,
                    'per_page'       => (int) $request->query('per_page', 10),
                    'total'          => 0,
                    'last_page'      => 1,
                    'from'           => null,
                    'to'             => null,
                    'data'           => [],
                    'first_page_url' => $request->fullUrlWithQuery(['page' => 1]),
                    'last_page_url'  => $request->fullUrlWithQuery(['page' => 1]),
                    'next_page_url'  => null,
                    'prev_page_url'  => null,
                    'links'         => [],
                    'filters' => [
                        'period'   => $request->query('period'),
                        'per_page' => (int) $request->query('per_page', 10),
                    ],
                ]
            ]);
        }

        // Pagination
        $perPage = min((int) $request->query('per_page', 10), 50);
        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Your performance reviews retrieved successfully',
            'data' => [
                'current_page'   => $reviews->currentPage(),
                'per_page'       => $reviews->perPage(),
                'total'          => $reviews->total(),
                'last_page'      => $reviews->lastPage(),
                'from'           => $reviews->firstItem(),
                'to'             => $reviews->lastItem(),
                'data'           => PerformanceReviewResource::collection($reviews->getCollection()),
                'first_page_url' => $reviews->url(1),
                'last_page_url'  => $reviews->url($reviews->lastPage()),
                'next_page_url'  => $reviews->nextPageUrl(),
                'prev_page_url'  => $reviews->previousPageUrl(),
                'path'           => $reviews->path(),
                'links'          => $reviews->linkCollection()->toArray(),
                'filters' => [
                    'period'   => $request->query('period'),
                    'per_page' => $perPage,
                ],
            ]
        ]);
    }

    /**
     * Create new performance review (Manager/Admin only)
     *
     * @param Request $request
     * @return JsonResponse
     * Aturan:
     * - Admin HR TIDAK boleh buat review untuk dirinya sendiri
     * - Hanya Manager yang boleh buat review untuk Admin HR
     * - Admin HR & Manager boleh buat review untuk karyawan biasa
     */
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr() || $user->isManager(), 403, 'Forbidden');

        $validated = $request->validate([
            'employee_id'        => 'required|exists:employees,id',
            'period'             => 'required|string|max:20',
            'total_star'         => 'required|integer|min:1|max:10',
            'review_description' => 'required|string',
        ]);

        $employeeId = $validated['employee_id'];

        // === DETEKSI: Apakah ini review untuk Admin HR? ===
        $targetEmployee = \App\Models\Employee::with('user')->find($employeeId);
        $isTargetAdminHr = $targetEmployee?->user?->isAdminHr() ?? false;

        // 1. Kalau yang dibuat review adalah Admin HR sendiri → DILARANG!
        if ($employeeId == $user->id && $user->isAdminHr()) {
            abort(403, 'You are not allowed to create performance review for yourself.');
        }

        // 2. Kalau target adalah Admin HR → HANYA Manager yang boleh buat
        if ($isTargetAdminHr) {
            abort_unless($user->isManager(), 403, 'Only Manager can create performance review for Admin HR.');
        }

        // 3. Kalau bukan Admin HR (karyawan biasa) → Admin HR & Manager boleh buat
        //    Tapi Manager hanya boleh buat untuk anak buahnya
        if ($user->isManager() && !$isTargetAdminHr) {
            abort_unless(
                $targetEmployee?->manager_id === $user->id,
                403, 'You can only create review for your own team members.'
            );
        }

        // Semua lolos → buat review
        $review = PerformanceReview::create([
            'employee_id'        => $employeeId,
            'reviewer_id'        => $user->id,
            'period'             => $validated['period'],
            'total_star'         => $validated['total_star'],
            'review_description' => $validated['review_description'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Performance review created successfully',
            'data'    => $review->load(['employee.user', 'reviewer']),
        ], 201);
    }

    /**
     * Get specific performance review
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $review = PerformanceReview::with(['employee.user', 'reviewer'])->findOrFail($id);

        // Employee hanya bisa lihat review miliknya
        if ($user->isEmployee()) {
            abort_if(!$user->employee, 422, 'Employee profile not available');
            abort_unless($review->employee_id === $user->employee->id, 403, 'Forbidden');
        }

        // Manager hanya bisa lihat review yang dia buat
        if ($user->isManager()) {
            abort_unless($review->reviewer_id === $user->id, 403, 'Forbidden');
        }

        return response()->json([
            'success' => true,
            'data' => $review,
        ]);
    }

    /**
     * Update performance review (Manager/Admin only, hanya yang membuatnya)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    /**
     * Update performance review (Admin/Manager only)
     * Aturan baru:
     * - Admin HR TIDAK boleh update review milik dirinya sendiri
     * - Hanya Manager yang boleh update review milik Admin HR
     * - Manager hanya boleh update review yang DIA buat sendiri (untuk karyawan biasa)
     * - Admin HR boleh update review semua karyawan biasa
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr() || $user->isManager(), 403, 'Forbidden');

        $review = PerformanceReview::with('employee.user')->findOrFail($id);

        // === DETEKSI: Apakah ini review milik Admin HR? ===
        $isReviewForAdminHr = (
            $review->employee_id == $review->employee?->user?->id &&
            $review->employee?->user?->isAdminHr()
        );

        // 1. Admin HR TIDAK boleh update review milik dirinya sendiri
        if ($review->employee_id === $user->id && $user->isAdminHr()) {
            abort(403, 'You are not allowed to update your own performance review.');
        }

        // 2. Jika ini review untuk Admin HR → HANYA Manager yang boleh update
        if ($isReviewForAdminHr) {
            abort_unless($user->isManager(), 403, 'Only Manager can update Admin HR performance review.');
        }

        // 3. Jika bukan review untuk Admin HR (karyawan biasa)
        //    → Manager hanya boleh update review yang DIA buat sendiri
        if ($user->isManager() && !$isReviewForAdminHr) {
            abort_unless(
                $review->reviewer_id === $user->id,
                403, 'You can only update performance review that you created yourself.'
            );
        }

        // Admin HR boleh update semua review (kecuali milik dirinya sendiri) → sudah lolos di atas

        // === Validasi data ===
        $validated = $request->validate([
            'employee_id'        => 'sometimes|exists:employees,id',
            'period'             => 'sometimes|string|max:20',
            'total_star'         => 'sometimes|integer|min:1|max:10',
            'review_description' => 'sometimes|string',
        ]);

        $review->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Performance review updated successfully.',
            'data'    => $review->fresh()->load(['employee.user', 'reviewer']),
        ]);
    }

    /**
     * Delete performance review (Admin/Manager only)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr() || $user->isManager(), 403, 'Forbidden');

        $review = PerformanceReview::with('employee.user')->findOrFail($id);

        // Manager hanya bisa delete review yang dia buat sendiri
        if ($user->isManager()) {
            abort_unless($review->reviewer_id === $user->id, 403, 'You can only delete performance review that you created yourself.');
        }

        // Admin HR bisa delete semua review
        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Performance review deleted successfully',
        ]);
    }

    /**
     * Get performance reviews by specific employee
     * Menampilkan seluruh performance review dari satu karyawan tertentu
     * Lengkap dengan statistik, chart data, dan informasi department
     *
     * Authorization:
     * - Admin HR: Bisa lihat semua employee
     * - Manager: Bisa lihat employee yang pernah di-review
     * - Employee: Hanya bisa lihat diri sendiri (redirect ke /me)
     *
     * @param Request $request
     * @param int $employeeId
     * @return JsonResponse
     */
    public function showByEmployee(Request $request, int $employeeId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Cari employee beserta relasi user dan department
        $employee = \App\Models\Employee::with(['user', 'department'])
            ->findOrFail($employeeId);

        // ========== Authorization Logic ==========

        // Employee hanya bisa lihat review dirinya sendiri
        if ($user->isEmployee()) {
            abort_if(!$user->employee, 422, 'Employee profile not available');

            // Jika employee coba lihat review orang lain, redirect ke review dirinya
            if ($employeeId !== $user->employee->id) {
                return $this->showByEmployee($request, $user->employee->id);
            }
        }

        // Manager bisa lihat employee yang pernah di-review
        if ($user->isManager()) {
            // Cek apakah manager pernah review employee ini
            $hasReviewedBefore = PerformanceReview::where('employee_id', $employeeId)
                ->where('reviewer_id', $user->id)
                ->exists();

            abort_unless(
                $hasReviewedBefore,
                403,
                'You can only view performance reviews for employees you have reviewed.'
            );
        }

        // Admin HR bisa lihat semua employee (no restriction)

        // ========== Query Reviews ==========

        $query = PerformanceReview::where('employee_id', $employeeId)
            ->with(['employee.user', 'employee.department', 'reviewer'])
            ->orderByDesc('created_at');

        // Filter berdasarkan periode jika ada
        if ($period = $request->query('period')) {
            if (preg_match('/^\d{4}$/', $period)) {
                // Filter by year: 2025
                $query->where('period', 'LIKE', $period . '%');
            } elseif (preg_match('/^\d{4}[-\/](Q[1-4]|[0-1]?\d)$/', $period)) {
                // Filter by specific period: 2025-10 atau Q4-2025
                $query->where('period', $period);
            }
        }

        // Filter berdasarkan tahun untuk chart
        $year = $request->query('year', now()->format('Y'));

        // ========== Get Statistics ==========
        $statistics = PerformanceReview::getEmployeeStatistics($employeeId);

        // ========== Get Chart Data ==========
        $chartData = PerformanceReview::getMonthlyChartData($employeeId, $year);

        // ========== Get Performance Trend ==========
        $trend = PerformanceReview::getPerformanceTrend($employeeId, 3);

        // ========== Pagination ==========
        $perPage = min((int) $request->query('per_page', 10), 50);
        $reviews = $query->paginate($perPage);

        // ========== Response ==========
        return response()->json([
            'success' => true,
            'message' => 'Employee performance reviews retrieved successfully',
            'data' => [
                // Informasi Employee
                'employee' => [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->user?->name,
                    'email' => $employee->user?->email,
                    'position' => $employee->position,
                    'department' => [
                        'id' => $employee->department?->id,
                        'name' => $employee->department?->name,
                    ],
                    'join_date' => $employee->join_date?->format('Y-m-d'),
                    'employment_status' => $employee->employment_status?->value,
                ],

                // Statistik Performance
                'statistics' => $statistics,

                // Performance Trend
                'trend' => $trend,

                // Chart Data untuk Grafik Monthly
                'chart' => $chartData,

                // List Reviews (Paginated)
                'reviews' => [
                    'current_page' => $reviews->currentPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'last_page' => $reviews->lastPage(),
                    'from' => $reviews->firstItem(),
                    'to' => $reviews->lastItem(),
                    'data' => PerformanceReviewResource::collection($reviews->items()),
                    'first_page_url' => $reviews->url(1),
                    'last_page_url' => $reviews->url($reviews->lastPage()),
                    'next_page_url' => $reviews->nextPageUrl(),
                    'prev_page_url' => $reviews->previousPageUrl(),
                    'path' => $reviews->path(),
                    'links' => $reviews->linkCollection()->toArray(),
                ],

                // Applied Filters
                'filters' => [
                    'period' => $request->query('period'),
                    'year' => $year,
                    'per_page' => $perPage,
                ],
            ],
        ]);
    }
}
