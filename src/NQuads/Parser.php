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
use SocialWeb\Rdf\Exception\MalformedNQuads;
use SocialWeb\Rdf\GraphName;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Quad;
use SocialWeb\Rdf\Resource;
use SocialWeb\Rdf\Term;

use function chr;
use function explode;
use function intval;
use function preg_match;
use function str_replace;
use function strpos;
use function substr;

/**
 * Parses N-Quads 1.1 documents into quads
 *
 * The parser implements the N-Quads 1.1 grammar and nothing more: one
 * statement per line, comments, IRI references with `\u` and `\U` escapes,
 * quoted literals with ECHAR and UCHAR escapes, language tags, datatype IRIs,
 * and blank node labels, which are preserved exactly as written. Input must
 * be UTF-8. The first line that does not conform raises {@see MalformedNQuads}
 * with its line and column; nothing is skipped or repaired.
 *
 * @link https://www.w3.org/TR/n-quads/
 */
final class Parser
{
    /**
     * Parses a whole document into a dataset
     *
     * @throws MalformedNQuads
     */
    public function parse(string $document): Dataset
    {
        return new Dataset($this->parseQuads($document));
    }

    /**
     * Yields one quad per statement as the document is read
     *
     * @return iterable<int, Quad>
     *
     * @throws MalformedNQuads
     */
    public function parseQuads(string $document): iterable
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $document));

        foreach ($lines as $index => $line) {
            $quad = $this->parseLine($line, $index + 1);

            if ($quad instanceof Quad) {
                yield $quad;
            }
        }
    }

    /**
     * @throws MalformedNQuads
     */
    private function parseLine(string $line, int $lineNumber): ?Quad
    {
        if (preg_match('//u', $line) !== 1) {
            throw new MalformedNQuads($lineNumber, 1, $line, 'The line is not valid UTF-8');
        }

        $scanner = new Scanner($line, $lineNumber);
        $scanner->skipWhitespace();

        if ($scanner->atEnd()) {
            return null;
        }

        $subject = $this->parseSubject($scanner);
        $scanner->skipWhitespace();
        $predicate = $this->parsePredicate($scanner);
        $scanner->skipWhitespace();
        $object = $this->parseObject($scanner);
        $scanner->skipWhitespace();
        $graph = $this->parseGraphLabel($scanner);
        $scanner->skipWhitespace();

        if (!$scanner->accept('.')) {
            throw $scanner->error("Expected '.' to end the statement");
        }

        $scanner->skipWhitespace();

        if (!$scanner->atEnd()) {
            throw $scanner->error("Expected the end of the line after '.'");
        }

        return new Quad($subject, $predicate, $object, $graph);
    }

    private function parseSubject(Scanner $scanner): Resource
    {
        return match ($scanner->peek()) {
            '<' => $this->parseIri($scanner),
            '_' => $this->parseBlankNode($scanner),
            default => throw $scanner->error('Expected an IRI or a blank node label as the subject'),
        };
    }

    private function parsePredicate(Scanner $scanner): Iri
    {
        if ($scanner->peek() !== '<') {
            throw $scanner->error('Expected an IRI as the predicate');
        }

        return $this->parseIri($scanner);
    }

    private function parseObject(Scanner $scanner): Term
    {
        return match ($scanner->peek()) {
            '<' => $this->parseIri($scanner),
            '_' => $this->parseBlankNode($scanner),
            default => throw $scanner->error('Expected an IRI, a blank node label, or a literal as the object'),
        };
    }

    private function parseGraphLabel(Scanner $scanner): GraphName
    {
        return match ($scanner->peek()) {
            '<' => $this->parseIri($scanner),
            '_' => $this->parseBlankNode($scanner),
            default => new DefaultGraph(),
        };
    }

    private function parseIri(Scanner $scanner): Iri
    {
        $start = $scanner->position();
        $text = $scanner->take(Grammar::IRIREF_TOKEN);

        if ($text === null) {
            throw $scanner->error('Malformed IRI reference');
        }

        $value = $this->unescape($text);

        if ($value === null) {
            throw $scanner->error('An escape in the IRI does not denote a Unicode scalar value', $start);
        }

        try {
            return new Iri($value);
        } catch (InvalidArgument $exception) {
            throw $scanner->error($exception->getMessage(), $start, $exception);
        }
    }

    private function parseBlankNode(Scanner $scanner): BlankNode
    {
        $label = $scanner->take(Grammar::BLANK_NODE_LABEL_TOKEN);

        if ($label === null) {
            throw $scanner->error('Malformed blank node label');
        }

        return new BlankNode($label);
    }

    /**
     * Decodes the ECHAR and UCHAR escapes in text that matched IRIREF or
     * STRING_LITERAL_QUOTE
     *
     * Because the text matched one of those productions, every backslash
     * starts a well-formed escape. The only remaining failure is a `\u` or
     * `\U` escape that names a surrogate or a code point above U+10FFFF,
     * for which this returns null.
     */
    private function unescape(string $text): ?string
    {
        $result = '';
        $position = 0;

        while (($backslash = strpos($text, '\\', $position)) !== false) {
            $result .= substr($text, $position, $backslash - $position);
            $kind = substr($text, $backslash + 1, 1);

            if ($kind === 'u' || $kind === 'U') {
                $digits = $kind === 'u' ? 4 : 8;
                $encoded = self::encode(intval(substr($text, $backslash + 2, $digits), 16));

                if ($encoded === null) {
                    return null;
                }

                $result .= $encoded;
                $position = $backslash + 2 + $digits;

                continue;
            }

            $result .= match ($kind) {
                't' => "\t",
                'b' => "\x08",
                'n' => "\n",
                'r' => "\r",
                'f' => "\x0C",
                default => $kind,
            };
            $position = $backslash + 2;
        }

        return $result . substr($text, $position);
    }

    /**
     * Encodes a code point as UTF-8, or returns null if it is negative, a
     * surrogate, or above U+10FFFF
     */
    private static function encode(int $codePoint): ?string
    {
        if ($codePoint < 0 || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) || $codePoint > 0x10FFFF) {
            return null;
        }

        if ($codePoint < 0x80) {
            return chr($codePoint);
        }

        if ($codePoint < 0x800) {
            return chr(0xC0 | ($codePoint >> 6)) . chr(0x80 | ($codePoint & 0x3F));
        }

        if ($codePoint < 0x10000) {
            return chr(0xE0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        return chr(0xF0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3F))
            . chr(0x80 | (($codePoint >> 6) & 0x3F))
            . chr(0x80 | ($codePoint & 0x3F));
    }
}
