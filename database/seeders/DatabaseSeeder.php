<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Urutan seeder sesuai dependency:
     * 1. UserSeeder           - Buat users (admin, manager, employees)
     * 2. EmployeeSeeder       - Buat profil employee (depends on users)
     * 3. AttendanceSeeder     - Buat data absensi (depends on employees)
     * 4. LeaveRequestSeeder   - Buat pengajuan cuti (depends on employees & users)
     * 5. PerformanceReviewSeeder - Buat penilaian kinerja (depends on employees & users)
     * 6. SalarySlipSeeder     - Buat slip gaji (depends on employees & users)
     * 7. NotificationSeeder   - Buat notifikasi (depends on users)
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting database seeding...');
        $this->command->newLine();

        // Jalankan seeder sesuai urutan dependency
        $this->call([
            UserSeeder::class,
            EmployeeSeeder::class,
            AttendanceSeeder::class,
            LeaveRequestSeeder::class,
            PerformanceReviewSeeder::class,
            SalarySlipSeeder::class,
            NotificationSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('🎉 Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->line('📊 Summary:');
        $this->command->line('   - 4 Users (1 Admin HR, 1 Manager, 2 Employees)');
        $this->command->line('   - 4 Employee Profiles');
        $this->command->line('   - ~40 Attendance Records (10 days per employee)');
        $this->command->line('   - 6 Leave Requests (3 per employee)');
        $this->command->line('   - 4 Performance Reviews (2 per employee)');
        $this->command->line('   - 8 Salary Slips (2 per employee)');
        $this->command->line('   - 8-10 Notifications');
        $this->command->newLine();
        $this->command->info('🔐 Demo Accounts:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin HR', 'admin@hris.com', 'password123'],
                ['Manager', 'manager@hris.com', 'password123'],
                ['Employee 1', 'employee1@hris.com', 'password123'],
                ['Employee 2', 'employee2@hris.com', 'password123'],
            ]
        );
    }
}

