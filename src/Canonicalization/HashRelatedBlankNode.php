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

use SocialWeb\Rdf\Quad;

/**
 * The Hash Related Blank Node algorithm of RDFC-1.0 section 4.7
 *
 * @internal
 */
final class HashRelatedBlankNode
{
    public function __construct(
        private readonly CanonicalizationState $state,
        private readonly HashFirstDegreeQuads $firstDegree,
    ) {
    }

    /**
     * Hashes how the related blank node is connected to the reference node
     * through the quad
     *
     * @param string $related The identifier of the related blank node
     * @param Quad $quad The quad in which both blank nodes appear
     * @param IdentifierIssuer $issuer The temporary issuer for the current path
     * @param 's' | 'o' | 'g' $position Whether the related node is the quad's
     *     subject, object, or graph name
     */
    public function hash(string $related, Quad $quad, IdentifierIssuer $issuer, string $position): string
    {
        $input = $position;

        if ($position !== 'g') {
            $input .= '<' . $quad->predicate->value . '>';
        }

        if ($this->state->canonicalIssuer->hasIssued($related)) {
            $input .= '_:' . $this->state->canonicalIssuer->issue($related);
        } elseif ($issuer->hasIssued($related)) {
            $input .= '_:' . $issuer->issue($related);
        } else {
            $input .= $this->firstDegree->hash($related);
        }

        return $this->state->hash($input);
    }
}
