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

namespace SocialWeb\Rdf;

use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\NQuads\Grammar;

use function preg_match;
use function sprintf;

/**
 * An Internationalized Resource Identifier (IRI)
 *
 * IRIs are compared by exact string comparison, as RDF 1.1 Concepts section
 * 3.2 prescribes. No normalization is applied and relative references are not
 * resolved.
 */
final readonly class Iri implements GraphName, Resource
{
    /**
     * @param string $value An absolute IRI
     *
     * @throws InvalidArgument if the value is empty, is not valid UTF-8, has no
     *     scheme, or contains a character the N-Quads IRIREF production forbids
     */
    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new InvalidArgument('An IRI must not be empty');
        }

        if (preg_match('//u', $value) !== 1) {
            throw new InvalidArgument('An IRI must be valid UTF-8');
        }

        if (preg_match(Grammar::SCHEME, $value) !== 1) {
            throw new InvalidArgument(sprintf('An IRI must be absolute; "%s" has no scheme', $value));
        }

        if (preg_match(Grammar::IRIREF_FORBIDDEN, $value) === 1) {
            throw new InvalidArgument(sprintf('"%s" contains a character that is not allowed in an IRI', $value));
        }
    }

    public function equals(Term | GraphName $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }
}
