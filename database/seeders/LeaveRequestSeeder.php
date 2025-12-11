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

        // Ambil salah satu manager untuk jadi reviewer
        $manager = User::where('role', 'manager')->first() 
                ?? User::where('email', 'admin@hris.com')->first();

        if ($employees->isEmpty()) {
            $this->command->warn('No employees found! Please run EmployeeSeeder first.');
            return;
        }

        if (!$manager) {
            $this->command->error('❌ No manager found! Please run UserSeeder first.');
            return;
        }

        $leaveRequests = [];

        $leaveRequests = [];
        $totalLeaves = 0;

        // Array alasan cuti yang bervariasi
        $leaveReasons = [
            'Liburan bersama keluarga',
            'Keperluan keluarga urgent',
            'Sakit demam',
            'Menikah (pernikahan adik)',
            'Mudik lebaran',
            'Liburan ke Bali',
            'Checkup kesehatan',
            'Acara keluarga besar',
            'Istirahat karena kelelahan',
            'Liburan akhir tahun',
            'Umrah bersama keluarga',
            'Honeymoon',
            'Melahirkan (istri)',
            'Renovasi rumah',
            'Wisuda anak'
        ];

        // Array catatan reviewer yang bervariasi
        $approvedNotes = [
            'Disetujui. Selamat berlibur!',
            'Approved. Enjoy your time off!',
            'Disetujui. Semoga lekas sembuh.',
            'Selamat! Semoga lancar acaranya.',
            'Disetujui. Selamat mudik!',
            'Approved. Have a great vacation!',
            'Disetujui. Jaga kesehatan ya.',
            'Selamat berlibur bersama keluarga.'
        ];

        $rejectedNotes = [
            'Mohon maaf, periode ini tim sedang padat project deadline.',
            'Sorry, tidak bisa approve karena ada meeting penting.',
            'Maaf, bulan ini sudah banyak yang cuti.',
            'Mohon reschedule ke bulan depan.',
            'Tim sedang short-handed, coba bulan depan.',
            'Periode end of month, mohon dijadwal ulang.',
            'Ada project urgent, mohon pengertiannya.'
        ];

        // Target months (September, Oktober, November 2025)
        $targetMonths = [9, 10, 11];

        foreach ($employees as $employee) {
            // Generate 8-12 leave requests per employee untuk 3 bulan
            $numRequests = rand(8, 12);

            // Employee leave pattern (some take more leaves, some less)
            $leaveFrequency = rand(1, 3); // 1=frequent, 2=moderate, 3=rare

            for ($i = 0; $i < $numRequests; $i++) {
                // Focus on Sep-Nov 2025 with some in Dec for variety
                $monthWeights = [9 => 30, 10 => 35, 11 => 25, 12 => 10]; // September-November focus
                $randomMonth = $this->getWeightedRandomMonth($monthWeights);

                // Different day patterns based on month
                if ($randomMonth == 9) { // September - back to work season
                    $startDay = rand(1, 30);
                } elseif ($randomMonth == 10) { // October - mid season
                    $startDay = rand(1, 31);
                } elseif ($randomMonth == 11) { // November - holiday prep
                    $startDay = rand(1, 30);
                } else { // December
                    $startDay = rand(1, 25); // Avoid New Year
                }

                $startDate = Carbon::create(2025, $randomMonth, $startDay);

                // Variasi durasi based on leave frequency and month
                if ($leaveFrequency == 1) { // Frequent leave taker
                    $duration = rand(1, 3); // Shorter leaves
                } elseif ($leaveFrequency == 2) { // Moderate
                    $duration = rand(1, 5); // Mixed duration
                } else { // Rare leave taker
                    $duration = rand(3, 7); // Longer leaves when taken
                }

                // Special duration for holiday months
                if ($randomMonth == 11 || $randomMonth == 12) {
                    $duration = rand(2, 8); // Longer holiday leaves
                }

                $endDate = $startDate->copy()->addDays($duration - 1);

                // Handle weekends more smartly
                if ($startDate->isWeekend()) {
                    // Some people plan around weekends, others don't
                    if (rand(1, 100) <= 70) { // 70% avoid weekend starts
                        $startDate = $startDate->next(Carbon::MONDAY);
                        $endDate = $startDate->copy()->addDays($duration - 1);
                    }
                }

                // Determine status (40% pending, 35% approved, 25% rejected)
                $statusRand = rand(1, 100);
                if ($statusRand <= 40) {
                    $status = LeaveStatus::PENDING;
                    $reviewedBy = null;
                    $reviewerNote = null;
                } elseif ($statusRand <= 75) {
                    $status = LeaveStatus::APPROVED;
                    $reviewedBy = $manager->id;
                    $reviewerNote = $approvedNotes[array_rand($approvedNotes)];
                } else {
                    $status = LeaveStatus::REJECTED;
                    $reviewedBy = $manager->id;
                    $reviewerNote = $rejectedNotes[array_rand($rejectedNotes)];
                }

                $leaveRequests[] = [
                    'employee_id' => $employee->id,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'reason' => $leaveReasons[array_rand($leaveReasons)],
                    'status' => $status,
                    'reviewed_by' => $reviewedBy,
                    'reviewer_note' => $reviewerNote,
                    'created_at' => $startDate->copy()->subDays(rand(1, 10)), // Applied 1-10 days before
                    'updated_at' => $reviewedBy ? $startDate->copy()->subDays(rand(0, 5)) : null
                ];

                $totalLeaves++;
            }
        }

        foreach ($leaveRequests as $leave) {
            LeaveRequest::create($leave);
        }

        $this->command->info("✅ {$totalLeaves} Leave Requests created successfully!");
    }

    /**
     * Get weighted random month based on probability weights
     */
    private function getWeightedRandomMonth(array $weights): int
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($weights as $month => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $month;
            }
        }

        return 9; // Fallback to September
    }
}
