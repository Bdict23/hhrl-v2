<?php

namespace App\Models\BanquetEvent;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class EventMenuPackage extends Model
{
    protected $table = 'event_menu_package_recipes';

    protected $fillable = [
        'event_menu_id',
        'recipe_package_id',
        'recipe_id',
        'package_code',
        'package_name',
        'description',
        'note',
        'quantity',
    ];

    public function eventMenu(): BelongsTo
    {
        return $this->belongsTo(EventMenu::class, 'event_menu_id');
    }
}
