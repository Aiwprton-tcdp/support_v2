<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReasonRequest extends FormRequest
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
            'name' => 'required|unique:reasons|string|min:2',
            'weight' => 'required|int|min:1|max:100',
            'group_id' => 'required|int|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Тема с таким названием уже существует',
            'group_id.min' => 'Не указана группа',
            'weight.min' => 'Вес должен быть не менее 1',
            'weight.max' => 'Вес должен быть не более 100',
        ];
    }
}
