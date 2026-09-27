<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * The ordinary case: the JSON column is cast, so Eloquent encodes it.
 */
class Product extends Model
{
    protected $table = 'products';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'settings' => AsArrayObject::class,
    ];
}
