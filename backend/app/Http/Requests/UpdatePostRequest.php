<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'featured_image' => 'nullable|string',
            'status' => 'required|in:draft,published',
            ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Başlık alanı zorunludur.',
            'title.string' => 'Başlık metin formatında olmalıdır.',
            'title.max' => 'Başlık en fazla 255 karakter olabilir.',

            'content.required' => 'İçerik alanı zorunludur.',
            'content.string' => 'İçerik metin formatında olmalıdır.',

            'featured_image.string' => 'Öne çıkan görsel alanı metin formatında olmalıdır.',

            'status.required' => 'Durum alanı zorunludur.',
            'status.in' => 'Durum yalnızca draft veya published olabilir.',
            ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'başlık',
            'content' => 'içerik',
            'featured_image' => 'öne çıkan görsel',
            'status' => 'durum',
            ];
    }
}
