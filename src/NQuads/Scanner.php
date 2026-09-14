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
 * @internal
 */
final class Scanner
{
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
