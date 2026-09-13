<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\NQuads\Grammar;

use function preg_match;

class BlankNodeTest extends TestCase
{
    #[DataProvider('validIdentifiers')]
    public function testAcceptsValidIdentifiers(string $identifier): void
    {
        $blankNode = new BlankNode($identifier);

        $this->assertSame($identifier, $blankNode->identifier);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validIdentifiers(): iterable
    {
        yield 'letters and digits' => ['b0'];
        yield 'canonical label' => ['c14n12'];
        yield 'single digit' => ['0'];
        yield 'colon inside' => ['a:b'];
        yield 'non-ASCII' => ['état'];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testRejectsInvalidIdentifiers(string $identifier): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('is not a valid blank node identifier');

        new BlankNode($identifier);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'leading hyphen' => ['-b'];
        yield 'trailing dot' => ['b.'];
        yield 'space' => ['b 0'];
        yield 'invalid UTF-8' => ["b\xff"];
    }

    public function testGenerateProducesAValidIdentifier(): void
    {
        $blankNode = BlankNode::generate();

        $this->assertSame(1, preg_match(Grammar::BLANK_NODE_LABEL, $blankNode->identifier));
    }

    public function testGenerateProducesDistinctIdentifiers(): void
    {
        $this->assertFalse(BlankNode::generate()->equals(BlankNode::generate()));
    }

    public function testEqualsABlankNodeWithTheSameIdentifier(): void
    {
        $this->assertTrue((new BlankNode('b0'))->equals(new BlankNode('b0')));
    }

    public function testDoesNotEqualABlankNodeWithADifferentIdentifier(): void
    {
        $this->assertFalse((new BlankNode('b0'))->equals(new BlankNode('b1')));
        $this->assertFalse((new BlankNode('b0'))->equals(new BlankNode('B0')));
    }

    public function testDoesNotEqualAnIriWithTheSameString(): void
    {
        $this->assertFalse((new BlankNode('a:b'))->equals(new Iri('a:b')));
        $this->assertFalse((new Iri('a:b'))->equals(new BlankNode('a:b')));
    }
}
