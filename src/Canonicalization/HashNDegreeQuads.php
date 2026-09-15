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

use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Exception\IterationLimitExceeded;

use function count;
use function ksort;
use function sort;
use function strcmp;

use const SORT_STRING;

/**
 * The Hash N-Degree Quads algorithm of RDFC-1.0 section 4.8
 *
 * Every call counts against the deep iteration budget in the state; when the
 * budget is spent the call raises {@see IterationLimitExceeded}, which is the
 * early termination RDFC-1.0 section 7.1 requires.
 *
 * @internal
 */
final class HashNDegreeQuads
{
    public function __construct(
        private readonly CanonicalizationState $state,
        private readonly HashRelatedBlankNode $related,
    ) {
    }

    /**
     * @param string $identifier The blank node whose N-degree hash is wanted
     * @param IdentifierIssuer $issuer The path identifier issuer
     *
     * @throws IterationLimitExceeded
     */
    public function hash(string $identifier, IdentifierIssuer $issuer): NDegreeHash
    {
        if ($this->state->remainingDeepIterations === 0) {
            throw new IterationLimitExceeded($this->state->deepIterationLimit);
        }

        $this->state->remainingDeepIterations--;

        // Steps 1 through 3: relate the hash of each connection to the blank
        // nodes that share it.
        $hashToRelated = [];

        foreach ($this->state->blankNodeToQuads[$identifier] as $quad) {
            foreach (['s' => $quad->subject, 'o' => $quad->object, 'g' => $quad->graph] as $position => $component) {
                if (!$component instanceof BlankNode || $component->identifier === $identifier) {
                    continue;
                }

                $hash = $this->related->hash($component->identifier, $quad, $issuer, $position);
                $hashToRelated[$hash][] = $component->identifier;
            }
        }

        ksort($hashToRelated, SORT_STRING);

        // Steps 4 and 5.
        $dataToHash = '';

        foreach ($hashToRelated as $relatedHash => $blankNodes) {
            $dataToHash .= $relatedHash;
            $chosenPath = '';
            $chosenIssuer = $issuer;

            foreach (self::permutations($blankNodes) as $permutation) {
                $issuerCopy = $issuer->copy();
                $path = '';
                $recursionList = [];
                $skip = false;

                foreach ($permutation as $related) {
                    if ($this->state->canonicalIssuer->hasIssued($related)) {
                        $path .= '_:' . $this->state->canonicalIssuer->issue($related);
                    } else {
                        if (!$issuerCopy->hasIssued($related)) {
                            $recursionList[] = $related;
                        }

                        $path .= '_:' . $issuerCopy->issue($related);
                    }

                    if ($chosenPath !== '' && strcmp($path, $chosenPath) > 0) {
                        $skip = true;

                        break;
                    }
                }

                if ($skip) {
                    continue;
                }

                foreach ($recursionList as $related) {
                    $result = $this->hash($related, $issuerCopy);
                    $path .= '_:' . $issuerCopy->issue($related) . '<' . $result->hash . '>';
                    $issuerCopy = $result->issuer;

                    if ($chosenPath !== '' && strcmp($path, $chosenPath) > 0) {
                        $skip = true;

                        break;
                    }
                }

                if ($skip) {
                    continue;
                }

                if ($chosenPath === '' || strcmp($path, $chosenPath) < 0) {
                    $chosenPath = $path;
                    $chosenIssuer = $issuerCopy;
                }
            }

            $dataToHash .= $chosenPath;
            $issuer = $chosenIssuer;
        }

        return new NDegreeHash($this->state->hash($dataToHash), $issuer);
    }

    /**
     * Yields every distinct permutation of the identifiers in code point
     * order, starting from the sorted list
     *
     * @param list<string> $identifiers
     *
     * @return iterable<array<int, string>>
     */
    private static function permutations(array $identifiers): iterable
    {
        sort($identifiers, SORT_STRING);
        $last = count($identifiers) - 1;

        while (true) {
            yield $identifiers;

            // Find the rightmost position whose identifier is less than the
            // one after it. If there is none, this was the last permutation.
            $pivot = $last - 1;

            while ($pivot >= 0 && strcmp($identifiers[$pivot], $identifiers[$pivot + 1]) >= 0) {
                $pivot--;
            }

            if ($pivot < 0) {
                return;
            }

            // Swap it with the rightmost identifier greater than it, then
            // reverse everything after the pivot.
            $successor = $last;

            while (strcmp($identifiers[$successor], $identifiers[$pivot]) <= 0) {
                $successor--;
            }

            [$identifiers[$pivot], $identifiers[$successor]] = [$identifiers[$successor], $identifiers[$pivot]];

            for ($left = $pivot + 1, $right = $last; $left < $right; $left++, $right--) {
                [$identifiers[$left], $identifiers[$right]] = [$identifiers[$right], $identifiers[$left]];
            }
        }
    }
}
