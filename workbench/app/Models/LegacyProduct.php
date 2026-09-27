<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * No cast on `meta`, so the column round-trips as a raw JSON string.
 *
 * This is the shape that 2.x corrupted: its fill re-read the column between
 * children, so the second child saw the first child's encoded string.
 */
class LegacyProduct extends Model
{
    protected $table = 'products';

    protected $guarded = [];
}
