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
            'id' => $this->id,
            'old_ticket_id' => $this->old_ticket_id,
            'user_id' => $this->new_user_id,
            'manager_id' => $this->new_manager_id,
            'user_crm_id' => $this->user_crm_id,
            'manager_crm_id' => $this->manager_crm_id,
            'reason_id' => $this->reason_id,
            'weight' => $this->weight,
            'mark' => $this->mark,
            'created_at' => $this->created_at,
            'user' => $this->user,
            'manager' => $this->manager,
            'reason' => $this->reason,
            'bx_name' => @$this->bx_name,
            'bx_acronym' => @$this->bx_acronym,
            'bx_domain' => @$this->bx_domain,
        ];
    }
}
