<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Deteksi apakah request ini dari endpoint index (admin/manager)
        $isIndexRequest = $request->is('api/leave-requests') 
                       || $request->routeIs('leave-requests.index');

        $baseData = [
            'id'              => $this->id,
            'employee'        => [
                'id'   => $this->employee?->id,
                'name' => $this->employee?->user?->name,
                'email'=> $this->employee?->user?->email ?? null,
                // tambahkan field lain yang boleh dilihat admin (nip, department, dll) di sini nanti
            ],
            'start_date'      => $this->start_date,
            'end_date'        => $this->end_date,
            'reason'          => $this->reason,
            'status'          => $this->status,
            'foto_cuti'       => $this->foto_cuti ? asset('storage/foto_cuti/' . $this->foto_cuti) : null,
            'reviewed_at'     => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'reviewer_note'   => $this->reviewer_note,
            'reviewer'        => $this->whenLoaded('reviewer', fn() => [
                'id'   => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
            ]),
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            // 'updated_at' TIDAK DITAMPILKAN SAMA SEKALI
        ];

        // Untuk endpoint selain index (misalnya me(), review(), show()), tampilkan full data
        if (! $isIndexRequest) {
            return array_merge($baseData, [
                'employee_id'  => $this->employee_id,
                'reviewer_id'  => $this->reviewer_id,
                'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
            ]);
        }

        // Untuk index(): HIDE employee_id, user_id, manager_id, reviewer_id, updated_at
        return $baseData;
    }
}
