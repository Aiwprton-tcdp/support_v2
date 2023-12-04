<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstructionRequest extends FormRequest
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
            'name' => 'required|string|min:3|max:150',
            'content' => 'required|string|min:3|max:150',
            'reason_id' => 'required|int|min:1',
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
            'reason_id.min' => 'Указан некорректный ID темы',
            'message.min' => 'Сообщение слишком короткое',
            'message.max' => 'Сообщение слишком длинное',
        ];
    }
}
