<?php

namespace App\Http\Requests;

use App\Models\PostCommentReport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostCommentReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        if (!$this->user() || !$comment) {
            return false;
        }

        return $this->user()->can('create', [PostCommentReport::class, $comment]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                Rule::in(PostCommentReport::REASONS),
            ],
            'description' => [
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
            'reason.required' => 'Şikâyet nedeni seçilmelidir.',
            'reason.string' => 'Şikâyet nedeni metin formatında olmalıdır.',
            'reason.in' => 'Geçersiz şikâyet nedeni seçildi.',
            'description.string' => 'Açıklama metin formatında olmalıdır.',
            'description.max' => 'Açıklama en fazla 500 karakter olabilir.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => 'şikâyet nedeni',
            'description' => 'açıklama',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && is_string($this->input('description'))) {
            $this->merge([
                'description' => trim($this->input('description')) ?: null,
            ]);
        }
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user();
        $comment = $this->route('comment');

        if (!$user) {
            throw new AuthorizationException('Bu işlem için giriş yapmalısınız.');
        }

        if ($user->role === 'admin') {
            throw new AuthorizationException(
                'Bu işlem yalnızca kullanıcılar tarafından yapılabilir.',
            );
        }

        if ($user->role !== 'user') {
            throw new AuthorizationException('Bu işlem için yetkiniz yok.');
        }

        if ($comment && $user->id === $comment->user_id) {
            throw new AuthorizationException('Kendi yorumunuzu şikâyet edemezsiniz.');
        }

        $comment?->loadMissing('post');

        if ($comment && $comment->post?->status !== 'published') {
            throw new AuthorizationException(
                'Yalnızca yayınlanmış yazılardaki yorumlar şikâyet edilebilir.',
            );
        }

        throw new AuthorizationException('Bu işlem için yetkiniz yok.');
    }
}
