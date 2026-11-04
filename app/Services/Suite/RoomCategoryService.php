<?php

namespace App\Services\Suite;

use App\Models\Suite\RoomCategory;

class RoomCategoryService
{
    /**
     * @param array{name: string, slug: string, description?: string|null, sort_order?: int, is_active?: bool} $data
     */
    public function create(array $data): RoomCategory
    {
        return RoomCategory::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
