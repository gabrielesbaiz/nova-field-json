<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Enums;

/**
 * The editing experience rendered by the JsonEditor field.
 */
enum EditorMode: string
{
    /** Code editor over the pretty-printed JSON source. */
    case Raw = 'raw';

    /** Flat, typed key/value rows. */
    case KeyValue = 'keyvalue';

    /** Collapsible tree over arbitrarily nested structures. */
    case Tree = 'tree';

    /** Repeated rows of a declared Nova sub-field schema. */
    case Repeatable = 'repeatable';
}
