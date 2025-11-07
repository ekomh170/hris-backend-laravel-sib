<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\LeaveRequest;
use App\Enums\LeaveStatus;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat data pengajuan cuti (Tim FWD Batch 3):
     * - Setiap employee punya 3 pengajuan cuti dengan status berbeda
     * - Status: Pending (belum di-review), Approved, Rejected
     * - Yang approved/rejected sudah di-review oleh Manager (Raka)
     *
     * Dependency: EmployeeSeeder (butuh employee_id), UserSeeder (butuh manager untuk reviewed_by)
     */
    public function run(): void
    {
        $employees = Employee::whereHas('user', function($query) {
            $query->where('role', 'employee');
        })->get();

        $manager = User::where('role', 'manager')->first();

        if ($employees->isEmpty()) {
            $this->command->error('❌ No employees found! Please run EmployeeSeeder first.');
            return;
        }

        if (!$manager) {
            $this->command->error('❌ No manager found! Please run UserSeeder first.');
            return;
        }

        $leaveRequests = [];

        $leaveRequests = [];
        $totalLeaves = 0;

        foreach ($employees as $employee) {
            // Cuti 1: Pending (belum di-review)
            $leaveRequests[] = [
                'employee_id' => $employee->id,
                'start_date' => Carbon::create(2025, 12, 20)->format('Y-m-d'),
                'end_date' => Carbon::create(2025, 12, 22)->format('Y-m-d'),
                'reason' => 'Liburan Natal bersama keluarga',
                'status' => LeaveStatus::PENDING,
                'reviewed_by' => null,
                'reviewer_note' => null,
            ];

            // Cuti 2: Approved
            $leaveRequests[] = [
                'employee_id' => $employee->id,
                'start_date' => Carbon::create(2025, 11, 15)->format('Y-m-d'),
                'end_date' => Carbon::create(2025, 11, 17)->format('Y-m-d'),
                'reason' => 'Keperluan keluarga',
                'status' => LeaveStatus::APPROVED,
                'reviewed_by' => $manager->id,
                'reviewer_note' => 'Disetujui. Selamat berlibur!',
            ];

            // Cuti 3: Rejected
            $leaveRequests[] = [
                'employee_id' => $employee->id,
                'start_date' => Carbon::create(2025, 11, 25)->format('Y-m-d'),
                'end_date' => Carbon::create(2025, 11, 27)->format('Y-m-d'),
                'reason' => 'Liburan ke Bali',
                'status' => LeaveStatus::REJECTED,
                'reviewed_by' => $manager->id,
                'reviewer_note' => 'Mohon maaf, periode ini tim sedang padat project deadline.',
            ];

            $totalLeaves += 3;
        }

        foreach ($leaveRequests as $leave) {
            LeaveRequest::create($leave);
        }

        $this->command->info("✅ {$totalLeaves} Leave Requests created successfully!");
    }
}
