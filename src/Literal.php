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

use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\NQuads\Grammar;
use SocialWeb\Rdf\Vocabulary\Rdf;
use SocialWeb\Rdf\Vocabulary\Xsd;

use function preg_match;
use function sprintf;
use function strtolower;

/**
 * A literal: a lexical form, a datatype IRI, and an optional language tag
 *
 * See RDF 1.1 Concepts section 3.3. The datatype is always present: a literal
 * with neither datatype nor language is an xsd:string, and a literal with a
 * language tag is an rdf:langString. Language tags are stored in lowercase,
 * which RDF 1.1 Concepts permits. Literals are compared by exact comparison
 * of all three components; the lexical form is never normalized.
 */
final readonly class Literal implements Term
{
    public Iri $datatype;

    public ?string $language;

    /**
     * @param string $lexicalForm The lexical form, which must be valid UTF-8
     * @param Iri | null $datatype The datatype IRI; defaults to xsd:string, or
     *     to rdf:langString when a language tag is given
     * @param string | null $language A language tag matching the N-Quads LANGTAG
     *     production; requires the datatype to be rdf:langString
     *
     * @throws InvalidArgument if the lexical form is not valid UTF-8, if the
     *     language tag is malformed, if a language tag is paired with a datatype
     *     other than rdf:langString, or if rdf:langString is given without a
     *     language tag
     */
    public function __construct(public string $lexicalForm, ?Iri $datatype = null, ?string $language = null)
    {
        if (preg_match('//u', $lexicalForm) !== 1) {
            throw new InvalidArgument('A literal lexical form must be valid UTF-8');
        }

        if ($language === null) {
            if ($datatype !== null && $datatype->value === Rdf::LANG_STRING) {
                throw new InvalidArgument('A literal with the datatype rdf:langString must have a language tag');
            }

            $this->datatype = $datatype ?? new Iri(Xsd::STRING);
            $this->language = null;

            return;
        }

        if (preg_match(Grammar::LANGTAG, $language) !== 1) {
            throw new InvalidArgument(sprintf('"%s" is not a well-formed language tag', $language));
        }

        if ($datatype !== null && $datatype->value !== Rdf::LANG_STRING) {
            throw new InvalidArgument(sprintf(
                'A literal with a language tag must have the datatype rdf:langString, not "%s"',
                $datatype->value,
            ));
        }

        $this->datatype = $datatype ?? new Iri(Rdf::LANG_STRING);
        $this->language = strtolower($language);
    }

    public function equals(Term $other): bool
    {
        return $other instanceof self
            && $other->lexicalForm === $this->lexicalForm
            && $other->language === $this->language
            && $other->datatype->equals($this->datatype);
    }
}
