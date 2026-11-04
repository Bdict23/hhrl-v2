<?php

namespace App\Models\Suite;

use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'icon'])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    protected $table = 'room_amenities';

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_room_amenities', 'amenity_id', 'room_id');
    }
}
