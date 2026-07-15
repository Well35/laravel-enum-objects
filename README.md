# laravel-enum-objects

[![Tests](https://github.com/Well35/laravel-enum-objects/actions/workflows/tests.yml/badge.svg)](https://github.com/Well35/laravel-enum-objects/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/well35/laravel-enum-objects.svg)](https://packagist.org/packages/well35/laravel-enum-objects)
[![Total Downloads](https://img.shields.io/packagist/dt/well35/laravel-enum-objects.svg)](https://packagist.org/packages/well35/laravel-enum-objects)
[![License](https://img.shields.io/packagist/l/well35/laravel-enum-objects.svg)](https://github.com/Well35/laravel-enum-objects/blob/main/LICENSE)

Generate frontend enum objects from your PHP enums, so the backend
enum is the single source of truth.

Unlike [spatie/laravel-typescript-transformer](https://github.com/spatie/typescript-transformer),
which generates *types*, this package generates *data*. Your frontend gets 
objects it can iterate for option lists, labels, and metadata, and the 
union types come from that data.

```php
enum BrowseSort: string
{
    case Newest = 'newest';
    case Popular = 'popular';

    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Newest',
            self::Popular => 'Most played',
        };
    }
}
```

becomes `resources/js/enums/BrowseSort.ts`:

```ts
export const BrowseSort = {
    Newest: { name: "Newest", value: "newest", label: "Newest" },
    Popular: { name: "Popular", value: "popular", label: "Most played" },
} as const;

export type BrowseSort = (typeof BrowseSort)[keyof typeof BrowseSort]['value'];
```

so the frontend can do:

```ts
import { BrowseSort } from '@/enums/BrowseSort';

Object.values(BrowseSort)          // option lists with labels
BrowseSort.Popular.value           // no magic strings
const foo: BrowseSort = 'newest'   // union type 'newest' | 'popular'
const bar: BrowseSort = 'newst'    // Error: tyop caught before runtime
```

## Installation

```sh
composer require well35/laravel-enum-objects
```

## Usage

```sh
php artisan enum-objects:generate
```

Every enum under `App\Enums` (nested
namespaces mirror into subdirectories) gets one file in
`resources/js/enums`. Commit the generated files and `.enum-objects.json`. 

When an enum is renamed or deleted, its old file is pruned on the next
run. Pruning is manifest based and non-destructive. Only files
the package itself wrote (listed in the manifest) are ever deleted.

### Labels

If the enum has a `label()` method it is called per case, otherwise the
label falls back to `Str::headline()` of the case name (`VeryHigh` →
`"Very High"`).

### Extra properties

The rule: **static values go on the case, computed values go on a method.**

```php
use Well35\EnumObjects\Attributes\ComputedProperty;
use Well35\EnumObjects\Attributes\ObjectProperty;

enum ItemRarity: string
{
    #[ObjectProperty('icon', 'circle')]
    case Common = 'common';

    #[ObjectProperty('icon', 'sparkle')]
    case Legendary = 'legendary';

    #[ComputedProperty]                 // exported as `color`
    public function color(): string
    {
        return match ($this) {
            self::Common => '#999',
            self::Legendary => '#f90',
        };
    }

    #[ComputedProperty('sortWeight')]   // key override
    public function weight(): int { /* ... */ }
}
```

`ComputedProperty` methods must be callable without arguments. Keys are
emitted exactly as you write them (`ComputedProperty` defaults to the
method name).

`ObjectProperty` on the enum class itself adds the property to every
case:

```php
#[ObjectProperty('group', 'items')]     // all cases get group: "items"
enum ItemRarity: string { ... }
```

On a key collision the most specific declaration wins: case-level
`ObjectProperty` beats `ComputedProperty` beats class-level
`ObjectProperty` beats the built-ins.

Keys can also be backed-enum cases instead of strings, so you can 
define your own key enum instead of repeating strings:

```php
enum PropKeys: string { case Group = 'group'; }

#[ObjectProperty(PropKeys::Group, 'items')]
```

### Excluding cases or enums

`#[Excluded]` keeps a case out of the generated object, or if placed on
the enum class, skips the enum entirely:

```php
use Well35\EnumObjects\Attributes\Excluded;

enum Priority: int
{
    case Low = 1;

    #[Excluded]
    case Internal = 99;   // never sent to the frontend
}
```

Excluded cases drop out of the union type too, so don't exclude anything the API still returns.

### Keeping frontend and backend in sync

Add this one test and CI fails whenever an enum changed without
regeneration:

```php
use Well35\EnumObjects\EnumObjects;

test('enum objects are in sync', fn () => EnumObjects::assertInSync());
```

Or in CI:

```sh
php artisan enum-objects:generate --check
```

### Dev watcher

The package ships a Vite plugin that reruns the generator whenever a
PHP enum changes, so the browser reloads with the new objects:

```js
// vite.config.js
import enumObjects from './vendor/well35/laravel-enum-objects/vite-plugin.mjs';

export default defineConfig({
    plugins: [laravel({ /* ... */ }), enumObjects()],
});
```

### Configuration

```sh
php artisan vendor:publish --tag=enum-objects-config
```

```php
return [
    'paths' => ['App\\Enums' => 'app/Enums'], // namespace => directory
    'output_path' => 'resources/js/enums',    // generated files + manifest go here
    'format' => 'ts',                         // ts | json
    'label_method' => 'label',
];
```

### Formatters

Keep your formatter off the generated files, or it will fight 
the sync check:

```
# .prettierignore
resources/js/enums
```

## Caveats

- Pure (unbacked) enums generate with the case name as value and trigger 
  a warning: PHP can't json_encode a pure enum, so if it's ever sent to or 
  received from the frontend, back it.
- Deleting `.enum-objects.json` orphans the generated files. The package
  forgets it wrote them, so renamed/removed enums stop being pruned. 
  Regenerating recreates the manifest.

## Credits

The backend-enums-as-single-source-of-truth generator this package grew
from was [IronSinew](https://github.com/IronSinew)'s idea and original
implementation.

## License

MIT
