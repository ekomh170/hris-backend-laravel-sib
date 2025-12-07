<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Attendance;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membuat data absensi untuk 4 bulan (September - Desember 2025) dengan variasi tinggi:
     * - 50 employees x ~65 working days = ~3250 records
     * - Skip weekend (Sabtu & Minggu) + simulasi sick leave (5% absent)
     * - Variasi check-in realistis: 07:30 - 09:30 (employee behavior patterns)
     * - Variasi check-out: 16:30 - 19:00 (overtime simulation)
     * - Monthly patterns: Sep (back to work), Oct (productive), Nov (holiday prep)
     * - Individual employee punctuality & overtime tendencies
     * - Work hour dihitung otomatis via model method computeWorkHour()
     *
     * Dependency: EmployeeSeeder (butuh employee_id)
     */
    public function run(): void
    {
        $employees = Employee::all();

        if ($employees->isEmpty()) {
            $this->command->error('❌ No employees found! Please run EmployeeSeeder first.');
            return;
        }

        $totalAttendances = 0;
        $months = [
            ['year' => 2025, 'month' => 9, 'name' => 'September'],
            ['year' => 2025, 'month' => 10, 'name' => 'October'],
            ['year' => 2025, 'month' => 11, 'name' => 'November'],
            ['year' => 2025, 'month' => 12, 'name' => 'December']
        ];

        foreach ($employees as $employee) {
            // Pola perilaku karyawan (ada yang lebih disiplin, ada yang sering terlambat)
            $punctualityLevel = rand(1, 3); // 1=sangat tepat waktu, 2=normal, 3=sering terlambat
            $overtimeFrequency = rand(1, 4); // 1=tidak pernah lembur, 2=jarang, 3=kadang-kadang, 4=sering

            foreach ($months as $monthData) {
                $daysInMonth = Carbon::create($monthData['year'], $monthData['month'])->daysInMonth;

                // Batasi data absensi Desember hanya untuk tanggal 1-5 (hari kerja awal bulan)
                if ($monthData['month'] == 12) {
                    $daysInMonth = 5;
                }

                // Penyesuaian perilaku bulanan
                $monthlyFactor = 1.0;
                $absentRate = 5; // Tingkat absen dasar 5%

                if ($monthData['month'] == 9) { // September - kembali kerja setelah liburan
                    $monthlyFactor = 0.9; // Sedikit kurang tepat waktu
                    $absentRate = 7; // Tingkat absen lebih tinggi (pasca liburan)
                } elseif ($monthData['month'] == 10) { // Oktober - bulan produktif
                    $monthlyFactor = 1.1; // Lebih produktif
                    $absentRate = 3; // Tingkat absen lebih rendah
                } elseif ($monthData['month'] == 11) { // November - persiapan liburan
                    $monthlyFactor = 0.95; // Sedikit terdistraksi
                    $absentRate = 8; // Tingkat absen lebih tinggi (persiapan liburan)
                } elseif ($monthData['month'] == 12) { // Desember - musim liburan akhir tahun
                    $monthlyFactor = 0.85; // Kurang produktif (holiday season)
                    $absentRate = 12; // Tingkat absen sangat tinggi (liburan akhir tahun)
                }

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = Carbon::create($monthData['year'], $monthData['month'], $day);

                    // Lewati hari weekend (Sabtu & Minggu)
                    if ($date->isWeekend()) {
                        continue;
                    }

                    // Tingkat absen yang disesuaikan per bulan
                    if (rand(1, 100) <= $absentRate) {
                        continue;
                    }

                    // *** POLA NAIK-TURUN JAM KERJA ***
                    // Buat jam kerja bervariasi sepanjang bulan dengan pola realistis
                    $dayOfMonth = $day;
                    $workHourMultiplier = 1.0;

                    // Pola naik-turun berdasarkan hari dalam bulan
                    if ($dayOfMonth <= 5) { // Awal bulan - semangat tinggi
                        $workHourMultiplier = 1.15 + (rand(-10, 10) / 100); // 1.05-1.25
                    } elseif ($dayOfMonth <= 10) { // Turun - masa adaptasi
                        $workHourMultiplier = 0.85 + (rand(-10, 15) / 100); // 0.75-1.0
                    } elseif ($dayOfMonth <= 15) { // Naik lagi - momentum pertengahan bulan
                        $workHourMultiplier = 1.1 + (rand(-5, 15) / 100); // 1.05-1.25
                    } elseif ($dayOfMonth <= 20) { // Stabil - periode produktif
                        $workHourMultiplier = 1.0 + (rand(-15, 10) / 100); // 0.85-1.1
                    } elseif ($dayOfMonth <= 25) { // Naik tinggi - kejar deadline
                        $workHourMultiplier = 1.2 + (rand(-5, 20) / 100); // 1.15-1.4
                    } else { // Akhir bulan - turun drastis (persiapan/kelelahan)
                        $workHourMultiplier = 0.7 + (rand(-15, 20) / 100); // 0.55-0.9
                    }

                    // Variasi jam masuk berdasarkan tingkat ketepatan waktu + faktor bulanan
                    $latenessPenalty = (1 - $monthlyFactor) * 15; // Maksimal 15 menit keterlambatan

                    switch ($punctualityLevel) {
                        case 1: // Sangat tepat waktu (07:30 - 08:15)
                            $checkInHour = rand(7, 8);
                            $baseMinute = $checkInHour == 7 ? rand(30, 59) : rand(0, 15);
                            $checkInMinute = min(59, $baseMinute + $latenessPenalty);
                            break;
                        case 2: // Normal (08:00 - 08:45)
                            $checkInHour = 8;
                            $baseMinute = rand(0, 45);
                            $checkInMinute = min(59, $baseMinute + $latenessPenalty);
                            if ($checkInMinute >= 60) {
                                $checkInHour = 9;
                                $checkInMinute = $checkInMinute - 60;
                            }
                            break;
                        case 3: // Sering terlambat (08:15 - 09:30)
                            $checkInHour = rand(8, 9);
                            $baseMinute = $checkInHour == 8 ? rand(15, 59) : rand(0, 30);
                            $checkInMinute = min(59, $baseMinute + $latenessPenalty);
                            if ($checkInMinute >= 60) {
                                $checkInHour = min(9, $checkInHour + 1);
                                $checkInMinute = $checkInMinute - 60;
                            }
                            break;
                    }

                    $checkInTime = $date->copy()->setTime($checkInHour, $checkInMinute);

                    // Variasi jam pulang berdasarkan frekuensi lembur + pola jam kerja
                    $baseCheckOutHour = 17; // Standar 17:00

                    // Sesuaikan jam pulang berdasarkan pengali jam kerja
                    $extraHours = ($workHourMultiplier - 1) * 2; // Konversi pengali ke jam tambahan

                    switch ($overtimeFrequency) {
                        case 1: // Tidak pernah lembur (16:30 - 17:15) + penyesuaian pola
                            $checkOutHour = rand(16, 17);
                            $checkOutMinute = $checkOutHour == 16 ? rand(30, 59) : rand(0, 15);
                            $checkOutHour += max(0, floor($extraHours)); // Tambah jam ekstra dari pola
                            $checkOutMinute += ($extraHours - floor($extraHours)) * 60;
                            break;
                        case 2: // Jarang lembur (17:00 - 17:45) + penyesuaian pola
                            $checkOutHour = 17;
                            $checkOutMinute = rand(0, 45);
                            $checkOutHour += max(0, floor($extraHours));
                            $checkOutMinute += ($extraHours - floor($extraHours)) * 60;
                            break;
                        case 3: // Kadang lembur (17:00 - 18:30) + penyesuaian pola
                            $checkOutHour = rand(17, 18);
                            $checkOutMinute = $checkOutHour == 18 ? rand(0, 30) : rand(0, 59);
                            $checkOutHour += max(0, floor($extraHours));
                            $checkOutMinute += ($extraHours - floor($extraHours)) * 60;
                            break;
                        case 4: // Sering lembur (17:30 - 19:00) + penyesuaian pola
                            $checkOutHour = rand(17, 19);
                            $checkOutMinute = $checkOutHour == 17 ? rand(30, 59) : rand(0, 59);
                            if ($checkOutHour == 19) $checkOutMinute = 0; // Maksimal 19:00 dasar
                            $checkOutHour += max(0, floor($extraHours));
                            $checkOutMinute += ($extraHours - floor($extraHours)) * 60;
                            break;
                    }

                    // Tangani overflow menit
                    if ($checkOutMinute >= 60) {
                        $checkOutHour += floor($checkOutMinute / 60);
                        $checkOutMinute = $checkOutMinute % 60;
                    }

                    // Batasi maksimal jam 20:00 untuk realisme
                    if ($checkOutHour > 20) {
                        $checkOutHour = 20;
                        $checkOutMinute = 0;
                    }

                    $checkOutTime = $date->copy()->setTime($checkOutHour, $checkOutMinute);

                    // Pastikan jam pulang selalu setelah jam masuk (minimal 4 jam kerja)
                    $actualDiffHours = $checkOutTime->diffInHours($checkInTime, false);
                    if ($actualDiffHours < 4 || $checkOutTime->lte($checkInTime)) {
                        // Buat durasi kerja yang bervariasi antara 7-9 jam (tidak selalu 8)
                        $randomWorkHours = 7 + ($workHourMultiplier * 2); // 7 + (0.55-1.4 * 2) = 8.1-9.8 jam
                        $randomWorkHours = max(6, min(10, $randomWorkHours)); // Batasi 6-10 jam
                        $checkOutTime = $checkInTime->copy()->addMinutes(round($randomWorkHours * 60));
                    }

                    $attendance = Attendance::create([
                        'employee_id' => $employee->id,
                        'date' => $date->format('Y-m-d'),
                        'check_in_time' => $checkInTime,
                        'check_out_time' => $checkOutTime,
                        'work_hour' => 0, // Akan dihitung otomatis
                    ]);

                    // Hitung jam kerja menggunakan method dari model
                    $attendance->computeWorkHour();
                    $attendance->save();

                    $totalAttendances++;
                }
            }
        }

        $this->command->info("✅ {$totalAttendances} Attendances created successfully!");
    }
}
