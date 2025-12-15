<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isIndexRequest = $request->is('api/performance-reviews')
                       || $request->routeIs('performance-reviews.index');

        $isEmployeeDetailRequest = $request->is('api/performance-reviews/employee/*');

        // Base data yang selalu ditampilkan
        $data = [
            'id'                 => $this->id,
            'period'             => $this->period,
            'total_star'         => $this->total_star,
            'review_description' => $this->review_description,
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),

            // Data karyawan yang boleh dilihat (tanpa ID sensitif)
            'employee' => $this->whenLoaded('employee', fn() => [
                'id'    => $this->employee?->id,
                'name'  => $this->employee?->user?->name,
                'email' => $this->employee?->user?->email,
                'employee_code' => $this->employee?->employee_code,
                'position' => $this->employee?->position,
            ]),

            // Data reviewer (manager/admin yang memberi review)
            'reviewer' => $this->whenLoaded('reviewer', fn() => [
                'id'   => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
            ]),
        ];

        // Untuk endpoint employee detail: tampilkan data lengkap termasuk department
        if ($isEmployeeDetailRequest) {
            return array_merge($data, [
                'employee_id'  => $this->employee_id,
                'reviewer_id'  => $this->reviewer_id,
                'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),

                // Enhanced employee data dengan department
                'employee' => $this->whenLoaded('employee', fn() => [
                    'id'    => $this->employee?->id,
                    'name'  => $this->employee?->user?->name,
                    'email' => $this->employee?->user?->email,
                    'employee_code' => $this->employee?->employee_code,
                    'position' => $this->employee?->position,
                    'department' => $this->when(
                        $this->employee && $this->employee->relationLoaded('department'),
                        fn() => [
                            'id' => $this->employee?->department?->id,
                            'name' => $this->employee?->department?->name,
                        ]
                    ),
                ]),

                // Enhanced reviewer data dengan role/feedback_from
                'reviewer' => $this->whenLoaded('reviewer', function() {
                    $reviewer = $this->reviewer;
                    if (!$reviewer) return null;

                    // Tentukan feedback_from berdasarkan role
                    $feedbackFrom = match($reviewer->role->value) {
                        'admin_hr' => 'HR Department',
                        'manager' => 'Manager',
                        'employee' => 'Peer Review',
                        default => 'Unknown'
                    };

                    return [
                        'id'   => $reviewer->id,
                        'name' => $reviewer->name,
                        'role' => $reviewer->role->value,
                        'feedback_from' => $feedbackFrom,
                    ];
                }),
            ]);
        }

        // Jika BUKAN dari index (misalnya dari show(), me(), dll), tampilkan full
        if (! $isIndexRequest) {
            return array_merge($data, [
                'employee_id'  => $this->employee_id,
                'reviewer_id'  => $this->reviewer_id,
                'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
            ]);
        }

        // Untuk index(): SEMUA field sensitif disembunyikan
        return $data;
    }
}
