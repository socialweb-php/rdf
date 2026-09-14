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
 * bodies of character classes; N-Quads reuses them for blank node labels.
 * `HEX`, `UCHAR`, and `ECHAR` are pattern fragments. The remaining constants
 * are complete patterns for use with preg_match(). Those whose names end in
 * `_TOKEN` are anchored with `\G` so that the parser can match them at a byte
 * offset into a line; the others are anchored to the whole subject. Patterns
 * that may see non-ASCII input carry the `u` modifier and therefore fail to
 * match (returning false) on input that is not valid UTF-8.
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

    /**
     * RDF 1.1 N-Quads also lists `:` here. RDF 1.1 erratum 30 removed it so
     * that blank node labels match Turtle's, RDF 1.2 N-Quads carries the
     * correction, and the W3C N-Quads syntax test suite rejects labels that
     * contain a colon. This library follows the erratum.
     *
     * @link https://www.w3.org/2001/sw/wiki/RDF1.1_Errata
     */
    public const string PN_CHARS_U = self::PN_CHARS_BASE . '_';

    public const string PN_CHARS = self::PN_CHARS_U . '\-0-9\x{00B7}\x{0300}-\x{036F}\x{203F}-\x{2040}';

    public const string HEX = '[0-9A-Fa-f]';

    public const string UCHAR = '\\\\u' . self::HEX . '{4}|\\\\U' . self::HEX . '{8}';

    public const string ECHAR = '\\\\[tbnrf"\'\\\\]';

    /**
     * The label part of BLANK_NODE_LABEL, without the leading `_:`
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
     * Matches the scheme of an absolute IRI, per RFC 3986 section 3.1
     */
    public const string SCHEME = '/\A[A-Za-z][A-Za-z0-9+.\-]*:/';

    /**
     * IRIREF at the offset; group 1 is the text between `<` and `>` with
     * escapes still encoded
     */
    public const string IRIREF_TOKEN = '/\G<((?:[^\x00-\x20<>"{}|^`\\\\]|' . self::UCHAR . ')*)>/u';

    /**
     * BLANK_NODE_LABEL at the offset; group 1 is the label without `_:`
     */
    public const string BLANK_NODE_LABEL_TOKEN = '/\G_:([' . self::PN_CHARS_U . '0-9]'
        . '(?:[' . self::PN_CHARS . '.]*[' . self::PN_CHARS . '])?)/u';

    /**
     * STRING_LITERAL_QUOTE at the offset; group 1 is the text between the
     * quotation marks with escapes still encoded
     */
    public const string STRING_LITERAL_QUOTE_TOKEN = '/\G"((?:[^"\\\\\n\r]|'
        . self::ECHAR . '|' . self::UCHAR . ')*)"/u';

    /**
     * LANGTAG at the offset; group 1 is the tag without `@`
     */
    public const string LANGTAG_TOKEN = '/\G@([a-zA-Z]+(?:-[a-zA-Z0-9]+)*)/';
}
