<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleKeyword extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha_validacion' => 'date'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
