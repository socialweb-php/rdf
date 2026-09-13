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

/**
 * Regular expression fragments and patterns for the N-Quads 1.1 grammar
 *
 * Constants are named after the productions in the specification's grammar.
 * The `PN_*` constants ("PN" stands for "prefixed name," because Turtle
 * introduced these character sets for names such as `foaf:name`) are the
 * bodies of character classes; N-Quads reuses them for blank node labels. The
 * remaining constants are complete patterns for use with preg_match().
 * Patterns that may see non-ASCII input carry the `u` modifier and therefore
 * fail to match (returning false) on input that is not valid UTF-8.
 *
 * @internal
 *
 * @link https://www.w3.org/TR/n-quads/#sec-grammar
 */
final class Grammar
{
    public const string PN_CHARS_BASE = 'A-Za-z'
        . '\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{02FF}\x{0370}-\x{037D}'
        . '\x{037F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}'
        . '\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}';

    public const string PN_CHARS_U = self::PN_CHARS_BASE . '_:';

    public const string PN_CHARS = self::PN_CHARS_U . '\-0-9\x{00B7}\x{0300}-\x{036F}\x{203F}-\x{2040}';

    /**
     * The label part of BLANK_NODE_LABEL, without the leading `_:`. Note that
     * PN_CHARS_U includes `:`, so a label may itself contain colons.
     */
    public const string BLANK_NODE_LABEL = '/\A[' . self::PN_CHARS_U . '0-9]'
        . '(?:[' . self::PN_CHARS . '.]*[' . self::PN_CHARS . '])?\z/u';

    /**
     * The tag part of LANGTAG, without the leading `@`
     */
    public const string LANGTAG = '/\A[a-zA-Z]+(?:-[a-zA-Z0-9]+)*\z/';

    /**
     * Matches any character that IRIREF forbids between `<` and `>`
     */
    public const string IRIREF_FORBIDDEN = '/[\x00-\x20<>"{}|^`\\\\]/u';

    /**
     * Matches the scheme of an absolute IRI, per RFC 3987
     */
    public const string SCHEME = '/\A[A-Za-z][A-Za-z0-9+.\-]*:/';
}
