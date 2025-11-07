<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use App\Models\SalarySlip;
use Illuminate\Database\Seeder;

class SalarySlipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat data slip gaji (Tim FWD Batch 3):
     * - Setiap employee punya 2 slip (Oktober & November 2025)
     * - Created by Admin HR (Eko Muchamad Haryono)
     * - Gaji berbeda berdasarkan posisi (HR Manager, Engineering Manager, Developer, Designer)
     * - Total salary dihitung otomatis via model method computeTotalSalary()
     * 
     * Dependency: EmployeeSeeder (butuh employee_id), UserSeeder (butuh admin_hr untuk created_by)
     */
    public function run(): void
    {
        $employees = Employee::all();
        $adminHr = User::where('role', 'admin_hr')->first();

        if ($employees->isEmpty()) {
            $this->command->error('❌ No employees found! Please run EmployeeSeeder first.');
            return;
        }

        if (!$adminHr) {
            $this->command->error('❌ No admin HR found! Please run UserSeeder first.');
            return;
        }

        // Mapping gaji berdasarkan posisi (Tim FWD Batch 3)
        $salaryByPosition = [
            'HR Manager' => ['basic' => 15000000, 'allowance' => 3000000, 'deduction' => 750000],
            'Engineering Manager' => ['basic' => 18000000, 'allowance' => 4000000, 'deduction' => 900000],
            'Software Developer' => ['basic' => 12000000, 'allowance' => 2500000, 'deduction' => 600000],
            'UI/UX Designer' => ['basic' => 10000000, 'allowance' => 2000000, 'deduction' => 500000],
        ];

        $slips = [];
        $totalSlips = 0;

        foreach ($employees as $employee) {
            $salaryData = $salaryByPosition[$employee->position] ?? [
                'basic' => 8000000,
                'allowance' => 1500000,
                'deduction' => 400000
            ];

            // Slip gaji Oktober 2025
            $slip1 = SalarySlip::create([
                'employee_id' => $employee->id,
                'created_by' => $adminHr->id,
                'period_month' => '2025-10',
                'basic_salary' => $salaryData['basic'],
                'allowance' => $salaryData['allowance'],
                'deduction' => $salaryData['deduction'],
                'total_salary' => 0, // Will be calculated
                'remarks' => 'Gaji bulan Oktober 2025',
            ]);
            $slip1->computeTotalSalary();
            $slip1->save();

            // Slip gaji November 2025
            $slip2 = SalarySlip::create([
                'employee_id' => $employee->id,
                'created_by' => $adminHr->id,
                'period_month' => '2025-11',
                'basic_salary' => $salaryData['basic'],
                'allowance' => $salaryData['allowance'],
                'deduction' => $salaryData['deduction'],
                'total_salary' => 0, // Will be calculated
                'remarks' => 'Gaji bulan November 2025',
            ]);
            $slip2->computeTotalSalary();
            $slip2->save();

            $totalSlips += 2;
        }

        $this->command->info("✅ {$totalSlips} Salary Slips created successfully!");
    }
}
