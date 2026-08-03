<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveCommentReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('admin_note') && is_string($this->input('admin_note'))) {
            $this->merge([
                'admin_note' => trim($this->input('admin_note')) ?: null,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::in(['keep', 'remove']),
            ],
            'admin_note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Moderasyon kararı seçilmelidir.',
            'action.string' => 'Moderasyon kararı metin formatında olmalıdır.',
            'action.in' => 'Geçersiz moderasyon kararı seçildi.',
            'admin_note.string' => 'Admin notu metin formatında olmalıdır.',
            'admin_note.max' => 'Admin notu en fazla 500 karakter olabilir.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => 'moderasyon kararı',
            'admin_note' => 'admin notu',
        ];
    }
}
