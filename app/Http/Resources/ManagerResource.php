<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerResource extends JsonResource
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
            // 'crm_id' => $this->crm_id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'role_id' => $this->role_id,
            'in_work' => $this->in_work,
            'created_at' => $this->created_at,
        ];
    }
}
