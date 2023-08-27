<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetalizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->tid,
            'user_id' => $this->user_id,
            'manager_id' => $this->manager_id,
            'reason_id' => $this->reason_id,
            'weight' => $this->weight,
            'mark' => $this->mark,
            'active' => $this->active,
            // 'created_at' => $this->created_at,
            'reason' => $this->reason,
            'user' => $this->user,
            'manager' => $this->manager,
            'time' => $this->time,
            'start_date' => $this->start_date,
        ];
    }
}
