<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Department Controller - Mengelola data departemen (CRUD)
 *
 * Fitur yang tersedia:
 * - CRUD Department (Create, Read, Update, Delete)
 * - Assign manager ke departemen
 * - Search dan filter departemen
 * - Pagination dengan customizable per_page
 * - Authorization berbasis role (Admin HR only)
 * - Database transactions untuk data consistency
 *
 * @author HRIS Development Team
 * @version 1.0
 * @since 2025-12-10
 */
class DepartmentController extends Controller
{
    /**
     * GET /api/departments - Menampilkan daftar semua departemen
     *
     * Query Parameters:
     * - search: string (pencarian berdasarkan nama atau deskripsi)
     * - sort_by: enum (name|manager|employees_count) - default: name
     * - sort_order: enum (asc|desc) - default: asc
     * - per_page: integer (1-100, default: 15)
     * - page: integer (default: 1)
     *
     * @param Request $request HTTP request dengan query parameters
     * @return JsonResponse Paginated list of departments dengan metadata
     */
    public function index(Request $request): JsonResponse
    {
        // Parameter pengurutan
        $sortBy = $request->query('sort_by', 'name');
        $sortOrder = $request->query('sort_order', 'asc');
        $allowedSorts = ['name', 'manager', 'created_at'];

        // Validasi sort_by
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'name';
        }

        // Query dasar (tanpa eager loading dulu)
        $query = Department::query();

        // Pencarian global
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('manager', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Pengurutan dengan distinct() untuk menghindari duplikasi
        if ($sortBy === 'manager') {
            $query->join('users', 'departments.manager_id', '=', 'users.id')
                  ->orderBy('users.name', $sortOrder)
                  ->select('departments.*')
                  ->distinct('departments.id');  // Gunakan distinct untuk menghindari duplikasi
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->query('per_page', 15), 100);
        $departments = $query->paginate($perPage);

        // Eager load relasi SETELAH pagination untuk performa lebih baik
        $departments->getCollection()->transform(function ($department) {
            return $department->load(['manager', 'employees']);
        });

        // Response JSON
        return response()->json([
            'success' => true,
            'message' => 'Department data retrieved successfully',
            'data' => [
                'current_page' => $departments->currentPage(),
                'per_page' => $departments->perPage(),
                'total' => $departments->total(),
                'last_page' => $departments->lastPage(),
                'from' => $departments->firstItem(),
                'to' => $departments->lastItem(),
                'data' => DepartmentResource::collection($departments->items()),
                'first_page_url' => $departments->url(1),
                'last_page_url' => $departments->url($departments->lastPage()),
                'next_page_url' => $departments->nextPageUrl(),
                'prev_page_url' => $departments->previousPageUrl(),
                'path' => $departments->path(),
                'links' => $departments->linkCollection()->toArray(),
            ],
        ]);
    }

    /**
     * GET /api/departments/{id} - Menampilkan detail departemen berdasarkan ID
     *
     * @param int $id Department ID yang akan ditampilkan
     * @return JsonResponse Department detail dengan relasi manager dan employees
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Jika departemen tidak ditemukan
     */
    public function show(int $id): JsonResponse
    {
        // Cari departemen dengan relasi, lempar 404 jika tidak ada
        $department = Department::with(['manager', 'employees.user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Department details retrieved successfully',
            'data' => new DepartmentResource($department),
        ]);
    }

    /**
     * POST /api/departments - Membuat departemen baru
     *
     * Request Body:
     * - name: string (required, unique) - Nama departemen
     * - description: string (nullable) - Deskripsi departemen
     * - manager_id: integer (nullable) - User ID yang akan menjadi manager
     *
     * Validation Rules:
     * - name: required, string, max 255, unique di tabel departments
     * - description: nullable, string
     * - manager_id: nullable, exists di users table, harus role manager atau admin_hr
     *
     * Authorization: Admin HR only
     *
     * @param Request $request Request dengan data departemen
     * @return JsonResponse Created department data dengan status 201 atau error
     */
    public function store(Request $request): JsonResponse
    {
        // Cek otorisasi - hanya Admin HR
        $this->authorizeAdmin();

        // Validasi input
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        // Validasi jika manager_id diberikan, user harus manager atau admin_hr
        if (isset($data['manager_id'])) {
            $manager = User::findOrFail($data['manager_id']);
            if (!$manager->isManager() && !$manager->isAdminHr()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manager must have manager or admin_hr role',
                    'errors' => ['manager_id' => 'User must be a manager or admin_hr']
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Buat departemen
            $department = Department::create($data);

            // Load relasi untuk response
            $department->load(['manager', 'employees']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => new DepartmentResource($department),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create department: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/departments/{id} - Update departemen
     *
     * Request Body (semua field optional):
     * - name: string (unique exclude current) - Nama departemen
     * - description: string (nullable) - Deskripsi departemen
     * - manager_id: integer (nullable) - User ID yang akan menjadi manager
     *
     * Authorization: Admin HR only
     *
     * @param Request $request Request dengan data yang akan diupdate
     * @param int $id Department ID yang akan diupdate
     * @return JsonResponse Updated department data atau error
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Jika departemen tidak ditemukan
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Cek otorisasi - hanya Admin HR
        $this->authorizeAdmin();

        // Cari departemen, lempar 404 jika tidak ada
        $department = Department::findOrFail($id);

        // Validasi input - semua field optional (sometimes)
        $data = $request->validate([
            'name' => "sometimes|required|string|max:255|unique:departments,name,{$id}",
            'description' => 'sometimes|nullable|string',
            'manager_id' => 'sometimes|nullable|exists:users,id',
        ]);

        // Validasi jika manager_id diberikan
        if (isset($data['manager_id']) && $data['manager_id']) {
            $manager = User::findOrFail($data['manager_id']);
            if (!$manager->isManager() && !$manager->isAdminHr()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manager must have manager or admin_hr role',
                    'errors' => ['manager_id' => 'User must be a manager or admin_hr']
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Update departemen
            $department->update($data);

            // Load relasi untuk response
            $department->load(['manager', 'employees']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => new DepartmentResource($department),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update department: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/departments/{id} - Menghapus departemen
     *
     * Note: Departemen hanya bisa dihapus jika tidak ada employee yang terdaftar
     *
     * Authorization: Admin HR only
     *
     * @param int $id Department ID yang akan dihapus
     * @return JsonResponse Success message atau error
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Jika departemen tidak ditemukan
     */
    public function destroy(int $id): JsonResponse
    {
        // Cek otorisasi - hanya Admin HR
        $this->authorizeAdmin();

        // Cari departemen, lempar 404 jika tidak ada
        $department = Department::findOrFail($id);

        // Cek apakah ada employee di departemen ini
        if ($department->employees()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department with active employees',
                'errors' => ['employees' => 'Please reassign employees before deleting this department']
            ], 422);
        }

        try {
            $department->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PRIVATE METHOD: Authorization check untuk Admin HR only
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 jika tidak authorized
     * @return void
     */
    private function authorizeAdmin(): void
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        abort_unless($user && $user->isAdminHr(), 403, 'Forbidden - Admin HR only');
    }
}

