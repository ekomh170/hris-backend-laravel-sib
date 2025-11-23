<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PerformanceReview;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardManagerController extends Controller
{
    /**
     * Dashboard Manager - Overview data untuk manager
     *
     * SECTION 1 – Ringkasan Kehadiran Tim
     * - Card: Total Record Kehadiran (Tim)
     * - Card: Rata-rata Jam Kerja (Tim)
     * - Chart: Jam Kerja per Karyawan (Bar Chart)
     *
     * SECTION 2 – Ringkasan Performa Tim
     * - Card: Rata-rata Rating Tim
     * - Card: Total Review (Periode Saat Ini)
     * - Chart: Tren Rating per Periode
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        // Pastikan user adalah manager
        if (!$user || !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Manager role required.'
            ], 403);
        }

        $managerId = $user->id;
        
        // Ambil data tim yang dikelola manager
        $teamMembers = Employee::managedBy($managerId)->with('user')->get();
        
        if ($teamMembers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No team members found for this manager.'
            ], 422);
        }

        $teamEmployeeIds = $teamMembers->pluck('id')->toArray();
        $now = Carbon::now();
        $yearMonth = $now->format('Y-m');
        $currentPeriod = $yearMonth; // Format: 2025-11

        // === SECTION 1: Ringkasan Kehadiran Tim ===
        
        // Total Record Kehadiran Tim (bulan ini)
        $totalAttendanceRecords = Attendance::whereIn('employee_id', $teamEmployeeIds)
            ->inMonth($yearMonth)
            ->count();
        
        // Rata-rata Jam Kerja Tim (bulan ini)
        $avgTeamWorkHours = Attendance::whereIn('employee_id', $teamEmployeeIds)
            ->inMonth($yearMonth)
            ->avg('work_hour') ?? 0;

        // Jam Kerja per Karyawan (Bar Chart) - bulan ini
        $workHoursPerEmployee = Attendance::with('employee.user')
            ->whereIn('employee_id', $teamEmployeeIds)
            ->inMonth($yearMonth)
            ->select(
                'employee_id',
                DB::raw('SUM(work_hour) as total_hours'),
                DB::raw('COUNT(*) as attendance_days'),
                DB::raw('AVG(work_hour) as avg_hours_per_day')
            )
            ->groupBy('employee_id')
            ->get()
            ->map(function ($item) {
                return [
                    'employee_id' => $item->employee_id,
                    'employee_name' => $item->employee->user->name ?? 'Unknown',
                    'total_hours' => round($item->total_hours, 2),
                    'attendance_days' => $item->attendance_days,
                    'avg_hours_per_day' => round($item->avg_hours_per_day, 2),
                ];
            })->toArray();

        // === SECTION 2: Ringkasan Performa Tim ===

        // Rata-rata Rating Tim (semua review yang dibuat manager)
        $avgTeamRating = PerformanceReview::where('reviewer_id', $managerId)
            ->avg('total_star') ?? 0;

        // Total Review Periode Saat Ini
        $totalReviewsCurrentPeriod = PerformanceReview::where('reviewer_id', $managerId)
            ->where('period', 'LIKE', $yearMonth . '%')
            ->count();

        // Tren Rating per Periode (6 periode terakhir)
        $ratingTrendPeriods = PerformanceReview::where('reviewer_id', $managerId)
            ->select(
                'period',
                DB::raw('AVG(total_star) as avg_rating'),
                DB::raw('COUNT(*) as review_count')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->limit(6)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'avg_rating' => round($item->avg_rating, 2),
                    'review_count' => $item->review_count,
                ];
            })->toArray();

        // Ringkasan cards kehadiran
        $attendanceOverview = [
            'total_attendance_records_team' => $totalAttendanceRecords,
            'average_work_hours_team' => round($avgTeamWorkHours, 2),
        ];

        // Ringkasan cards performa
        $performanceOverview = [
            'average_team_rating' => round($avgTeamRating, 2),
            'total_reviews_current_period' => $totalReviewsCurrentPeriod,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Manager dashboard data retrieved successfully',
            'data' => [
                'attendance_overview' => [
                    'cards' => $attendanceOverview,
                    'chart_work_hours_per_employee' => $workHoursPerEmployee,
                ],
                'performance_overview' => [
                    'cards' => $performanceOverview,
                    'chart_rating_trend_over_periods' => $ratingTrendPeriods,
                ],
                'team_info' => [
                    'team_size' => $teamMembers->count(),
                    'manager_name' => $user->name,
                    'current_period' => $currentPeriod,
                ],
                'period_info' => [
                    'current_month' => $now->format('F Y'),
                    'year_month' => $yearMonth,
                ]
            ]
        ]);
    }
}