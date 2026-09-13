<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\DefaultGraph;
use SocialWeb\Rdf\Iri;

class DefaultGraphTest extends TestCase
{
    public function testEqualsAnyOtherDefaultGraph(): void
    {
        $this->assertTrue((new DefaultGraph())->equals(new DefaultGraph()));
    }

    public function testDoesNotEqualAnIriOrBlankNode(): void
    {
        $this->assertFalse((new DefaultGraph())->equals(new Iri('http://example.com/g')));
        $this->assertFalse((new DefaultGraph())->equals(new BlankNode('g0')));
    }

    public function testIsNotEqualedByAnIriOrBlankNode(): void
    {
        $this->assertFalse((new Iri('http://example.com/g'))->equals(new DefaultGraph()));
        $this->assertFalse((new BlankNode('g0'))->equals(new DefaultGraph()));
    }
}
