<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
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
            'content' => 'required_without:0|string',
            // 'user_crm_id' => 'required|numeric|min:1',
            'ticket_id' => 'required|int|min:1',
            '0' => 'required_without:content|mimes:jpeg,jpg,bmp,png,webp,heic|max:25000',
            '1' => 'mimes:jpeg,jpg,bmp,png,webp,heic|max:25000',
            '2' => 'mimes:jpeg,jpg,bmp,png,webp,heic|max:25000',
            '3' => 'mimes:jpeg,jpg,bmp,png,webp,heic|max:25000',
            '4' => 'mimes:jpeg,jpg,bmp,png,webp,heic|max:25000',
            // '5' => 'mimes:jpeg,jpg,bmp,png,webp,heic,pdf,doc,docx,xls,xlsx|max:25000',
            // '6' => 'mimes:jpeg,jpg,bmp,png,webp,heic,pdf,doc,docx,xls,xlsx|max:25000',
            // '7' => 'mimes:jpeg,jpg,bmp,png,webp,heic,pdf,doc,docx,xls,xlsx|max:25000',
            // '8' => 'mimes:jpeg,jpg,bmp,png,webp,heic,pdf,doc,docx,xls,xlsx|max:25000',
            // '9' => 'mimes:jpeg,jpg,bmp,png,webp,heic,pdf,doc,docx,xls,xlsx|max:25000',
            // '10' => 'mimes:jpeg,jpg,bmp,png,webp,heic,pdf,doc,docx,xls,xlsx|max:25000',
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_id.min' => 'ID тикета не определено',
            '0.mimes' => 'Допускаются только изображения',
            '1.mimes' => 'Допускаются только изображения',
            '2.mimes' => 'Допускаются только изображения',
            '3.mimes' => 'Допускаются только изображения',
            '4.mimes' => 'Допускаются только изображения',
        ];
    }
}
