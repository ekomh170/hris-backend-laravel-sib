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
     * Get all performance reviews
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

        if ($user->isEmployee()) {
            abort_if(!$user->employee, 422, 'Profile employee belum tersedia');
            $query->where('employee_id', $user->employee->id);
        } elseif ($user->isManager()) {
            $query->where('reviewer_id', $user->id);
        }

        // Filter by employee_id
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter by period
        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }

        // Batasi per_page maksimal 100, default 10
        $perPage = min($request->query('per_page', 10), 100);
        $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        
        return response()->json([
            'success' => true,
            'message' => 'Performance review data retrieved successfully',
            'data' => [
                // Pagination info
                'current_page'    => $reviews->currentPage(),
                'per_page'        => $reviews->perPage(),
                'total'           => $reviews->total(),
                'last_page'       => $reviews->lastPage(),
                'from'            => $reviews->firstItem(),
                'to'              => $reviews->lastItem(),

                // Data Performance Review (sudah difilter oleh Resource)
                'data'            => PerformanceReviewResource::collection($reviews->items()),

                // Navigation URLs
                'first_page_url'  => $reviews->url(1),
                'last_page_url'   => $reviews->url($reviews->lastPage()),
                'next_page_url'   => $reviews->nextPageUrl(),
                'prev_page_url'   => $reviews->previousPageUrl(),
                'path'            => $reviews->path(),

                // Pagination links untuk UI
                'links'           => $reviews->linkCollection()->toArray(),
            ],
        ]); 
    }

    /**
     * Get my performance reviews (employee)
     *
     * @param Request $request
     * @return JsonResponse
     * - Employee biasa → lihat review dirinya
     * - Admin HR → juga bisa lihat review dirinya sendiri
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        // Tentukan employee_id yang dipakai
        $employeeId = $user->employee?->id ?? ($user->isAdminHr() ? $user->id : null);

        abort_if(is_null($employeeId), 422, 'Employee profile not available');

        $query = PerformanceReview::with(['employee.user', 'reviewer'])
            ->where('employee_id', $employeeId);

        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }

        $reviews = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Your performance reviews retrieved successfully',
            'data'    => PerformanceReviewResource::collection($reviews),
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
            abort(403, 'Anda tidak diperbolehkan membuat performance review untuk diri sendiri.');
        }

        // 2. Kalau target adalah Admin HR → HANYA Manager yang boleh buat
        if ($isTargetAdminHr) {
            abort_unless($user->isManager(), 403, 'Hanya Manager yang boleh membuat performance review untuk Admin HR.');
        }

        // 3. Kalau bukan Admin HR (karyawan biasa) → Admin HR & Manager boleh buat
        //    Tapi Manager hanya boleh buat untuk anak buahnya
        if ($user->isManager() && !$isTargetAdminHr) {
            abort_unless(
                $targetEmployee?->manager_id === $user->id,
                403, 'Anda hanya boleh membuat review untuk anggota tim Anda sendiri.'
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
            abort_if(!$user->employee, 422, 'Profile employee belum tersedia');
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
            abort(403, 'Anda tidak diperbolehkan mengubah performance review diri sendiri.');
        }

        // 2. Jika ini review untuk Admin HR → HANYA Manager yang boleh update
        if ($isReviewForAdminHr) {
            abort_unless($user->isManager(), 403, 'Hanya Manager yang boleh mengubah performance review Admin HR.');
        }

        // 3. Jika bukan review untuk Admin HR (karyawan biasa)
        //    → Manager hanya boleh update review yang DIA buat sendiri
        if ($user->isManager() && !$isReviewForAdminHr) {
            abort_unless(
                $review->reviewer_id === $user->id,
                403, 'Anda hanya boleh mengubah performance review yang Anda buat sendiri.'
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
            'message' => 'Performance review berhasil diperbarui.',
            'data'    => $review->fresh()->load(['employee.user', 'reviewer']),
        ]);
    }
       
    /**
     * Delete performance review (Admin only)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr(), 403, 'Forbidden');

        $review = PerformanceReview::findOrFail($id);
        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Performance review deleted successfully',
        ]);
    }
}
