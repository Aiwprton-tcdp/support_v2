<?php

namespace App\Http\Resources;

use App\Models\BxCrm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $app_domain = BxCrm::leftJoin('tickets', 'tickets.crm_id', 'bx_crms.id')
            ->leftJoin('resolved_tickets AS rt', 'rt.crm_id', 'bx_crms.id')
            ->where('tickets.id', $this->ticket_id)
            ->orWhere('rt.old_ticket_id', $this->ticket_id)
            ->first();

        if (isset($this->attachments) && !empty($this->attachments) && isset($app_domain)) {
            $at = array_filter($this->attachments->all(), fn($e) => get_headers($app_domain->app_domain . $e->link, 1)[0] == 'HTTP/1.1 200 OK');
        } else {
            $at = [];
        }

        return [
            'id' => $this->id,
            'content' => $this->content,
            'attachments' => $at,
            'attachments_domain' => $app_domain->app_domain,
            'user_crm_id' => $this->user_crm_id,
            'user_id' => $this->new_user_id,
            'user' => $this->new_user_id == 1 ? null : $this->user,
            'ticket_id' => $this->ticket_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}