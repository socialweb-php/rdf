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

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Thrown when canonicalization reaches a limit the caller set
 *
 * RDF Dataset Canonicalization requires implementations to stop early on
 * inputs that would otherwise take unbounded time; see RDFC-1.0 section 7.1.
 * The limit is the bound in force, whether the caller gave it or the
 * canonicalizer derived it from the work factor.
 */
final class LimitExceeded extends RuntimeException implements RdfException
{
    /**
     * @param string $parameter The name of the constructor parameter whose
     *     limit was exceeded
     * @param int $limit The limit that was in force
     */
    public function __construct(
        public readonly string $parameter,
        public readonly int $limit,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('The %s limit of %d was exceeded', $parameter, $limit), 0, $previous);
    }
}
