<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        // Pin the initial manual choice once; only automatic mode should roll it forward.
        // Never overwrite an administrator's existing year selection.
        $schema->getConnection()->table('settings')->insertOrIgnore([
            'key' => 'huseyinfiliz-rewind.active_year',
            'value' => (string) ((int) gmdate('Y') - 1),
        ]);
    },
    'down' => function (Builder $schema): void {
        // Keep the saved manual choice on downgrade as well.
    },
];
