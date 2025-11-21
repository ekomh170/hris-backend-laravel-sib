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

        abort(422, 'Employee profile not yet available');
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
            'message' => 'Pengajuan cuti berhasil dikirim',
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
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr() || $user->isManager(), 403, 'Forbidden');

        $query = LeaveRequest::with(['employee.user', 'reviewer']);

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by employee_id
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter by period (month)
        if ($yearMonth = $request->query('period')) {
            $query->inPeriod($yearMonth);
        }

        // If manager: limit to their team
        if ($user->isManager()) {
            $query->forManagerTeam($user->id);
        }

        // Batasi per_page maksimal 100, default 10
        $perPage = min($request->query('per_page', 10), 100);

        // Eksekusi query dengan pagination
        $leaveRequests = $query->orderByDesc('id')->paginate($perPage);

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
                'data'           => $resourceCollection,

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
            abort(403, 'Anda tidak diperbolehkan mereview cuti sendiri. Hanya Manager yang boleh.');
        }

        // 2. Manager hanya boleh review anak buahnya, KECUALI kalau cuti dari Admin HR
        if ($user->isManager()) {
            if (!$isLeaveFromAdminHr) {
                abort_unless(
                    $leaveRequest->employee?->manager_id === $user->id,
                    403,
                    'Anda hanya boleh mereview cuti anggota tim Anda sendiri.'
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
                ? 'Permohonan cuti telah disetujui.'
                : 'Permohonan cuti telah ditolak.';
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
