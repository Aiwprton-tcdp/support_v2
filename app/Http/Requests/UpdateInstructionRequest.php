<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstructionRequest extends FormRequest
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
            'name' => 'sometimes|required|string|min:3|max:150',
            'content' => 'sometimes|required|string|min:3|max:150',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Название инструкции не указано',
            'name.min' => 'Название инструкции слишком короткое',
            'name.max' => 'Название инструкции слишком длинное',
            'content.required' => 'Содержимое инструкции не указано',
            'content.min' => 'Содержимое инструкции слишком короткое',
            'content.max' => 'Содержимое инструкции слишком длинное',
            'reason_id.required' => 'Тема не определена',
        ];
    }
}
