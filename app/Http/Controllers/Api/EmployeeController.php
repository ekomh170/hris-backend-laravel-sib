<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Employee Controller - Mengelola data karyawan (CRUD)
 *
 * Fitur yang tersedia:
 * - CRUD Employee dengan validasi lengkap
 * - Dual mode: Create user baru atau gunakan existing user
 * - Search dan filter advanced (departemen, posisi, status)
 * - Pagination dengan customizable per_page
 * - Authorization berbasis role (Admin HR, Manager, Employee)
 * - Validation rules untuk duplicate prevention
 * - Database transactions untuk data consistency
 *
 * Authorization Matrix:
 * - Admin HR: Full access (CRUD semua employee)
 * - Manager: Read access untuk employee yang di-manage
 * - Employee: Read access untuk data sendiri
 *
 * @author HRIS Development Team
 * @version 1.0
 * @since 2025-11-16
 */
class EmployeeController extends Controller
{
    /**
     * GET /api/employees - Menampilkan daftar karyawan dengan fitur search dan filter
     *
     * Query Parameters:
     * - search: string (pencarian global nama, email, employee_code)
     * - department: string (filter berdasarkan departemen)
     * - employment_status: enum (permanent|contract|intern|resigned)
     * - position: string (filter berdasarkan posisi/jabatan)
     * - sort_by: enum (name|employee_code|position|department|join_date)
     * - sort_order: enum (asc|desc)
     * - per_page: integer (1-100, default: 15)
     * - page: integer (default: 1)
     *
     * Authorization:
     * - Admin HR: Dapat melihat semua employee
     * - Manager: Hanya employee yang di-manage
     * - Employee: Access denied (403)
     *
     * @param Request $request HTTP request dengan query parameters
     * @return JsonResponse Paginated list of employees dengan metadata
     */
    public function index(Request $request): JsonResponse
    {
        // Mendapatkan pengguna yang sudah login dari JWT token
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Cek otorisasi - hanya Admin HR dan Manager yang bisa akses
        if ($user->isAdminHr()) {
            // Admin HR dapat melihat semua karyawan
            $query = Employee::with(['user', 'department']);
        } elseif ($user->isManager()) {
            // Manager hanya bisa melihat karyawan berdasarkan department manager
            $departmentIds = Department::where('manager_id', $user->id)->pluck('id');
            $query = Employee::with(['user', 'department'])
                ->whereIn('department_id', $departmentIds);
        } else {
            // Karyawan biasa tidak bisa akses daftar karyawan
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        /* =================================
         * FITUR PENCARIAN & FILTER
         * ================================= */

        // Pencarian global - mencari di nama, email, employee_code (didefinisikan di Employee model scope)
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Filter berdasarkan departemen (tidak sensitif huruf besar/kecil dengan pencocokan sebagian)
        if ($department = $request->query('department')) {
            $query->whereHas('department', function ($q) use ($department) {
                $q->where('name', 'like', "%{$department}%");
            });
        }

        // Filter berdasarkan status kerja (pencocokan persis)
        // Nilai yang valid: permanent, contract, intern, resigned
        if ($status = $request->query('employment_status')) {
            $query->where('employment_status', $status);
        }

        // Filter berdasarkan posisi/jabatan (tidak sensitif huruf besar/kecil dengan pencocokan sebagian)
        if ($position = $request->query('position')) {
            $query->where('position', 'like', "%{$position}%");
        }

        /* =================================
         * FITUR PENGURUTAN DATA
         * ================================= */

        // Parameter pengurutan dengan nilai default
        $sortBy = $request->query('sort_by', 'employee_code');
        $sortOrder = $request->query('sort_order', 'asc');

        // Daftar kolom yang diizinkan untuk diurutkan demi keamanan
        $allowedSorts = ['name', 'employee_code', 'position', 'department', 'join_date'];

        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'name') {
                // Khusus untuk pengurutan berdasarkan nama, perlu join ke tabel users
                $query->orderBy('users.name', $sortOrder);
                $query->join('users', 'employees.user_id', '=', 'users.id');
            } else {
                // Pengurutan berdasarkan kolom di tabel employees
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            // Pengurutan cadangan jika parameter tidak valid
            $query->orderBy('employee_code', 'asc');
        }

        /* =================================
         * PENGATURAN PAGINASI
         * ================================= */

        // Batasi per_page maksimal 100 untuk performa, default 10
        $perPage = min($request->query('per_page', 10), 100);

        // Jalankan query dengan paginasi Laravel
        $employees = $query->paginate($perPage);

        /* =================================
         * FORMAT RESPON JSON
         * ================================= */

        // Mengembalikan respon JSON standar dengan metadata paginasi lengkap
        return response()->json([
            'success' => true,
            'message' => 'Employee data retrieved successfully',
            'data' => [
                // Informasi paginasi
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),

                // Data karyawan dengan transformasi resource
                'data' => EmployeeResource::collection($employees->items()),

                // URL navigasi
                'first_page_url' => $employees->url(1),
                'last_page_url' => $employees->url($employees->lastPage()),
                'next_page_url' => $employees->nextPageUrl(),
                'prev_page_url' => $employees->previousPageUrl(),
                'path' => $employees->path(),

                // Link paginasi untuk UI
                'links' => $employees->linkCollection()->toArray(),
            ],
        ]);
    }

    /**
     * GET /api/employees/{id} - Menampilkan detail karyawan berdasarkan ID
     *
     * Authorization Rules:
     * - Admin HR: Dapat melihat detail semua employee
     * - Manager: Hanya employee yang ada di departemen yang dikelola
     * - Employee: Hanya data diri sendiri (employee.user_id == user_id)
     *
     * @param string $id Employee ID yang akan ditampilkan
     * @return JsonResponse Employee detail dengan relasi user dan manager
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Jika employee tidak ditemukan
     */
    public function show(string $id): JsonResponse
    {
        // Mendapatkan pengguna yang sudah login
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Cari karyawan dengan eager loading relasi, lempar 404 jika tidak ada
        $employee = Employee::with(['user', 'department'])->findOrFail($id);

        /* =================================
         * CEK OTORISASI
         * ================================= */

        // Otorisasi bertingkat:
        // 1. Admin HR - akses penuh
        // 2. Manager - hanya karyawan di departemen yang dikelola
        // 3. Employee - hanya data diri sendiri
        if ($user->isAdminHr() ||
            ($user->isManager() && $user->id === Department::find($employee->department_id)?->manager_id) ||
            ($user->isEmployee() && $user->id === $employee->user_id)) {

            return response()->json([
                'success' => true,
                'message' => 'Employee details retrieved successfully',
                'data' => new EmployeeResource($employee),
            ]);
        }

        // Akses ditolak jika tidak memenuhi aturan otorisasi
        return response()->json([
            'success' => false,
            'message' => 'Forbidden'
        ], 403);
    }

    /**
     * POST /api/employees - Membuat data karyawan baru
     *
     * DUAL MODE OPERATION:
     * Mode 1 - Create User Baru + Employee:
     *   Required: name, email, password, role, position, department_id, join_date, employment_status
     *   Optional: employee_code (auto-generate jika kosong), contact
     *
     * Mode 2 - Gunakan Existing User:
     *   Required: user_id, position, department_id, join_date, employment_status
     *   Optional: employee_code (auto-generate jika kosong), contact
     *
     * Validation Rules:
     * - employee_code: unique, string (nullable - auto-generate format HR-XX jika kosong)
     * - email: unique di tabel users
     * - user_id: unique di tabel employees (1 user = 1 employee max)
     * - role: enum (employee|manager|admin_hr)
     * - employment_status: enum (permanent|contract|intern|resigned)
     *
     * Auto-Generate Employee Code:
     * - Format: HR-01, HR-02, HR-03, dst
     * - Otomatis increment dari code terakhir
     * - Retry mechanism untuk mencegah collision
     *
     * Database Transaction: Semua operasi di-wrap dalam transaction untuk consistency
     *
     * Authorization: Hanya Admin HR yang bisa create employee
     *
     * @param Request $request Request data dengan validation rules
     * @return JsonResponse Created employee data dengan status 201 atau error 422/500
     */
    public function store(Request $request): JsonResponse
    {
        // Cek otorisasi - hanya Admin HR
        $this->authorizeAdmin();

        /* =================================
         * ATURAN VALIDASI - DUAL MODE
         * ================================= */

        $data = $request->validate([
            /* --- MODE 1: BUAT USER BARU --- */
            // Wajib jika user_id tidak ada (mode buat user baru)
            'name' => 'required_without:user_id|string|max:255',
            'email' => 'required_without:user_id|email|unique:users,email', // Email harus unik
            'password' => 'required_without:user_id|string|min:6',
            'role' => 'required_without:user_id|in:employee,manager,admin_hr',

            /* --- MODE 2: GUNAKAN USER YANG SUDAH ADA --- */
            'user_id' => 'required_without:name|exists:users,id|unique:employees,user_id',

            /* --- DATA KARYAWAN (KEDUA MODE) --- */
            'employee_code' => 'nullable|string|max:50|unique:employees,employee_code',
            'position' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'join_date' => 'required|date',
            'employment_status' => 'required|in:permanent,contract,intern,resigned',
            'contact' => 'nullable|string',
        ]);

        /* =================================
         * MULAI TRANSAKSI DATABASE
         * ================================= */

        DB::beginTransaction();
        try {
            // Tentukan user ID (dari yang sudah ada atau akan dibuat baru)
            $userId = $data['user_id'] ?? null;

            /* --- LANGKAH 1: TANGANI PEMBUATAN USER --- */
            if (!$userId) {
                // Mode 1: Buat user baru sekaligus karyawan
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']), // Hash password untuk keamanan
                    'role' => $data['role'],
                    'status_active' => true, // Set aktif secara default
                ]);
                $userId = $user->id;
            }
            // Jika user_id ada, lewati pembuatan user (Mode 2: user yang sudah ada)

            /* --- LANGKAH 2: GENERATE KODE KARYAWAN --- */
            // Auto-generate employee_code jika tidak disediakan
            $employeeCode = $data['employee_code'] ?? $this->generateEmployeeCode();

            /* --- LANGKAH 3: BUAT PROFIL KARYAWAN --- */
            $employeeData = [
                'user_id' => $userId,
                'employee_code' => $employeeCode,
                'position' => $data['position'],
                'department_id' => $data['department_id'],
                'join_date' => $data['join_date'],
                'employment_status' => $data['employment_status'],
                'contact' => $data['contact'] ?? null,
            ];

            // Insert record karyawan
            $employee = Employee::create($employeeData);

            // Eager load relasi untuk response
            $employee->load(['user', 'department']);

            /* --- LANGKAH 4: COMMIT TRANSAKSI --- */
            DB::commit();

            // Kembalikan response sukses dengan status 201 (Created)
            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => new EmployeeResource($employee),
            ], 201);

        } catch (\Exception $e) {
            /* --- PENANGANAN ERROR --- */
            // Rollback semua perubahan jika ada error
            DB::rollBack();

            // Kembalikan error dengan detail untuk debugging
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/employees/{id} - Update data karyawan dan user terkait
     *
     * PARTIAL UPDATE SUPPORT: Semua field optional (sometimes validation)
     *
     * User Data yang bisa diupdate:
     * - name: string, max 255 chars
     * - email: valid email, unique (exclude current user)
     * - password: string, min 6 chars (akan di-hash)
     * - role: enum (employee|manager|admin_hr)
     * - status_active: boolean (aktif/nonaktif user)
     *
     * Employee Data yang bisa diupdate:
     * - employee_code: alpha_num, unique (exclude current employee)
     * - position: string (jabatan)
     * - department: string (departemen)
     * - join_date: valid date
     * - employment_status: enum (permanent|contract|intern|resigned)
     * - contact: string, nullable
     *
     * Database Transaction: Semua update di-wrap dalam transaction
     * Authorization: Hanya Admin HR yang bisa update employee
     *
     * @param Request $request Request data dengan field yang akan diupdate
     * @param string $id Employee ID yang akan diupdate
     * @return JsonResponse Updated employee data atau error
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Jika employee tidak ditemukan
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Cek otorisasi - hanya Admin HR
        $this->authorizeAdmin();

        // Cari karyawan dengan relasi user, lempar 404 jika tidak ada
        $employee = Employee::with('user')->findOrFail($id);

        /* =================================
         * ATURAN VALIDASI - UPDATE SEBAGIAN
         * ================================= */

        $data = $request->validate([
            /* --- DATA USER (UPDATE OPSIONAL) --- */
            'name' => 'sometimes|string|max:255', // Field opsional
            'email' => "sometimes|email|unique:users,email,{$employee->user_id}", // Kecualikan user saat ini dari pengecekan unik
            'password' => 'sometimes|nullable|string|min:6',
            'role' => 'sometimes|in:employee,manager,admin_hr',
            'status_active' => 'sometimes|boolean',

            /* --- DATA KARYAWAN (UPDATE OPSIONAL) --- */
            'employee_code' => "sometimes|string|max:50|unique:employees,employee_code,{$employee->id}",
            'position' => 'sometimes|string',
            'department_id' => 'sometimes|exists:departments,id',
            'join_date' => 'sometimes|date',
            'employment_status' => 'sometimes|in:permanent,contract,intern,resigned',
            'contact' => 'nullable|string',
        ]);

        /* =================================
         * MULAI TRANSAKSI DATABASE
         * ================================= */

        DB::beginTransaction();
        try {
            /* --- LANGKAH 1: SIAPKAN DATA UPDATE USER --- */
            $userUpdateData = [];

            // Bangun array update user hanya untuk field yang ada di request
            if (isset($data['name'])) $userUpdateData['name'] = $data['name'];
            if (isset($data['email'])) $userUpdateData['email'] = $data['email'];
            // Hash password hanya jika tidak null/kosong
            if (isset($data['password']) && !empty($data['password'])) {
                $userUpdateData['password'] = Hash::make($data['password']);
            }
            if (isset($data['role'])) $userUpdateData['role'] = $data['role'];
            if (isset($data['status_active'])) $userUpdateData['status_active'] = $data['status_active'];

            // Update record user jika ada data yang berubah
            if (!empty($userUpdateData)) {
                $employee->user->update($userUpdateData);
            }

            /* --- LANGKAH 2: SIAPKAN DATA UPDATE KARYAWAN --- */
            $employeeUpdateData = [];

            // Bangun array update karyawan hanya untuk field yang ada di request
            if (isset($data['employee_code'])) $employeeUpdateData['employee_code'] = $data['employee_code'];
            if (isset($data['position'])) $employeeUpdateData['position'] = $data['position'];
            if (isset($data['department'])) $employeeUpdateData['department'] = $data['department'];
            if (isset($data['join_date'])) $employeeUpdateData['join_date'] = $data['join_date'];
            if (isset($data['employment_status'])) $employeeUpdateData['employment_status'] = $data['employment_status'];
            if (isset($data['contact'])) $employeeUpdateData['contact'] = $data['contact'];

            // Update record karyawan jika ada data yang berubah
            if (!empty($employeeUpdateData)) {
                $employee->update($employeeUpdateData);
            }

            /* --- LANGKAH 3: REFRESH RELASI & COMMIT --- */
            // Reload relasi untuk response terbaru
            $employee->load(['user', 'department']);

            DB::commit();

            // Kembalikan response sukses dengan data terbaru
            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => new EmployeeResource($employee),
            ]);

        } catch (\Exception $e) {
            /* --- PENANGANAN ERROR --- */
            // Rollback semua perubahan jika ada error
            DB::rollBack();

            // Kembalikan error dengan detail untuk debugging
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/employees/{id} - Menghapus data karyawan
     *
     * SOFT DELETE: Employee record akan dihapus permanen
     * NOTE: User record TIDAK ikut terhapus (hanya employee profile)
     *
     * Authorization: Hanya Admin HR yang bisa delete employee
     *
     * IMPORTANT: Pastikan tidak ada referensi ke employee ini di:
     * - attendance records
     * - leave requests
     * - performance reviews
     * - salary slips
     *
     * @param string $id Employee ID yang akan dihapus
     * @return JsonResponse Success message atau error
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Jika employee tidak ditemukan
     */
    public function destroy(string $id): JsonResponse
    {
        // Cek otorisasi - hanya Admin HR
        $this->authorizeAdmin();

        // Cari dan hapus karyawan (lempar 404 jika tidak ada)
        // CATATAN: Ini hard delete, record user tetap ada
        Employee::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }

    /**
     * GET /api/employees/managers - Mendapatkan daftar semua manager aktif
     *
     * Digunakan untuk:
     * - Dropdown manager di form create/edit employee
     * - Assignment manager ke employee baru
     * - Filter berdasarkan manager di employee list
     *
     * Query Parameters:
     * - search: string (pencarian di nama atau email)
     *
     * Authorization: Admin HR dan Manager yang bisa akses
     *
     * @param Request $request HTTP request dengan optional search parameter
     * @return JsonResponse List of active managers
     */
    public function getManagers(Request $request): JsonResponse
    {
        // Ambil pengguna yang sudah login
        /** @var User $user */
        $user = Auth::guard('api')->user();

        /* =================================
         * CEK OTORISASI
         * ================================= */

        // Hanya Admin HR dan Manager yang bisa mengakses daftar manager
        if (!$user->isAdminHr() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        /* =================================
         * BANGUN QUERY
         * ================================= */

        // Query dasar: ambil user dengan peran manager dan status aktif
        $query = User::where('role', 'manager')
            ->where('status_active', true)
            ->select('id', 'name', 'email', 'role'); // Select field minimal untuk performa

        /* =================================
         * FITUR PENCARIAN
         * ================================= */

        // Pencarian berdasarkan nama atau email (tidak sensitif huruf besar/kecil)
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Eksekusi query dengan pengurutan
        $managers = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Manager list retrieved successfully',
            'data' => $managers,
        ]);
    }

    /**
     * PRIVATE METHOD: Authorization check untuk Admin HR only
     *
     * Digunakan oleh method yang membutuhkan akses Admin HR:
     * - store() - Create employee
     * - update() - Update employee
     * - destroy() - Delete employee
     *
     * Security:
     * - Menggunakan JWT guard 'api' untuk authentication
     * - Check user exists dan role isAdminHr()
     * - Throw 403 Forbidden jika tidak authorized
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 jika tidak authorized
     * @return void
     */
    private function authorizeAdmin(): void
    {
        // Ambil pengguna yang sudah login dari JWT token
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Hentikan dengan 403 jika user tidak ada atau bukan Admin HR
        abort_unless($user && $user->isAdminHr(), 403, 'Forbidden');
    }

    /**
     * PRIVATE METHOD: Auto-generate employee code dengan format HR-XX
     *
     * Format: HR-01, HR-02, HR-03, dst
     * Logic:
     * - Ambil employee_code terakhir dengan prefix "HR-"
     * - Extract nomor urut dan increment +1
     * - Pad dengan leading zero (2 digit)
     * - Pastikan unique dengan retry mechanism jika collision
     *
     * @return string Employee code yang unique (contoh: HR-01, HR-02)
     */
    private function generateEmployeeCode(): string
    {
        // Loop untuk memastikan mendapat kode yang unik
        do {
            // Ambil kode karyawan terakhir dengan prefix HR-
            $lastEmployee = Employee::where('employee_code', 'like', 'HR-%')
                ->orderBy('employee_code', 'desc')
                ->first();

            if ($lastEmployee) {
                // Ekstrak nomor dari employee_code terakhir (HR-01 -> 01)
                $lastNumber = (int) substr($lastEmployee->employee_code, 3);
                $nextNumber = $lastNumber + 1;
            } else {
                // Jika belum ada karyawan dengan prefix HR-, mulai dari 1
                $nextNumber = 1;
            }

            // Format dengan leading zero: HR-01, HR-02, dst
            $newEmployeeCode = 'HR-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

            // Cek apakah kode sudah ada (untuk mencegah race condition)
            $exists = Employee::where('employee_code', $newEmployeeCode)->exists();

        } while ($exists); // Retry jika masih ada bentrokan

        return $newEmployeeCode;
    }
}
