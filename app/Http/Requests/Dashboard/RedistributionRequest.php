<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class RedistributionRequest extends FormRequest
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
            'user_crm_id' => 'required|int|min:1',
            'reason_id' => 'required|int|min:1',
            'count' => 'required|int|min:1',
            'new_crm_ids' => 'required|array|min:1',
            'new_crm_ids.*' => 'int',
        ];
    }
}
