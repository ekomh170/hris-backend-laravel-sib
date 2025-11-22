<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LeaveRequestController extends Controller
{
    // Helper: dapatkan employee_id (sama seperti di AttendanceController)
    private function resolveEmployeeId(): int
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Karyawan biasa
        if ($user->employee) {
            return $user->employee->id;
        }

        // Admin HR boleh pakai user_id sebagai employee_id untuk cuti pribadi
        if ($user->isAdminHr()) {
            return $user->id;
        }

        abort(422, 'Employee profile not available');
    }



    /**
     * Store a newly created leave request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId();

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|string|max:1000',
        ]);

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employeeId,
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'reason'      => $data['reason'],
            'status'      => LeaveStatus::PENDING->value, // Konsisten pakai enum
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'data'    => $leaveRequest->load('employee.user'),
        ], 201);
    }

    /**
     * Get leave requests for logged in employee
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId();

        $leaveRequests = LeaveRequest::where('employee_id', $employeeId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => LeaveRequestResource::collection($leaveRequests),
        ]);
    }

    /**
     * Get all leave requests (Admin HR / Manager only)
     *
     * Query Parameters:
     * - search: string (pencarian global nama, email, employee_code, department, position, reason)
     * - status: string (filter berdasarkan status: Pending, Approved, Rejected)
     * - employee_id: integer (filter berdasarkan employee ID)
     * - period: string (filter berdasarkan bulan, format: YYYY-MM)
     * - department: string (filter berdasarkan departemen)
     * - date_from: string (filter tanggal mulai cuti, format: YYYY-MM-DD)
     * - date_to: string (filter tanggal akhir cuti, format: YYYY-MM-DD)
     * - min_duration: integer (filter durasi minimum cuti dalam hari)
     * - max_duration: integer (filter durasi maksimum cuti dalam hari)
     * - sort_by: string (start_date|end_date|status|created_at|employee_name, default: created_at)
     * - sort_order: string (asc|desc, default: desc)
     * - per_page: integer (1-100, default: 10)
     * - page: integer (default: 1)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr() || $user->isManager(), 403, 'Forbidden');

        $query = LeaveRequest::with(['employee.user', 'reviewer']);

        /* =================================
         * FITUR PENCARIAN & FILTER BARU
         * ================================= */

        // Pencarian global - mencari di nama, email, employee_code, department, position, reason
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Filter by status (existing, enhanced)
        if ($status = $request->query('status')) {
            $query->byStatus($status);
        }

        // Filter by employee_id (existing)
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter by period (month) (existing)
        if ($yearMonth = $request->query('period')) {
            $query->inPeriod($yearMonth);
        }

        // Filter berdasarkan departemen
        if ($department = $request->query('department')) {
            $query->byDepartment($department);
        }

        // Filter berdasarkan range tanggal cuti
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        if ($dateFrom || $dateTo) {
            $query->byDateRange($dateFrom, $dateTo);
        }

        // Filter berdasarkan durasi cuti
        $minDuration = $request->query('min_duration') ? (int) $request->query('min_duration') : null;
        $maxDuration = $request->query('max_duration') ? (int) $request->query('max_duration') : null;
        if ($minDuration !== null || $maxDuration !== null) {
            $query->byDuration($minDuration, $maxDuration);
        }

        // If manager: limit to their team (existing)
        if ($user->isManager()) {
            $query->forManagerTeam($user->id);
        }

        /* =================================
         * SORTING OPTIONS
         * ================================= */

        // Validasi sort_by parameter
        $allowedSortBy = ['start_date', 'end_date', 'status', 'created_at', 'employee_name'];
        $sortBy = $request->query('sort_by', 'created_at');
        $sortBy = in_array($sortBy, $allowedSortBy) ? $sortBy : 'created_at';

        // Validasi sort_order parameter
        $sortOrder = $request->query('sort_order', 'desc');
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';

        // Apply sorting
        if ($sortBy === 'employee_name') {
            $query->join('employees', 'leave_requests.employee_id', '=', 'employees.id')
                  ->join('users', 'employees.user_id', '=', 'users.id')
                  ->orderBy('users.name', $sortOrder)
                  ->select('leave_requests.*'); // Avoid column conflicts
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Secondary sort untuk konsistensi
        if ($sortBy !== 'created_at') {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->query('per_page', 10), 100);

        // Eksekusi query dengan pagination
        $leaveRequests = $query->paginate($perPage);

        // Transform items menggunakan Resource
        $resourceCollection = LeaveRequestResource::collection($leaveRequests->items());

        return response()->json([
            'success' => true,
            'message' => 'Leave requests retrieved successfully',
            'data' => [
                // Pagination info
                'current_page'   => $leaveRequests->currentPage(),
                'per_page'       => $leaveRequests->perPage(),
                'total'          => $leaveRequests->total(),
                'last_page'      => $leaveRequests->lastPage(),
                'from'           => $leaveRequests->firstItem(),
                'to'             => $leaveRequests->lastItem(),

                // Data Leave Request (sudah difilter oleh Resource)
                'data'            => LeaveRequestResource::collection($leaveRequests->items()),

                // Navigation URLs
                'first_page_url' => $leaveRequests->url(1),
                'last_page_url'  => $leaveRequests->url($leaveRequests->lastPage()),
                'next_page_url'  => $leaveRequests->nextPageUrl(),
                'prev_page_url'  => $leaveRequests->previousPageUrl(),
                'path'           => $leaveRequests->path(),

                // Links untuk frontend (seperti Laravel default)
                'links'          => $leaveRequests->linkCollection()->toArray(),

                // Filter info untuk frontend
                'filters' => [
                    'search' => $request->query('search'),
                    'status' => $request->query('status'),
                    'employee_id' => $request->query('employee_id'),
                    'period' => $request->query('period'),
                    'department' => $request->query('department'),
                    'date_from' => $request->query('date_from'),
                    'date_to' => $request->query('date_to'),
                    'min_duration' => $minDuration,
                    'max_duration' => $maxDuration,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ]
        ]);
    }

        /**
     * Review (Approve/Reject) leave request
     *
     * Aturan baru:
     * - Admin HR TIDAK BOLEH review cuti sendiri
     * - Hanya Manager yang boleh review cuti Admin HR
     * - Admin HR boleh review cuti karyawan biasa
     * - Manager hanya boleh review anak buahnya
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function review(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr() || $user->isManager(), 403, 'Forbidden');

        $leaveRequest = LeaveRequest::with('employee.user')->findOrFail($id);

        // === DETEKSI: Apakah ini cuti milik Admin HR? ===
        $isLeaveFromAdminHr = (
            $leaveRequest->employee_id == $leaveRequest->employee?->user?->id &&
            $leaveRequest->employee?->user?->isAdminHr()
        );

        // 1. Admin HR tidak boleh review cuti sendiri
        if ($leaveRequest->employee_id === $user->id && $user->isAdminHr()) {
            abort(403, 'You are not allowed to review your own leave request. Only Manager can review it.');
        }

        // 2. Manager hanya boleh review anak buahnya, KECUALI kalau cuti dari Admin HR
        if ($user->isManager()) {
            if (!$isLeaveFromAdminHr) {
                abort_unless(
                    $leaveRequest->employee?->manager_id === $user->id,
                    403,
                    'You can only review leave requests from your own team members.'
                );
            }
        }

        // === Validasi input ===
        $request->validate([
            'status'        => 'required|in:approve,reject',
            'reviewer_note' => 'nullable|string|max:500',
        ]);

        $statusInput = $request->input('status');
        $newStatus   = $statusInput === 'approve' ? 'Approved' : 'Rejected';

        // === Tentukan reviewer_note otomatis kalau kosong ===
        $note = $request->input('reviewer_note');
        if (empty($note)) {
            $note = $newStatus === 'Approved'
                ? 'Leave request has been approved.'
                : 'Leave request has been rejected.';
        }

        // === Update data ===
        $leaveRequest->update([
            'status'        => $newStatus,
            'reviewed_by'   => $user->id,
            'reviewed_at'   => now(),
            'reviewer_note' => $note, // ← Sekarang pasti terisi!
        ]);

        // === Response ===
        return response()->json([
            'success' => true,
            'message' => $note, // ← Pesan sesuai yang diisi / otomatis
            'data'    => $leaveRequest->load('employee.user', 'reviewer'),
        ]);
    }
}
