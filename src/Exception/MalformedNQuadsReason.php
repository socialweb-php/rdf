<?php

/**
 * This file is part of socialweb/rdf
 *
 * Copyright © socialweb/rdf Contributors
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

namespace SocialWeb\Rdf\Exception;

/**
 * Why a line of an N-Quads document was refused
 *
 * Each case names the production of the N-Quads 1.1 grammar, or the rule of
 * RDF 1.1 Concepts, that the line broke. The exception's detail says what was
 * found, and its line and column say where.
 */
enum MalformedNQuadsReason
{
    /**
     * The line is not valid UTF-8, which RDF 1.1 N-Quads section 3.1 requires
     * of every document
     */
    case InvalidUtf8;

    /**
     * The statement does not start with an IRI reference or a blank node
     * label
     */
    case InvalidSubject;

    /**
     * The predicate is not an IRI reference
     */
    case InvalidPredicate;

    /**
     * The object is not an IRI reference, a blank node label, or a literal
     */
    case InvalidObject;

    /**
     * The statement does not end with a period: a term is missing, a term too
     * many was found, or Turtle syntax such as a list was used
     */
    case MissingTerminator;

    /**
     * Something other than white space or a comment follows the period
     */
    case TextAfterTerminator;

    /**
     * An IRI reference is unterminated, holds a character the IRIREF
     * production forbids, or has an escape that is not a UCHAR
     */
    case MalformedIriReference;

    /**
     * A UCHAR escape in an IRI reference names a surrogate or a code point
     * above U+10FFFF, so it does not denote a Unicode scalar value
     */
    case InvalidIriEscape;

    /**
     * The IRI reference decodes to a value the Iri class refuses: it is
     * empty, it is relative, or an escape decodes to a character IRIREF
     * forbids. The previous throwable is the InvalidArgument the class threw.
     */
    case InvalidIri;

    /**
     * A blank node label does not match the BLANK_NODE_LABEL production
     */
    case MalformedBlankNodeLabel;

    /**
     * A literal is unterminated, holds a raw line break, or has an escape
     * that is neither an ECHAR nor a UCHAR
     */
    case MalformedStringLiteral;

    /**
     * A UCHAR escape in a literal names a surrogate or a code point above
     * U+10FFFF, so it does not denote a Unicode scalar value
     */
    case InvalidLiteralEscape;

    /**
     * The two carets after a literal are not followed by an IRI reference
     */
    case MissingDatatype;

    /**
     * The text after the at sign does not match the LANGTAG production
     */
    case MalformedLanguageTag;

    /**
     * The literal decodes to a value the Literal class refuses, such as the
     * datatype rdf:langString without a language tag. The previous throwable
     * is the InvalidArgument the class threw.
     */
    case InvalidLiteral;
}
