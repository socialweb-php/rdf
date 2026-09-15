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
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Exception\IterationLimitExceeded;
use SocialWeb\Rdf\NQuads\Serializer;
use SocialWeb\Rdf\Quad;

use function array_keys;
use function count;
use function hash_algos;
use function in_array;
use function is_int;
use function ksort;
use function sprintf;
use function strcmp;
use function usort;

use const PHP_INT_MAX;
use const SORT_STRING;

/**
 * RDF Dataset Canonicalization (RDFC-1.0)
 *
 * Assigns every blank node in a dataset a canonical identifier so that any
 * two isomorphic datasets produce the same canonical N-Quads. This is a
 * transcription of RDFC-1.0 section 4.4.
 *
 * @link https://www.w3.org/TR/rdf-canon/
 */
final class Canonicalizer
{
    /**
     * @param string $hashAlgorithm A name listed by PHP's hash_algos();
     *     RDFC-1.0 requires sha256 by default and sha384 to be supported
     * @param int $workFactor Sets the deep iteration limit to the number of
     *     blank nodes with non-unique first degree hashes raised to this
     *     power: 0 disables deep iterations, 1 allows one call to Hash
     *     N-Degree Quads per such node, 2 allows the square of that count
     * @param int | null $maxDeepIterations An explicit deep iteration limit
     *     that overrides the work factor
     *
     * @throws InvalidArgument if the hash algorithm is unknown, the work
     *     factor is negative, or the explicit limit is negative
     */
    public function __construct(
        private readonly string $hashAlgorithm = 'sha256',
        private readonly int $workFactor = 1,
        private readonly ?int $maxDeepIterations = null,
    ) {
        if (!in_array($hashAlgorithm, hash_algos(), true)) {
            throw new InvalidArgument(sprintf('"%s" is not a hash algorithm this PHP supports', $hashAlgorithm));
        }

        if ($workFactor < 0) {
            throw new InvalidArgument(sprintf('The work factor must not be negative; %d given', $workFactor));
        }

        if ($maxDeepIterations !== null && $maxDeepIterations < 0) {
            throw new InvalidArgument(sprintf(
                'The deep iteration limit must not be negative; %d given',
                $maxDeepIterations,
            ));
        }
    }

    /**
     * @throws IterationLimitExceeded if the dataset needs more calls to Hash
     *     N-Degree Quads than the deep iteration limit allows
     * @throws InvalidArgument if a quad holds a term that is not one of this
     *     library's own term classes
     */
    public function canonicalize(Dataset $dataset): CanonicalizationResult
    {
        // Step 1: create the canonicalization state.
        $state = new CanonicalizationState($this->hashAlgorithm, new Serializer());

        // Step 2: relate each blank node to the quads that mention it. A quad
        // that mentions the same blank node twice is listed once.
        foreach ($dataset as $quad) {
            $seen = [];

            foreach ([$quad->subject, $quad->object, $quad->graph] as $component) {
                if (!$component instanceof BlankNode || in_array($component->identifier, $seen, true)) {
                    continue;
                }

                $seen[] = $component->identifier;
                $state->blankNodeToQuads[$component->identifier][] = $quad;
            }
        }

        // Step 3: hash the first degree quads of every blank node.
        $firstDegree = new HashFirstDegreeQuads($state);

        foreach (array_keys($state->blankNodeToQuads) as $identifier) {
            $identifier = (string) $identifier;
            $state->hashToBlankNodes[$firstDegree->hash($identifier)][] = $identifier;
        }

        // Step 4: issue canonical identifiers to blank nodes with a unique
        // hash, in hash order, and drop them from the map.
        ksort($state->hashToBlankNodes, SORT_STRING);

        foreach ($state->hashToBlankNodes as $hash => $identifiers) {
            if (count($identifiers) > 1) {
                continue;
            }

            $state->canonicalIssuer->issue($identifiers[0]);
            unset($state->hashToBlankNodes[$hash]);
        }

        // The deep iteration limit depends on how many blank nodes remain.
        $nonUnique = 0;

        foreach ($state->hashToBlankNodes as $identifiers) {
            $nonUnique += count($identifiers);
        }

        $state->deepIterationLimit = $this->maxDeepIterations ?? self::limit($nonUnique, $this->workFactor);
        $state->remainingDeepIterations = $state->deepIterationLimit;

        // Step 5: resolve the blank nodes that share a hash, in hash order.
        $nDegree = new HashNDegreeQuads($state, new HashRelatedBlankNode($state, $firstDegree));

        foreach ($state->hashToBlankNodes as $identifiers) {
            $hashPathList = [];

            foreach ($identifiers as $identifier) {
                if ($state->canonicalIssuer->hasIssued($identifier)) {
                    continue;
                }

                $temporaryIssuer = new IdentifierIssuer('b');
                $temporaryIssuer->issue($identifier);
                $hashPathList[] = $nDegree->hash($identifier, $temporaryIssuer);
            }

            usort($hashPathList, static fn (NDegreeHash $a, NDegreeHash $b): int => strcmp($a->hash, $b->hash));

            foreach ($hashPathList as $result) {
                foreach (array_keys($result->issuer->issued()) as $existing) {
                    $state->canonicalIssuer->issue((string) $existing);
                }
            }
        }

        // Steps 6 and 7: build the canonicalized dataset.
        $canonicalized = new Dataset();

        foreach ($dataset as $quad) {
            $canonicalized->add(new Quad(
                $quad->subject instanceof BlankNode ? self::relabel($quad->subject, $state) : $quad->subject,
                $quad->predicate,
                $quad->object instanceof BlankNode ? self::relabel($quad->object, $state) : $quad->object,
                $quad->graph instanceof BlankNode ? self::relabel($quad->graph, $state) : $quad->graph,
            ));
        }

        return new CanonicalizationResult($canonicalized, $state->canonicalIssuer->issued());
    }

    /**
     * Raises the count to the work factor, treating an overflow as no limit
     *
     * A work factor of 0 is a special case: it means no deep iterations at
     * all, whereas any count raised to the power 0 would be 1.
     */
    private static function limit(int $nonUnique, int $workFactor): int
    {
        if ($workFactor === 0) {
            return 0;
        }

        $limit = $nonUnique ** $workFactor;

        return is_int($limit) ? $limit : PHP_INT_MAX;
    }

    private static function relabel(BlankNode $node, CanonicalizationState $state): BlankNode
    {
        return new BlankNode($state->canonicalIssuer->issue($node->identifier));
    }
}
