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
            'page_scope' => ['nullable', 'string', 'in:all,range'],
            'page_start' => ['nullable', 'integer', 'min:1'],
            'page_end' => ['nullable', 'integer', 'min:1'],
            'start_subtopic_heading' => ['nullable', 'string', 'max:160'],
            'last_subtopic_heading' => ['nullable', 'string', 'max:160'],
            'has_teacher_challenges_toggle' => ['nullable'],
            'enable_teacher_challenges' => ['nullable', 'boolean'],
            'curated_body' => ['nullable', 'string'],
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
