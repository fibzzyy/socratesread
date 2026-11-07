<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentQuizQuestion extends Model
{
    protected $fillable = [
        'document_id',
        'sequence',
        'question',
        'options',
        'correct_answer',
        'explanation',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'options' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
