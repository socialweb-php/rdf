<?php

/**
 * This file is part of socialweb/rdf
 *
 * Copyright © socialweb/rdf Contributors
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

use Throwable;
use UnexpectedValueException;

use function sprintf;

/**
 * Thrown when an N-Quads document does not conform to the N-Quads 1.1 grammar
 *
 * The reason says which rule the line broke, and the line and column say
 * where. The properties are named `lineNumber` and `columnNumber` because
 * `Exception` already declares `$line`, which backs `getLine()`.
 */
final class MalformedNQuads extends UnexpectedValueException implements RdfException
{
    /**
     * @param MalformedNQuadsReason $reason Which rule the line broke
     * @param int $lineNumber The one-based line number where the problem was
     *     detected
     * @param int $columnNumber The one-based column where the problem was
     *     detected, counted in code points
     * @param string $offendingLine The full text of the line that failed to
     *     parse
     * @param string $detail What was wrong, in words, starting in lowercase
     */
    public function __construct(
        public readonly MalformedNQuadsReason $reason,
        public readonly int $lineNumber,
        public readonly int $columnNumber,
        public readonly string $offendingLine,
        string $detail,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Malformed N-Quads at line %d, column %d: %s; the offending line is "%s"',
                $lineNumber,
                $columnNumber,
                $detail,
                $offendingLine,
            ),
            0,
            $previous,
        );
    }
}
