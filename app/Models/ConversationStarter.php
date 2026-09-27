<?php

namespace App\Models;

use Database\Factories\ConversationStarterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['text_fr', 'text_en', 'is_active', 'sort_order'])]
class ConversationStarter extends Model
{
    /** @use HasFactory<ConversationStarterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
