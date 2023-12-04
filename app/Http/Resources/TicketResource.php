<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
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
            // 'user_crm_id' => $this->user_crm_id,
            'user_id' => $this->user_id,
            'new_user_id' => $this->new_user_id,
            // 'manager_crm_id' => $this->manager_crm_id,
            'manager_id' => $this->manager_id,
            'new_manager_id' => $this->new_manager_id,
            'crm_id' => $this->crm_id,
            'reason_id' => $this->reason_id,
            'weight' => $this->weight,
            'active' => $this->active,
            'anydesk' => @$this->anydesk,
            'incompetence' => $this->incompetence,
            'technical_problem' =>$this->technical_problem,
            'created_at' => $this->created_at,
            'reason' => $this->reason,
            'user' => $this->user,
            'manager' => $this->manager,
            // 'last_message_crm_id' => $this->last_message_crm_id,
            'last_message_user_id' => $this->last_message_user_id,
            'last_message_date' => $this->last_message_date,
            'last_system_message_date' => @$this->last_system_message_date,
            'bx_name' => @$this->bx_name,
            'bx_acronym' => @$this->bx_acronym,
            'bx_domain' => @$this->bx_domain,
            // 'bx_user_name' => @$this->bx_user_name,
            // 'bx_user_acronym' => @$this->bx_user_acronym,
            // 'bx_user_domain' => @$this->bx_user_domain,
            // 'bx_manager_name' => @$this->bx_manager_name,
            // 'bx_manager_acronym' => @$this->bx_manager_acronym,
            // 'bx_manager_domain' => @$this->bx_manager_domain,
            // 'messages_count' => $this->messages_count,
            'pinned' => false,
        ];
        // return parent::toArray($request);
    }
}
