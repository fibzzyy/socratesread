<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocraticQuestion extends Model
{
    protected $fillable = ['document_id', 'sequence', 'skill', 'prompt', 'context', 'hint'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
