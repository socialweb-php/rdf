<?php

/**
 * This file is part of socialweb/rdf
 *
 * socialweb/rdf is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * socialweb/rdf is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with socialweb/rdf. If not, see <https://www.gnu.org/licenses/>.
 *
 * SPDX-License-Identifier: LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace SocialWeb\Rdf\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Thrown when an N-Quads document does not conform to the N-Quads 1.1 grammar
 */
final class MalformedNQuads extends RuntimeException implements RdfException
{
    /**
     * The properties are named `lineNumber` and `columnNumber` because
     * `Exception` already declares `$line`, which backs `getLine()`.
     *
     * @param int $lineNumber The one-based line number where the problem was detected
     * @param int $columnNumber The one-based column where the problem was detected
     * @param string $offendingLine The full text of the line that failed to parse
     * @param string $reason A short description of what was wrong
     */
    public function __construct(
        public readonly int $lineNumber,
        public readonly int $columnNumber,
        public readonly string $offendingLine,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Malformed N-Quads at line %d, column %d: %s. Offending line: %s',
                $lineNumber,
                $columnNumber,
                $reason,
                $offendingLine,
            ),
            0,
            $previous,
        );
    }
}
