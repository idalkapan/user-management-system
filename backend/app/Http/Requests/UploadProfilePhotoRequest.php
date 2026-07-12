<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return  true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
            'required',
            'image',
            'mimes:jpg,jpeg,png',
            'max:2048',
            ],
            
        ];
    }
    /**
     * Get the custom validation messages.
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Profil fotoğrafı seçmeniz gerekmektedir.',
            'photo.image' => 'Yüklenen dosya geçerli bir görsel olmalıdır.',
            'photo.mimes' => 'Profil fotoğrafı JPG, JPEG veya PNG formatında olmalıdır.',
            'photo.max' => 'Profil fotoğrafının boyutu en fazla 2 MB olabilir.',
        ];
    }

}
