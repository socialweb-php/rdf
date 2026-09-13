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
 * Thrown when canonicalization reaches its deep iteration limit
 *
 * RDF Dataset Canonicalization requires implementations to terminate early on
 * inputs that would otherwise take unbounded time. See RDFC-1.0 section 7.1.
 */
final class IterationLimitExceeded extends RuntimeException implements RdfException
{
    /**
     * @param int $limit The limit that was reached
     */
    public function __construct(public readonly int $limit, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Canonicalization exceeded the deep iteration limit of %d', $limit), 0, $previous);
    }
}
