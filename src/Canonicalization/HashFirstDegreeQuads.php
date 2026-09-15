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
use SocialWeb\Rdf\Quad;

use function implode;
use function sort;

use const SORT_STRING;

/**
 * The Hash First Degree Quads algorithm of RDFC-1.0 section 4.6
 *
 * @internal
 */
final class HashFirstDegreeQuads
{
    private readonly BlankNode $reference;

    private readonly BlankNode $other;

    public function __construct(private readonly CanonicalizationState $state)
    {
        $this->reference = new BlankNode('a');
        $this->other = new BlankNode('z');
    }

    /**
     * Hashes the quads that mention the reference blank node, with the
     * reference node written as `_:a` and every other blank node as `_:z`
     *
     * The result is cached in the state, because it depends only on the
     * input dataset and never changes.
     */
    public function hash(string $identifier): string
    {
        if (isset($this->state->firstDegreeHashes[$identifier])) {
            return $this->state->firstDegreeHashes[$identifier];
        }

        $nquads = [];

        foreach ($this->state->blankNodeToQuads[$identifier] as $quad) {
            $nquads[] = $this->state->serializer->serializeQuad(new Quad(
                $quad->subject instanceof BlankNode ? $this->placeholder($quad->subject, $identifier) : $quad->subject,
                $quad->predicate,
                $quad->object instanceof BlankNode ? $this->placeholder($quad->object, $identifier) : $quad->object,
                $quad->graph instanceof BlankNode ? $this->placeholder($quad->graph, $identifier) : $quad->graph,
            ));
        }

        sort($nquads, SORT_STRING);

        return $this->state->firstDegreeHashes[$identifier] = $this->state->hash(implode('', $nquads));
    }

    private function placeholder(BlankNode $node, string $identifier): BlankNode
    {
        return $node->identifier === $identifier ? $this->reference : $this->other;
    }
}
