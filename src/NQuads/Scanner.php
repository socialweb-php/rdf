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

use SocialWeb\Rdf\Exception\MalformedNQuads;
use Throwable;

use function ord;
use function preg_match;
use function strcspn;
use function strlen;
use function strspn;
use function substr;

/**
 * A cursor over one line of an N-Quads document
 *
 * The scanner matches tokens at its current byte offset and reports errors
 * with one-based line and column numbers. Columns count code points, not
 * bytes, so they are meaningful to a person reading the document. The line
 * must be valid UTF-8; the parser checks that before constructing a scanner.
 *
 * Most tokens are matched with a `\G`-anchored pattern from {@see Grammar}
 * through {@see self::take()}. IRI references and quoted literals are not,
 * because a pattern for either one repeats an alternation once per character
 * and so exhausts the regular expression engine's stack on a long term.
 * {@see self::takeIriRef()} and {@see self::takeStringLiteralQuote()} scan
 * those bodies a run at a time instead, matching a pattern only to validate
 * an escape. Every character the two productions forbid is ASCII, and the
 * line is valid UTF-8, so scanning byte by byte never splits a character.
 *
 * @internal
 */
final class Scanner
{
    /**
     * Bytes that end a run of ordinary characters inside an IRI reference:
     * every byte IRIREF forbids, which includes the backslash that starts an
     * escape and the `>` that closes the token
     */
    private const string IRIREF_STOPS = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F"
        . "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F"
        . ' <>"{}|^`\\';

    /**
     * Bytes that end a run of ordinary characters inside a quoted literal:
     * the closing quotation mark, the backslash that starts an escape, and
     * the two line breaks the production forbids
     */
    private const string STRING_LITERAL_STOPS = "\"\\\n\r";

    private int $offset = 0;

    public function __construct(private readonly string $line, private readonly int $lineNumber)
    {
    }

    /**
     * Returns the current byte offset into the line
     */
    public function position(): int
    {
        return $this->offset;
    }

    /**
     * Returns the byte at the current offset, or an empty string at the end
     * of the line
     */
    public function peek(): string
    {
        return substr($this->line, $this->offset, 1);
    }

    /**
     * Advances past any run of spaces and tabs
     */
    public function skipWhitespace(): void
    {
        $this->offset += strspn($this->line, " \t", $this->offset);
    }

    /**
     * Returns true at the end of the line or at the start of a comment
     */
    public function atEnd(): bool
    {
        $next = $this->peek();

        return $next === '' || $next === '#';
    }

    /**
     * Advances past the given text if it appears at the current offset
     */
    public function accept(string $text): bool
    {
        if (substr($this->line, $this->offset, strlen($text)) !== $text) {
            return false;
        }

        $this->offset += strlen($text);

        return true;
    }

    /**
     * Matches a `\G`-anchored pattern with one capturing group at the current
     * offset and advances past the whole match
     *
     * @param string $pattern A `*_TOKEN` pattern from {@see Grammar}
     *
     * @return string | null The capturing group, or null if the pattern does
     *     not match at the current offset, in which case the offset is unchanged
     */
    public function take(string $pattern): ?string
    {
        if (preg_match($pattern, $this->line, $matches, 0, $this->offset) !== 1) {
            return null;
        }

        $this->offset += strlen($matches[0]);

        return $matches[1];
    }

    /**
     * Matches an IRIREF at the current offset and advances past its closing
     * `>`
     *
     * @return string | null The text between `<` and `>` with escapes still
     *     encoded, or null when no IRIREF begins at the current offset, in
     *     which case the offset is unchanged
     */
    public function takeIriRef(): ?string
    {
        return $this->takeDelimited('<', self::IRIREF_STOPS, '>', Grammar::IRIREF_ESCAPE_TOKEN);
    }

    /**
     * Matches a STRING_LITERAL_QUOTE at the current offset and advances past
     * its closing quotation mark
     *
     * @return string | null The text between the quotation marks with escapes
     *     still encoded, or null when no quoted literal begins at the current
     *     offset, in which case the offset is unchanged
     */
    public function takeStringLiteralQuote(): ?string
    {
        return $this->takeDelimited('"', self::STRING_LITERAL_STOPS, '"', Grammar::STRING_LITERAL_ESCAPE_TOKEN);
    }

    /**
     * Scans a delimited token by taking runs of ordinary bytes and validating
     * one escape at each stop byte
     *
     * @param string $open The byte that opens the token
     * @param string $stops The bytes that end a run of ordinary characters
     * @param string $close The byte that closes the token
     * @param string $escape A `\G`-anchored pattern for a single escape
     *
     * @return string | null The body with escapes still encoded, or null when
     *     the opening byte is missing, the token runs off the end of the line,
     *     or a stop byte is neither the closing byte nor a valid escape
     */
    private function takeDelimited(string $open, string $stops, string $close, string $escape): ?string
    {
        if ($this->peek() !== $open) {
            return null;
        }

        $length = strlen($this->line);
        $position = $this->offset + 1;
        $body = '';

        while (true) {
            $run = strcspn($this->line, $stops, $position);
            $body .= substr($this->line, $position, $run);
            $position += $run;

            if ($position === $length) {
                return null;
            }

            if ($this->line[$position] === $close) {
                $this->offset = $position + 1;

                return $body;
            }

            if (preg_match($escape, $this->line, $matches, 0, $position) !== 1) {
                return null;
            }

            // The whole match is the escape, which the body keeps encoded.
            [$escaped] = $matches;

            $body .= $escaped;
            $position += strlen($escaped);
        }
    }

    /**
     * Builds an exception for a problem at the given byte offset, or at the
     * current offset when none is given
     */
    public function error(string $reason, ?int $offset = null, ?Throwable $previous = null): MalformedNQuads
    {
        $offset ??= $this->offset;
        $column = 1;

        for ($i = 0; $i < $offset; $i++) {
            // Count code points by skipping UTF-8 continuation bytes.
            if ((ord($this->line[$i]) & 0xC0) !== 0x80) {
                $column++;
            }
        }

        return new MalformedNQuads($this->lineNumber, $column, $this->line, $reason, $previous);
    }
}
