<?php

use App\Models\DataManagement\Recipe;

it('retains the menu type when recipe attributes are mass assigned', function () {
    $recipe = new Recipe(['menu_type' => 'Banquet']);

    expect($recipe->menu_type)->toBe('Banquet');
});
