<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * Check-in attendance
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkIn(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $employee = $user->employee;

        abort_if(!$employee, 422, 'Employee profile not yet available');

        $today = now()->toDateString();

        // Cari data absen hari ini
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();
        
        // Kalau sudah ada data absen hari ini
        if ($attendance && $attendance->check_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked in today',
                'data' => $attendance,
            ], 409);
        }

        // Kalau belum ada data absen hari ini, buat baru
        if (!$attendance){
            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'check_in_time' => now(),
            ]);
        } else {
            // Kalau ada data absen tapi belum check-in (jarang terjadi)
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
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $employee = $user->employee;

        abort_if(!$employee, 422, 'Employee profile not yet available');

        $today = now()->toDateString();

        // Cari data absen hari ini
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        // Kalau belum ada data absen hari ini atau belum check-in
        if (!$attendance || !$attendance->check_in_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have not checked in today',
            ], 422);
        }

        // Kalau sudah check-out hari ini
        if ($attendance->check_out_time) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked out today',
                'data' => $attendance,
            ], 409);
        }

        // validasi check-out
        $attendance->check_out_time = now();
        $attendance->computeWorkHour(); // Otomatis hitung work_hour (dikurangi 1 jam break)
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
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $employee = $user->employee;

        abort_if(!$employee, 422, 'Employee profile not yet available');

        $query = Attendance::ofEmployee($employee->id);

        // Filter by month (optional)
        if ($yearMonth = $request->query('month')) {
            $query->inMonth($yearMonth);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('date', 'desc')->get(),
        ]);
    }

    /**
     * Get all attendances (Admin HR / Manager only)
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

        // Filter by employee_id
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter by month
        if ($yearMonth = $request->query('month')) {
            $query->inMonth($yearMonth);
        }

        // If manager: limit to their team
        if ($user->isManager()) {
            $managerId = $user->id;
            $query->whereHas('employee', function ($employeeQuery) use ($managerId) {
                $employeeQuery->where('manager_id', $managerId);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('date', 'desc')->paginate(20),
        ]);
    }
}
