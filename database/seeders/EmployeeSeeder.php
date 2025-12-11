<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Employee;
use App\Enums\EmploymentStatus;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat profil employee untuk semua 50 users.
     *
     * Struktur organisasi:
     * - EMP001: Eko Muchamad Haryono (Admin HR) - No manager
     * - EMP002-005: 4 Managers - Managed by Admin HR
     * - EMP006-050: 45 Employees - Distributed across departments
     *   - IT Department: ~15 employees (Developers, Designers, QA, DevOps, etc)
     *   - Marketing: ~10 employees (Executives, Content Creators, Social Media, etc)
     *   - Finance: ~10 employees (Accountants, Finance Staff, Auditors, etc)
     *   - Operations: ~10 employees (Operations Staff, Admin, Logistics, etc)
     *
     * Dependency: UserSeeder (butuh user_id dan manager_id)
     */
    public function run(): void
    {
        // Ambil semua users
        $adminUser = User::where('email', 'admin@hris.com')->first();
        $managerUser = User::where('email', 'manager@hris.com')->first();
        $yossyUser = User::where('email', 'yossy.manager@hris.com')->first();
        $dinaUser = User::where('email', 'dina.manager@hris.com')->first();
        $ahmadUser = User::where('email', 'ahmad.manager@hris.com')->first();

        // Check managers exist
        if (!$adminUser || !$managerUser || !$yossyUser || !$dinaUser || !$ahmadUser) {
            $this->command->error('❌ Manager users not found! Please run UserSeeder first.');
            return;
        }

        // Get all employee users (employee1-45)
        $employeeUsers = [];
        for ($i = 1; $i <= 45; $i++) {
            $user = User::where('email', "employee{$i}@hris.com")->first();
            if (!$user) {
                $this->command->error("❌ Employee user {$i} not found! Please run UserSeeder first.");
                return;
            }
            $employeeUsers[] = $user;
        }

        // Ambil semua departments sekali (efisien, nggak query ulang-ulang)
        $departments = Department::all()->keyBy('name'); // Index by name untuk mudah ambil ID

        // Update $employees: langsung panggil $departments['Nama']->id
        $employees = [
            [
                'user_id' => $adminUser->id,
                'employee_code' => 'EMP001',
                'position' => 'HR Manager',
                'department_id' => $departments['Human Resources']?->id,
                'join_date' => '2023-01-15',
                'employment_status' => EmploymentStatus::PERMANENT,
                'contact' => '+628123456789',
            ],
            [
                'user_id' => $managerUser->id,
                'employee_code' => 'EMP002',
                'position' => 'Engineering Manager',
                'department_id' => $departments['IT']?->id,
                'join_date' => '2023-03-01',
                'employment_status' => EmploymentStatus::PERMANENT,
                'contact' => '+628234567890',
            ],
            [
                'user_id' => $yossyUser->id,
                'employee_code' => 'EMP003',
                'position' => 'Marketing Manager',
                'department_id' => $departments['Marketing']?->id,
                'join_date' => '2023-09-10',
                'employment_status' => EmploymentStatus::PERMANENT,
                'contact' => '+628567890123',
            ],
            [
                'user_id' => $dinaUser->id,
                'employee_code' => 'EMP004',
                'position' => 'Finance Manager',
                'department_id' => $departments['Finance']?->id,
                'join_date' => '2023-10-05',
                'employment_status' => EmploymentStatus::PERMANENT,
                'contact' => '+628678901234',
            ],
            [
                'user_id' => $ahmadUser->id,
                'employee_code' => 'EMP005',
                'position' => 'Operations Manager',
                'department_id' => $departments['Operations']?->id,
                'join_date' => '2023-11-01',
                'employment_status' => EmploymentStatus::PERMANENT,
                'contact' => '+628789012345',
            ],
        ];

        // Department positions mapping untuk employee generation
        $departmentPositions = [
            'Human Resources' => ['HR Specialist', 'Recruiter', 'HR Coordinator'],
            'IT' => ['Software Developer', 'UI/UX Designer', 'QA Engineer', 'DevOps Engineer', 'Tech Lead'],
            'Marketing' => ['Marketing Executive', 'Content Creator', 'Social Media Manager', 'SEO Specialist', 'Brand Manager'],
            'Finance' => ['Accountant', 'Finance Analyst', 'Finance Manager', 'Auditor', 'Tax Specialist'],
            'Operations' => ['Operations Coordinator', 'Logistics Manager', 'Admin Staff', 'Facilities Manager', 'Supply Chain Specialist'],
        ];

        // Generate employee data (EMP006-050)
        $empNumber = 6;
        $employeeIndex = 0;

        foreach ($departments as $deptName => $deptObject) {
            // Tentukan jumlah employee per department (total 45 employee untuk 5 department)
            $departmentCounts = [
                'Human Resources' => 3,   // 3 employees
                'IT' => 15,                // 15 developers/designers/qa
                'Marketing' => 10,         // 10 marketing staff
                'Finance' => 10,           // 10 finance staff
                'Operations' => 7,         // 7 operations staff
            ];

            $empCount = $departmentCounts[$deptName] ?? 0;

            for ($i = 0; $i < $empCount; $i++) {
                if ($employeeIndex >= 45) break; // Safety check

                $user = $employeeUsers[$employeeIndex];
                $positions = $departmentPositions[$deptName] ?? ['Staff'];
                $position = $positions[$i % count($positions)];

                // Random join date between 2023-2024
                $joinYear = rand(2023, 2024);
                $joinMonth = rand(1, 12);
                $joinDay = rand(1, 28);

                // Random employment status (70% permanent, 20% contract, 10% intern)
                $statusRand = rand(1, 100);
                if ($statusRand <= 70) {
                    $empStatus = EmploymentStatus::PERMANENT;
                } elseif ($statusRand <= 90) {
                    $empStatus = EmploymentStatus::CONTRACT;
                } else {
                    $empStatus = EmploymentStatus::INTERN;
                }

                $employees[] = [
                    'user_id' => $user->id,
                    'employee_code' => sprintf('EMP%03d', $empNumber),
                    'position' => $position,
                    'department_id' => $deptObject->id,
                    'join_date' => sprintf('%d-%02d-%02d', $joinYear, $joinMonth, $joinDay),
                    'employment_status' => $empStatus,
                    'contact' => '+62' . rand(800000000, 899999999)
                ];

                $empNumber++;
                $employeeIndex++;
            }
        }

        foreach ($employees as $index => $employeeData) {
            $employee = Employee::create($employeeData);

            // Variasi created_at untuk chart "employees per month" yang lebih menarik
            // Distribusi: 12 bulan terakhir dengan pola realistis (lebih banyak employee baru di bulan-bulan tertentu)
            if ($index < 5) {
                // Admin HR dan Managers: dibuat di awal tahun (Jan-Mar 2025)
                $createdMonth = rand(1, 3); // Januari - Maret 2025
                $employee->created_at = Carbon::create(2025, $createdMonth, rand(1, 28), rand(8, 17), rand(0, 59));
            } else {
                // Employees: distribusi realistis sepanjang tahun dengan pola hiring (Jan-Nov)
                $monthWeights = [
                    1 => 8,   // Januari (hiring awal tahun)
                    2 => 6,   // Februari
                    3 => 10,  // Maret (hiring puncak Q1)
                    4 => 4,   // April
                    5 => 3,   // Mei
                    6 => 7,   // Juni (mid-year hiring)
                    7 => 8,   // Juli (fresh graduate season)
                    8 => 9,   // Agustus (fresh graduate season)
                    9 => 12,  // September (puncak hiring H2)
                    10 => 10, // Oktober (hiring tinggi)
                    11 => 18, // November (hiring akhir tahun) - PALING TINGGI
                ];

                // Weighted random selection untuk bulan
                $totalWeight = array_sum($monthWeights);
                $randomValue = rand(1, $totalWeight);

                $currentWeight = 0;
                $selectedMonth = 11; // Default November

                foreach ($monthWeights as $month => $weight) {
                    $currentWeight += $weight;
                    if ($randomValue <= $currentWeight) {
                        $selectedMonth = $month;
                        break;
                    }
                }

                // Set created_at dengan bulan terpilih
                $employee->created_at = Carbon::create(2025, $selectedMonth, rand(1, 28), rand(8, 17), rand(0, 59));
            }

            $employee->save();
        }

        $this->command->info('✅ 50 Employees created successfully with varied created_at dates!');
    }
}
