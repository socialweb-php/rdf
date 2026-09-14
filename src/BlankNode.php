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

use Random\RandomException;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\NQuads\Grammar;

use function bin2hex;
use function preg_match;
use function random_bytes;
use function sprintf;

/**
 * A blank node, identified by a label that is meaningful only within one
 * dataset
 *
 * See RDF 1.1 Concepts section 3.4. Blank nodes are compared by exact
 * identifier comparison. This class does not rename blank nodes; the
 * canonicalizer assigns canonical identifiers when asked.
 */
final readonly class BlankNode implements GraphName, Resource
{
    /**
     * @param string $identifier The label without the `_:` prefix, matching the
     *     N-Quads BLANK_NODE_LABEL production as corrected by RDF 1.1 erratum
     *     30, which removed `:` from the characters a label may contain
     *
     * @throws InvalidArgument if the identifier does not match the production
     */
    public function __construct(public string $identifier)
    {
        if (preg_match(Grammar::BLANK_NODE_LABEL, $identifier) !== 1) {
            throw new InvalidArgument(sprintf('"%s" is not a valid blank node identifier', $identifier));
        }
    }

    /**
     * Returns a blank node with a fresh, random identifier
     *
     * @throws RandomException if the system cannot supply random bytes
     */
    public static function generate(): self
    {
        return new self('b' . bin2hex(random_bytes(16)));
    }

    public function equals(Term | GraphName $other): bool
    {
        return $other instanceof self && $other->identifier === $this->identifier;
    }
}
