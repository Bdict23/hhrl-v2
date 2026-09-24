<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CarouselSlide extends Model
{
    use HasFactory;

    protected $table = 'carousel_slides';

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    // public function getImageUrlAttribute(): string
    // {
    //     if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
    //         return $this->image_path;
    //     }

    //     if (str_starts_with($this->image_path, 'assets/') || str_starts_with($this->image_path, '/assets/')) {
    //         return '/' . ltrim($this->image_path, '/');
    //     }

    //     return '/storage/' . ltrim($this->image_path, '/');
    // }

    public function getImageUrlAttribute(): string
    {
        $imagePath = (string) $this->image_path;

        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        if (str_starts_with($imagePath, 'assets/') || str_starts_with($imagePath, '/assets/')) {
            return '/' . ltrim($imagePath, '/');
        }

        return url(Storage::disk('public')->url($imagePath));
    }

    /**
     * Retrieve active slides or a default fallback if none exist.
     *
     * @return Collection<int, self>
     */
    public static function getActiveSlides(): Collection
    {
        return static::active()->get();
    }
}
