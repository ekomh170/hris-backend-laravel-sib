<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            'foto_cuti'  => 'nullable|file|mimes:jpg,jpeg,png|max:5120', // Max 5MB
        ]);

        $fotoFileName = null;
        if ($request->hasFile('foto_cuti')) {
            $file = $request->file('foto_cuti');
            $fotoFileName = $file->store('foto_cuti', 'public');
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employeeId,
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'reason'      => $data['reason'],
            'foto_cuti'   => $fotoFileName ? basename($fotoFileName) : null,
            'status'      => LeaveStatus::PENDING->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'data'    => new LeaveRequestResource($leaveRequest->load('employee.user')),
        ], 201);
    }

    /**
     * Update leave request
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId();
        $leaveRequest = LeaveRequest::findOrFail($id);

        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Authorization check: hanya employee sendiri atau admin HR yang boleh update
        $isOwnLeaveRequest = $leaveRequest->employee_id === $employeeId;
        $isAdminHr = $user->isAdminHr();

        abort_if(
            !$isOwnLeaveRequest && !$isAdminHr,
            403,
            'You can only update your own leave request'
        );

        // Hanya bisa update jika status masih PENDING
        abort_if(
            $leaveRequest->status !== LeaveStatus::PENDING,
            422,
            'Cannot update leave request that has already been reviewed'
        );

        $data = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'reason'     => 'nullable|string|max:1000',
            'foto_cuti'  => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        // Handle file update: delete old file jika ada file baru
        if ($request->hasFile('foto_cuti')) {
            // Delete old file if exists
            if ($leaveRequest->foto_cuti && Storage::disk('public')->exists('foto_cuti/' . $leaveRequest->foto_cuti)) {
                Storage::disk('public')->delete('foto_cuti/' . $leaveRequest->foto_cuti);
            }

            // Store new file
            $file = $request->file('foto_cuti');
            $fotoFileName = $file->store('foto_cuti', 'public');
            $data['foto_cuti'] = basename($fotoFileName);
        } else {
            // Remove foto_cuti from data if not provided
            unset($data['foto_cuti']);
        }

        $leaveRequest->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully',
            'data'    => $leaveRequest->load('employee.user'),
        ]);
    }

    /**
     * Delete leave request
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId();
        $leaveRequest = LeaveRequest::findOrFail($id);

        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Authorization check: hanya employee sendiri atau admin HR yang boleh delete
        $isOwnLeaveRequest = $leaveRequest->employee_id === $employeeId;
        $isAdminHr = $user->isAdminHr();

        abort_if(
            !$isOwnLeaveRequest && !$isAdminHr,
            403,
            'You can only delete your own leave request'
        );

        // Hanya bisa delete jika status masih PENDING
        abort_if(
            $leaveRequest->status !== LeaveStatus::PENDING,
            422,
            'Cannot delete leave request that has already been reviewed'
        );

        // Delete foto_cuti file if exists
        if ($leaveRequest->foto_cuti && Storage::disk('public')->exists('foto_cuti/' . $leaveRequest->foto_cuti)) {
            Storage::disk('public')->delete('foto_cuti/' . $leaveRequest->foto_cuti);
        }

        $leaveRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leave request deleted successfully',
        ]);
    }

    /**
     * Get leave requests for logged in employee (with pagination & filters)
     *
     * Query Parameters:
     * - status: pending|approved|rejected
     * - period: YYYY-MM (filter jika cuti tumpang tindih dengan bulan tersebut)
     * - per_page: 5|10|20|50 (default: 10)
     * - page: integer
     *
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId();

        $query = LeaveRequest::where('employee_id', $employeeId)
            ->with('employee.user', 'reviewer')
            ->orderByDesc('created_at');

        // === FILTER STATUS ===
        $appliedStatus = null;
        if ($statusParam = $request->query('status')) {
            $statusParam = strtolower(trim($statusParam));

            $statusMap = [
                'pending'   => LeaveStatus::PENDING->value,
                'approved'  => LeaveStatus::APPROVED->value,
                'rejected'  => LeaveStatus::REJECTED->value,
                'approve'   => LeaveStatus::APPROVED->value,
                'reject'    => LeaveStatus::REJECTED->value,
            ];

            if (array_key_exists($statusParam, $statusMap)) {
                $query->where('status', $statusMap[$statusParam]);
                $appliedStatus = $statusParam; // simpan untuk pesan
            }
        }

        // === FILTER PERIODE (BULAN)
        $appliedPeriod = null;
        if ($yearMonth = $request->query('period')) {
            $query->inPeriod($yearMonth);           
            $appliedPeriod = $yearMonth;
        }

        $totalAfterFilter = $query->count();

        // Cek apakah ada cuti sama sekali di bulan itu (tanpa filter status)
        $hasLeaveInPeriod = false;
        $monthText = '';
        if ($appliedPeriod) {
            $monthText = Carbon::createFromFormat('Y-m', $appliedPeriod)->format('F Y');
            $hasLeaveInPeriod = LeaveRequest::where('employee_id', $employeeId)
                ->inPeriod($appliedPeriod)         
                ->exists();
        }

        // === JIKA DATA KOSONG ===
        if ($totalAfterFilter === 0) {
            if ($appliedPeriod) {
                if (!$hasLeaveInPeriod) {
                    // Benar-benar TIDAK ADA cuti sama sekali di bulan itu
                    $message = "No leave requests found in {$monthText}.";
                } else {
                    // Ada cuti di bulan itu, tapi status yang dicari tidak ada
                    $statusText = $appliedStatus === 'approve' ? 'approved' :
                                ($appliedStatus === 'reject' ? 'rejected' : $appliedStatus);
                    $message = "No {$statusText} leave requests found in {$monthText}.";
                }
            } elseif ($appliedStatus) {
                $statusText = $appliedStatus === 'approve' ? 'approved' :
                            ($appliedStatus === 'reject' ? 'rejected' : $appliedStatus);
                $message = "No {$statusText} leave requests found.";
            } else {
                $message = "You have no leave requests yet.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => [
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
                    'links'          => [],
                    'filters' => [
                        'status'   => $request->query('status'),
                        'period'   => $request->query('period'),
                        'per_page' => (int) $request->query('per_page', 10),
                    ],
                ]
            ]);
        }

        // === JIKA ADA DATA → LANJUTKAN PAGINATION ===
        $perPage       = min((int) $request->query('per_page', 10), 50);
        $leaveRequests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Your leave requests retrieved successfully',
            'data'    => [
                'current_page'   => $leaveRequests->currentPage(),
                'per_page'       => $leaveRequests->perPage(),
                'total'          => $leaveRequests->total(),
                'last_page'      => $leaveRequests->lastPage(),
                'from'           => $leaveRequests->firstItem(),
                'to'             => $leaveRequests->lastItem(),
                'data'           => LeaveRequestResource::collection($leaveRequests->getCollection()),
                'first_page_url' => $leaveRequests->url(1),
                'last_page_url'  => $leaveRequests->url($leaveRequests->lastPage()),
                'next_page_url'  => $leaveRequests->nextPageUrl(),
                'prev_page_url'  => $leaveRequests->previousPageUrl(),
                'links'          => $leaveRequests->linkCollection()->toArray(),
                'filters' => [
                    'status'   => $request->query('status'),
                    'period'   => $request->query('period'),
                    'per_page' => $perPage,
                ],
            ]
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

    /**
     * Helper: Delete file foto_cuti
     */
    private function deleteFotoCutiFile(?string $fileName): void
    {
        if ($fileName && Storage::disk('public')->exists('foto_cuti/' . $fileName)) {
            Storage::disk('public')->delete('foto_cuti/' . $fileName);
        }
    }
}
