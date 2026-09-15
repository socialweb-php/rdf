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

namespace SocialWeb\Rdf\Canonicalization;

use SocialWeb\Rdf\NQuads\Serializer;
use SocialWeb\Rdf\Quad;

use function hash;

/**
 * The canonicalization state of RDFC-1.0 section 4.2, plus the hash
 * algorithm, the serializer, a cache of first degree hashes, and the deep
 * iteration budget
 *
 * One instance is created for each call to the canonicalizer and shared by
 * the hashing steps.
 *
 * @internal
 */
final class CanonicalizationState
{
    /**
     * The blank node to quads map: each blank node identifier to the quads in
     * which it appears, in dataset order, each quad listed once
     *
     * @var array<string, list<Quad>>
     */
    public array $blankNodeToQuads = [];

    /**
     * The hash to blank nodes map: each first degree hash to the identifiers
     * of the blank nodes that produced it
     *
     * @var array<string, list<string>>
     */
    public array $hashToBlankNodes = [];

    public readonly IdentifierIssuer $canonicalIssuer;

    /**
     * First degree hashes already computed, by blank node identifier
     *
     * RDFC-1.0 section 4.7.3 advises reusing these rather than hashing again.
     *
     * @var array<string, string>
     */
    public array $firstDegreeHashes = [];

    /**
     * The number of calls to Hash N-Degree Quads that the canonicalizer
     * allows; see RDFC-1.0 section 7.1
     */
    public int $deepIterationLimit = 0;

    public int $remainingDeepIterations = 0;

    public function __construct(public readonly string $hashAlgorithm, public readonly Serializer $serializer)
    {
        $this->canonicalIssuer = new IdentifierIssuer('c14n');
    }

    /**
     * Returns the lowercase hexadecimal digest of the input
     */
    public function hash(string $input): string
    {
        return hash($this->hashAlgorithm, $input);
    }
}
