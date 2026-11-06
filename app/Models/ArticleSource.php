<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha_consulta' => 'date'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
