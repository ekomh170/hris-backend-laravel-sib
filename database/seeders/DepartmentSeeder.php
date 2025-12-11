<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil manager dari UserSeeder
        $itManager = User::where('email', 'manager@hris.com')->first();
        $marketingManager = User::where('email', 'yossy.manager@hris.com')->first();
        $financeManager = User::where('email', 'dina.manager@hris.com')->first();
        $operationsManager = User::where('email', 'ahmad.manager@hris.com')->first();
        $hrManager = User::where('email', 'admin@hris.com')->first();

        // Buat departments berdasarkan data lama di EmployeeSeeder
        $departments = [
            [
                'name' => 'Human Resources',
                'description' => 'Manajemen SDM dan rekrutmen',
                'manager_id' => $hrManager->id,
            ],
            [
                'name' => 'IT',
                'description' => 'Teknologi Informasi dan pengembangan',
                'manager_id' => $itManager->id,
            ],
            [
                'name' => 'Marketing',
                'description' => 'Pemasaran dan promosi',
                'manager_id' => $marketingManager->id,
            ],
            [
                'name' => 'Finance',
                'description' => 'Keuangan dan akuntansi',
                'manager_id' => $financeManager->id,
            ],
            [
                'name' => 'Operations',
                'description' => 'Operasional dan logistik',
                'manager_id' => $operationsManager->id,
            ],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        $this->command->info('✅ Departments seeded successfully!');
    }
}
