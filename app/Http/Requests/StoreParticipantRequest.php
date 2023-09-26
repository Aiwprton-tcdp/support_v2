<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParticipantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'ticket_id' => 'required|int|min:1',
            // 'user_crm_id' => 'required|int|min:1',
            'user_id' => 'required|int|min:1',
            // 'user_crm_id' => [
            //     'required',
            //     'numeric',
            //     'min:1',
            //     Rule::unique('participants', 'user_crm_id')
            //         ->where('ticket_id', $this->input('ticket_id')),
            // ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_crm_id.unique' => 'Данный менеджер уже добавлен в этом тикете',
        ];
    }
}