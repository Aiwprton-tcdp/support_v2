<?php

namespace App\Http\Resources;

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
        if (isset($this->attachments)) {
            $at = array_filter($this->attachments->all(), fn($e) => get_headers(env('APP_URL') . $e->link, 1)[0] == 'HTTP/1.1 200 OK');
        } else {
            $at = [];
        }

        return [
            'id' => $this->id,
            'content' => $this->content,
            'attachments' => $at,
            'user_id' => $this->user_crm_id,
            'ticket_id' => $this->ticket_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}