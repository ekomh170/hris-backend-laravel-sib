<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'position' => $this->position,
            'department' => $this->department,
            'join_date' => $this->join_date?->format('Y-m-d'),
            'employment_status' => $this->employment_status,
            'contact' => $this->contact,

            // Relasi user tanpa field yang sensitive
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'role' => $this->user?->role,
                'status_active' => $this->user?->status_active,
            ],

            // Relasi manager (always present with consistent structure)
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
            ] : [
                'id' => null,
                'name' => null,
                'email' => null,
            ],
        ];
    }
}
