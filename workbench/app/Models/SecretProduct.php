<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Already encrypted by Eloquent, so ->encrypted() on top would double-encrypt.
 */
class SecretProduct extends Model
{
    protected $table = 'products';

    protected $guarded = [];

    protected $casts = [
        'vault' => 'encrypted:array',
    ];
}
