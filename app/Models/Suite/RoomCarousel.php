<?php

namespace App\Models\Suite;

use Database\Factories\CarouselFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'subtitle', 'image_path', 'cta_text', 'cta_url', 'sort_order', 'is_active'])]
class RoomCarousel extends Model
{
    /** @use HasFactory<CarouselFactory> */
    use HasFactory;

    protected $table = 'room_carousels';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }
}
