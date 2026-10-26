<?php

namespace HuseyinFiliz\Rewind\Model;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property int $year
 * @property array<string, mixed> $data
 * @property \Carbon\Carbon $generated_at
 */
class CommunitySnapshot extends AbstractModel
{
    protected $table = 'rw_community';

    public $timestamps = false;

    protected $fillable = [
        'year',
        'data',
        'generated_at',
    ];

    protected $casts = [
        'data' => 'array',
        'generated_at' => 'datetime',
    ];
}
