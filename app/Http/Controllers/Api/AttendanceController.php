<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * Tentukan employee_id yang akan digunakan untuk absensi
     * - Karyawan biasa: pakai employee_id
     * - Admin HR (tanpa employee): pakai user_id sebagai employee_id
     */
    private function resolveAttendanceEmployeeId(): int
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        // Jika punya employee (karyawan biasa)
        if ($user->employee) {
            return $user->employee->id;
        }

        // Jika Admin HR, boleh absen pakai user_id sebagai employee_id
        if ($user->isAdminHr()) {
            return $user->id; // Pakai user_id langsung
        }

        // Jika bukan keduanya → abort
        abort(422, 'Employee profile not available');
    }

    /**
     * Check-in attendance
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkIn(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $employeeId = $this->resolveAttendanceEmployeeId();

        $today = now()->toDateString();

        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();

        if ($attendance && $attendance->check_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked in today',
                'data' => $attendance,
            ], 409);
        }

        if (!$attendance) {
            $attendance = Attendance::create([
                'employee_id' => $employeeId,
                'date' => $today,
                'check_in_time' => now(),
            ]);
        } else {
            $attendance->check_in_time = now();
            $attendance->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Check-in successful',
            'data' => $attendance,
        ]);
    }

    /**
     * Check-out attendance
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkOut(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $employeeId = $this->resolveAttendanceEmployeeId();

        $today = now()->toDateString();

        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have not checked in today',
            ], 422);
        }

        if ($attendance->check_out_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked out today',
                'data' => $attendance,
            ], 409);
        }

        $attendance->check_out_time = now();
        $attendance->computeWorkHour();
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'Check-out successful',
            'data' => $attendance,
        ]);
    }

    /**
     * Get attendance history for logged in employee (with smart date filter + pagination)
     *
     * Query Parameters:
     * - date: YYYY-MM (filter seluruh bulan) atau YYYY-MM-DD (filter tanggal spesifik)
     *         Contoh:
     *         ?date=2025-11     → seluruh November 2025
     *         ?date=2025-11-15  → hanya tanggal 15 November 2025
     * - per_page: 5|10|20|50 (default: 10)
     * - page: integer
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $employeeId = $this->resolveAttendanceEmployeeId();

        $query = Attendance::where('employee_id', $employeeId)
            ->with('employee.user')
            ->orderBy('date', 'desc');

        $inputDate = $request->query('date'); // Bisa YYYY-MM atau YYYY-MM-DD
        $appliedFilter = null;
        $messagePrefix = 'You have no attendance records';

        if ($inputDate) {
            // Validasi format dasar (YYYY-MM atau YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}$/', $inputDate)) {
                // Format YYYY-MM → filter seluruh bulan
                $query->whereYear('date', substr($inputDate, 0, 4))
                    ->whereMonth('date', substr($inputDate, 5, 2));
                $appliedFilter = $inputDate . ' (month)';
                $monthText = Carbon::createFromFormat('Y-m', $inputDate)->format('F Y');
                $messagePrefix = "No attendance records found in {$monthText}";

            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $inputDate)) {
                // Format YYYY-MM-DD → filter berdasarkan check_in atau check_out di tanggal itu
                $query->where(function ($q) use ($inputDate) {
                    $q->whereDate('check_in_time', $inputDate)
                    ->orWhereDate('check_out_time', $inputDate);
                });
                $appliedFilter = $inputDate . ' (specific date)';
                $dateText = Carbon::parse($inputDate)->format('d F Y');
                $messagePrefix = "No attendance records found on {$dateText}";
            }
            // Kalau format salah → akan diabaikan (bisa ditambah validasi kalau mau)
        }

        // Cek apakah ada data
        $totalFiltered = $query->count();

        if ($totalFiltered === 0) {
            return response()->json([
                'success' => true,
                'message' => $inputDate ? $messagePrefix . '.' : 'You have no attendance records yet.',
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
                        'date'     => $request->query('date'),
                        'per_page' => (int) $request->query('per_page', 10),
                    ]
                ]
            ]);
        }

        // Pagination
        $perPage     = min((int) $request->query('per_page', 10), 50);
        $attendances = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Your attendance history retrieved successfully',
            'data' => [
                // Pagination info
                'current_page'   => $attendances->currentPage(),
                'per_page'       => $attendances->perPage(),
                'total'          => $attendances->total(),
                'last_page'      => $attendances->lastPage(),
                'from'           => $attendances->firstItem(),
                'to'             => $attendances->lastItem(),

                // Data
                'data'           => AttendanceResource::collection($attendances->getCollection()),

                // Navigation
                'first_page_url' => $attendances->url(1),
                'last_page_url'  => $attendances->url($attendances->lastPage()),
                'next_page_url'  => $attendances->nextPageUrl(),
                'prev_page_url'  => $attendances->previousPageUrl(),
                'path'           => $attendances->path(),
                'links'          => $attendances->linkCollection()->toArray(),

                // Filters yang aktif
                'filters' => [
                    'date'     => $request->query('date'),
                    'per_page' => $perPage,
                ]
            ]
        ]);
    }

    /**
     * Get all attendances (Admin HR / Manager only)
     *
     * Query Parameters:
     * - search: string (pencarian global nama, email, employee_code, department, position)
     * - employee_id: integer (filter berdasarkan employee ID)
     * - month: string (filter berdasarkan bulan, format: YYYY-MM)
     * - department: string (filter berdasarkan departemen)
     * - min_work_hour: float (filter jam kerja minimum)
     * - max_work_hour: float (filter jam kerja maksimum)
     * - date: string (filter berdasarkan tanggal spesifik, format: YYYY-MM-DD)
     * - sort_by: string (date|employee_code|work_hour, default: date)
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

        $query = Attendance::with('employee.user');

        /* =================================
         * FITUR PENCARIAN & FILTER BARU
         * ================================= */

        // Pencarian global - mencari di nama, email, employee_code, department, position
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Filter by employee_id (existing)
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter by month (existing)
        if ($yearMonth = $request->query('month')) {
            $query->inMonth($yearMonth);
        }

        // Filter berdasarkan departemen
        if ($department = $request->query('department')) {
            $query->byDepartment($department);
        }

        // Filter berdasarkan range jam kerja
        $minWorkHour = $request->query('min_work_hour') ? (float) $request->query('min_work_hour') : null;
        $maxWorkHour = $request->query('max_work_hour') ? (float) $request->query('max_work_hour') : null;

        if ($minWorkHour !== null || $maxWorkHour !== null) {
            $query->byWorkHour($minWorkHour, $maxWorkHour);
        }

        // Filter berdasarkan tanggal spesifik
        if ($date = $request->query('date')) {
            $query->whereDate('date', $date);
        }

        // If manager: limit to their team (employees in their managed department)
        if ($user->isManager()) {
            $managerId = $user->id;
            $query->whereHas('employee.department', function ($deptQuery) use ($managerId) {
                $deptQuery->where('manager_id', $managerId);
            });
        }

        /* =================================
         * SORTING OPTIONS
         * ================================= */

        // Validasi sort_by parameter
        $allowedSortBy = ['date', 'employee_code', 'work_hour', 'created_at'];
        $sortBy = $request->query('sort_by', 'date');
        $sortBy = in_array($sortBy, $allowedSortBy) ? $sortBy : 'date';

        // Validasi sort_order parameter
        $sortOrder = $request->query('sort_order', 'desc');
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';

        // Apply sorting
        if ($sortBy === 'employee_code') {
            $query->join('employees', 'attendances.employee_id', '=', 'employees.id')
                  ->orderBy('employees.employee_code', $sortOrder)
                  ->select('attendances.*'); // Avoid column conflicts
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Secondary sort untuk konsistensi
        if ($sortBy !== 'date') {
            $query->orderBy('date', 'desc');
        }

        // Pagination
        $perPage = min($request->query('per_page', 10), 100);
        $attendances = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Attendance data retrieved successfully',
            'data' => [
                // Pagination info
                'current_page'   => $attendances->currentPage(),
                'per_page'       => $attendances->perPage(),
                'total'          => $attendances->total(),
                'last_page'      => $attendances->lastPage(),
                'from'           => $attendances->firstItem(),
                'to'             => $attendances->lastItem(),

                // Data Attendance (sudah difilter oleh Resource)
                'data'           => AttendanceResource::collection($attendances->getCollection()),

                // Navigation URLs
                'first_page_url' => $attendances->url(1),
                'last_page_url'  => $attendances->url($attendances->lastPage()),
                'next_page_url'  => $attendances->nextPageUrl(),
                'prev_page_url'  => $attendances->previousPageUrl(),
                'path'           => $attendances->path(),

                // Pagination links untuk UI
                'links'          => $attendances->linkCollection()->toArray(),

                // Filter info untuk frontend
                'filters' => [
                    'search' => $request->query('search'),
                    'employee_id' => $request->query('employee_id'),
                    'month' => $request->query('month'),
                    'department' => $request->query('department'),
                    'min_work_hour' => $minWorkHour,
                    'max_work_hour' => $maxWorkHour,
                    'date' => $request->query('date'),
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ],
        ]);
    }
}
