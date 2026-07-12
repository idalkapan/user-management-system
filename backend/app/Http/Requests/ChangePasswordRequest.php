<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
            ];
    }
     /**
     * Get the custom validation messages.
     */
     public function messages(): array
     {
         return [
             'current_password.required' => 'Mevcut şifre alanı zorunludur.',
             'current_password.current_password' => 'Mevcut şifre hatalı.',
 
             'password.required' => 'Yeni şifre alanı zorunludur.',
             'password.min' => 'Yeni şifre en az 8 karakter olmalıdır.',
             'password.confirmed' => 'Yeni şifreler eşleşmiyor.',
         ];
     }
}
