<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'role'            => is_object($this->role) ? $this->role->value : $this->role,
            'status_active'   => $this->status_active,

            // Data dari tabel employees (jika ada)
            'profile' => $employee ? [
                'employee_code'     => $employee->employee_code,
                'position'          => $employee->position,
                'department'        => $employee->department ? [
                    'id'   => $employee->department->id,
                    'name' => $employee->department->name,
                ] : null,
                'join_date'         => $employee->join_date?->format('Y-m-d'),
                'employment_status' => $employee->employment_status?->value ?? null,
                'contact'           => $employee->contact,

                // Manager dari department (untuk employee & admin_hr)
                'manager' => $employee->department?->manager ? [
                    'id'   => $employee->department->manager->id,
                    'name' => $employee->department->manager->name,
                ] : null,
            ] : null,
        ];
    }
}