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

use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\NQuads\Serializer;

use function implode;
use function sort;

use const SORT_STRING;

/**
 * The output of the canonicalizer: the canonicalized dataset and the issued
 * identifiers map
 */
final readonly class CanonicalizationResult
{
    /**
     * @param Dataset $dataset The canonicalized dataset
     * @param array<string, string> $issuedIdentifiers Each input blank node
     *     identifier to its canonical identifier
     */
    public function __construct(private Dataset $dataset, private array $issuedIdentifiers)
    {
    }

    /**
     * Returns a dataset whose blank nodes carry their canonical identifiers
     */
    public function dataset(): Dataset
    {
        return $this->dataset;
    }

    /**
     * Returns the canonical N-Quads document: one line per quad, in code
     * point order, each ending with a line feed
     *
     * This is the string to hash or sign.
     */
    public function toNQuads(): string
    {
        $serializer = new Serializer();
        $lines = [];

        foreach ($this->dataset as $quad) {
            $lines[] = $serializer->serializeQuad($quad);
        }

        sort($lines, SORT_STRING);

        return implode('', $lines);
    }

    /**
     * Returns each input blank node identifier and its canonical identifier
     *
     * PHP stores an array key such as "0" as the integer 0, so cast the keys
     * to strings if they are to be used as identifiers.
     *
     * @return array<string, string>
     */
    public function issuedIdentifiers(): array
    {
        return $this->issuedIdentifiers;
    }
}
