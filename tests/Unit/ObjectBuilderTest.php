<?php

use Well35\EnumObjects\EnumLocator;
use Well35\EnumObjects\Exceptions\EnumObjectsException;
use Well35\EnumObjects\ObjectBuilder;
use Well35\EnumObjects\Tests\Fixtures\Enums\ItemRarity;
use Well35\EnumObjects\Tests\Fixtures\Invalid\BadComputedProperty;

it('builds the full object for a case with properties', function () {
    $objects = new ObjectBuilder('label', 'name', 'value', 'label')->build(ItemRarity::class);

    expect($objects['Common'])->toBe([
        'name' => 'Common',
        'value' => 'common',
        'label' => 'Common',
        'group' => 'items',
        'color' => '#999',
        'sortWeight' => 1,
        'icon' => 'circle',
        'is_public' => true,
        'description' => 'A common item',
    ]);
});

it('rejects ComputedProperty on methods with required parameters', function () {
    new ObjectBuilder('label', 'name', 'value', 'label')->build(BadComputedProperty::class);
})->throws(EnumObjectsException::class, 'requires arguments');

it('errors clearly when a configured path does not exist', function () {
    new EnumLocator(['App\\Nope' => __DIR__.'/does-not-exist'])->enums();
})->throws(EnumObjectsException::class, 'does not exist');
