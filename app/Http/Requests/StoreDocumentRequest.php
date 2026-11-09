<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'teacher';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:80'],
            'target_grade' => ['nullable', 'string', 'in:all,10,11,12'],
            'quiz_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'quiz_question_count' => ['nullable', 'integer', 'min:5', 'max:100'],
            'file' => ['required', 'file', File::types(['pdf'])->max('50mb')],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file PDF terlebih dahulu.',
            'file.max' => 'Ukuran PDF maksimal 50 MB.',
            'file.*' => 'File harus berupa PDF yang valid dan berukuran maksimal 50 MB.',
        ];
    }
}
