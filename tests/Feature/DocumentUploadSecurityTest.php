<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_upload_rejects_a_non_pdf_file(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->from(route('teacher.documents'))
            ->post(route('teacher.documents.store'), [
                'title' => 'Bukan PDF',
                'category' => 'Literasi',
                'file' => UploadedFile::fake()->create('catatan.txt', 1, 'text/plain'),
            ])
            ->assertRedirect(route('teacher.documents'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }
}
