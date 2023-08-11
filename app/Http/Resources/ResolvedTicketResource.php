<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResolvedTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->old_ticket_id,
            'user_id' => $this->user_id,
            'manager_id' => $this->manager_id,
            'reason_id' => $this->reason_id,
            'weight' => $this->weight,
            'mark' => $this->mark,
            'created_at' => $this->created_at,
            'user' => $this->user,
            'manager' => $this->manager,
            'reason' => $this->reason,
        ];
    }
}
