<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
            'message' => 'required|string|min:1',
            'anydesk' => 'required|string|min:11|max:13',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Сообщение не указано',
            'message.min' => 'Сообщение слишком короткое',
            'message.max' => 'Сообщение слишком длинное',
            'anydesk.required' => 'Адрес AnyDesk не указан',
            'anydesk.min' => 'Адрес AnyDesk должен состоять минимум из 9 цифр',
            'anydesk.max' => 'Адрес AnyDesk должен состоять максимум из 10 цифр',
        ];
    }
}