<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Cek role valid
        $role = $user->role instanceof \UnitEnum ? $user->role->value : $user->role;
        if (!in_array($role, ['employee', 'manager', 'admin_hr'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Invalid role.'
            ], 403);
        }

        // Pastikan punya employee profile
        if (!$user->employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee profile not found. Please contact HR Administrator.'
            ], 404);
        }

        // HANYA load relasi manager JIKA role-nya employee dan admin_hr
       if ($role === 'manager') {
            $user->loadMissing('employee.department');
        } else {
            // employee & admin_hr → load department dan manager-nya
            $user->loadMissing([
                'employee.department.manager' => fn ($query) => $query->select('id', 'name')
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully',
            'data'    => new ProfileResource($user)
        ]);
    }
}