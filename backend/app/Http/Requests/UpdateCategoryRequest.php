<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore(
                    $this->route('category')?->id
                    ),
            ],
            'description' => 'nullable|string|max:500',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Kategori adı zorunludur.',
            'name.string' => 'Kategori adı metin formatında olmalıdır.',
            'name.max' => 'Kategori adı en fazla 100 karakter olabilir.',
            'name.unique' => 'Bu kategori adı zaten kullanılıyor.',

            'description.string' => 'Açıklama metin formatında olmalıdır.',
            'description.max' => 'Açıklama en fazla 500 karakter olabilir.',

            'sort_order.required' => 'Sıralama değeri zorunludur.',
            'sort_order.integer' => 'Sıralama değeri tam sayı olmalıdır.',
            'sort_order.min' => 'Sıralama değeri 0 veya daha büyük olmalıdır.',

            'is_active.required' => 'Aktiflik durumu belirtilmelidir.',
            'is_active.boolean' => 'Aktiflik durumu geçerli bir boolean değer olmalıdır.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'kategori adı',
            'description' => 'açıklama',
            'sort_order' => 'sıralama',
            'is_active' => 'aktiflik durumu',
        ];
    }
}
