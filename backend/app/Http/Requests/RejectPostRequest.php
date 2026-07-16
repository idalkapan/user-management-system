<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectPostRequest extends FormRequest
{
    /**
     * Bu isteği yalnızca route üzerindeki auth ve admin
     * middleware kontrollerini geçen kullanıcı kullanabilir.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Ret sebebi yazılması zorunludur.',
            'rejection_reason.string' => 'Ret sebebi metin formatında olmalıdır.',
            'rejection_reason.max' => 'Ret sebebi en fazla 500 karakter olabilir.',
        ];
    }

    public function attributes(): array
    {
        return [
            'rejection_reason' => 'ret sebebi',
        ];
    }
}