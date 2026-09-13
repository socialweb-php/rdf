<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\DefaultGraph;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Quad;

class QuadTest extends TestCase
{
    public function testDefaultsToTheDefaultGraph(): void
    {
        $subject = new Iri('http://example.com/s');
        $predicate = new Iri('http://example.com/p');
        $object = new Literal('o');

        $quad = new Quad($subject, $predicate, $object);

        $this->assertSame($subject, $quad->subject);
        $this->assertSame($predicate, $quad->predicate);
        $this->assertSame($object, $quad->object);
        $this->assertInstanceOf(DefaultGraph::class, $quad->graph);
    }

    public function testAcceptsANamedGraph(): void
    {
        $graph = new Iri('http://example.com/g');

        $quad = new Quad(new BlankNode('s'), new Iri('http://example.com/p'), new BlankNode('o'), $graph);

        $this->assertSame($graph, $quad->graph);
    }

    public function testAcceptsABlankNodeGraphName(): void
    {
        $graph = new BlankNode('g0');

        $quad = new Quad(new Iri('http://example.com/s'), new Iri('http://example.com/p'), new Literal('o'), $graph);

        $this->assertSame($graph, $quad->graph);
    }

    public function testEqualsAQuadWithEqualComponents(): void
    {
        $first = new Quad(
            new Iri('http://example.com/s'),
            new Iri('http://example.com/p'),
            new Literal('o', language: 'en'),
            new Iri('http://example.com/g'),
        );
        $second = new Quad(
            new Iri('http://example.com/s'),
            new Iri('http://example.com/p'),
            new Literal('o', language: 'EN'),
            new Iri('http://example.com/g'),
        );

        $this->assertTrue($first->equals($second));
        $this->assertTrue($second->equals($first));
    }

    public function testDoesNotEqualAQuadThatDiffersInAnyOneComponent(): void
    {
        $base = new Quad(new Iri('a:s'), new Iri('a:p'), new Iri('a:o'), new Iri('a:g'));

        $this->assertFalse($base->equals(new Quad(new Iri('a:x'), new Iri('a:p'), new Iri('a:o'), new Iri('a:g'))));
        $this->assertFalse($base->equals(new Quad(new Iri('a:s'), new Iri('a:x'), new Iri('a:o'), new Iri('a:g'))));
        $this->assertFalse($base->equals(new Quad(new Iri('a:s'), new Iri('a:p'), new Iri('a:x'), new Iri('a:g'))));
        $this->assertFalse($base->equals(new Quad(new Iri('a:s'), new Iri('a:p'), new Iri('a:o'), new Iri('a:x'))));
        $this->assertFalse($base->equals(new Quad(new Iri('a:s'), new Iri('a:p'), new Iri('a:o'))));
    }

    public function testDoesNotEqualAQuadWhoseObjectIsADifferentKindOfTerm(): void
    {
        $withIri = new Quad(new Iri('a:s'), new Iri('a:p'), new Iri('a:o'));
        $withLiteral = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('a:o'));

        $this->assertFalse($withIri->equals($withLiteral));
    }
}
