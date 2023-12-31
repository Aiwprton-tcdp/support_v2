<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
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
            'reason_id' => 'sometimes|required|int',
            // 'weight' => 'sometimes|required|int',
            'active' => 'sometimes|required|boolean',
            'incompetence' => 'sometimes|required|boolean',
            'technical_problem' => 'sometimes|required|boolean',
        ];
    }
}
