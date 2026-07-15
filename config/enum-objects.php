<?php

return [

    /*
    | Which enums to generate, as: PHP namespace => folder containing them.
    |
    |    'App\\Enums' => 'app/Enums'
    |
    | Reads as "app/Enums/Status.php contains App\Enums\Status".
    | Subfolders are scanned too and mirror into the output path.
    */
    'paths' => [
        'App\\Enums' => 'app/Enums',
    ],

    /*
    | Generated files land here, plus a .enum-objects.json manifest
    */
    'output_path' => 'resources/js/enums',

    /*
    | Output format: 'ts' or 'json'.
    */
    'format' => 'ts',

    /*
    | Method called on each case for its label when the enum defines it.
    | Enums without it fall back to Str::headline() of the case name.
    */
    'label_method' => 'label',
];
