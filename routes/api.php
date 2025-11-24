<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DashboardEmployeeController;
use App\Http\Controllers\Api\DashboardAdminController;
use App\Http\Controllers\Api\DashboardManagerController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\PerformanceReviewController;
use App\Http\Controllers\Api\SalarySlipController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth Routes
Route::prefix('auth')->group(function () {
    // Login dengan email & password, return JWT token
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        // Get data user yang sedang login
        Route::get('me', [AuthController::class, 'me']);

        // Logout & invalidate JWT token
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Protected Routes
Route::middleware(['auth:api'])->group(function () {

    // ========== Dashboard Employee ==========
    // Dashboard overview untuk employee (Admin HR, Employee)
    Route::get('dashboard/employee', [DashboardEmployeeController::class, 'index'])
        ->middleware('role:admin_hr,employee');

    // ========== Dashboard Admin HR ==========
    // Dashboard overview untuk admin HR (Admin HR only)
    Route::get('dashboard/admin', [DashboardAdminController::class, 'index'])
        ->middleware('role:admin_hr');

    // ========== Dashboard Manager ==========
    // Dashboard overview untuk manager (Manager only)
    Route::get('dashboard/manager', [DashboardManagerController::class, 'index'])
        ->middleware('role:manager');

    // ========== Employees ==========
    // Get list of managers (Admin HR & Manager only)
    Route::get('employees/managers', [EmployeeController::class, 'getManagers'])
        ->middleware('role:admin_hr,manager');

    // List semua karyawan (Admin HR & Manager only)
    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('role:admin_hr,manager');

    // Tambah karyawan baru (Admin HR only)
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('role:admin_hr');

    // Detail karyawan by ID
    Route::get('employees/{id}', [EmployeeController::class, 'show'])
        ->middleware('role:admin_hr,manager,employee');

    // Update data karyawan (Admin HR only)
    Route::put('employees/{id}', [EmployeeController::class, 'update'])
        ->middleware('role:admin_hr');

    // Hapus karyawan (Admin HR only)
    Route::delete('employees/{id}', [EmployeeController::class, 'destroy'])
        ->middleware('role:admin_hr');

    // ========== Attendances ==========
    // Absen masuk dengan lokasi & waktu
    Route::post('attendances/check-in', [AttendanceController::class, 'checkIn'])
        ->middleware('role:employee,admin_hr');

    // Absen pulang dengan waktu
    Route::post('attendances/check-out', [AttendanceController::class, 'checkOut'])
        ->middleware('role:employee,admin_hr');

    // History absensi user yang login
    Route::get('attendances/me', [AttendanceController::class, 'me'])
        ->middleware('role:employee,admin_hr');

    // List semua absensi (Admin HR & Manager only)
    Route::get('attendances', [AttendanceController::class, 'index'])
        ->middleware('role:admin_hr,manager');

    // ========== Leave Requests ==========
    // Ajukan permohonan cuti
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])
        ->middleware('role:employee,admin_hr');

    // Riwayat cuti user yang login
    Route::get('leave-requests/me', [LeaveRequestController::class, 'me'])
        ->middleware('role:employee,admin_hr');

    // List semua permohonan cuti (Admin HR & Manager)
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])
        ->middleware('role:admin_hr,manager');

    // Review (approve/reject) permohonan cuti (Admin HR & Manager only)
    Route::patch('leave-requests/{id}/review', [LeaveRequestController::class, 'review'])
    ->middleware('role:admin_hr,manager');

    // ========== Performance Reviews ==========
    // List semua review kinerja
    Route::get('performance-reviews', [PerformanceReviewController::class, 'index'])
        ->middleware('role:admin_hr,manager,employee');

    // Buat review kinerja baru (Admin HR & Manager only)
    Route::post('performance-reviews', [PerformanceReviewController::class, 'store'])
        ->middleware('role:admin_hr,manager');

    // Review kinerja user yang login
    Route::get('performance-reviews/me', [PerformanceReviewController::class, 'me'])
        ->middleware('role:admin_hr,employee');

    // Detail review kinerja by ID
    Route::get('performance-reviews/{id}', [PerformanceReviewController::class, 'show'])
        ->middleware('role:admin_hr,manager,employee');

    // Update review kinerja (Admin HR & Manager only)
    Route::put('performance-reviews/{id}', [PerformanceReviewController::class, 'update'])
        ->middleware('role:admin_hr,manager');

    // Hapus review kinerja (Admin HR & Manager only)
    Route::delete('performance-reviews/{id}', [PerformanceReviewController::class, 'destroy'])
        ->middleware('role:admin_hr,manager');

    // ========== Salary Slips ==========
    // Slip gaji user yang login
    Route::get('salary-slips/me', [SalarySlipController::class, 'me'])
        ->middleware('role:employee,admin_hr');

    // List semua slip gaji (admin HR only)
    Route::get('salary-slips', [SalarySlipController::class, 'index'])
        ->middleware('role:admin_hr');

    // Buat slip gaji baru (Admin HR only)
    Route::post('salary-slips', [SalarySlipController::class, 'store'])
        ->middleware('role:admin_hr');

    // Detail slip gaji by ID
    Route::get('salary-slips/{id}', [SalarySlipController::class, 'show'])
        ->middleware('role:admin_hr,manager,employee');

    // Update slip gaji (Admin HR only)
    Route::put('salary-slips/{id}', [SalarySlipController::class, 'update'])
        ->middleware('role:admin_hr');

    // Hapus slip gaji (Admin HR only)
    Route::delete('salary-slips/{id}', [SalarySlipController::class, 'destroy'])
        ->middleware('role:admin_hr');

    // ========== Notifications ==========
    // Notifikasi user yang login
    Route::get('notifications/me', [NotificationController::class, 'me'])
        ->middleware('role:admin_hr,manager,employee');

    // Notifikasi belum dibaca
    Route::get('notifications/unread', [NotificationController::class, 'unread'])
        ->middleware('role:admin_hr,manager,employee');

    // Tandai semua notifikasi sudah dibaca
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->middleware('role:admin_hr,manager,employee');

    // List semua notifikasi
    Route::get('notifications', [NotificationController::class, 'index'])
        ->middleware('role:admin_hr,manager,employee');

    // Buat notifikasi personal (Admin HR only)
    Route::post('notifications', [NotificationController::class, 'store'])
        ->middleware('role:admin_hr');

    // Broadcast notifikasi ke semua user (Admin HR only)
    Route::post('notifications/broadcast', [NotificationController::class, 'broadcast'])
        ->middleware('role:admin_hr');

    // Detail notifikasi by ID
    Route::get('notifications/{id}', [NotificationController::class, 'show'])
        ->middleware('role:admin_hr,manager,employee');

    // Tandai notifikasi sudah dibaca
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->middleware('role:admin_hr,manager,employee');

    // Hapus notifikasi
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])
        ->middleware('role:admin_hr,manager,employee');
});
