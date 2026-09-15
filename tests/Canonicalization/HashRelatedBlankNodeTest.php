<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Canonicalization\HashFirstDegreeQuads;
use SocialWeb\Rdf\Canonicalization\HashRelatedBlankNode;
use SocialWeb\Rdf\Canonicalization\IdentifierIssuer;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Quad;
use SocialWeb\Test\Rdf\TestCase;

use function hash;

class HashRelatedBlankNodeTest extends TestCase
{
    /**
     * The "double circle" example from RDFC-1.0 section 4.8, whose logged
     * related hash is asserted here
     */
    private const string DOUBLE_CIRCLE = "_:e0 <http://example.org/vocab#next> _:e1 .\n"
        . "_:e0 <http://example.org/vocab#prev> _:e1 .\n"
        . "_:e1 <http://example.org/vocab#next> _:e0 .\n"
        . "_:e1 <http://example.org/vocab#prev> _:e0 .\n";

    public function testUsesTheCanonicalIdentifierWhenOneHasBeenIssued(): void
    {
        $state = StateBuilder::fromNQuads("_:e0 <http://example.com/#p> _:e2 .\n");
        $state->canonicalIssuer->issue('e2');
        $hasher = new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state));

        // sha256 of "o<http://example.com/#p>_:c14n0", as logged in RDFC-1.0 section 4.7.3
        $this->assertSame(
            '29cf7e22790bc2ed395b81b3933e5329fc7b25390486085cac31ce7252ca60fa',
            $hasher->hash('e2', $state->blankNodeToQuads['e0'][0], new IdentifierIssuer('b'), 'o'),
        );
    }

    public function testUsesTheTemporaryIdentifierWhenOnlyTheIssuerHasIssuedOne(): void
    {
        $state = StateBuilder::fromNQuads("_:e0 <http://example.com/#p> _:e2 .\n");
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e2');
        $hasher = new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state));

        // sha256 of "o<http://example.com/#p>_:b0"
        $this->assertSame(
            'c5b4e03b9bfe78d9b0fe5c4841d6610569200f828dcb84e65e6bf18b5ae518e3',
            $hasher->hash('e2', $state->blankNodeToQuads['e0'][0], $issuer, 'o'),
        );
    }

    public function testPrefersTheCanonicalIdentifierOverTheTemporaryOne(): void
    {
        $state = StateBuilder::fromNQuads("_:e0 <http://example.com/#p> _:e2 .\n");
        $state->canonicalIssuer->issue('e2');
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e2');
        $hasher = new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state));

        $this->assertSame(
            '29cf7e22790bc2ed395b81b3933e5329fc7b25390486085cac31ce7252ca60fa',
            $hasher->hash('e2', $state->blankNodeToQuads['e0'][0], $issuer, 'o'),
        );
    }

    public function testUsesTheFirstDegreeHashWhenNoIdentifierHasBeenIssued(): void
    {
        $state = StateBuilder::fromNQuads(self::DOUBLE_CIRCLE);
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');
        $hasher = new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state));

        // sha256 of "o<http://example.org/vocab#next>" followed by the first
        // degree hash of e1, as logged in RDFC-1.0 section 4.8.3 step 3
        $this->assertSame(
            '20bb08971220a5382a9a06ba2977c5fb859e63192e0b2015a378af89e453f25e',
            $hasher->hash('e1', $state->blankNodeToQuads['e0'][0], $issuer, 'o'),
        );
    }

    public function testIncludesThePredicateForTheSubjectPositionOnly(): void
    {
        $state = StateBuilder::fromNQuads("_:e2 <http://example.com/#p> \"x\" _:e0 .\n");
        $state->canonicalIssuer->issue('e2');
        $hasher = new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state));
        $quad = $state->blankNodeToQuads['e0'][0];

        // sha256 of "s<http://example.com/#p>_:c14n0"
        $this->assertSame(
            'd9e14d63bf74bb8fb17016354f5401194b4c3307b3cd7291e766a0c623dcb077',
            $hasher->hash('e2', $quad, new IdentifierIssuer('b'), 's'),
        );
    }

    public function testOmitsThePredicateForTheGraphPosition(): void
    {
        $state = StateBuilder::fromNQuads("_:e0 <http://example.com/#p> \"x\" _:e2 .\n");
        $state->canonicalIssuer->issue('e2');
        $hasher = new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state));

        // sha256 of "g_:c14n0"
        $this->assertSame(
            'bc6602030aa1ced3c91703293fbb054a17c7a22988343bb2b8a949ac0eee3bf3',
            $hasher->hash('e2', $state->blankNodeToQuads['e0'][0], new IdentifierIssuer('b'), 'g'),
        );
    }

    public function testHashesWithTheStateAlgorithm(): void
    {
        $state = StateBuilder::fromNQuads("_:e0 <http://example.com/#p> _:e2 .\n", 'sha384');
        $state->canonicalIssuer->issue('e2');
        $hasher = new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state));
        $quad = new Quad(new BlankNode('e0'), new Iri('http://example.com/#p'), new BlankNode('e2'));

        $this->assertSame(
            hash('sha384', 'o<http://example.com/#p>_:c14n0'),
            $hasher->hash('e2', $quad, new IdentifierIssuer('b'), 'o'),
        );
    }
}
