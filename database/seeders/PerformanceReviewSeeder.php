<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\PerformanceReview;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PerformanceReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat data penilaian kinerja (Tim FWD Batch 3):
     * - Setiap employee mendapat review dari manager departmentnya
     * - 4 Managers untuk 5 departments (1 manager handle 2 dept jika perlu)
     * - Rating 6-10 bintang dengan deskripsi lengkap
     *
     * Dependency: EmployeeSeeder (butuh employee_id), UserSeeder (butuh manager untuk reviewer_id)
     */
    public function run(): void
    {
        $employees = Employee::whereHas('user', function($query) {
            $query->whereIn('role', ['employee', 'admin_hr']); // Include admin_hr too
        })->get();

        $managers = User::where('role', 'manager')->get();

        if ($employees->isEmpty()) {
            $this->command->error('❌ No employees found! Please run EmployeeSeeder first.');
            return;
        }

        if ($managers->isEmpty()) {
            $this->command->error('❌ No managers found! Please run UserSeeder first.');
            return;
        }

        // Assign managers to departments
        $departmentManagers = [
            'IT' => $managers[0]->id,                    // Raka Muhammad Rabbani
            'Marketing' => $managers[1]->id,             // Yossy Indra Kusuma
            'Finance' => $managers[2]->id,               // Dina Ayu Lestari
            'Operations' => $managers[3]->id,            // Ahmad Rizky Pratama
            'Human Resources' => $managers[0]->id        // Raka (handles 2 depts)
        ];

        $performanceReviews = [];
        $totalReviews = 0;

        // Review periods - fokus pada 3 bulan utama dengan beberapa bulan sebelumnya
        $reviewPeriods = [
            '2025-07', '2025-08', '2025-09', '2025-10', '2025-11', '2025-12'
        ];

        // Monthly performance factors (Sep-Nov focus)
        $monthlyFactors = [
            '2025-07' => 0.85, // Summer - lower performance
            '2025-08' => 0.90, // Late summer
            '2025-09' => 1.05, // Back to work - high motivation
            '2025-10' => 1.10, // Peak productive season
            '2025-11' => 0.95, // Holiday distraction starts
            '2025-12' => 0.80  // Holiday season - lower focus
        ];

        // Performance descriptions based on rating
        $reviewDescriptions = [
            10 => [
                'Performance luar biasa! Selalu exceed expectations di semua aspek.',
                'Outstanding performance! Menjadi role model untuk tim lainnya.',
                'Excellent work! Konsisten memberikan hasil terbaik.'
            ],
            9 => [
                'Kinerja sangat baik dan konsisten. Keep up the good work!',
                'Very good performance dengan beberapa achievement yang notable.',
                'Great job! Menunjukkan improvement yang signifikan.'
            ],
            8 => [
                'Good performance overall. Ada beberapa area untuk improvement.',
                'Solid work! Memenuhi ekspektasi dengan baik.',
                'Nice work! Terus pertahankan dan tingkatkan kualitas.'
            ],
            7 => [
                'Acceptable performance. Perlu fokus di beberapa key areas.',
                'Meeting basic expectations. Ada room for growth.',
                'Good effort! Mari fokus pada skill development.'
            ],
            6 => [
                'Performance cukup baik tapi perlu improvement di several areas.',
                'Needs improvement dalam beberapa aspek. Mari diskusi action plan.',
                'Ada potensi bagus, tapi perlu lebih konsisten.'
            ]
        ];

        foreach ($employees as $employee) {
            // Get the manager for this employee's department
            $deptName = $employee->department?->name ?? 'Unknown';

            // Mapping manual (bisa diambil dari DepartmentSeeder)
            $departmentManagers = [
                'Human Resources' => User::where('email', 'admin@hris.com')->first()->id,
                'IT'              => User::where('email', 'manager@hris.com')->first()->id,
                'Marketing'       => User::where('email', 'yossy.manager@hris.com')->first()->id,
                'Finance'         => User::where('email', 'dina.manager@hris.com')->first()->id,
                'Operations'      => User::where('email', 'ahmad.manager@hris.com')->first()->id,
            ];

            $managerId = $departmentManagers[$deptName] ?? $managers[0]->id;

            // Employee performance trend (some improve, some decline, some stable)
            $performanceTrend = rand(1, 3); // 1=improving, 2=stable, 3=declining
            $basePerformance = rand(6, 9); // Base performance level

            foreach ($reviewPeriods as $period) {
                // Get monthly factor
                $monthlyFactor = $monthlyFactors[$period] ?? 1.0;

                // Adjust rating based on trend + monthly factor
                switch ($performanceTrend) {
                    case 1: // Improving trend
                        $baseRating = min(10, $basePerformance + (array_search($period, $reviewPeriods) * 0.3));
                        break;
                    case 2: // Stable
                        $baseRating = $basePerformance + rand(-1, 1) * 0.3;
                        break;
                    case 3: // Declining (rare)
                        $baseRating = max(6, $basePerformance - (array_search($period, $reviewPeriods) * 0.2));
                        break;
                }

                // Apply monthly factor
                $rating = $baseRating * $monthlyFactor;

                $rating = round($rating);
                $rating = max(6, min(10, $rating)); // Ensure rating is between 6-10

                // Get random description based on rating
                $descriptions = $reviewDescriptions[$rating];
                $description = $descriptions[array_rand($descriptions)];

                $performanceReviews[] = [
                    'employee_id' => $employee->id,
                    'reviewer_id' => $managerId,
                    'period' => $period,
                    'total_star' => $rating,
                    'review_description' => $description,
                    'created_at' => Carbon::createFromFormat('Y-m', $period)->addDays(rand(25, 35)), // Review created end of month
                    'updated_at' => Carbon::createFromFormat('Y-m', $period)->addDays(rand(25, 35))
                ];

                $totalReviews++;
            }
        }

        foreach ($performanceReviews as $review) {
            PerformanceReview::create($review);
        }

        $this->command->info("✅ {$totalReviews} Performance Reviews created successfully!");
    }
}
