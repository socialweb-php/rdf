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

namespace SocialWeb\Rdf\NQuads;

use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\DefaultGraph;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\GraphName;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Quad;
use SocialWeb\Rdf\Term;
use SocialWeb\Rdf\Vocabulary\Xsd;

use function sprintf;
use function strtr;

/**
 * Writes quads in the canonical form of N-Quads
 *
 * The output follows Appendix A of RDF Dataset Canonicalization (RDFC-1.0),
 * "A Canonical form of N-Quads": one quad per line, a single space after the
 * subject, predicate, object, and graph label, no datatype on xsd:string
 * literals, a fixed escaping table for literals, and a line feed after every
 * quad. The serializer writes quads in the order given; sorting is the
 * canonicalizer's job.
 *
 * @link https://www.w3.org/TR/rdf-canon/#canonical-quads
 */
final class Serializer
{
    /**
     * The escaping table for STRING_LITERAL_QUOTE from RDFC-1.0 Appendix A
     *
     * ECHAR for backspace, tab, line feed, form feed, carriage return, the
     * quotation mark, and the backslash; UCHAR with four uppercase hexadecimal
     * digits for the other C0 controls, DEL, and the two code points below
     * U+10000 that the XML 1.1 Char production excludes. Surrogates cannot
     * occur in a valid UTF-8 lexical form. Every other code point is written
     * natively. URDNA2015 differs from RDFC-1.0 only in this table; a legacy
     * mode would be an alternative table selected in {@see self::escape()}.
     */
    private const array ESCAPES = [
        "\x00" => '\u0000',
        "\x01" => '\u0001',
        "\x02" => '\u0002',
        "\x03" => '\u0003',
        "\x04" => '\u0004',
        "\x05" => '\u0005',
        "\x06" => '\u0006',
        "\x07" => '\u0007',
        "\x08" => '\b',
        "\x09" => '\t',
        "\x0A" => '\n',
        "\x0B" => '\u000B',
        "\x0C" => '\f',
        "\x0D" => '\r',
        "\x0E" => '\u000E',
        "\x0F" => '\u000F',
        "\x10" => '\u0010',
        "\x11" => '\u0011',
        "\x12" => '\u0012',
        "\x13" => '\u0013',
        "\x14" => '\u0014',
        "\x15" => '\u0015',
        "\x16" => '\u0016',
        "\x17" => '\u0017',
        "\x18" => '\u0018',
        "\x19" => '\u0019',
        "\x1A" => '\u001A',
        "\x1B" => '\u001B',
        "\x1C" => '\u001C',
        "\x1D" => '\u001D',
        "\x1E" => '\u001E',
        "\x1F" => '\u001F',
        '"' => '\"',
        '\\' => '\\\\',
        "\x7F" => '\u007F',
        "\u{FFFE}" => '\uFFFE',
        "\u{FFFF}" => '\uFFFF',
    ];

    /**
     * Writes every quad in the dataset, in dataset order, one per line
     *
     * @throws InvalidArgument if a quad holds a term that is not one of this
     *     library's own term classes
     */
    public function serialize(Dataset $dataset): string
    {
        $output = '';

        foreach ($dataset as $quad) {
            $output .= $this->serializeQuad($quad);
        }

        return $output;
    }

    /**
     * Writes one quad as a line, including its terminating line feed
     *
     * @throws InvalidArgument if the quad holds a term that is not one of this
     *     library's own term classes
     */
    public function serializeQuad(Quad $quad): string
    {
        $line = $this->term($quad->subject) . ' '
            . $this->term($quad->predicate) . ' '
            . $this->term($quad->object) . ' ';

        if (!$quad->graph instanceof DefaultGraph) {
            $line .= $this->term($quad->graph) . ' ';
        }

        return $line . ".\n";
    }

    private function term(Term | GraphName $term): string
    {
        return match (true) {
            $term instanceof Iri => '<' . $term->value . '>',
            $term instanceof BlankNode => '_:' . $term->identifier,
            $term instanceof Literal => $this->literal($term),
            default => throw new InvalidArgument(sprintf('Unsupported term type %s', $term::class)),
        };
    }

    private function literal(Literal $literal): string
    {
        $output = '"' . $this->escape($literal->lexicalForm) . '"';

        if ($literal->language !== null) {
            return $output . '@' . $literal->language;
        }

        if ($literal->datatype->value === Xsd::STRING) {
            return $output;
        }

        return $output . '^^<' . $literal->datatype->value . '>';
    }

    /**
     * Applies the escaping table to a lexical form
     *
     * All escaping lives here so that an alternative table can be added as
     * one option without a second serializer.
     */
    private function escape(string $lexicalForm): string
    {
        return strtr($lexicalForm, self::ESCAPES);
    }
}
