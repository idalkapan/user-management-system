<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('is_active', true)->whereNull('deleted_at');
                }),
            ],
            'status' => 'required|in:draft,pending',
            'featured_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
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

            'category_id.required' => 'Kategori seçimi zorunludur.',
            'category_id.integer' => 'Kategori geçerli bir sayı olmalıdır.',
            'category_id.exists' => 'Seçilen kategori bulunamadı veya aktif değil.',

            'status.required' => 'Yayın durumu seçilmelidir.',
            'status.in' => 'Geçersiz yayın durumu seçildi.',

            'featured_image.image' => 'Öne çıkan görsel geçerli bir görsel dosyası olmalıdır.',
            'featured_image.mimes' => 'Öne çıkan görsel JPG, JPEG, PNG veya WEBP formatında olmalıdır.',
            'featured_image.max' => 'Öne çıkan görsel en fazla 2 MB olabilir.',
        ];
    }
}