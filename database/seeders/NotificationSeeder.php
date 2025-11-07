<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat notifikasi untuk user (Tim FWD Batch 3):
     * - Setiap employee punya 4-5 notifikasi random
     * - Campuran status read/unread (2 pertama read, sisanya unread)
     * - Berbagai jenis notifikasi (leave approved, salary slip, review, meeting, system)
     *
     * Dependency: UserSeeder (butuh user_id dengan role employee)
     */
    public function run(): void
    {
        $employees = User::where('role', 'employee')->get();

        if ($employees->isEmpty()) {
            $this->command->error('❌ No employees found! Please run UserSeeder first.');
            return;
        }

        $notificationTemplates = [
            [
                'type' => 'leave_approved',
                'message' => 'Pengajuan cuti Anda untuk tanggal 15-17 November 2025 telah disetujui oleh Manager. Selamat berlibur!',
                'is_read' => true,
            ],
            [
                'type' => 'salary_slip',
                'message' => 'Slip gaji bulan November 2025 sudah tersedia. Silakan cek di menu Salary Slips.',
                'is_read' => false,
            ],
            [
                'type' => 'performance_review',
                'message' => 'Penilaian kinerja bulan November 2025 telah dipublikasikan. Rating Anda: 4 bintang.',
                'is_read' => false,
            ],
            [
                'type' => 'meeting_reminder',
                'message' => 'Jangan lupa meeting team hari ini jam 14.00 via Zoom. Link sudah dikirim ke email.',
                'is_read' => true,
            ],
            [
                'type' => 'system_maintenance',
                'message' => 'Sistem HRIS akan maintenance hari Minggu 10 November 2025 pukul 01.00-05.00 WIB.',
                'is_read' => false,
            ],
        ];

        $totalNotifications = 0;

        foreach ($employees as $user) {
            // Buat 3-5 notifikasi random untuk setiap user
            $numNotifications = rand(3, 5);

            for ($i = 0; $i < $numNotifications; $i++) {
                $template = $notificationTemplates[array_rand($notificationTemplates)];

                Notification::create([
                    'user_id' => $user->id,
                    'type' => $template['type'],
                    'message' => $template['message'],
                    'is_read' => $i < 2 ? true : false, // 2 pertama sudah dibaca
                ]);

                $totalNotifications++;
            }
        }

        $this->command->info("✅ {$totalNotifications} Notifications created successfully!");
    }
}
