<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
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
     * - manager_id: integer (filter berdasarkan manager)
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
        // Mendapatkan authenticated user dari JWT token
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Authorization check - hanya Admin HR dan Manager yang bisa akses
        if ($user->isAdminHr()) {
            // Admin HR dapat melihat semua employee
            $query = Employee::with(['user', 'manager']);
        } elseif ($user->isManager()) {
            // Manager hanya bisa melihat employee yang di-manage
            $query = Employee::with(['user', 'manager'])
                ->managedBy($user->id);
        } else {
            // Employee biasa tidak bisa akses daftar employee
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        /* =================================
         * SEARCH & FILTER FUNCTIONALITY
         * ================================= */

        // Global search - mencari di nama, email, employee_code (defined di Employee model scope)
        if ($search = $request->query('search')) {
            $query->search($search);
        }

        // Filter berdasarkan departemen (case-insensitive partial match)
        if ($department = $request->query('department')) {
            $query->where('department', 'like', "%{$department}%");
        }

        // Filter berdasarkan status kerja (exact match)
        // Nilai valid: permanent, contract, intern, resigned
        if ($status = $request->query('employment_status')) {
            $query->where('employment_status', $status);
        }

        // Filter berdasarkan posisi/jabatan (case-insensitive partial match)
        if ($position = $request->query('position')) {
            $query->where('position', 'like', "%{$position}%");
        }

        // Filter berdasarkan manager (exact match dengan manager user_id)
        if ($managerId = $request->query('manager_id')) {
            $query->where('manager_id', $managerId);
        }

        /* =================================
         * SORTING FUNCTIONALITY
         * ================================= */

        // Sorting parameters dengan default values
        $sortBy = $request->query('sort_by', 'employee_code');
        $sortOrder = $request->query('sort_order', 'asc');

        // Whitelist kolom yang boleh di-sort untuk security
        $allowedSorts = ['name', 'employee_code', 'position', 'department', 'join_date'];

        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'name') {
                // Khusus untuk sort by name, perlu join ke tabel users
                $query->orderBy('users.name', $sortOrder);
                $query->join('users', 'employees.user_id', '=', 'users.id');
            } else {
                // Sort berdasarkan kolom di tabel employees
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            // Fallback sorting jika parameter tidak valid
            $query->orderBy('employee_code', 'asc');
        }

        /* =================================
         * PENGATURAN PAGINATION
         * ================================= */

        // Batasi per_page maksimal 100 untuk performa, default 10
        $perPage = min($request->query('per_page', 10), 100);

        // Jalankan query dengan pagination Laravel
        $employees = $query->paginate($perPage);

        /* =================================
         * FORMAT RESPONSE JSON
         * ================================= */

        // Return standardized JSON response dengan pagination metadata lengkap
        return response()->json([
            'success' => true,
            'message' => 'Employee data retrieved successfully',
            'data' => [
                // Pagination info
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),

                // Employee data dengan resource transformation
                'data' => EmployeeResource::collection($employees->items()),

                // Navigation URLs
                'first_page_url' => $employees->url(1),
                'last_page_url' => $employees->url($employees->lastPage()),
                'next_page_url' => $employees->nextPageUrl(),
                'prev_page_url' => $employees->previousPageUrl(),
                'path' => $employees->path(),

                // Pagination links untuk UI
                'links' => $employees->linkCollection()->toArray(),
            ],
        ]);
    }

    /**
     * GET /api/employees/{id} - Menampilkan detail karyawan berdasarkan ID
     *
     * Authorization Rules:
     * - Admin HR: Dapat melihat detail semua employee
     * - Manager: Hanya employee yang di-manage (manager_id == user_id)
     * - Employee: Hanya data diri sendiri (employee.user_id == user_id)
     *
     * @param string $id Employee ID yang akan ditampilkan
     * @return JsonResponse Employee detail dengan relasi user dan manager
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Jika employee tidak ditemukan
     */
    public function show(string $id): JsonResponse
    {
        // Mendapatkan authenticated user
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Find employee dengan eager loading relasi, throw 404 jika tidak ada
        $employee = Employee::with(['user', 'manager'])->findOrFail($id);

        /* =================================
         * AUTHORIZATION CHECK
         * ================================= */

        // Multi-level authorization:
        // 1. Admin HR - full access
        // 2. Manager - hanya employee yang di-manage
        // 3. Employee - hanya data sendiri
        if ($user->isAdminHr() ||
            ($user->isManager() && $employee->manager_id == $user->id) ||
            ($user->id == $employee->user_id)) {

            // Return employee detail dengan resource transformation
            return response()->json([
                'success' => true,
                'message' => 'Employee details retrieved successfully',
                'data' => new EmployeeResource($employee),
            ]);
        }

        // Access denied jika tidak memenuhi authorization rules
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
     *   Required: name, email, password, role, position, department, join_date, employment_status
     *   Optional: employee_code (auto-generate jika kosong), contact, manager_id
     *
     * Mode 2 - Gunakan Existing User:
     *   Required: user_id, position, department, join_date, employment_status
     *   Optional: employee_code (auto-generate jika kosong), contact, manager_id
     *
     * Validation Rules:
     * - employee_code: unique, alpha_num (nullable - auto-generate format HR-XX jika kosong)
     * - email: unique di tabel users
     * - user_id: unique di tabel employees (1 user = 1 employee max)
     * - role: enum (employee|manager|admin_hr)
     * - employment_status: enum (permanent|contract|intern|resigned)
     * - manager_id: must exist in users table
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
        // Authorization check - hanya Admin HR
        $this->authorizeAdmin();

        /* =================================
         * VALIDATION RULES - DUAL MODE
         * ================================= */

        $data = $request->validate([
            /* --- MODE 1: CREATE USER BARU --- */
            // Required jika user_id tidak ada (mode create user baru)
            'name' => 'required_without:user_id|string|max:255',
            'email' => 'required_without:user_id|email|unique:users,email', // Email harus unique
            'password' => 'required_without:user_id|string|min:6',
            'role' => 'required_without:user_id|in:employee,manager,admin_hr', // Tambah admin_hr role

            /* --- MODE 2: GUNAKAN EXISTING USER --- */
            // Required jika name tidak ada (mode existing user)
            // user_id harus unique di employees (1 user = 1 employee max)
            'user_id' => 'required_without:name|exists:users,id|unique:employees,user_id',

            /* --- EMPLOYEE DATA (BOTH MODES) --- */
            'employee_code' => 'nullable|alpha_num|unique:employees,employee_code', // Optional - akan auto-generate jika kosong
            'position' => 'required|string',
            'department' => 'required|string',
            'join_date' => 'required|date',
            'employment_status' => 'required|in:permanent,contract,intern,resigned', // Enum values
            'contact' => 'nullable|string', // Optional
            'manager_id' => 'nullable|exists:users,id', // Must exist if provided
        ]);

        /* =================================
         * DATABASE TRANSACTION START
         * ================================= */

        DB::beginTransaction();
        try {
            // Determine user ID (dari existing atau akan dibuat baru)
            $userId = $data['user_id'] ?? null;

            /* --- STEP 1: HANDLE USER CREATION --- */
            if (!$userId) {
                // Mode 1: Create user baru sekaligus employee
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']), // Hash password untuk security
                    'role' => $data['role'],
                    'status_active' => true, // Set aktif by default
                ]);
                $userId = $user->id;
            }
            // Jika user_id ada, skip user creation (Mode 2: existing user)

            /* --- STEP 2: GENERATE EMPLOYEE CODE --- */
            // Auto-generate employee_code jika tidak disediakan
            $employeeCode = $data['employee_code'] ?? $this->generateEmployeeCode();

            /* --- STEP 3: CREATE EMPLOYEE PROFILE --- */
            $employeeData = [
                'user_id' => $userId, // Link ke user (existing atau baru dibuat)
                'employee_code' => $employeeCode, // UNIQUE constraint (auto-generated atau manual)
                'position' => $data['position'],
                'department' => $data['department'],
                'join_date' => $data['join_date'],
                'employment_status' => $data['employment_status'],
                'contact' => $data['contact'] ?? null, // Nullable field
                'manager_id' => $data['manager_id'] ?? null, // Foreign key ke users
            ];

            // Insert employee record
            $employee = Employee::create($employeeData);

            // Eager load relasi untuk response
            $employee->load(['user', 'manager']);

            /* --- STEP 4: COMMIT TRANSACTION --- */
            DB::commit();

            // Return success response dengan status 201 (Created)
            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => new EmployeeResource($employee),
            ], 201);

        } catch (\Exception $e) {
            /* --- ERROR HANDLING --- */
            // Rollback semua changes jika ada error
            DB::rollBack();

            // Return error dengan detail untuk debugging
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
     * - manager_id: exists in users, nullable
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
        // Authorization check - hanya Admin HR
        $this->authorizeAdmin();

        // Find employee dengan relasi user, throw 404 jika tidak ada
        $employee = Employee::with('user')->findOrFail($id);

        /* =================================
         * VALIDATION RULES - PARTIAL UPDATE
         * ================================= */

        $data = $request->validate([
            /* --- USER DATA (OPTIONAL UPDATE) --- */
            'name' => 'sometimes|string|max:255', // Optional field
            'email' => "sometimes|email|unique:users,email,{$employee->user_id}", // Exclude current user dari unique check
            'password' => 'sometimes|nullable|string|min:6', // Nullable dan akan di-hash jika provided
            'role' => 'sometimes|in:employee,manager,admin_hr', // Extended role options
            'status_active' => 'sometimes|boolean', // Untuk activate/deactivate user

            /* --- EMPLOYEE DATA (OPTIONAL UPDATE) --- */
            'employee_code' => "sometimes|alpha_num|unique:employees,employee_code,{$employee->id}", // Exclude current employee
            'position' => 'sometimes|string',
            'department' => 'sometimes|string',
            'join_date' => 'sometimes|date',
            'employment_status' => 'sometimes|in:permanent,contract,intern,resigned',
            'contact' => 'nullable|string', // Can be set to null
            'manager_id' => 'nullable|exists:users,id', // Can be set to null atau valid user_id
        ]);

        /* =================================
         * DATABASE TRANSACTION START
         * ================================= */

        DB::beginTransaction();
        try {
            /* --- STEP 1: PREPARE USER UPDATE DATA --- */
            $userUpdateData = [];

            // Build user update array hanya untuk field yang ada di request
            if (isset($data['name'])) $userUpdateData['name'] = $data['name'];
            if (isset($data['email'])) $userUpdateData['email'] = $data['email'];
            // Hash password hanya jika tidak null/empty
            if (isset($data['password']) && !empty($data['password'])) {
                $userUpdateData['password'] = Hash::make($data['password']);
            }
            if (isset($data['role'])) $userUpdateData['role'] = $data['role'];
            if (isset($data['status_active'])) $userUpdateData['status_active'] = $data['status_active'];

            // Update user record jika ada data yang berubah
            if (!empty($userUpdateData)) {
                $employee->user->update($userUpdateData);
            }

            /* --- STEP 2: PREPARE EMPLOYEE UPDATE DATA --- */
            $employeeUpdateData = [];

            // Build employee update array hanya untuk field yang ada di request
            if (isset($data['employee_code'])) $employeeUpdateData['employee_code'] = $data['employee_code'];
            if (isset($data['position'])) $employeeUpdateData['position'] = $data['position'];
            if (isset($data['department'])) $employeeUpdateData['department'] = $data['department'];
            if (isset($data['join_date'])) $employeeUpdateData['join_date'] = $data['join_date'];
            if (isset($data['employment_status'])) $employeeUpdateData['employment_status'] = $data['employment_status'];
            if (isset($data['contact'])) $employeeUpdateData['contact'] = $data['contact']; // Can be null
            if (isset($data['manager_id'])) $employeeUpdateData['manager_id'] = $data['manager_id']; // Can be null

            // Update employee record jika ada data yang berubah
            if (!empty($employeeUpdateData)) {
                $employee->update($employeeUpdateData);
            }

            /* --- STEP 3: REFRESH RELATIONS & COMMIT --- */
            // Reload relasi untuk response terbaru
            $employee->load(['user', 'manager']);

            DB::commit();

            // Return success response dengan data terbaru
            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => new EmployeeResource($employee),
            ]);

        } catch (\Exception $e) {
            /* --- ERROR HANDLING --- */
            // Rollback semua changes jika ada error
            DB::rollBack();

            // Return error dengan detail untuk debugging
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
        // Authorization check - hanya Admin HR
        $this->authorizeAdmin();

        // Find and delete employee (throw 404 jika tidak ada)
        // NOTE: Ini hard delete, user record tetap ada
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
        // Get authenticated user
        /** @var User $user */
        $user = Auth::guard('api')->user();

        /* =================================
         * AUTHORIZATION CHECK
         * ================================= */

        // Hanya Admin HR dan Manager yang bisa mengakses daftar manager
        if (!$user->isAdminHr() && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        /* =================================
         * BUILD QUERY
         * ================================= */

        // Base query: ambil user dengan role manager dan status aktif
        $query = User::where('role', 'manager')
            ->where('status_active', true)
            ->select('id', 'name', 'email', 'role'); // Select minimal fields untuk performa

        /* =================================
         * SEARCH FUNCTIONALITY
         * ================================= */

        // Pencarian berdasarkan nama atau email (case-insensitive)
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Execute query dengan sorting
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
        // Get authenticated user dari JWT token
        /** @var User $user */
        $user = Auth::guard('api')->user();

        // Abort dengan 403 jika user tidak ada atau bukan Admin HR
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
        // Loop untuk memastikan mendapat code yang unique
        do {
            // Ambil employee code terakhir dengan prefix HR-
            $lastEmployee = Employee::where('employee_code', 'like', 'HR-%')
                ->orderBy('employee_code', 'desc')
                ->first();

            if ($lastEmployee) {
                // Extract nomor dari employee_code terakhir (HR-01 -> 01)
                $lastNumber = (int) substr($lastEmployee->employee_code, 3);
                $nextNumber = $lastNumber + 1;
            } else {
                // Jika belum ada employee dengan prefix HR-, mulai dari 1
                $nextNumber = 1;
            }

            // Format dengan leading zero: HR-01, HR-02, dst
            $newEmployeeCode = 'HR-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

            // Check apakah code sudah ada (untuk mencegah race condition)
            $exists = Employee::where('employee_code', $newEmployeeCode)->exists();

        } while ($exists); // Retry jika masih ada collision

        return $newEmployeeCode;
    }
}
