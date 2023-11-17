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
        $images = [];
        $files = [];
        $imgTypes = ['png', 'jpeg', 'jpg', 'bmp', 'webp', 'heic'];

        $at = $this->attachments ?? [];
        // if (isset($this->attachments) && !empty($this->attachments) && isset($this->attachments_domain)) {
        //     $at = array_filter($this->attachments->all(), fn($e) => get_headers($this->attachments_domain . $e->link, 1)[0] == 'HTTP/1.1 200 OK');
        // } else {
        //     $at = [];
        // }

        foreach ($at as $file) {
            $name = $file->name;
            $part = explode('.', $name);
            $ext = $part[count($part) - 1];
            if (in_array($ext, $imgTypes)) {
                array_push($images, $file);
            } else {
                array_push($files, $file);
            }
        }

        return [
            'id' => $this->id,
            'content' => $this->content,
            // 'attachments' => $at,
            'images' => $images,
            'files' => $files,
            // 'attachments_domain' => $app_domain->app_domain,
            'attachments_domain' => @$this->attachments_domain,
            'user_crm_id' => $this->user_crm_id,
            'user_id' => $this->new_user_id,
            'user' => $this->new_user_id == 1 ? null : $this->user,
            'ticket_id' => $this->ticket_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}