<?php

declare(strict_types=1);

namespace Phil\DevTools\Init;

/**
 * What `init` did with a file.
 */
enum InitStatus: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';

    /** The file exists with other content, and was left as it is: `--force` replaces it. */
    case Kept = 'kept';
}
