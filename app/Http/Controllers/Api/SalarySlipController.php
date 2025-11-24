<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalarySlipResource;
use App\Models\SalarySlip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SalarySlipController extends Controller
{
    /**
     * Get all salary slips
     * Admin: semua slip
     * Employee: slip miliknya sendiri
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Mulai query dengan eager loading relasi
        $query = SalarySlip::with(['employee.user', 'creator']);

        // Jika employee, hanya bisa lihat slip gaji sendiri
        if ($user->isEmployee()) {
            abort_if(!$user->employee, 422, 'Employee profile not available');
            $query->where('employee_id', $user->employee->id);
        }

        // Filter berdasarkan employee_id (opsional)
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Filter berdasarkan periode (opsional) - tidak ada default filter
        if ($period = $request->query('period')) {
            $query->where('period_month', 'like', "%{$period}%");
        }

        // Validasi dan pengaturan pagination
        $perPage = min($request->query('per_page', 10), 100);

        // Jalankan query dengan pagination, urutkan terbaru dulu
        $salarySlips = $query->orderBy('period_month', 'desc')
                           ->orderBy('created_at', 'desc')
                           ->paginate($perPage);

        // Response JSON lengkap & rapi
        return response()->json([
            'success' => true,
            'message' => 'Salary slip data retrieved successfully',
            'data' => [
                // Pagination info
                'current_page'   => $salarySlips->currentPage(),
                'per_page'       => $salarySlips->perPage(),
                'total'          => $salarySlips->total(),
                'last_page'      => $salarySlips->lastPage(),
                'from'           => $salarySlips->firstItem(),
                'to'             => $salarySlips->lastItem(),

                // Data slip gaji (sudah difilter oleh Resource)
                'data'           => SalarySlipResource::collection($salarySlips->items()),

                // Navigation URLs
                'first_page_url' => $salarySlips->url(1),
                'last_page_url'  => $salarySlips->url($salarySlips->lastPage()),
                'next_page_url'  => $salarySlips->nextPageUrl(),
                'prev_page_url'  => $salarySlips->previousPageUrl(),
                'path'           => $salarySlips->path(),

                // Pagination links untuk UI
                'links'          => $salarySlips->linkCollection()->toArray(),
            ],
        ]);
    }

    /**
     * Get my salary slips → HANYA 1 PARAMETER: period
     *
     * ?period=2025        → semua slip tahun 2025
     * ?period=2025-11     → hanya November 2025
    */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Support employee biasa & Admin HR (yang mungkin tidak punya record employee)
        $employeeId = $user->employee?->id ?? ($user->isAdminHr() ? $user->id : null);
        abort_if(is_null($employeeId), 422, 'Employee profile not available');

        $query = SalarySlip::where('employee_id', $employeeId)
            ->with(['employee.user', 'creator'])
            ->orderBy('period_month', 'desc');

        $periodInput = $request->query('period');
        $appliedPeriod = null;
        $message = 'You have no salary slips yet.';

        if ($periodInput) {
            // CASE 1: Hanya tahun (contoh: 2025)
            if (preg_match('/^\d{4}$/', $periodInput)) {
                $query->where('period_month', 'LIKE', $periodInput . '%');
                $appliedPeriod = $periodInput;
                $message = "No salary slips found for period {$periodInput}.";
            }
            // CASE 2: Tahun + bulan atau quarter (contoh: 2025-11, 2025-Q1, 2025-06, dll)
            elseif (preg_match('/^\d{4}[-\/](Q[1-4]|[0-1]?\d)$/', $periodInput)) {
                $query->where('period_month', $periodInput); // exact match
                $appliedPeriod = $periodInput;

                // Format pesan lebih cantik
                if (str_contains($periodInput, '-Q')) {
                    $message = "No salary slips found for period {$periodInput}.";
                } else {
                    $nice = Carbon::createFromFormat('Y-m', $periodInput)->format('F Y');
                    $message = "No salary slips found for period {$nice}.";
                }
            }
            else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid period format. Use YYYY or YYYY-MM (e.g., 2025 or 2025-11)'
                ], 400);
            }
        }

        // Cek apakah ada data
        $total = $query->count();

        if ($total === 0) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'current_page'   => 1,
                    'per_page'       => (int)$request->query('per_page', 10),
                    'total'          => 0,
                    'last_page'      => 1,
                    'from'           => null,
                    'to'             => null,
                    'data'           => [],
                    'first_page_url' => $request->fullUrlWithQuery(['page' => 1]),
                    'last_page_url'  => $request->fullUrlWithQuery(['page' => 1]),
                    'next_page_url'  => null,
                    'prev_page_url'     => null,
                    'links'          => [],
                    'filters' => [
                        'period'   => $periodInput,
                        'per_page' => (int)$request->query('per_page', 10),
                    ],
                ]
            ]);
        }

        // Pagination
        $perPage = min((int)$request->query('per_page', 10), 50);
        $slips = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Your salary slips retrieved successfully',
            'data' => [
                'current_page'       => $slips->currentPage(),
                'per_page'       => $slips->perPage(),
                'total'          => $slips->total(),
                'last_page'      => $slips->lastPage(),
                'from'           => $slips->firstItem(),
                'to'             => $slips->lastItem(),
                'data'           => SalarySlipResource::collection($slips->getCollection()),
                'first_page_url' => $slips->url(1),
                'last_page_url'  => $slips->url($slips->lastPage()),
                'next_page_url'  => $slips->nextPageUrl(),
                'prev_page_url'  => $slips->previousPageUrl(),
                'path'           => $slips->path(),
                'links'          => $slips->linkCollection()->toArray(),
                'filters' => [
                    'period'   => $periodInput,
                    'per_page' => $perPage,
                ],
            ]
        ]);
    }

    /**
     * Create new salary slip (Admin HR only)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Cek otorisasi - hanya Admin HR
        abort_unless($user->isAdminHr(), 403, 'Forbidden - Admin HR only');

        // Validasi input yang fleksibel tapi tetap aman
        // employee_id: harus ada di tabel employees
        // period_month: format bebas (bisa YYYY-MM, MM-YYYY, atau format lain)
        // basic_salary: wajib, minimal 0 (tidak ada batas maksimal)
        // allowance & deduction: opsional, minimal 0 jika diisi
        // remarks: opsional, panjang bebas untuk catatan
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_month' => 'required|string|max:20',
            'basic_salary' => 'required|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        // Validasi duplikasi: Satu employee hanya boleh punya satu slip per periode
        // Ini sesuai dengan constraint 'unique_employee_period' di database
        // Mencegah error SQL constraint violation saat insert
        $existingSlip = SalarySlip::where('employee_id', $validated['employee_id'])
                                 ->where('period_month', $validated['period_month'])
                                 ->first();

        if ($existingSlip) {
            return response()->json([
                'success' => false,
                'message' => 'Salary slip for this employee and period already exists',
                'errors' => [
                    'period_month' => ['A salary slip for this period has already been created']
                ]
            ], 422);
        }

        try {
            // Siapkan data dengan nilai default
            $slipData = [
                'employee_id' => $validated['employee_id'],
                'created_by' => $user->id,
                'period_month' => $validated['period_month'],
                'basic_salary' => $validated['basic_salary'],
                'allowance' => $validated['allowance'] ?? 0,
                'deduction' => $validated['deduction'] ?? 0,
                'remarks' => $validated['remarks'] ?? null,
            ];

            // Buat instance slip gaji
            $slip = new SalarySlip($slipData);

            // Hitung total salary sebelum menyimpan
            $slip->computeTotalSalary();

            // Simpan ke database
            $slip->save();

            // Load relasi untuk response
            $slip->load(['employee.user', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Salary slip created successfully',
                'data' => new SalarySlipResource($slip),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create salary slip: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific salary slip
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $slip = SalarySlip::with(['employee.user', 'creator'])->findOrFail($id);

        // Employee hanya bisa lihat slip miliknya sendiri
        if ($user->isEmployee()) {
            abort_if(!$user->employee, 422, 'Employee profile not available');
            abort_unless($slip->employee_id === $user->employee->id, 403, 'Forbidden');
        }

        return response()->json([
            'success' => true,
            'message' => 'Salary slip details retrieved successfully',
            'data' => new SalarySlipResource($slip),
        ]);
    }

    /**
     * Update salary slip (Admin HR only)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Cek otorisasi - hanya Admin HR
        abort_unless($user->isAdminHr(), 403, 'Forbidden - Admin HR only');

        $slip = SalarySlip::findOrFail($id);

        // Validasi input untuk update yang lebih fleksibel
        $validated = $request->validate([
            'period_month' => 'sometimes|string|max:50', // Format bebas
            'basic_salary' => 'sometimes|numeric|min:0',
            'allowance' => 'sometimes|numeric|min:0',
            'deduction' => 'sometimes|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        // Validasi duplikasi saat update period_month
        // Pastikan tidak bentrok dengan slip lain di periode baru
        // Exclude slip yang sedang diedit (id != current slip)
        if (isset($validated['period_month']) && $validated['period_month'] !== $slip->period_month) {
            $existingSlip = SalarySlip::where('employee_id', $slip->employee_id)
                                     ->where('period_month', $validated['period_month'])
                                     ->where('id', '!=', $slip->id)
                                     ->first();

            if ($existingSlip) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary slip for this employee and period already exists',
                    'errors' => [
                        'period_month' => ['This period already has a salary slip for the same employee']
                    ]
                ], 422);
            }
        }

        try {
            // Update data slip
            $slip->fill($validated);

            // Hitung ulang total_salary jika ada perubahan finansial
            if (isset($validated['basic_salary']) || isset($validated['allowance']) || isset($validated['deduction'])) {
                $slip->computeTotalSalary();
            }

            $slip->save();

            // Load relasi untuk response
            $slip->load(['employee.user', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Salary slip updated successfully',
                'data' => new SalarySlipResource($slip),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update salary slip: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete salary slip (Admin HR only)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user->isAdminHr(), 403, 'Forbidden - Admin HR only');

        $slip = SalarySlip::findOrFail($id);
        $slip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Salary slip deleted successfully',
        ]);
    }
}
