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

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;
use SocialWeb\Rdf\Exception\InvalidArgument;

use function array_values;
use function count;
use function sprintf;
use function strlen;

/**
 * An RDF dataset: an ordered set of unique quads
 *
 * Adding a quad that is already present, by quad equality, is a no-op.
 * Insertion order is preserved for iteration. This is the quad view of the
 * RDF 1.1 Concepts dataset; the default graph and named graphs are
 * distinguished by each quad's graph name.
 *
 * @implements IteratorAggregate<int, Quad>
 */
final class Dataset implements Countable, IteratorAggregate
{
    /**
     * @var array<string, Quad>
     */
    private array $quads = [];

    /**
     * @param iterable<Quad> $quads Quads to add, in order
     */
    public function __construct(iterable $quads = [])
    {
        foreach ($quads as $quad) {
            $this->add($quad);
        }
    }

    public function add(Quad $quad): void
    {
        $this->quads[self::keyOf($quad)] ??= $quad;
    }

    public function remove(Quad $quad): void
    {
        unset($this->quads[self::keyOf($quad)]);
    }

    public function contains(Quad $quad): bool
    {
        return isset($this->quads[self::keyOf($quad)]);
    }

    public function count(): int
    {
        return count($this->quads);
    }

    /**
     * @return Iterator<int, Quad>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator(array_values($this->quads));
    }

    /**
     * @return list<Quad>
     */
    public function toArray(): array
    {
        return array_values($this->quads);
    }

    /**
     * Builds a string that is identical for equal quads and distinct for
     * unequal ones
     *
     * Each component is tagged with its kind and each string is prefixed with
     * its byte length, so no combination of components can collide.
     */
    private static function keyOf(Quad $quad): string
    {
        return self::termKey($quad->subject)
            . self::termKey($quad->predicate)
            . self::termKey($quad->object)
            . self::termKey($quad->graph);
    }

    private static function termKey(Term | GraphName $term): string
    {
        return match (true) {
            $term instanceof Iri => 'I' . self::lengthPrefixed($term->value),
            $term instanceof BlankNode => 'B' . self::lengthPrefixed($term->identifier),
            $term instanceof Literal => 'L' . self::lengthPrefixed($term->lexicalForm)
                . self::lengthPrefixed($term->datatype->value)
                . ($term->language === null ? 'N' : 'T' . self::lengthPrefixed($term->language)),
            $term instanceof DefaultGraph => 'D',
            default => throw new InvalidArgument(sprintf('Unsupported term type %s', $term::class)),
        };
    }

    private static function lengthPrefixed(string $value): string
    {
        return strlen($value) . ':' . $value;
    }
}
