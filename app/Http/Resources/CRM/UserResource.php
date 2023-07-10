<?php

namespace App\Http\Resources\CRM;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'crm_id' => $this['ID'],
            'name' => trim($this['LAST_NAME'] . " " . $this['NAME'] . " " . $this['SECOND_NAME']),
            'avatar' => $this['PERSONAL_PHOTO'],
            'post' => trim($this['WORK_POSITION']),
            'departments' => $this['UF_DEPARTMENT'],
            'inner_phone' => $this['UF_PHONE_INNER'],
        ];
    }
}
