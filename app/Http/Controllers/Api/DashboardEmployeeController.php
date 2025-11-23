<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardEmployeeController extends Controller
{
    /**
     * Dashboard Employee - Overview data untuk employee
     *
     * SECTION 1 – Ringkasan Info Personal
     * - Card: Departemen Saya
     * - Card: Manager Saya
     *
     * SECTION 2 – Ringkasan Kehadiran
     * - Card: Total Jam Kerja (Bulan Ini)
     * - Card: Hari Hadir (Bulan Ini)
     * - Chart: Tren Jam Kerja Harian Saya
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        // Pastikan user adalah employee
        if (!$user || !$user->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Employee role required.'
            ], 403);
        }

        // Pastikan punya profile employee
        $employee = $user->employee;
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found.'
            ], 422);
        }

        // === SECTION 1: Ringkasan Info Personal ===
        $personalInfo = [
            'my_department' => $employee->department ?? 'Not Assigned',
            'my_manager' => $employee->manager?->name ?? 'No Manager Assigned',
        ];

        // === SECTION 2: Ringkasan Kehadiran (Bulan Ini) ===
        $now = Carbon::now();
        $yearMonth = $now->format('Y-m');

        // Ambil data kehadiran untuk bulan ini
        $attendances = Attendance::where('employee_id', $employee->id)
            ->inMonth($yearMonth)
            ->orderBy('date')
            ->get();

        // Hitung total jam kerja dan hari hadir
        $totalWorkHours = $attendances->sum(function ($attendance) {
            // Konversi work_hour dari format HH:MM ke decimal jika perlu
            $workHour = $attendance->getOriginal('work_hour') ?? 0;
            return is_numeric($workHour) ? (float) $workHour : 0;
        });

        $daysPresent = $attendances->whereNotNull('check_in_time')->count();

        // Siapkan data chart harian
        $dailyChart = $attendances->map(function ($attendance) {
            $workHour = $attendance->getOriginal('work_hour') ?? 0;
            $workHourDecimal = is_numeric($workHour) ? (float) $workHour : 0;

            return [
                'date' => Carbon::parse($attendance->date)->format('Y-m-d'),
                'work_hours' => round($workHourDecimal, 2),
                'work_hours_formatted' => $attendance->work_hour, // Format HH:MM
                'status' => $attendance->check_in_time ? 'present' : 'absent'
            ];
        })->values()->toArray();

        // Cards ringkasan kehadiran
        $attendanceSummary = [
            'total_work_hours_this_month' => round($totalWorkHours, 2),
            'days_present_this_month' => $daysPresent,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Employee dashboard data retrieved successfully',
            'data' => [
                'personal_info' => $personalInfo,
                'attendance_summary' => [
                    'cards' => $attendanceSummary,
                    'chart_work_hours_daily' => $dailyChart,
                ],
                'period_info' => [
                    'current_month' => $now->format('F Y'),
                    'year_month' => $yearMonth,
                ]
            ]
        ]);
    }
}
