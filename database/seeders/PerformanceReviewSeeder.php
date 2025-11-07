<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\PerformanceReview;
use Illuminate\Database\Seeder;

class PerformanceReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat data penilaian kinerja (Tim FWD Batch 3):
     * - Setiap employee mendapat 2 review (Oktober & November 2025)
     * - Reviewer: Manager (Raka Muhammad Rabbani)
     * - Rating 6-10 bintang dengan deskripsi lengkap
     *
     * Dependency: EmployeeSeeder (butuh employee_id), UserSeeder (butuh manager untuk reviewer_id)
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

        $performanceReviews = [];
        $totalReviews = 0;

        foreach ($employees as $employee) {
            // Review Oktober 2025
            $performanceReviews[] = [
                'employee_id' => $employee->id,
                'reviewer_id' => $manager->id,
                'period' => '2025-10',
                'total_star' => rand(6, 10),
                'review_description' => 'Kinerja bulan Oktober sangat baik. Terus pertahankan!',
            ];

            // Review 2: November 2025
            $performanceReviews[] = [
                'employee_id' => $employee->id,
                'reviewer_id' => $manager->id,
                'period' => '2025-11',
                'total_star' => rand(6, 10),
                'review_description' => 'Perkembangan positif di bulan November. Kerja bagus!',
            ];

            $totalReviews += 2;
        }

        foreach ($performanceReviews as $review) {
            PerformanceReview::create($review);
        }

        $this->command->info("✅ {$totalReviews} Performance Reviews created successfully!");
    }
}
