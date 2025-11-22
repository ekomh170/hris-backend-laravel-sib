<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\User;
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
     * Get attendance history for logged in employee
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        $employeeId = $this->resolveAttendanceEmployeeId();

        $query = Attendance::where('employee_id', $employeeId);

        if ($yearMonth = $request->query('month')) {
            $query->inMonth($yearMonth);
        }

        $attendances = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $attendances,
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

        // If manager: limit to their team (existing)
        if ($user->isManager()) {
            $managerId = $user->id;
            $query->whereHas('employee', function ($employeeQuery) use ($managerId) {
                $employeeQuery->where('manager_id', $managerId);
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
