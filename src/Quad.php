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

/**
 * An RDF statement with its graph name: subject, predicate, object, and graph
 *
 * The parameter types enforce the RDF 1.1 position rules: the subject is an
 * IRI or blank node, the predicate is an IRI, the object is any term, and the
 * graph is an IRI, a blank node, or the default graph. A quad in the default
 * graph is what RDF 1.1 Concepts calls a triple.
 */
final readonly class Quad
{
    public function __construct(
        public Resource $subject,
        public Iri $predicate,
        public Term $object,
        public GraphName $graph = new DefaultGraph(),
    ) {
    }

    public function equals(Quad $other): bool
    {
        return $this->subject->equals($other->subject)
            && $this->predicate->equals($other->predicate)
            && $this->object->equals($other->object)
            && $this->graph->equals($other->graph);
    }
}
